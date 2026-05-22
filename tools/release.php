#!/usr/bin/env php
<?php

require_once __DIR__ . '/../include/release.php';

$allowedModes = ['auto', 'major', 'minor', 'patch'];
$mode = 'auto';
$dryRun = false;

foreach (array_slice($argv, 1) as $argument) {
  if ($argument === '--dry-run') {
    $dryRun = true;
    continue;
  }

  if (in_array($argument, $allowedModes, true)) {
    $mode = $argument;
    continue;
  }

  fwrite(STDERR, "Unknown option: {$argument}\n");
  fwrite(STDERR, "Usage: php tools/release.php [auto|major|minor|patch] [--dry-run]\n");
  exit(1);
}

if (!pims_release_git_available()) {
  fwrite(STDERR, "Git metadata is required for automated releases.\n");
  exit(1);
}

$workingTreeDirty = pims_release_git('status --porcelain');
if ($workingTreeDirty !== '' && !$dryRun) {
  fwrite(STDERR, "Working tree is not clean. Commit or stash changes before running a release.\n");
  exit(1);
}

$latestTag = pims_release_latest_tag();
$commits = pims_release_commits($latestTag);

if (!$commits) {
  fwrite(STDOUT, "No unreleased commits found.\n");
  exit(0);
}

$subjects = array_column($commits, 'raw_subject');
$bump = $mode === 'auto' ? pims_release_detect_bump($subjects) : $mode;
$nextVersion = pims_release_next_version($latestTag, $bump);
$nextTag = pims_release_format_tag($nextVersion);
$releaseDate = date('Y-m-d');
$entry = pims_release_render_entry($nextTag, $releaseDate, $commits);

if ($dryRun) {
  if ($workingTreeDirty !== '') {
    fwrite(STDOUT, "Warning: working tree is not clean. Dry run only.\n\n");
  }
  fwrite(STDOUT, "Latest tag: " . ($latestTag ?: 'none') . PHP_EOL);
  fwrite(STDOUT, "Detected bump: {$bump}" . PHP_EOL);
  fwrite(STDOUT, "Next tag: {$nextTag}" . PHP_EOL . PHP_EOL);
  fwrite(STDOUT, $entry . PHP_EOL);
  exit(0);
}

$changelogPath = pims_release_changelog_path();
$changelog = pims_release_prepare_changelog($entry);
if (file_put_contents($changelogPath, $changelog) === false) {
  fwrite(STDERR, "Failed to update CHANGELOG.md.\n");
  exit(1);
}

$commands = [
  'add ' . escapeshellarg($changelogPath),
  'commit -m ' . escapeshellarg('release: ' . $nextTag),
  'tag -a ' . escapeshellarg($nextTag) . ' -m ' . escapeshellarg('Release ' . $nextTag),
];

foreach ($commands as $command) {
  passthru('git ' . $command, $exitCode);
  if ($exitCode !== 0) {
    fwrite(STDERR, "Release command failed: git {$command}\n");
    exit($exitCode);
  }
}

fwrite(STDOUT, "Created {$nextTag} and updated CHANGELOG.md.\n");
