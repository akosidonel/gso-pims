<?php

const PIMS_RELEASE_NAME = 'P.I.M.S';
const PIMS_RELEASE_INITIAL_MAJOR = 1;
const PIMS_RELEASE_INITIAL_MINOR = 0;
const PIMS_RELEASE_INITIAL_PATCH = 0;
const PIMS_RELEASE_ROOT = __DIR__ . '/..';

function pims_release_git_binary(): string {
  $candidates = [
    '/usr/bin/git',
    '/opt/homebrew/bin/git',
    '/usr/local/bin/git',
  ];

  foreach ($candidates as $candidate) {
    if (is_executable($candidate)) {
      return $candidate;
    }
  }

  return 'git';
}

function pims_release_git_available(): bool {
  $command = escapeshellarg(pims_release_git_binary()) . ' -C ' . escapeshellarg(PIMS_RELEASE_ROOT) . ' rev-parse --is-inside-work-tree 2>/dev/null';
  return trim((string) @shell_exec($command)) === 'true';
}

function pims_release_git(string $command): string {
  $binary = escapeshellarg(pims_release_git_binary());
  return trim((string) @shell_exec($binary . ' -C ' . escapeshellarg(PIMS_RELEASE_ROOT) . ' ' . $command . ' 2>/dev/null'));
}

function pims_release_commit_log_format(): string {
  return '--pretty=format:%H%x1f%h%x1f%s%x1f%an%x1f%ad%x1f%at --date=iso-strict';
}

function pims_release_relative_time(?int $timestamp): string {
  $timestamp = (int) $timestamp;
  if ($timestamp <= 0) {
    return 'Unknown time';
  }

  $diff = time() - $timestamp;
  if ($diff <= 0) {
    return 'Just now';
  }

  if ($diff < 60) {
    return $diff . 's ago';
  }

  if ($diff < 3600) {
    return floor($diff / 60) . 'm ago';
  }

  if ($diff < 86400) {
    return floor($diff / 3600) . 'h ago';
  }

  if ($diff < 604800) {
    return floor($diff / 86400) . 'd ago';
  }

  return date('M j, Y g:i A', $timestamp);
}

function pims_release_parse_commit_line(string $line): ?array {
  $parts = explode("\x1f", trim($line));
  if (count($parts) !== 6) {
    return null;
  }

  $timestamp = (int) trim($parts[5]);

  return [
    'hash' => trim($parts[0]),
    'short_hash' => trim($parts[1]),
    'subject' => pims_release_pretty_subject($parts[2]),
    'raw_subject' => trim($parts[2]),
    'author' => trim($parts[3]),
    'committed_at' => trim($parts[4]),
    'committed_timestamp' => $timestamp,
    'committed_ago' => pims_release_relative_time($timestamp),
  ];
}

function pims_release_parse_tag(?string $tag): ?array {
  if (!$tag || !preg_match('/^v(\d+)\.(\d+)\.(\d+)$/', $tag, $matches)) {
    return null;
  }

  return [
    'major' => (int) $matches[1],
    'minor' => (int) $matches[2],
    'patch' => (int) $matches[3],
  ];
}

function pims_release_starts_with(string $value, string $prefix): bool {
  return strpos($value, $prefix) === 0;
}

function pims_release_format_version(array $version): string {
  return $version['major'] . '.' . $version['minor'] . '.' . $version['patch'];
}

function pims_release_format_tag(array $version): string {
  return 'v' . pims_release_format_version($version);
}

function pims_release_initial_version(): array {
  return [
    'major' => PIMS_RELEASE_INITIAL_MAJOR,
    'minor' => PIMS_RELEASE_INITIAL_MINOR,
    'patch' => PIMS_RELEASE_INITIAL_PATCH,
  ];
}

function pims_release_latest_tag(): ?string {
  $tag = pims_release_git('tag --list "v[0-9]*" --sort=-version:refname | head -n 1');
  return $tag !== '' ? $tag : null;
}

function pims_release_all_tags(): array {
  $output = pims_release_git('tag --list "v[0-9]*" --sort=-version:refname');
  if ($output === '') {
    return [];
  }

  return array_values(array_filter(array_map('trim', explode("\n", $output))));
}

function pims_release_exact_tag(): ?string {
  $tag = pims_release_git('describe --tags --exact-match --match "v[0-9]*" HEAD');
  return $tag !== '' ? $tag : null;
}

function pims_release_commit_count(?string $tag): int {
  $range = $tag ? escapeshellarg($tag . '..HEAD') : 'HEAD';
  return (int) pims_release_git('rev-list --count ' . $range);
}

function pims_release_commit_subjects(?string $tag): array {
  $range = $tag ? escapeshellarg($tag . '..HEAD') : 'HEAD';
  $output = pims_release_git('log --reverse --pretty=format:%s ' . $range);

  if ($output === '') {
    return [];
  }

  return array_values(array_filter(array_map('trim', explode("\n", $output))));
}

function pims_release_commits(?string $tag): array {
  $range = $tag ? escapeshellarg($tag . '..HEAD') : 'HEAD';
  $output = pims_release_git('log --reverse ' . pims_release_commit_log_format() . ' ' . $range);

  if ($output === '') {
    return [];
  }

  $commits = [];
  foreach (explode("\n", $output) as $line) {
    $commit = pims_release_parse_commit_line($line);
    if ($commit) {
      $commits[] = $commit;
    }
  }

  return $commits;
}

function pims_release_commits_between(?string $fromTag, string $toTag): array {
  if ($fromTag) {
    $range = escapeshellarg($fromTag . '..' . $toTag);
  } else {
    $range = escapeshellarg($toTag);
  }

  $output = pims_release_git('log --reverse ' . pims_release_commit_log_format() . ' ' . $range);

  if ($output === '') {
    return [];
  }

  $commits = [];
  foreach (explode("\n", $output) as $line) {
    $commit = pims_release_parse_commit_line($line);
    if ($commit) {
      $commits[] = $commit;
    }
  }

  return $commits;
}

function pims_release_recent_commits(int $limit = 12): array {
  $limit = max(1, (int) $limit);
  $output = pims_release_git('log -n ' . $limit . ' ' . pims_release_commit_log_format() . ' HEAD');

  if ($output === '') {
    return [];
  }

  $commits = [];
  foreach (explode("\n", $output) as $line) {
    $commit = pims_release_parse_commit_line($line);
    if ($commit) {
      $commits[] = $commit;
    }
  }

  return $commits;
}

function pims_release_tag_date(string $tag): string {
  return pims_release_git('log -1 --format=%cd --date=short ' . escapeshellarg($tag)) ?: date('Y-m-d');
}

function pims_release_pretty_subject(string $subject): string {
  $subject = preg_replace('/\s+/', ' ', trim($subject));
  $subject = rtrim($subject, '.');

  if ($subject === '') {
    return 'Updated project files';
  }

  if (preg_match('/^file udpated$/i', $subject) || preg_match('/^file updated$/i', $subject)) {
    return 'Updated internal project files';
  }

  if (preg_match('/^(minor )?bug fixes? and (system )?improvements?( and updates)?$/i', $subject)) {
    return 'General bug fixes and maintenance improvements';
  }

  if (preg_match('/^minor update and bug fixes$/i', $subject)) {
    return 'General maintenance updates and bug fixes';
  }

  return ucfirst($subject);
}

function pims_release_detect_bump(array $subjects): string {
  foreach ($subjects as $subject) {
    $normalized = strtolower(trim($subject));
    if (
      pims_release_starts_with($normalized, 'major:') ||
      strpos($normalized, 'breaking change') !== false ||
      strpos($normalized, 'breaking:') !== false
    ) {
      return 'major';
    }
  }

  foreach ($subjects as $subject) {
    $normalized = strtolower(trim($subject));
    if (preg_match('/^(feat|feature|add|added|new|create|created)\b/', $normalized)) {
      return 'minor';
    }
  }

  return 'patch';
}

function pims_release_next_version(?string $latestTag, string $bump): array {
  $current = $latestTag ? pims_release_parse_tag($latestTag) : null;
  if (!$current) {
    return pims_release_initial_version();
  }

  $next = $current;
  if ($bump === 'major') {
    $next['major']++;
    $next['minor'] = 0;
    $next['patch'] = 0;
    return $next;
  }

  if ($bump === 'minor') {
    $next['minor']++;
    $next['patch'] = 0;
    return $next;
  }

  $next['patch']++;
  return $next;
}

function pims_release_group_name(string $subject): string {
  $normalized = strtolower(trim($subject));

  if (preg_match('/^(feat|feature|add|added|new|create|created)\b/', $normalized)) {
    return 'Added';
  }

  if (preg_match('/^(fix|fixed|bug|hotfix|repair)\b/', $normalized) || strpos($normalized, 'bug fix') !== false) {
    return 'Fixed';
  }

  if (preg_match('/^(remove|removed|delete|deleted|drop)\b/', $normalized)) {
    return 'Removed';
  }

  return 'Changed';
}

function pims_release_section_lines(array $commits): array {
  $sections = pims_release_grouped_sections($commits);
  $lines = [];

  foreach ($sections as $title => $items) {
    $lines[] = '### ' . $title;
    foreach ($items as $item) {
      $lines[] = '- ' . $item;
    }
    $lines[] = '';
  }

  if ($lines && end($lines) === '') {
    array_pop($lines);
  }

  return $lines;
}

function pims_release_grouped_sections(array $commits): array {
  $sections = [
    'Added' => [],
    'Changed' => [],
    'Fixed' => [],
    'Removed' => [],
  ];

  foreach ($commits as $commit) {
    $sections[pims_release_group_name($commit['raw_subject'])][] = $commit['subject'];
  }

  foreach ($sections as $title => $items) {
    $counts = array_count_values($items);
    $deduped = [];
    foreach ($counts as $item => $count) {
      $deduped[] = $count > 1 ? $item . ' (' . $count . ' times)' : $item;
    }
    $sections[$title] = $deduped;
  }

  return array_filter($sections);
}

function pims_release_render_entry(string $tag, string $date, array $commits): string {
  $lines = [
    '## ' . $tag . ' - ' . $date,
    '',
  ];

  $lines = array_merge($lines, pims_release_section_lines($commits), ['']);
  return implode(PHP_EOL, $lines);
}

function pims_release_changelog_path(): string {
  return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'CHANGELOG.md';
}

function pims_release_changelog_baseline_path(): string {
  return __DIR__ . '/changelog_baseline.json';
}

function pims_release_load_changelog_baseline(): ?array {
  $path = pims_release_changelog_baseline_path();
  if (!is_readable($path)) {
    return null;
  }

  $decoded = json_decode((string) file_get_contents($path), true);
  if (!is_array($decoded)) {
    return null;
  }

  $hash = trim((string) ($decoded['hash'] ?? ''));
  $version = trim((string) ($decoded['version'] ?? ''));
  if ($hash === '' || $version === '') {
    return null;
  }

  return [
    'hash' => $hash,
    'version' => $version,
    'date' => trim((string) ($decoded['date'] ?? '')),
    'created_at' => trim((string) ($decoded['created_at'] ?? '')),
  ];
}

function pims_release_changelog_template(): string {
  return implode(PHP_EOL, [
    '# Changelog',
    '',
    'This file is generated from git history by `php tools/release.php`.',
    '',
  ]);
}

function pims_release_strip_unreleased_section(string $content): string {
  $content = trim($content);
  if ($content === '') {
    return '';
  }

  return trim((string) preg_replace(
    '/(^|\n)## Unreleased(?:\s*\(planned [^)]+\))?\s*-\s*[^\n]*(?:\n(?!## ).*)*/m',
    '',
    $content
  ));
}

function pims_release_prepare_changelog(string $entry): string {
  $path = pims_release_changelog_path();
  $existing = is_readable($path) ? (string) file_get_contents($path) : pims_release_changelog_template();
  $existing = rtrim($existing);
  $template = rtrim(pims_release_changelog_template());

  if ($existing === '') {
    $existing = $template;
  }

  $tail = $existing;
  if (pims_release_starts_with($existing, $template)) {
    $tail = trim(substr($existing, strlen($template)));
  }

  $tail = pims_release_strip_unreleased_section($tail);

  if ($tail === 'No tagged releases yet.') {
    $tail = '';
  }

  $content = $template . PHP_EOL . PHP_EOL . trim($entry);
  if ($tail !== '') {
    $content .= PHP_EOL . PHP_EOL . $tail;
  }

  return $content . PHP_EOL;
}

function pims_release_parse_changelog_content(string $content): array {
  $lines = preg_split('/\R/', $content);
  if (!is_array($lines)) {
    return [];
  }

  $entries = [];
  $currentEntry = null;
  $currentSection = null;

  foreach ($lines as $line) {
    $trimmed = trim($line);

    if (preg_match('/^##\s+(.+?)\s*-\s*(.+)$/', $trimmed, $matches)) {
      if ($currentEntry) {
        $entries[] = $currentEntry;
      }

      $currentEntry = [
        'tag' => $matches[1],
        'date' => trim($matches[2]),
        'sections' => [],
      ];
      $currentSection = null;
      continue;
    }

    if (!$currentEntry) {
      continue;
    }

    if (preg_match('/^###\s+(.+)$/', $trimmed, $matches)) {
      $currentSection = trim($matches[1]);
      if (!isset($currentEntry['sections'][$currentSection])) {
        $currentEntry['sections'][$currentSection] = [];
      }
      continue;
    }

    if ($currentSection && preg_match('/^-+\s+(.+)$/', $trimmed, $matches)) {
      $currentEntry['sections'][$currentSection][] = trim($matches[1]);
    }
  }

  if ($currentEntry) {
    $entries[] = $currentEntry;
  }

  return $entries;
}

function pims_release_parse_changelog(?string $path = null): array {
  $path = $path ?: pims_release_changelog_path();
  if (!is_readable($path)) {
    return [];
  }

  $content = (string) file_get_contents($path);
  if ($content === '') {
    return [];
  }

  return pims_release_parse_changelog_content($content);
}

function pims_release_pending_summary(): array {
  $baseline = pims_release_load_changelog_baseline();

  if (!pims_release_git_available()) {
    return [
      'latest_tag' => null,
      'commits' => [],
      'bump' => 'patch',
      'next_tag' => pims_release_format_tag(pims_release_initial_version()),
      'baseline' => $baseline,
    ];
  }

  $latestTag = pims_release_latest_tag();
  $baseRef = $baseline['hash'] ?? $latestTag;
  $commits = pims_release_commits($baseRef);
  $subjects = array_column($commits, 'raw_subject');
  $bump = pims_release_detect_bump($subjects);
  $versionBaseTag = $baseline ? ('v' . $baseline['version']) : $latestTag;
  $nextVersion = pims_release_next_version($versionBaseTag, $bump);

  return [
    'latest_tag' => $baseline['version'] ?? $latestTag,
    'commits' => $commits,
    'bump' => $bump,
    'next_tag' => pims_release_format_tag($nextVersion),
    'baseline' => $baseline,
  ];
}

function pims_release_render_unreleased_entry(string $label, string $date, array $sections): string {
  $lines = [
    '## ' . $label . ' - ' . $date,
    '',
  ];

  foreach ($sections as $title => $items) {
    $lines[] = '### ' . $title;
    foreach ($items as $item) {
      $lines[] = '- ' . $item;
    }
    $lines[] = '';
  }

  if ($lines && end($lines) === '') {
    array_pop($lines);
  }

  return implode(PHP_EOL, $lines);
}

function pims_release_sync_changelog_snapshot(bool $writeToFile = true): array {
  $path = pims_release_changelog_path();
  $template = rtrim(pims_release_changelog_template());
  $pending = pims_release_pending_summary();
  $baseline = $pending['baseline'] ?? null;
  $existingContent = is_readable($path) ? (string) file_get_contents($path) : '';

  if (!pims_release_git_available()) {
    $content = trim($existingContent) !== ''
      ? $existingContent
      : $template . PHP_EOL . PHP_EOL . 'No recorded entries yet.' . PHP_EOL;

    return [
      'pending' => $pending,
      'sections' => [],
      'content' => $content,
      'entries' => pims_release_parse_changelog_content($content),
    ];
  }

  $pendingSections = pims_release_grouped_sections($pending['commits']);
  $parts = [$template];

  if ($pendingSections) {
    $parts[] = pims_release_render_unreleased_entry(
      'Unreleased (planned ' . $pending['next_tag'] . ')',
      date('F j, Y'),
      $pendingSections
    );
  }

  $releaseEntries = [];
  if (!$baseline) {
    $tags = pims_release_all_tags();
    $tagCount = count($tags);
    for ($index = 0; $index < $tagCount; $index++) {
      $tag = $tags[$index];
      $previousTag = ($index + 1 < $tagCount) ? $tags[$index + 1] : null;
      $commits = pims_release_commits_between($previousTag, $tag);
      if (!$commits) {
        continue;
      }

      $releaseEntries[] = pims_release_render_entry($tag, pims_release_tag_date($tag), $commits);
    }
  }

  if ($releaseEntries) {
    $parts = array_merge($parts, $releaseEntries);
  } elseif (!$pendingSections) {
    $parts[] = 'No recorded entries yet.';
  }

  $final = implode(PHP_EOL . PHP_EOL, array_filter($parts, static function($part) {
    return trim((string) $part) !== '';
  })) . PHP_EOL;

  if ($writeToFile) {
    @file_put_contents($path, $final);
  }

  return [
    'pending' => $pending,
    'sections' => $pendingSections,
    'content' => $final,
    'entries' => pims_release_parse_changelog_content($final),
  ];
}
