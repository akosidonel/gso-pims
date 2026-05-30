<?php
include_once('../config/session.php');
include('../config/check_session.php');

if (!isset($_SESSION['alogin'])) {
  header('Location:../index.php');
  exit();
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
          <div id="gsoChangeLogVersion"></div>
        </article>
      </section>

      <section class="gso-live-card">
        <div id="gsoChangeLogComments"></div>
      </section>
    </div>
  </section>
</div>

<?php include('../include/footer.php') ?><!--footer-->
</div><!-- ./wrapper -->
<?php include('../include/script.php') ?><!--script-->
