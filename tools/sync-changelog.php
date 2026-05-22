#!/usr/bin/env php
<?php

require_once __DIR__ . '/../include/release.php';

$state = pims_release_sync_changelog_snapshot();
$pendingCount = count($state['pending']['commits']);
$plannedTag = $state['pending']['next_tag'];

fwrite(
  STDOUT,
  'CHANGELOG synced. Pending commits: ' . $pendingCount . '. Next release: ' . $plannedTag . PHP_EOL
);
