<?php
include_once('../config/session.php');
include('../config/check_session.php');
require_once('../include/release.php');
require_once('../include/version.php');

if (!isset($_SESSION['alogin'])) {
  header('Location:../index.php');
  exit();
}

$versionPayload = pims_compute_version_autoload();
$gitLatestTag = pims_release_latest_tag();
$gitCommits = pims_release_recent_commits(12);
$gitTimeline = pims_version_patch_timeline($gitLatestTag ? ltrim($gitLatestTag, 'v') : null, array_reverse(pims_release_commits($gitLatestTag)));
$gitPatchByHash = [];

foreach ($gitTimeline as $timelineEntry) {
  $timelineHash = trim((string) ($timelineEntry['hash'] ?? ''));
  if ($timelineHash === '') {
    continue;
  }
  $gitPatchByHash[$timelineHash] = (string) ($timelineEntry['version'] ?? '');
}

foreach ($gitCommits as $index => $commit) {
  $commitHash = trim((string) ($commit['hash'] ?? ''));
  $gitCommits[$index]['patch_version'] = $gitPatchByHash[$commitHash] ?? '';
}
?>
<?php include('../include/header.php') ?><!--Header-->
<?php include('../include/navbar.php') ?><!-- Navbar -->
<?php include('../include/sidebar.php') ?><!--Sidebar-->

<div id="destroy"></div>

<div class="content-wrapper gso-dashboard">
  <section class="content">
    <div class="container-fluid gso-changelog-shell gso-realtime-changelog" data-changelog-live="1">
      <h1 class="gso-changelog-title">Changelog</h1>

      <section class="gso-live-grid">
        <article class="gso-live-card">
          <div id="gsoChangeLogVersion">
            <div class="gso-live-cardhead">
              <h2 class="gso-live-cardtitle">Current Version</h2>
            </div>
            <div class="gso-live-version"><?= htmlspecialchars($versionPayload['full'] ?? 'Unavailable'); ?></div>
          </div>
        </article>
      </section>

      <section class="gso-live-card">
        <div id="gsoChangeLogComments">
          <?php if (!$gitCommits): ?>
            <p class="gso-changelog-empty">No commit messages yet.</p>
          <?php else: ?>
            <?php foreach ($gitCommits as $commit): ?>
              <article class="gso-simple-log-row">
                <div class="gso-simple-log-version"><?= htmlspecialchars($commit['patch_version'] ?: 'Pending'); ?></div>
                <div class="gso-simple-log-comment"><?= htmlspecialchars($commit['subject'] ?? 'Updated project files'); ?></div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </section>
</div>

<?php include('../include/footer.php') ?><!--footer-->
</div><!-- ./wrapper -->
<?php include('../include/script.php') ?><!--script-->
