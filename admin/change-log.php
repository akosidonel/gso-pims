<?php
include_once('../config/session.php');
include('../config/check_session.php');
require_once('../include/release.php');

if (!isset($_SESSION['alogin'])) {
  header('Location:../index.php');
  exit();
}

$returnVersionArray = true;
$versionMeta = include('../include/version.php');
$syncState = pims_release_sync_changelog_snapshot();
$releaseEntries = pims_release_parse_changelog();
$pendingRelease = $syncState['pending'];
?>
<?php include('../include/header.php') ?><!--Header-->
<?php include('../include/navbar.php') ?><!-- Navbar -->
<?php include('../include/sidebar.php') ?><!--Sidebar-->

<div id="destroy"></div>

<div class="content-wrapper gso-dashboard">
  <section class="content">
    <div class="container-fluid gso-changelog-shell">
      <h1 class="gso-changelog-title">Changelog</h1>

      <?php if (!$releaseEntries): ?>
        <p class="gso-changelog-empty">No changelog entries available yet.</p>
      <?php else: ?>
        <?php foreach ($releaseEntries as $entry): ?>
          <section class="gso-changelog-release">
            <h2 class="gso-changelog-version">
              <?= htmlspecialchars($entry['tag']); ?>
              <span>(<?= htmlspecialchars($entry['date']); ?>)</span>
            </h2>

            <div class="gso-changelog-timeline">
              <?php foreach ($entry['sections'] as $sectionTitle => $items): ?>
                <div class="gso-changelog-group">
                  <div class="gso-changelog-grouphead">
                    <span class="gso-changelog-dot">
                      <?php if ($sectionTitle === 'Added'): ?>
                        <i class="fas fa-star"></i>
                      <?php elseif ($sectionTitle === 'Fixed'): ?>
                        <i class="fas fa-bug"></i>
                      <?php elseif ($sectionTitle === 'Removed'): ?>
                        <i class="fas fa-exclamation"></i>
                      <?php else: ?>
                        <i class="fas fa-pencil-alt"></i>
                      <?php endif; ?>
                    </span>
                    <h3 class="gso-changelog-grouptitle">
                      <?php if ($sectionTitle === 'Added'): ?>
                        New Features
                      <?php elseif ($sectionTitle === 'Fixed'): ?>
                        Bug fixes / Improvements
                      <?php elseif ($sectionTitle === 'Removed'): ?>
                        Breaking Changes
                      <?php else: ?>
                        Changes
                      <?php endif; ?>
                    </h3>
                  </div>

                  <ul class="gso-changelog-list">
                    <?php foreach ($items as $item): ?>
                      <li><?= htmlspecialchars($item); ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php include('../include/footer.php') ?><!--footer-->
</div><!-- ./wrapper -->
<?php include('../include/script.php') ?><!--script-->
