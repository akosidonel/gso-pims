<?php
include_once('../config/session.php');
include('../config/check_session.php');
require_once('../auth/auth.php');

if (!isset($_SESSION['alogin'])) {
  header('Location:../index.php');
  exit();
}

$changeLogPayload = gso_realtime_changelog_payload();
$changeLogVersion = is_array($changeLogPayload['version'] ?? null) ? $changeLogPayload['version'] : [];
$gitCommits = is_array($changeLogPayload['recent_comments'] ?? null) ? $changeLogPayload['recent_comments'] : [];
$changeLogUpdatedLabel = trim((string)($changeLogPayload['updated_label'] ?? ''));
?>
<?php include('../include/header.php') ?><!--Header-->
<?php include('../include/navbar.php') ?><!-- Navbar -->
<?php include('../include/sidebar.php') ?><!--Sidebar-->

<div id="destroy"></div>

<div class="content-wrapper gso-dashboard">
  <section class="content">
    <div class="container-fluid gso-changelog-shell gso-realtime-changelog" data-changelog-live="1">
      <h1 class="gso-changelog-title">Changelog</h1>

      <section class="gso-live-card gso-live-comments-card">
        <div id="gsoChangeLogVersion">
          <div class="gso-live-cardhead">
            <h2 class="gso-live-cardtitle">Current Version</h2>
          </div>
          <div class="gso-live-version"><?= htmlspecialchars($changeLogVersion['full'] ?? 'Unavailable'); ?></div>
          <div class="gso-live-meta"><?= htmlspecialchars($changeLogUpdatedLabel !== '' ? 'Updated ' . $changeLogUpdatedLabel : 'Date unavailable'); ?></div>
        </div>
      </section>

      <section class="gso-live-card">
        <div id="gsoChangeLogComments">
          <?php if (!$gitCommits): ?>
            <p class="gso-changelog-empty">No commit messages yet.</p>
          <?php else: ?>
            <?php foreach ($gitCommits as $commit): ?>
              <?php $commitDate = trim((string)($commit['committed_label'] ?? $commit['committed_ago'] ?? '')); ?>
              <article class="gso-simple-log-row">
                <div class="gso-simple-log-version"><?= htmlspecialchars($commit['patch_version'] ?: 'Pending'); ?></div>
                <div class="gso-simple-log-comment"><?= htmlspecialchars($commit['subject'] ?? 'Updated project files'); ?></div>
                <div class="gso-simple-log-date"><?= htmlspecialchars($commitDate !== '' ? $commitDate : 'Date unavailable'); ?></div>
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
