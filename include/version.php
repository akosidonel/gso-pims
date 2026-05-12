<?php
/**
 * Automatic versioning (standard pattern) for P.I.M.S
 *
 * Hierarchy:
 * 1. If APP_VERSION env var is set -> use it (manual override for deployments / containers)
 * 2. Else derive from git:
 *    - If at tag vX.Y.Z exactly: X.Y.Z
 *    - If ahead of tag: X.Y.Z-N+<hash>
 *    - If no tag yet: 0.0.0+<hash>
 *    - Append .dirty if uncommitted changes
 * 3. Fallback (no git): 0.0.0+YYYYMMDDHHMM
 *
 * Lightweight caching:
 *  - Writes last computed version & HEAD hash to a cache file; recomputes only when HEAD changes.
 */

function pims_compute_version_autoload(): array {
  // 1. Environment override
  $override = getenv('APP_VERSION');
  if ($override) {
    return [
      'name' => 'P.I.M.S',
      'version' => $override,
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
  $gitAvailable = (trim(@shell_exec('git rev-parse --is-inside-work-tree 2>/dev/null')) === 'true');
  $currentHead = $gitAvailable ? trim(@shell_exec('git rev-parse HEAD 2>/dev/null')) : '';

  // 2. Use cache if head unchanged
  if ($gitAvailable && $currentHead && is_readable($cacheFile)) {
    $cached = @include $cacheFile;
    if (is_array($cached) && ($cached['head'] ?? '') === $currentHead) {
      return $cached['payload'];
    }
  }

  // Compute fresh
  $shortHash = '';
  $dirty = false;
  $commitDate = date('Y-m-d');
  $source = $gitAvailable ? 'git' : 'fallback';
  $totalCommits = 0;
  if ($gitAvailable) {
    $totalCommits = (int) trim(@shell_exec('git rev-list --count HEAD 2>/dev/null')) ?: 0;
    $shortHash = trim(@shell_exec('git rev-parse --short HEAD 2>/dev/null')) ?: '';
    $dirty = (trim(@shell_exec('git diff --quiet 2>/dev/null; echo $?')) === '1');
    $commitDate = trim(@shell_exec('git log -1 --format=%cd --date=short 2>/dev/null')) ?: $commitDate;
  } else {
    // Fallback: pseudo commit count based on minutes since epoch / 10 (rough uniqueness)
    $totalCommits = (int) floor(time() / 600);
  }
  // Option 2 scheme: major fixed (configurable), minor = floor(total/100), patch = total % 100
  $majorSeed = 1; // change this if you ever want to bump major manually
  $minor = (int) floor($totalCommits / 100);
  $patch = $totalCommits % 100;
  $base = $majorSeed . '.' . $minor . '.' . $patch;
  $full = $base;
  if ($shortHash) { $full .= '+' . $shortHash; }
  if ($dirty) { $full .= '.dirty'; }

  $payload = [
    'name' => 'P.I.M.S',
    'version' => $base,
    'full' => $full,
    'source' => $source,
    'hash' => $shortHash ?: null,
    'tag' => null,
    'commits_since_tag' => null,
    'total_commits' => $totalCommits,
    'dirty' => $dirty,
    'date' => $commitDate,
  ];

  // Write cache (best effort)
  if ($gitAvailable && $currentHead) {
    @file_put_contents($cacheFile, '<?php return ' . var_export(['head' => $currentHead, 'payload' => $payload], true) . ';');
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
  v<?= htmlspecialchars($versionPayload['full']); ?>
  <?php if ($versionPayload['source'] === 'env'): ?>
  <small class="text-muted" title="APP_VERSION override">(override)</small>
  <?php elseif ($versionPayload['source'] === 'git'): ?>
    <small class="text-muted" title="Total commits">(<?= (int)$versionPayload['total_commits']; ?>)</small>
  <?php endif; ?>
</div>