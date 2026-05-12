<?php 
include_once('../config/session.php');
include('../config/check_session.php');

if(!isset($_SESSION['alogin'])){
  header('Location:../index.php');
  exit();
}
?>
  <?php include('../include/header.php')?><!--Header-->
  <?php include('../include/navbar.php')?><!-- Navbar -->
  <?php include('../include/sidebar.php')?><!--Sidebar-->

   <!-- Preloader -->
<div class="preloader flex-column justify-content-center align-items-center">
    <img src="../assets/dist/img/spin.gif" alt="AdminLogo" height="90" width="90">
</div>

<div id="destroy"></div>

  <div class="content-wrapper"><!-- Content Wrapper. Contains page content -->
    <section class="content-header"> <!-- Content Header (Page header) -->
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Disposal</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item active">Disposal</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content"> <!-- Main content -->
      <div class="card"> <!-- Default box -->
        <div class="card-header d-flex align-items-center">
          <h3 class="card-title mb-0"><i class="fas fa-dolly-flatbed"></i>&nbsp; Disposal Activities</h3>
          <div class="card-tools ml-auto">
            <span id="createDisposalBtnWrap" style="display:inline-block;" data-toggle="tooltip" data-placement="left" title="">
              <button type="button" class="btn btn-block bg-gradient-success btn-sm" id="createDisposalBtn">
                <i class="fas fa-plus"></i>&nbsp; Create Disposal
              </button>
            </span>
          </div>
        </div>

        <div class="card-body">
          <table id="disposalActivitiesTable" class="table table-bordered table-hover" style="width:100%">
            <thead>
              <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                <th class="col-sm-2">DATE</th>
                <th class="col-sm-3">REFERENCE NUMBER</th>
                <th class="col-sm-2">STATUS</th>
                <th class="col-sm-2">ACTION</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

      <!-- Disposal Info Modal -->
      <div class="modal fade" id="disposalInfoModal" tabindex="-1" role="dialog" aria-labelledby="disposalInfoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
          <div class="modal-content gso-modal">
            <div class="modal-header border-0 p-0">
              <div class="gso-hero w-100" style="border-radius:0; box-shadow:none;">
                <div class="card-body py-3">
                  <div class="d-flex align-items-start justify-content-between flex-wrap">
                    <div class="mb-2 mb-md-0">
                      <div class="gso-kicker">Disposal Activity</div>
                      <div class="gso-title" style="font-size:22px;">Disposal Information</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="modal-body">
              <div id="dispInfoAlert" class="gso-hero d-none" style="box-shadow: var(--gso-shadow-soft); border-radius: var(--gso-radius); margin-bottom: 14px;">
                <div class="card-body py-3" style="padding: 14px 16px;">
                  <div class="d-flex align-items-start justify-content-between flex-wrap">
                    <div>
                      <div class="gso-kicker">Required</div>
                      <div style="font-weight:800; letter-spacing:-0.02em;">No details found for this disposal activity yet.</div>
                      <div class="gso-meta" style="margin-top:6px;">Please fill out the required information and save.</div>
                    </div>
                    <div class="mt-2 mt-md-0">
                      <span class="gso-pill" style="padding: 8px 10px;">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>Action needed</span>
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-lg-3 col-sm-6 mb-3">
                  <div class="gso-stat-card" role="group" aria-label="Disposal reference">
                    <div class="gso-stat-icon"><i class="fa-solid fa-hashtag"></i></div>
                    <div>
                      <div class="gso-stat-title">Reference</div>
                      <div class="gso-stat-value" id="dispInfoSumRef">—</div>
                      <div class="gso-stat-note">Disposal activity</div>
                    </div>
                  </div>
                </div>
                <div class="col-lg-3 col-sm-6 mb-3">
                  <div class="gso-stat-card" role="group" aria-label="Disposal status">
                    <div class="gso-stat-icon"><i class="fa-solid fa-flag"></i></div>
                    <div>
                      <div class="gso-stat-title">Status</div>
                      <div class="gso-stat-value" id="dispInfoSumStatus">—</div>
                      <div class="gso-stat-note" id="dispInfoSumCreated">—</div>
                    </div>
                  </div>
                </div>
                <div class="col-lg-3 col-sm-6 mb-3">
                  <div class="gso-stat-card" role="group" aria-label="Disposal items">
                    <div class="gso-stat-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                    <div>
                      <div class="gso-stat-title">Items</div>
                      <div class="gso-stat-value"><span id="dispInfoSumItems">—</span></div>
                      <div class="gso-stat-note">Total qty: <span id="dispInfoSumQty">—</span></div>
                    </div>
                  </div>
                </div>
                <div class="col-lg-3 col-sm-6 mb-3">
                  <div class="gso-stat-card" role="group" aria-label="Total appraisal">
                    <div class="gso-stat-icon"><i class="fa-solid fa-peso-sign"></i></div>
                    <div>
                      <div class="gso-stat-title">Total Appraisal</div>
                      <div class="gso-stat-value" id="dispInfoSumTotal">—</div>
                      <div class="gso-stat-note">Based on FMV per item</div>
                    </div>
                  </div>
                </div>
              </div>

              <form id="disposalInfoForm" autocomplete="off">
                <input type="hidden" id="dispInfoExists" value="0">

                <div class="row">
                  <div class="col-lg-5 mb-3">
                    <div class="card gso-card h-100">
                      <div class="card-header border-0">
                        <div class="d-flex justify-content-between align-items-center">
                          <h3 class="card-title mb-0"><i class="fas fa-id-card"></i>&nbsp; Accountable Officer</h3>
                        </div>
                      </div>
                      <div class="card-body">
                        <div class="form-group">
                          <label>Reference Number</label>
                          <input type="text" class="form-control" id="dispInfoRef" readonly>
                          <small class="text-muted">(Not shown in IIRUP print)</small>
                        </div>

                        <div class="form-group">
                          <label>Name of Accountable Officer <span class="text-danger">*</span></label>
                          <input type="text" class="form-control" id="dispInfoAccountableOfficer" placeholder="e.g., Juan Dela Cruz">
                        </div>

                        <div class="form-row">
                          <div class="form-group col-md-6">
                            <label>Designation <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dispInfoDesignation" placeholder="e.g., OIC - GSO">
                          </div>
                          <div class="form-group col-md-6">
                            <label>Station <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dispInfoStation" placeholder="e.g., Parañaque City">
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="col-lg-7 mb-3">
                    <div class="card gso-card h-100">
                      <div class="card-header border-0">
                        <div class="d-flex justify-content-between align-items-center">
                          <h3 class="card-title mb-0"><i class="fas fa-file-signature"></i>&nbsp; Signatories</h3>
                          <small class="text-muted">IIRUP footer section</small>
                        </div>
                      </div>
                      <div class="card-body">
                        <div class="form-row">
                          <div class="form-group col-md-6">
                            <label>Disposal Committee Chairperson <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dispInfoCommitteeChair" placeholder="e.g., City Administrator">
                          </div>
                          <div class="form-group col-md-6">
                            <label>Approved by <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dispInfoApprovedBy" placeholder="(This is the City Mayor of Parañaque)">
                          </div>
                        </div>

                        <div class="form-row">
                          <div class="form-group col-md-6">
                            <label>Witness <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dispInfoWitness" placeholder="e.g., Jane D. Doe">
                          </div>
                          <div class="form-group col-md-6">
                            <label>Witness Position <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dispInfoWitnessPosition" placeholder="e.g., Inspection Officer">
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="card gso-card mb-0">
                  <div class="card-header border-0 d-flex align-items-center">
                    <h3 class="card-title mb-0"><i class="fas fa-user-check"></i>&nbsp; Inspection Officers <small class="text-muted">(max of 5)</small></h3>
                    <div class="card-tools ml-auto">
                      <button type="button" class="btn btn-sm btn-outline-success" id="dispInfoAddOfficer">
                        <i class="fas fa-plus"></i>&nbsp; Add Officer
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div id="dispInfoOfficers"></div>
                    <small class="text-muted">At least one Inspection Officer is required (Name + Position).</small>
                  </div>
                </div>
              </form>
            </div>

            <div class="modal-footer border-0 pt-0">
              <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
              <div class="ml-auto">
                <button type="button" class="btn btn-outline-primary" id="dispInfoEditBtn">Update</button>
                <button type="button" class="btn btn-success" id="dispInfoSaveBtn"><i class="fas fa-save"></i>&nbsp; Save</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Disposal Documents Modal (UI only) -->
      <div class="modal fade" id="disposalDocsModal" tabindex="-1" role="dialog" aria-labelledby="disposalDocsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
          <div class="modal-content gso-modal">
            <div class="modal-header border-0 p-0">
              <div class="gso-hero w-100" style="border-radius:0; box-shadow:none;">
                <div class="card-body py-3">
                  <div class="d-flex align-items-start justify-content-between flex-wrap">
                    <div class="mb-2 mb-md-0">
                      <div class="gso-kicker">Disposal Activity</div>
                      <div class="gso-title" style="font-size:22px;">Upload Required Documents</div>
                      <div class="gso-meta" style="margin-top:6px;">Reference: <span id="dispDocsRefText">—</span></div>
                    </div>
                    <div class="mt-2 mt-md-0">
                      <span class="gso-pill" style="padding: 8px 10px;">
                        <i class="fas fa-file-upload"></i>
                        <span>Documents</span>
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="modal-body">
              <div id="dispDocsAlert" class="alert d-none" role="alert"></div>

              <div class="card gso-card">
                <div class="card-header border-0">
                  <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <h3 class="card-title mb-0"><i class="fas fa-clipboard-list"></i>&nbsp; Required Uploads</h3>
                    <span id="dispDocsCountBadge" class="badge badge-secondary">0/9 selected</span>
                  </div>
                </div>
                <div class="card-body">
                  <form id="disposalDocsForm" autocomplete="off">
                    <input type="hidden" id="dispDocsRef" value="">

                    <div class="mb-3">
                      <small class="text-muted">Accepted: PDF only (max 25MB each).</small>
                    </div>

                    <div class="pb-3 mb-3 border-bottom">
                      <div class="font-weight-bold">Inventory and Appraisal Report <span class="text-danger">*</span></div>
                      <small class="text-muted">FMV / appraisal supporting document.</small>
                      <div class="mt-2">
                        <div class="custom-file">
                          <input type="file" class="custom-file-input dispDocsInput" id="dispDocsAppraisalReport" accept=".pdf,application/pdf" data-name-target="#dispDocsAppraisalReportLabel">
                          <label class="custom-file-label" id="dispDocsAppraisalReportLabel" for="dispDocsAppraisalReport">Choose file</label>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                          <button type="button" class="btn btn-sm btn-outline-success dispDocsUploadOne" data-input="#dispDocsAppraisalReport" data-status="#dispDocsStatusAppraisalReport" disabled>
                            <i class="fas fa-upload"></i>&nbsp; Upload
                          </button>
                          <span class="badge badge-secondary" id="dispDocsStatusAppraisalReport">Not uploaded</span>
                        </div>
                      </div>
                    </div>

                    <div class="pb-3 mb-3 border-bottom">
                      <div class="font-weight-bold">Formal Request for Disposal <span class="text-danger">*</span></div>
                      <small class="text-muted">Signed request/endorsement letter.</small>
                      <div class="mt-2">
                        <div class="custom-file">
                          <input type="file" class="custom-file-input dispDocsInput" id="dispDocsFormalRequest" accept=".pdf,application/pdf" data-name-target="#dispDocsFormalRequestLabel">
                          <label class="custom-file-label" id="dispDocsFormalRequestLabel" for="dispDocsFormalRequest">Choose file</label>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                          <button type="button" class="btn btn-sm btn-outline-success dispDocsUploadOne" data-input="#dispDocsFormalRequest" data-status="#dispDocsStatusFormalRequest" disabled>
                            <i class="fas fa-upload"></i>&nbsp; Upload
                          </button>
                          <span class="badge badge-secondary" id="dispDocsStatusFormalRequest">Not uploaded</span>
                        </div>
                      </div>
                    </div>

                    <div class="pb-3 mb-3 border-bottom">
                      <div class="font-weight-bold">TAAS Report <span class="text-danger">*</span></div>
                      <small class="text-muted">TAAS report attachment.</small>
                      <div class="mt-2">
                        <div class="custom-file">
                          <input type="file" class="custom-file-input dispDocsInput" id="dispDocsTaasReport" accept=".pdf,application/pdf" data-name-target="#dispDocsTaasReportLabel">
                          <label class="custom-file-label" id="dispDocsTaasReportLabel" for="dispDocsTaasReport">Choose file</label>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                          <button type="button" class="btn btn-sm btn-outline-success dispDocsUploadOne" data-input="#dispDocsTaasReport" data-status="#dispDocsStatusTaasReport" disabled>
                            <i class="fas fa-upload"></i>&nbsp; Upload
                          </button>
                          <span class="badge badge-secondary" id="dispDocsStatusTaasReport">Not uploaded</span>
                        </div>
                      </div>
                    </div>

                    <div class="pb-3 mb-3 border-bottom">
                      <div class="d-flex align-items-center justify-content-between">
                        <div class="font-weight-bold">Invitation to Bid <span class="text-danger">*</span></div>
                        <button type="button" class="btn btn-xs btn-outline-secondary" id="dispDocsShowMoreInvitationBtn" title="Add additional invitation to bid files" aria-expanded="false">
                          <i class="fas fa-plus"></i>
                        </button>
                      </div>
                      <small class="text-muted">Invitation to Bid document/attachment.</small>
                      <div class="mt-2">
                        <div class="custom-file">
                          <input type="file" class="custom-file-input dispDocsInput" id="dispDocsInvitationToBid" accept=".pdf,application/pdf" data-name-target="#dispDocsInvitationToBidLabel">
                          <label class="custom-file-label" id="dispDocsInvitationToBidLabel" for="dispDocsInvitationToBid">Choose file</label>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                          <button type="button" class="btn btn-sm btn-outline-success dispDocsUploadOne" data-input="#dispDocsInvitationToBid" data-status="#dispDocsStatusInvitationToBid" disabled>
                            <i class="fas fa-upload"></i>&nbsp; Upload
                          </button>
                          <span class="badge badge-secondary" id="dispDocsStatusInvitationToBid">Not uploaded</span>
                        </div>
                      </div>

                      <div id="dispDocsInvitationOptionalWrap" class="collapse">
                        <div class="mt-3">
                          <div class="font-weight-bold">Invitation to Bid - Additional File 1 <span class="text-muted" style="font-weight:600;">(Optional)</span></div>
                          <small class="text-muted">Additional supporting invitation to bid attachment.</small>
                          <div class="mt-2">
                            <div class="custom-file">
                              <input type="file" class="custom-file-input dispDocsInput" id="dispDocsInvitationOptional1" accept=".pdf,application/pdf" data-name-target="#dispDocsInvitationOptional1Label">
                              <label class="custom-file-label" id="dispDocsInvitationOptional1Label" for="dispDocsInvitationOptional1">Choose file</label>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mt-2">
                              <button type="button" class="btn btn-sm btn-outline-success dispDocsUploadOne" data-input="#dispDocsInvitationOptional1" data-status="#dispDocsStatusInvitationOptional1" disabled>
                                <i class="fas fa-upload"></i>&nbsp; Upload
                              </button>
                              <span class="badge badge-secondary" id="dispDocsStatusInvitationOptional1">Not uploaded</span>
                            </div>
                          </div>
                        </div>

                        <div class="mt-3">
                          <div class="font-weight-bold">Invitation to Bid - Additional File 2 <span class="text-muted" style="font-weight:600;">(Optional)</span></div>
                          <small class="text-muted">Additional supporting invitation to bid attachment.</small>
                          <div class="mt-2">
                            <div class="custom-file">
                              <input type="file" class="custom-file-input dispDocsInput" id="dispDocsInvitationOptional2" accept=".pdf,application/pdf" data-name-target="#dispDocsInvitationOptional2Label">
                              <label class="custom-file-label" id="dispDocsInvitationOptional2Label" for="dispDocsInvitationOptional2">Choose file</label>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mt-2">
                              <button type="button" class="btn btn-sm btn-outline-success dispDocsUploadOne" data-input="#dispDocsInvitationOptional2" data-status="#dispDocsStatusInvitationOptional2" disabled>
                                <i class="fas fa-upload"></i>&nbsp; Upload
                              </button>
                              <span class="badge badge-secondary" id="dispDocsStatusInvitationOptional2">Not uploaded</span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="pb-3 mb-3 border-bottom">
                      <div class="d-flex align-items-center justify-content-between">
                        <div class="font-weight-bold">Resolution (Committee) <span class="text-danger">*</span></div>
                        <button type="button" class="btn btn-xs btn-outline-secondary" id="dispDocsShowMoreResolutionBtn" title="Add additional resolution files" aria-expanded="false">
                          <i class="fas fa-plus"></i>
                        </button>
                      </div>
                      <small class="text-muted">Approving resolution document.</small>
                      <div class="mt-2">
                        <div class="custom-file">
                          <input type="file" class="custom-file-input dispDocsInput" id="dispDocsResolution" accept=".pdf,application/pdf" data-name-target="#dispDocsResolutionLabel">
                          <label class="custom-file-label" id="dispDocsResolutionLabel" for="dispDocsResolution">Choose file</label>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                          <button type="button" class="btn btn-sm btn-outline-success dispDocsUploadOne" data-input="#dispDocsResolution" data-status="#dispDocsStatusResolution" disabled>
                            <i class="fas fa-upload"></i>&nbsp; Upload
                          </button>
                          <span class="badge badge-secondary" id="dispDocsStatusResolution">Not uploaded</span>
                        </div>
                      </div>

                      <div id="dispDocsResolutionOptionalWrap" class="collapse">
                        <div class="mt-3">
                          <div class="font-weight-bold">Resolution (Committee) - Additional File 1 <span class="text-muted" style="font-weight:600;">(Optional)</span></div>
                          <small class="text-muted">Additional supporting resolution attachment.</small>
                          <div class="mt-2">
                            <div class="custom-file">
                              <input type="file" class="custom-file-input dispDocsInput" id="dispDocsResolutionOptional1" accept=".pdf,application/pdf" data-name-target="#dispDocsResolutionOptional1Label">
                              <label class="custom-file-label" id="dispDocsResolutionOptional1Label" for="dispDocsResolutionOptional1">Choose file</label>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mt-2">
                              <button type="button" class="btn btn-sm btn-outline-success dispDocsUploadOne" data-input="#dispDocsResolutionOptional1" data-status="#dispDocsStatusResolutionOptional1" disabled>
                                <i class="fas fa-upload"></i>&nbsp; Upload
                              </button>
                              <span class="badge badge-secondary" id="dispDocsStatusResolutionOptional1">Not uploaded</span>
                            </div>
                          </div>
                        </div>

                        <div class="mt-3">
                          <div class="font-weight-bold">Resolution (Committee) - Additional File 2 <span class="text-muted" style="font-weight:600;">(Optional)</span></div>
                          <small class="text-muted">Additional supporting resolution attachment.</small>
                          <div class="mt-2">
                            <div class="custom-file">
                              <input type="file" class="custom-file-input dispDocsInput" id="dispDocsResolutionOptional2" accept=".pdf,application/pdf" data-name-target="#dispDocsResolutionOptional2Label">
                              <label class="custom-file-label" id="dispDocsResolutionOptional2Label" for="dispDocsResolutionOptional2">Choose file</label>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mt-2">
                              <button type="button" class="btn btn-sm btn-outline-success dispDocsUploadOne" data-input="#dispDocsResolutionOptional2" data-status="#dispDocsStatusResolutionOptional2" disabled>
                                <i class="fas fa-upload"></i>&nbsp; Upload
                              </button>
                              <span class="badge badge-secondary" id="dispDocsStatusResolutionOptional2">Not uploaded</span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="pb-3 mb-3 border-bottom">
                      <div class="font-weight-bold">Notice of Award <span class="text-danger">*</span></div>
                      <small class="text-muted">Notice of Award document/attachment.</small>
                      <div class="mt-2">
                        <div class="custom-file">
                          <input type="file" class="custom-file-input dispDocsInput" id="dispDocsNoticeOfAward" accept=".pdf,application/pdf" data-name-target="#dispDocsNoticeOfAwardLabel">
                          <label class="custom-file-label" id="dispDocsNoticeOfAwardLabel" for="dispDocsNoticeOfAward">Choose file</label>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                          <button type="button" class="btn btn-sm btn-outline-success dispDocsUploadOne" data-input="#dispDocsNoticeOfAward" data-status="#dispDocsStatusNoticeOfAward" disabled>
                            <i class="fas fa-upload"></i>&nbsp; Upload
                          </button>
                          <span class="badge badge-secondary" id="dispDocsStatusNoticeOfAward">Not uploaded</span>
                        </div>
                      </div>
                    </div>

                    <div class="pb-3 mb-3 border-bottom">
                      <div class="font-weight-bold">Deed of Sale <span class="text-danger">*</span></div>
                      <small class="text-muted">Executed deed of sale document.</small>
                      <div class="mt-2">
                        <div class="custom-file">
                          <input type="file" class="custom-file-input dispDocsInput" id="dispDocsDeedOfSale" accept=".pdf,application/pdf" data-name-target="#dispDocsDeedOfSaleLabel">
                          <label class="custom-file-label" id="dispDocsDeedOfSaleLabel" for="dispDocsDeedOfSale">Choose file</label>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                          <button type="button" class="btn btn-sm btn-outline-success dispDocsUploadOne" data-input="#dispDocsDeedOfSale" data-status="#dispDocsStatusDeedOfSale" disabled>
                            <i class="fas fa-upload"></i>&nbsp; Upload
                          </button>
                          <span class="badge badge-secondary" id="dispDocsStatusDeedOfSale">Not uploaded</span>
                        </div>
                      </div>
                    </div>

                    <div class="pb-3 mb-3 border-bottom">
                      <div class="font-weight-bold">Transmittal (Accounting) <span class="text-danger">*</span></div>
                      <small class="text-muted">Transmittal copy for Accounting.</small>
                      <div class="mt-2">
                        <div class="custom-file">
                          <input type="file" class="custom-file-input dispDocsInput" id="dispDocsTransmittalAccounting" accept=".pdf,application/pdf" data-name-target="#dispDocsTransmittalAccountingLabel">
                          <label class="custom-file-label" id="dispDocsTransmittalAccountingLabel" for="dispDocsTransmittalAccounting">Choose file</label>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                          <button type="button" class="btn btn-sm btn-outline-success dispDocsUploadOne" data-input="#dispDocsTransmittalAccounting" data-status="#dispDocsStatusTransmittalAccounting" disabled>
                            <i class="fas fa-upload"></i>&nbsp; Upload
                          </button>
                          <span class="badge badge-secondary" id="dispDocsStatusTransmittalAccounting">Not uploaded</span>
                        </div>
                      </div>
                    </div>

                    <div class="mb-3">
                      <div class="font-weight-bold">Transmittal (COA) <span class="text-danger">*</span></div>
                      <small class="text-muted">Transmittal copy for COA.</small>
                      <div class="mt-2">
                        <div class="custom-file">
                          <input type="file" class="custom-file-input dispDocsInput" id="dispDocsTransmittalCOA" accept=".pdf,application/pdf" data-name-target="#dispDocsTransmittalCOALabel">
                          <label class="custom-file-label" id="dispDocsTransmittalCOALabel" for="dispDocsTransmittalCOA">Choose file</label>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                          <button type="button" class="btn btn-sm btn-outline-success dispDocsUploadOne" data-input="#dispDocsTransmittalCOA" data-status="#dispDocsStatusTransmittalCOA" disabled>
                            <i class="fas fa-upload"></i>&nbsp; Upload
                          </button>
                          <span class="badge badge-secondary" id="dispDocsStatusTransmittalCOA">Not uploaded</span>
                        </div>
                      </div>
                    </div>

                    <small class="text-muted">All fields marked with <span class="text-danger">*</span> are required.</small>
                  </form>
                </div>
              </div>
            </div>

            <div class="modal-footer border-0 pt-0">
              <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
              <button type="button" class="btn btn-success" id="dispDocsUploadBtn" disabled>
                <i class="fas fa-paper-plane"></i>&nbsp; Submit
              </button>
            </div>
          </div>
        </div>
      </div>
    </section><!-- /.content -->
  </div><!-- /.content-wrapper -->

  <?php include('../include/footer.php') ?><!--footer-->
</div><!-- ./wrapper -->

<?php include('../include/script.php')?><!--script-->

</body>
</html>
