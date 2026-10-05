<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/academics.php';
$user = require_role(ROLE_SUPERADMIN);
require_module('student_profile');
$title = 'Student Profile';
$active = 'students';

$id = (int) ($_GET['id'] ?? 0);
$student = db_one(
    "SELECT st.*, b.name AS batch_name, s.name AS sem_name, sec.name AS section_name, p.name AS program_name, u.username
     FROM students st
     LEFT JOIN batches b ON b.id = st.batch_id
     LEFT JOIN semesters s ON s.id = st.current_semester_id
     LEFT JOIN sections sec ON sec.id = st.section_id
     LEFT JOIN programs p ON p.id = st.program_id
     LEFT JOIN users u ON u.id = st.user_id
     WHERE st.id = ?", [$id]);
if (!$student) { flash('danger', 'Student not found.'); redirect(url('admin/students.php')); }

$subjects = db_all(
    "SELECT su.*, sm.name AS semester_name
     FROM student_subjects ss JOIN subjects su ON su.id = ss.subject_id
     JOIN semesters sm ON sm.id = su.semester_id
     WHERE ss.student_id = ? AND ss.status = 1 ORDER BY sm.semester_no, su.code", [$id]);

$semesters = student_semesters_summary($id);
$cumul = cumulative_gpa($semesters);
$backs = student_back_subjects($id);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-person-badge me-2"></i>Student Profile</h4>
    <div class="d-flex gap-2 no-print">
        <a class="btn btn-sm btn-outline-primary" href="student_subjects.php?student_id=<?php echo $id; ?>"><i class="bi bi-ui-checks-grid me-1"></i> Enrol Subjects</a>
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
        <a class="btn btn-sm btn-rms" href="students.php?edit=<?php echo $id; ?>"><i class="bi bi-pencil me-1"></i> Edit</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <?php if ($student['photo']): ?>
                    <img src="<?php echo e(url($student['photo'])); ?>" class="rounded-circle border mb-2" width="110" height="110" alt="photo">
                <?php else: ?>
                    <div class="avatar-circle mx-auto mb-2 bg-primary text-white" style="width:110px;height:110px;font-size:2.4rem;"><?php echo e(strtoupper(substr($student['full_name'], 0, 1))); ?></div>
                <?php endif; ?>
                <h5 class="mb-0"><?php echo e($student['full_name']); ?></h5>
                <span class="badge text-bg-dark"><?php echo e($student['student_code']); ?></span>
                <div class="mt-2"><?php echo bool_badge($student['status']); ?></div>
            </div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">TU Reg. No.</span><span><?php echo e($student['tu_reg_no'] ?: '—'); ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Exam Roll No.</span><span><?php echo e($student['exam_roll_no'] ?: '—'); ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Program</span><span><?php echo e($student['program_name'] ?? '—'); ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Batch</span><span><?php echo e($student['batch_name'] ?? '—'); ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Semester</span><span><?php echo e($student['sem_name'] ?? '—'); ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Section</span><span><?php echo e($student['section_name'] ?? '—'); ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Admission Year</span><span><?php echo e($student['admission_year'] ?: '—'); ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Date of Birth</span><span><?php echo fdate($student['dob']); ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Gender</span><span><?php echo e($student['gender'] ?: '—'); ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Email</span><span><?php echo e($student['email'] ?: '—'); ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Phone</span><span><?php echo e($student['phone'] ?: '—'); ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Address</span><span><?php echo e($student['address'] ?: '—'); ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Login</span><span><code><?php echo e($student['username'] ?? '—'); ?></code></span></li>
            </ul>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body">
                <div class="stat-title">Semesters Done</div><div class="stat-value"><?php echo count($semesters); ?></div></div></div></div>
            <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#16a34a"><div class="card-body">
                <div class="stat-title">SGPA (Last)</div><div class="stat-value"><?php echo $semesters ? fmt_gpa(end($semesters)['sgpa']) : '—'; ?></div></div></div></div>
            <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#d97706"><div class="card-body">
                <div class="stat-title">CGPA</div><div class="stat-value"><?php echo $cumul['total_credits'] ? fmt_gpa($cumul['cgpa']) : '—'; ?></div></div></div></div>
            <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#dc2626"><div class="card-body">
                <div class="stat-title">Back Subjects</div>
                <div class="stat-value"><?php echo count($backs); ?></div></div></div></div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabSubjects">Subjects (<?php echo count($subjects); ?>)</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabResults">Semester Results</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabBacks">Back Subjects</button></li>
        </ul>
        <div class="tab-content border border-top-0 rounded-bottom bg-white p-3 mb-3">
            <!-- Subjects -->
            <div class="tab-pane fade show active" id="tabSubjects">
                <?php if (!$subjects): ?><p class="text-muted mb-0">No subjects enrolled. <a href="student_subjects.php?student_id=<?php echo $id; ?>">Enrol now</a>.</p>
                <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead><tr><th>Code</th><th>Subject</th><th>Semester</th><th>Credits</th></tr></thead>
                    <tbody>
                    <?php foreach ($subjects as $s): ?>
                        <tr><td><span class="badge text-bg-dark"><?php echo e($s['code']); ?></span></td>
                            <td class="fw-semibold"><?php echo e($s['name']); ?></td>
                            <td><?php echo e($s['semester_name']); ?></td>
                            <td><?php echo e($s['credit_hours']); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Results history -->
            <div class="tab-pane fade" id="tabResults">
                <?php foreach ($semesters as $i => $r): $rows = result_subject_rows($r['id']); ?>
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap mb-2">
                            <strong><?php echo e($r['semester_name']); ?></strong>
                            <span class="d-flex gap-2"><?php echo status_badge($r['status']); ?> <?php echo status_badge($r['result_status']); ?></span>
                        </div>
                        <table class="table table-sm table-striped mb-1">
                            <thead><tr><th>Code</th><th>Subject</th><th class="text-center">Credit</th><th class="text-center">Total</th><th class="text-center">Grade</th><th class="text-center">GP</th><th class="text-center">CP</th><th class="text-center">Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $rd): ?>
                                <tr>
                                    <td><?php echo e($rd['subject_code']); ?></td>
                                    <td><?php echo e($rd['subject_name']); ?></td>
                                    <td class="text-center"><?php echo e($rd['credit_hours']); ?></td>
                                    <td class="text-center fw-semibold"><?php echo marks_round($rd['total_marks']); ?>/<?php echo e((int) $rd['max_marks']); ?></td>
                                    <td class="text-center"><span class="badge text-bg-primary"><?php echo e($rd['grade'] ?: '—'); ?></span></td>
                                    <td class="text-center"><?php echo e($rd['grade_point']); ?></td>
                                    <td class="text-center"><?php echo e($rd['credit_point']); ?></td>
                                    <td class="text-center"><?php echo status_badge($rd['subject_status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="small text-muted">Credits: <b><?php echo e($r['total_credits']); ?></b> · Credit Points: <b><?php echo e($r['total_credit_points']); ?></b> · SGPA: <b><?php echo fmt_gpa($r['sgpa']); ?></b> · CGPA: <b><?php echo fmt_gpa($r['cgpa']); ?></b></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$semesters): ?><p class="text-muted mb-0">No results computed yet.</p><?php endif; ?>
            </div>

            <!-- Back subjects -->
            <div class="tab-pane fade" id="tabBacks">
                <?php if (!$backs): ?><p class="text-success mb-0"><i class="bi bi-check-circle"></i> No back / failed subjects. Good!</p>
                <?php else: ?>
                <table class="table table-sm table-striped mb-0">
                    <thead><tr><th>Code</th><th>Subject</th><th>Semester</th><th>Credits</th></tr></thead>
                    <tbody>
                    <?php foreach ($backs as $b): ?>
                        <tr><td><span class="badge text-bg-dark"><?php echo e($b['code']); ?></span></td>
                            <td class="fw-semibold text-danger"><?php echo e($b['name']); ?></td>
                            <td><?php echo e($b['semester_name']); ?></td>
                            <td><?php echo e($b['credit_hours']); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>