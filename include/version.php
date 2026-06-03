<?php
/**
 * Automatic version display for tagged releases.
 *
 * Preferred format:
 * - Tagged release: v1.20.1
 * - Ahead of latest tag: v1.20.2-dev.3
 *
 * The app prefers git metadata, falls back to a committed version snapshot,
 * and uses APP_VERSION only as a manual override.
 */

require_once __DIR__ . '/release.php';

function pims_version_payload_from_value(string $fullVersion, string $source, array $overrides = []): array {
  return array_merge([
    'name' => PIMS_RELEASE_NAME,
    'version' => ltrim($fullVersion, 'v'),
    'full' => ltrim($fullVersion, 'v'),
    'source' => $source,
    'hash' => null,
    'tag' => null,
    'commits_since_tag' => null,
    'total_commits' => 0,
    'dirty' => false,
    'changed_files' => 0,
    'dirty_fingerprint' => null,
    'dirty_sequence' => null,
    'date' => date('Y-m-d'),
  ], $overrides);
}

function pims_version_public_endpoint(): string {
  $root = realpath(PIMS_RELEASE_ROOT) ?: PIMS_RELEASE_ROOT;
  return '/' . basename(str_replace('\\', '/', $root)) . '/auth/auth.php';
}

function pims_version_dirty_state(string $currentStatus): array {
  $lines = array_values(array_filter(array_map('trim', explode("\n", $currentStatus))));
  if (!$lines) {
    return [
      'dirty' => false,
      'changed_files' => 0,
      'fingerprint' => null,
      'sequence' => null,
    ];
  }

  $untrackedState = [];
  foreach ($lines as $line) {
    if (strpos($line, '?? ') !== 0) {
      continue;
    }

    $relativePath = trim(substr($line, 3));
    if ($relativePath === '') {
      continue;
    }

    $fullPath = PIMS_RELEASE_ROOT . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($fullPath)) {
      $untrackedState[] = $relativePath . ':' . (string) @filesize($fullPath) . ':' . (string) @filemtime($fullPath);
      continue;
    }

    $untrackedState[] = $relativePath;
  }

  $diffHash = pims_release_git('diff --no-ext-diff --binary HEAD -- | git hash-object --stdin');
  $fingerprint = substr(md5($currentStatus . '|' . $diffHash . '|' . implode('|', $untrackedState)), 0, 8);
  $sequence = (hexdec(substr($fingerprint, 0, 5)) % 9000) + 1000;

  return [
    'dirty' => true,
    'changed_files' => count($lines),
    'fingerprint' => $fingerprint,
    'sequence' => $sequence,
  ];
}

function pims_version_filesystem_paths(): array {
  return [
    PIMS_RELEASE_ROOT . '/admin',
    PIMS_RELEASE_ROOT . '/auth',
    PIMS_RELEASE_ROOT . '/config',
    PIMS_RELEASE_ROOT . '/include',
    PIMS_RELEASE_ROOT . '/services',
    PIMS_RELEASE_ROOT . '/assets/dist',
    PIMS_RELEASE_ROOT,
  ];
}

function pims_version_filesystem_state(): array {
  $allowedExtensions = ['php', 'js', 'css'];
  $ignoredRootNames = ['.git', 'vendor', 'tcpdf', 'assets/plugins'];
  $files = [];

  foreach (pims_version_filesystem_paths() as $path) {
    if (!is_dir($path)) {
      continue;
    }

    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
      if (!$fileInfo->isFile()) {
        continue;
      }

      $fullPath = str_replace('\\', '/', $fileInfo->getPathname());
      $relativePath = ltrim(str_replace(str_replace('\\', '/', PIMS_RELEASE_ROOT), '', $fullPath), '/');
      if ($relativePath === '') {
        continue;
      }

      foreach ($ignoredRootNames as $ignored) {
        if ($relativePath === $ignored || strpos($relativePath, $ignored . '/') === 0) {
          continue 2;
        }
      }

      $extension = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));
      if (!in_array($extension, $allowedExtensions, true)) {
        continue;
      }

      $files[$relativePath] = $relativePath . ':' . (string) $fileInfo->getSize() . ':' . (string) $fileInfo->getMTime();
    }
  }

  ksort($files);
  $fingerprint = substr(md5(implode('|', $files)), 0, 8);
  $sequence = (hexdec(substr($fingerprint, 0, 5)) % 9000) + 1000;

  return [
    'fingerprint' => $fingerprint,
    'sequence' => $sequence,
    'tracked_files' => count($files),
  ];
}

function pims_version_meta_label(array $payload): string {
  return '';
}

function pims_version_parse_numeric(string $value): array {
  if (preg_match('/(\d+)\.(\d+)\.(\d+)/', $value, $matches)) {
    return [
      'major' => (int) $matches[1],
      'minor' => (int) $matches[2],
      'patch' => (int) $matches[3],
    ];
  }

  return [
    'major' => 0,
    'minor' => 0,
    'patch' => 0,
  ];
}

function pims_version_numeric_string(array $parts): string {
  return (int) $parts['major'] . '.' . (int) $parts['minor'] . '.' . (int) $parts['patch'];
}

function pims_version_runtime_state_path(): string {
  return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pims_live_version_state.php';
}

function pims_version_read_runtime_state(): array {
  $path = pims_version_runtime_state_path();
  if (!is_readable($path)) {
    return [];
  }

  $state = @include $path;
  return is_array($state) ? $state : [];
}

function pims_version_write_runtime_state(array $state): void {
  $path = pims_version_runtime_state_path();
  $export = '<?php return ' . var_export($state, true) . ';' . PHP_EOL;
  @file_put_contents($path, $export, LOCK_EX);
}

function pims_version_live_patch_number(string $majorMinorKey, int $basePatch, ?string $fingerprint): int {
  $basePatch = max(0, $basePatch);
  $fingerprint = $fingerprint ? trim($fingerprint) : '';
  if ($fingerprint === '') {
    return $basePatch;
  }

  $state = pims_version_read_runtime_state();
  $savedKey = (string) ($state['major_minor'] ?? '');
  $savedFingerprint = (string) ($state['fingerprint'] ?? '');
  $savedPatch = (int) ($state['patch'] ?? 0);

  if ($savedKey === $majorMinorKey && $savedFingerprint === $fingerprint && $savedPatch >= $basePatch) {
    return $savedPatch;
  }

  $nextPatch = $basePatch;
  if ($savedKey === $majorMinorKey && $savedPatch >= $basePatch) {
    $nextPatch = $savedPatch + 1;
  } elseif ($fingerprint !== '') {
    $nextPatch = $basePatch + 1;
  }

  pims_version_write_runtime_state([
    'major_minor' => $majorMinorKey,
    'fingerprint' => $fingerprint,
    'patch' => $nextPatch,
    'updated_at' => date('c'),
  ]);

  return $nextPatch;
}

function pims_version_base_parts_from_value(?string $value): array {
  $value = trim((string) $value);
  if ($value !== '' && strpos($value, 'v') !== 0) {
    $value = 'v' . $value;
  }

  $parsed = pims_release_parse_tag($value !== '' ? $value : null);
  if ($parsed) {
    return $parsed;
  }

  return pims_release_initial_version();
}

function pims_version_patch_timeline(?string $baseVersion, array $commits): array {
  $base = pims_version_base_parts_from_value($baseVersion);
  $patchNumber = (int) $base['patch'];
  $timeline = [];

  foreach ($commits as $commit) {
    $patchNumber++;
    $timeline[] = [
      'hash' => (string) ($commit['hash'] ?? ''),
      'version' => pims_version_numeric_string([
        'major' => (int) $base['major'],
        'minor' => (int) $base['minor'],
        'patch' => $patchNumber,
      ]),
    ];
  }

  return $timeline;
}

function pims_version_baseline_state(): array {
  $baseline = pims_release_load_changelog_baseline();
  $baselineHash = trim((string) ($baseline['hash'] ?? ''));
  $baselineVersion = trim((string) ($baseline['version'] ?? ''));

  if ($baselineVersion === '') {
    $latestTag = pims_release_latest_tag();
    $baselineVersion = $latestTag ? ltrim($latestTag, 'v') : pims_release_format_version(pims_release_initial_version());
  }

  return [
    'hash' => $baselineHash,
    'version' => $baselineVersion,
  ];
}

function pims_version_apply_numeric_display(array $payload): array {
  $parts = pims_version_parse_numeric((string) ($payload['version'] ?? $payload['full'] ?? '0.0.0'));
  $numeric = pims_version_numeric_string($parts);
  $payload['version'] = $numeric;
  $payload['full'] = $numeric;
  $payload['meta_label'] = '';

  return $payload;
}

function pims_version_current_live_patch_version(array $payload): string {
  $parts = pims_version_parse_numeric((string) ($payload['version'] ?? $payload['full'] ?? '0.0.0'));
  return pims_version_numeric_string($parts);
}

function pims_version_apply_live_filesystem_suffix(array $payload): array {
  $state = pims_version_filesystem_state();
  if (empty($state['sequence'])) {
    return pims_version_apply_numeric_display($payload);
  }

  $payload['live_fingerprint'] = $state['fingerprint'];
  $payload['live_sequence'] = $state['sequence'];
  $payload['tracked_source_files'] = $state['tracked_files'];
  return pims_version_apply_numeric_display($payload);
}

function pims_version_snapshot_path(): string {
  return __DIR__ . '/version_snapshot.php';
}

function pims_version_load_snapshot(): ?array {
  $path = pims_version_snapshot_path();
  if (!is_readable($path)) {
    return null;
  }

  $payload = @include $path;
  return is_array($payload) ? $payload : null;
}

function pims_version_write_snapshot(array $payload): bool {
  $path = pims_version_snapshot_path();
  $export = '<?php return ' . var_export($payload, true) . ';' . PHP_EOL;

  return @file_put_contents($path, $export) !== false;
}

function pims_version_compute_git_payload(): array {
  $cacheDir = sys_get_temp_dir();
  $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'pims_version_cache.php';
  $currentHead = pims_release_git('rev-parse HEAD');
  $currentStatus = pims_release_git('status --porcelain');
  $dirtyState = pims_version_dirty_state($currentStatus);
  $cacheState = $currentHead . '|' . ($dirtyState['fingerprint'] ?? 'clean');

  if ($currentHead && is_readable($cacheFile)) {
    $cached = @include $cacheFile;
    if (is_array($cached) && ($cached['state'] ?? '') === $cacheState) {
      return $cached['payload'];
    }
  }

  $shortHash = pims_release_git('rev-parse --short HEAD');
  $dirty = (bool) $dirtyState['dirty'];
  $commitDate = date('Y-m-d');
  $version = '0.0.0';
  $full = '0.0.0-local';
  $latestTag = null;
  $commitsSinceTag = null;
  $totalCommits = 0;
  $baselineState = pims_version_baseline_state();
  $baselineHash = $baselineState['hash'];
  $baselineVersion = $baselineState['version'];

  $commitDate = pims_release_git('log -1 --format=%cd --date=short') ?: $commitDate;
  $totalCommits = (int) pims_release_git('rev-list --count HEAD');
  $latestTag = pims_release_latest_tag();
  $exactTag = pims_release_exact_tag();
  $baseRef = $baselineHash !== '' ? $baselineHash : $latestTag;
  $commitsSinceTag = pims_release_commit_count($baseRef);

  if ($baselineVersion !== '') {
    $timeline = pims_version_patch_timeline($baselineVersion, pims_release_commits($baseRef));
    if ($timeline) {
      $lastVersion = trim((string) ($timeline[count($timeline) - 1]['version'] ?? ''));
      $version = $lastVersion !== '' ? $lastVersion : $baselineVersion;
    } else {
      $version = $baselineVersion;
    }
    $full = 'v' . $version;
    $commitsSinceTag = 0;
  } elseif ($exactTag) {
    $version = ltrim($exactTag, 'v');
    $full = $exactTag;
    $commitsSinceTag = 0;
  } else {
    $subjects = pims_release_commit_subjects($latestTag);
    $nextVersion = pims_release_next_version($latestTag, pims_release_detect_bump($subjects));
    $version = pims_release_format_version($nextVersion);
    $full = 'v' . $version;

    if ($commitsSinceTag > 0) {
      $full .= '-dev.' . $commitsSinceTag;
    }
  }

  $payload = [
    'name' => PIMS_RELEASE_NAME,
    'version' => $version,
    'full' => $full,
    'source' => 'git',
    'hash' => $shortHash ?: null,
    'tag' => $latestTag,
    'commits_since_tag' => $commitsSinceTag,
    'total_commits' => $totalCommits,
    'dirty' => $dirty,
    'changed_files' => (int) $dirtyState['changed_files'],
    'dirty_fingerprint' => $dirtyState['fingerprint'],
    'dirty_sequence' => $dirtyState['sequence'],
    'date' => $commitDate,
  ];

  $payload = pims_version_apply_numeric_display($payload);

  if ($currentHead) {
    @file_put_contents($cacheFile, '<?php return ' . var_export(['state' => $cacheState, 'payload' => $payload], true) . ';');
  }

  return $payload;
}

function pims_compute_version_autoload(): array {
  $override = getenv('APP_VERSION');
  if ($override) {
    return pims_version_payload_from_value($override, 'env');
  }

  if (pims_release_git_available()) {
    return pims_version_compute_git_payload();
  }

  $snapshot = pims_version_load_snapshot();
  if ($snapshot) {
    return gso_version_snapshot_payload($snapshot);
  }

  return gso_version_snapshot_payload(
    pims_version_payload_from_value('0.0.0', 'fallback')
  );
}

function gso_version_snapshot_payload(array $payload): array {
  $version = trim((string) ($payload['full'] ?? $payload['version'] ?? '0.0.0'));
  return pims_version_payload_from_value($version, (string) ($payload['source'] ?? 'snapshot'), $payload);
}

$versionPayload = pims_compute_version_autoload();
if (!isset($versionPayload['meta_label'])) {
  $versionPayload['meta_label'] = pims_version_meta_label($versionPayload);
}

if (isset($returnVersionArray) && $returnVersionArray === true) {
  return $versionPayload;
}
?>
<div
  class="float-right d-none d-sm-block"
  data-live-version-root="1"
  data-version-endpoint="<?= htmlspecialchars(pims_version_public_endpoint(), ENT_QUOTES); ?>"
>
  <b data-version-name><?= htmlspecialchars($versionPayload['name']); ?></b>
  <span data-version-full><?= htmlspecialchars($versionPayload['full']); ?></span>
  <small class="text-muted d-none" data-version-meta></small>
</div>
