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
    'full' => $fullVersion,
    'source' => $source,
    'hash' => null,
    'tag' => null,
    'commits_since_tag' => null,
    'total_commits' => 0,
    'dirty' => false,
    'date' => date('Y-m-d'),
  ], $overrides);
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

  return file_put_contents($path, $export) !== false;
}

function pims_version_compute_git_payload(): array {
  $cacheDir = sys_get_temp_dir();
  $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'pims_version_cache.php';
  $currentHead = pims_release_git('rev-parse HEAD');
  $currentStatus = pims_release_git('status --porcelain');
  $cacheState = $currentHead . '|' . md5($currentStatus);

  if ($currentHead && is_readable($cacheFile)) {
    $cached = @include $cacheFile;
    if (is_array($cached) && ($cached['state'] ?? '') === $cacheState) {
      return $cached['payload'];
    }
  }

  $shortHash = pims_release_git('rev-parse --short HEAD');
  $dirty = $currentStatus !== '';
  $commitDate = date('Y-m-d');
  $version = '0.0.0';
  $full = '0.0.0-local';
  $latestTag = null;
  $commitsSinceTag = null;
  $totalCommits = 0;

  $commitDate = pims_release_git('log -1 --format=%cd --date=short') ?: $commitDate;
  $totalCommits = (int) pims_release_git('rev-list --count HEAD');
  $latestTag = pims_release_latest_tag();
  $exactTag = pims_release_exact_tag();
  $commitsSinceTag = pims_release_commit_count($latestTag);

  if ($exactTag) {
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

  if ($dirty) {
    $full .= '.dirty';
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
    'date' => $commitDate,
  ];

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
    return $snapshot;
  }

  return pims_version_payload_from_value('0.0.0-local', 'fallback');
}

$versionPayload = pims_compute_version_autoload();

if (isset($returnVersionArray) && $returnVersionArray === true) {
  return $versionPayload;
}
?>
<div class="float-right d-none d-sm-block">
  <b><?= htmlspecialchars($versionPayload['name']); ?></b>
  <?= htmlspecialchars($versionPayload['full']); ?>
  <?php if ($versionPayload['source'] === 'env'): ?>
  <small class="text-muted" title="APP_VERSION override">(override)</small>
  <?php elseif (in_array($versionPayload['source'], ['git', 'snapshot'], true)): ?>
    <?php if ((int) $versionPayload['commits_since_tag'] > 0): ?>
    <small class="text-muted" title="Unreleased commits">(<?= (int) $versionPayload['commits_since_tag']; ?> unreleased)</small>
    <?php endif; ?>
  <?php endif; ?>
</div>
