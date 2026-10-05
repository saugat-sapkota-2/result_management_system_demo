<?php
require_once __DIR__ . '/includes/init.php';
$user = require_login();
require_module('about');
$title = 'About This Project';
$active = 'about';

include __DIR__ . '/includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-info-circle me-2"></i>About This Project</h4>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="avatar-circle bg-primary text-white" style="width:64px;height:64px;font-size:1.5rem;">SS</div>
                    <div>
                        <h3 class="mb-1">BCA Result Management System</h3>
                        <p class="text-muted mb-0">A college-level academic result management project.</p>
                    </div>
                </div>
                <p class="mb-0">This system manages academic structures, students, subjects, examinations, marks, results, reports, and role-based access for a BCA programme.</p>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Project Author</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5">Name</dt>
                    <dd class="col-7">Saugat Sapkota</dd>
                    <dt class="col-5">Programme</dt>
                    <dd class="col-7">BCA 4th Semester</dd>
                    <dt class="col-5">Website</dt>
                    <dd class="col-7"><a href="https://saugatsapkota1.com.np" target="_blank" rel="noopener">saugatsapkota1.com.np</a></dd>
                    <dt class="col-5">Profile</dt>
                    <dd class="col-7"><a href="https://github.com/saugat-sapkota-1" target="_blank" rel="noopener">saugat-sapkota-1</a></dd>
                </dl>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
