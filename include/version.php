<?php
/**
 * Automatic version display for tagged releases.
 *
 * Preferred format:
 * - Tagged release: v1.20.1
 * - Ahead of latest tag: v1.20.2-dev.3
 *
 * Set APP_VERSION to override the computed value in environments that do not
 * include git metadata.
 */

require_once __DIR__ . '/release.php';

function pims_compute_version_autoload(): array {
  $override = getenv('APP_VERSION');
  if ($override) {
    return [
      'name' => PIMS_RELEASE_NAME,
      'version' => ltrim($override, 'v'),
      'full' => $override,
      'source' => 'env',
      'hash' => null,
      'tag' => null,
      'commits_since_tag' => null,
      'dirty' => false,
      'date' => date('Y-m-d'),
    ];
  }

  $cacheDir = sys_get_temp_dir();
  $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'pims_version_cache.php';
  $gitAvailable = pims_release_git_available();
  $currentHead = $gitAvailable ? pims_release_git('rev-parse HEAD') : '';
  $currentStatus = $gitAvailable ? pims_release_git('status --porcelain') : '';
  $cacheState = $currentHead . '|' . md5($currentStatus);

  if ($gitAvailable && $currentHead && is_readable($cacheFile)) {
    $cached = @include $cacheFile;
    if (is_array($cached) && ($cached['state'] ?? '') === $cacheState) {
      return $cached['payload'];
    }
  }

  $shortHash = '';
  $dirty = false;
  $commitDate = date('Y-m-d');
  $source = $gitAvailable ? 'git' : 'fallback';
  $version = '0.0.0';
  $full = '0.0.0-local';
  $latestTag = null;
  $commitsSinceTag = null;
  $totalCommits = 0;

  if ($gitAvailable) {
    $shortHash = pims_release_git('rev-parse --short HEAD');
    $dirty = pims_release_git('status --porcelain') !== '';
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
  }

  $payload = [
    'name' => PIMS_RELEASE_NAME,
    'version' => $version,
    'full' => $full,
    'source' => $source,
    'hash' => $shortHash ?: null,
    'tag' => $latestTag,
    'commits_since_tag' => $commitsSinceTag,
    'total_commits' => $totalCommits,
    'dirty' => $dirty,
    'date' => $commitDate,
  ];

  if ($gitAvailable && $currentHead) {
    @file_put_contents($cacheFile, '<?php return ' . var_export(['state' => $cacheState, 'payload' => $payload], true) . ';');
  }

  return $payload;
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
  <?php elseif ($versionPayload['source'] === 'git'): ?>
    <?php if ((int) $versionPayload['commits_since_tag'] > 0): ?>
    <small class="text-muted" title="Unreleased commits">(<?= (int) $versionPayload['commits_since_tag']; ?> unreleased)</small>
    <?php endif; ?>
  <?php endif; ?>
</div>
