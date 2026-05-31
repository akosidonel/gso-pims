#!/usr/bin/env php
<?php

$returnVersionArray = true;
$versionPayload = require __DIR__ . '/../include/version.php';

if (!is_array($versionPayload)) {
  fwrite(STDERR, "Unable to compute version snapshot.\n");
  exit(1);
}

$versionPayload['source'] = 'snapshot';
$versionPayload['hash'] = null;
$versionPayload['full'] = preg_replace('/\.dirty$/', '', (string) $versionPayload['full']) ?: $versionPayload['full'];
$versionPayload['dirty'] = false;
$versionPayload['changed_files'] = 0;
$versionPayload['dirty_fingerprint'] = null;
$versionPayload['dirty_sequence'] = null;
$versionPayload['live_fingerprint'] = null;
$versionPayload['live_sequence'] = null;
$versionPayload['tracked_source_files'] = null;

if (!pims_version_write_snapshot($versionPayload)) {
  fwrite(STDERR, "Failed to write version snapshot.\n");
  exit(1);
}

fwrite(STDOUT, 'Version snapshot synced: ' . $versionPayload['full'] . PHP_EOL);
