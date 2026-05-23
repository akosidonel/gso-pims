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

if (!pims_version_write_snapshot($versionPayload)) {
  fwrite(STDERR, "Failed to write version snapshot.\n");
  exit(1);
}

fwrite(STDOUT, 'Version snapshot synced: ' . $versionPayload['full'] . PHP_EOL);
