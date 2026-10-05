<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/academics.php';
require_once __DIR__ . '/../includes/result_engine.php';
$user = require_role(ROLE_SUPERADMIN);
require_module('view_student_result');
$title = 'View Student Results';
$active = 'view_result';

$q = trim($_GET['q'] ?? '');
$id = (int) ($_GET['id'] ?? 0);

$matches = [];
if ($q !== '' && !$id) {
    $like = "%$q%";
    $matches = db_all(
        "SELECT st.*, b.name AS batch_name, s.name AS sem_name, p.name AS program_name
         FROM students st
         LEFT JOIN batches b ON b.id = st.batch_id
         LEFT JOIN semesters s ON s.id = st.current_semester_id
         LEFT JOIN programs p ON p.id = st.program_id
         WHERE st.full_name LIKE ? OR st.student_code LIKE ? OR st.tu_reg_no LIKE ? OR st.exam_roll_no LIKE ?
         ORDER BY st.student_code
         LIMIT 25",
        [$like, $like, $like, $like]);
}

$student = null;
if ($id) {
    $student = db_one(
        "SELECT st.*, b.name AS batch_name, s.name AS sem_name, sec.name AS section_name, p.name AS program_name
         FROM students st
         LEFT JOIN batches b ON b.id = st.batch_id
         LEFT JOIN semesters s ON s.id = st.current_semester_id
         LEFT JOIN sections sec ON sec.id = st.section_id
         LEFT JOIN programs p ON p.id = st.program_id
         WHERE st.id = ?", [$id]);
}

$exams = [];
if ($student) {
    $exams = db_all(
        "SELECT DISTINCT e.id, e.name exam_name, e.exam_date, e.published_at, e.academic_year_id,
                e.exam_status, et.name type_name, et.code type_code, sm.name semester_name, sm.semester_no
         FROM exams e
         JOIN exam_types et ON et.id = e.exam_type_id
         JOIN exam_subjects es ON es.exam_id = e.id AND es.status = 1
         JOIN student_subjects ss ON ss.subject_id = es.subject_id AND ss.status = 1
         JOIN semesters sm ON sm.id = e.semester_id
         JOIN students st ON st.id = ss.student_id
         WHERE ss.student_id = ? AND st.program_id = e.program_id
         ORDER BY sm.semester_no, e.id",
        [$student['id']]);
    foreach ($exams as &$e) { $e['exam_type_code'] = $e['type_code']; }
    unset($e);
}

$publishedFinals = [];
if ($student) {
    $publishedFinals = db_all(
        "SELECT r.*, sm.name AS semester_name, sm.semester_no
         FROM results r JOIN semesters sm ON sm.id = r.semester_id
         WHERE r.student_id = ? AND r.is_locked = 1 ORDER BY sm.semester_no",
        [$student['id']]);
}
$cumul = $student ? cumulative_gpa($publishedFinals) : null;
$backs = $student ? student_back_subjects((int) $student['id']) : [];

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-search me-2"></i>View Student Results</h4>
    <?php if ($student): ?>
    <div class="d-flex gap-2 no-print">
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print All</button>
        <a class="btn btn-sm btn-outline-primary" href="student_profile.php?id=<?php echo $student['id']; ?>"><i class="bi bi-person-badge me-1"></i> Profile</a>
    </div>
    <?php endif; ?>
</div>

<div class="card mb-3 no-print">
    <div class="card-body">
        <form class="row g-2" method="get" id="resultSearchForm">
            <div class="col-md-8">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" name="q" id="resultSearchBox" value="<?php echo e($q); ?>"
                           placeholder="Search student by name, student ID, TU reg no, or exam roll no (e.g. BCA2085001)" autocomplete="off">
                    <?php if ($student || $matches): ?>
                    <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/view_result.php')); ?>">Reset</a>
                    <?php endif; ?>
                </div>
                <div class="form-text">Start typing — matching students appear live. Click a row to open all their results.</div>
            </div>
        </form>
    </div>
</div>

<?php if ($q !== '' && !$student): ?>
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Student Code</th><th>Name</th><th>Roll No</th><th>Program</th><th>Batch</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                <?php foreach ($matches as $m): ?>
                    <tr>
                        <td><span class="badge text-bg-dark"><?php echo e($m['student_code']); ?></span></td>
                        <td class="fw-semibold"><?php echo e($m['full_name']); ?>
                            <div class="small text-muted"><?php echo e($m['tu_reg_no'] ?: '—'); ?></div></td>
                        <td><?php echo e($m['exam_roll_no'] ?: '—'); ?></td>
                        <td><?php echo e($m['program_name'] ?? '—'); ?></td>
                        <td><?php echo e($m['batch_name'] ?? '—'); ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-rms" href="?id=<?php echo $m['id']; ?>&q=<?php echo e(urlencode($q)); ?>"><i class="bi bi-eye me-1"></i> View Result</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$matches): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-search me-1"></i>No student matches "<?php echo e($q); ?>".</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($student): ?>
    <div class="row g-3 mb-3">
        <div class="col-md-5">
            <div class="card">
                <div class="card-body d-flex gap-3 align-items-center">
                    <?php if ($student['photo']): ?>
                        <img src="<?php echo e(url($student['photo'])); ?>" class="rounded-circle border" width="70" height="70" alt="">
                    <?php else: ?>
                        <div class="avatar-circle bg-primary text-white" style="width:70px;height:70px;font-size:1.6rem;"><?php echo e(strtoupper(substr($student['full_name'], 0, 1))); ?></div>
                    <?php endif; ?>
                    <div>
                        <h5 class="mb-0"><?php echo e($student['full_name']); ?></h5>
                        <div><span class="badge text-bg-dark"><?php echo e($student['student_code']); ?></span>
                            <span class="badge text-bg-light border"><?php echo e($student['exam_roll_no'] ?: '—'); ?></span></div>
                        <div class="small text-muted"><?php echo e($student['program_name'] ?? '—'); ?> · <?php echo e($student['batch_name'] ?? '—'); ?> · <?php echo e($student['sem_name'] ?? '—'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="row g-3">
                <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body">
                    <div class="stat-title">Published Exams</div><div class="stat-value"><?php echo count($publishedFinals) + count(array_filter($exams, static fn($e) => $e['exam_status'] === 'published' && $e['type_code'] !== 'final')); ?></div></div></div></div>
                <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#16a34a"><div class="card-body">
                    <div class="stat-title">SGPA (Last)</div><div class="stat-value"><?php echo $publishedFinals ? fmt_gpa(end($publishedFinals)['sgpa'], (int) get_setting('sgpa_decimals', 2)) : '—'; ?></div></div></div></div>
                <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#d97706"><div class="card-body">
                    <div class="stat-title">CGPA</div><div class="stat-value"><?php echo $cumul && $cumul['total_credits'] ? fmt_gpa($cumul['cgpa'], (int) get_setting('sgpa_decimals', 2)) : '—'; ?></div></div></div></div>
                <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#dc2626"><div class="card-body">
                    <div class="stat-title">Back Subjects</div><div class="stat-value"><?php echo count($backs); ?></div></div></div></div>
            </div>
        </div>
    </div>

    <?php if (!$exams): ?>
        <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>This student has no exams yet. Enrol them in subjects via <a href="student_subjects.php">Semester Enrollment</a>.</div>
    <?php else: ?>
        <?php foreach ($exams as $e2): $eid = (int) $e2['id'];
            $isTerm = in_array($e2['type_code'], ['first_terminal', 'second_terminal', 'third_terminal'], true);
            $terminal = $isTerm ? terminal_student_summary($eid, (int) $student['id']) : null;
            $result = !$isTerm ? db_one('SELECT * FROM results WHERE student_id = ? AND exam_id = ?', [$student['id'], $eid]) : null;
            $rows = $result ? result_subject_rows((int) $result['id']) : [];
            $lifecycle = exam_display_status($e2);
            $resultReady = $isTerm ? ($terminal['entered'] > 0) : ($result && $result['is_locked'] == 1);
        ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 border-bottom pb-2 mb-3 no-print">
                    <span class="badge text-bg-dark"><?php echo e($e2['semester_name']); ?></span>
                    <strong class="me-1"><?php echo e($e2['exam_name']); ?></strong>
                    <span class="small text-muted"><?php echo e($e2['type_name']); ?> · <?php echo e($e2['exam_date'] ? fdate($e2['exam_date']) : '—'); ?></span>
                    <span class="badge <?php echo $lifecycle['class']; ?>"><?php echo e($lifecycle['label']); ?></span>
                    <span class="ms-auto d-flex gap-2">
                        <?php if ($resultReady): ?>
                            <a class="btn btn-sm btn-outline-info" target="_blank" href="<?php echo e(url('reports/individual_result.php?exam_id=' . $eid . '&student_id=' . $student['id'])); ?>"><i class="bi bi-eye me-1"></i> View</a>
                            <a class="btn btn-sm btn-outline-danger" target="_blank" href="<?php echo e(url('pdf/marksheet.php?exam_id=' . $eid . '&student_id=' . $student['id'])); ?>"><i class="bi bi-file-pdf me-1"></i> PDF</a>
                        <?php else: ?>
                            <span class="small text-muted"><i class="bi bi-hourglass-split me-1"></i>Result not ready</span>
                        <?php endif; ?>
                    </span>
                </div>

                <?php if ($isTerm): ?>
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>Code</th><th>Subject</th><th class="text-center">Credit</th><th class="text-center">Full</th><th class="text-center">Obtained</th><th class="text-center">%</th><th class="text-center">Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($terminal['subjects'] as $d): ?>
                            <tr>
                                <td><?php echo e($d['subject_code']); ?></td>
                                <td><?php echo e($d['subject_name']); ?></td>
                                <td class="text-center"><?php echo e($d['credit_hours']); ?></td>
                                <td class="text-center"><?php echo marks_round($d['full_marks']); ?></td>
                                <td class="text-center fw-semibold"><?php echo marks_round($d['obtained']); ?></td>
                                <td class="text-center"><?php echo round($d['percentage'], 1); ?>%</td>
                                <td class="text-center"><?php echo status_badge($d['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$terminal['subjects']): ?><tr><td colspan="7" class="text-center text-muted py-3">No marks entered for this student yet.</td></tr><?php endif; ?>
                        </tbody>
                        <?php if ($terminal['subjects']): ?>
                        <tfoot class="table-light"><tr>
                            <th colspan="3" class="text-end">Overall</th>
                            <th class="text-center"><?php echo marks_round($terminal['full']); ?></th>
                            <th class="text-center"><?php echo marks_round($terminal['total']); ?></th>
                            <th class="text-center"><?php echo round($terminal['percentage'], 1); ?>%</th>
                            <th class="text-center"><?php echo status_badge($terminal['overall']); ?></th>
                        </tr></tfoot>
                        <?php endif; ?>
                    </table>
                <?php else: ?>
                    <?php if ($result): ?>
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>Code</th><th>Subject</th><th class="text-center">Credit</th><th class="text-center">Total</th><th class="text-center">Grade</th><th class="text-center">GP</th><th class="text-center">CP</th><th class="text-center">Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $d): ?>
                                <tr>
                                    <td><?php echo e($d['subject_code']); ?></td>
                                    <td><?php echo e($d['subject_name']); ?></td>
                                    <td class="text-center"><?php echo e($d['credit_hours']); ?></td>
                                    <td class="text-center fw-semibold"><?php echo marks_round($d['total_marks']); ?>/<?php echo (int) $d['max_marks']; ?></td>
                                    <td class="text-center"><span class="badge text-bg-primary"><?php echo e($d['grade']); ?></span></td>
                                    <td class="text-center"><?php echo e($d['grade_point']); ?></td>
                                    <td class="text-center"><?php echo e($d['credit_point']); ?></td>
                                    <td class="text-center"><?php echo status_badge($d['subject_status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="2" class="text-end">Summary</th>
                                    <th class="text-center"><?php echo e($result['total_credits']); ?></th>
                                    <th colspan="4" class="text-center">SGPA: <span class="fw-bold"><?php echo fmt_gpa($result['sgpa'], (int) get_setting('sgpa_decimals', 2)); ?></span> · CGPA: <?php echo fmt_gpa($result['cgpa'], (int) get_setting('sgpa_decimals', 2)); ?></th>
                                    <th class="text-center"><?php echo status_badge($result['result_status']); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                        <div class="small text-muted">Published on <?php echo fdate($result['published_at']); ?><?php echo $result['reopen_reason'] ? ' · Reopened: ' . e($result['reopen_reason']) : ''; ?></div>
                    <?php else: ?>
                        <p class="text-muted mb-0"><i class="bi bi-hourglass-split me-1"></i>Final result not computed / published yet.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<script>
(function () {
    var box = document.getElementById('resultSearchBox');
    var form = document.getElementById('resultSearchForm');
    if (!box || !form) return;
    var timer = null;
    box.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { form.submit(); }, 450);
    });
    box.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') { box.value = ''; form.submit(); }
    });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>