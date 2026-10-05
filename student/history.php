<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/academics.php';
$user = require_role(ROLE_STUDENT);
$title = 'Academic History';
$active = 'history';

$studentId = (int) db_val('SELECT student_id FROM users WHERE id = ?', [$user['id']]);
$semesters = student_semesters_summary($studentId, 'published');
$cumul = cumulative_gpa($semesters);
$backs = student_back_subjects($studentId);

$publishedExams = db_all(
    "SELECT DISTINCT e.id, e.name exam_name, e.exam_date, e.published_at, et.name type_name, et.code type_code, sm.name semester_name
     FROM exams e
     JOIN exam_types et ON et.id = e.exam_type_id
     JOIN exam_subjects es ON es.exam_id = e.id AND es.status = 1
     JOIN student_subjects ss ON ss.subject_id = es.subject_id AND ss.status = 1
     JOIN semesters sm ON sm.id = e.semester_id
     JOIN students st ON st.id = ss.student_id
     WHERE e.exam_status = 'published' AND ss.student_id = ? AND st.program_id = e.program_id
     ORDER BY sm.semester_no DESC, e.id DESC", [$studentId]);

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-book-half me-2"></i>Academic History</h4>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="stat-title">Published Finals</div><div class="stat-value"><?php echo count($semesters); ?></div></div></div></div>
    <div class="col-md-3"><div class="card stat-card" style="border-left-color:#16a34a"><div class="card-body"><div class="stat-title">Total Credits</div><div class="stat-value"><?php echo e($cumul['total_credits']); ?></div></div></div></div>
    <div class="col-md-3"><div class="card stat-card" style="border-left-color:#2563eb"><div class="card-body"><div class="stat-title">Total Credit Points</div><div class="stat-value"><?php echo e($cumul['total_credit_points']); ?></div></div></div></div>
    <div class="col-md-3"><div class="card stat-card" style="border-left-color:#d97706"><div class="card-body"><div class="stat-title">Overall CGPA</div><div class="stat-value text-warning"><?php echo $cumul['total_credits'] ? fmt_gpa($cumul['cgpa'], (int) get_setting('sgpa_decimals', 2)) : '—'; ?></div></div></div></div>
</div>

<?php if (!$semesters && !$publishedExams): ?>
    <div class="alert alert-info">No published results yet. Your academic history will appear here after results are published.</div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header">Published Examinations</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Exam</th><th>Type</th><th>Semester</th><th>Exam Date</th><th>Published</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php foreach ($publishedExams as $x): ?>
                <tr>
                    <td class="fw-semibold"><?php echo e($x['exam_name']); ?></td>
                    <td><?php echo e($x['type_name']); ?></td>
                    <td><?php echo e($x['semester_name']); ?></td>
                    <td><?php echo fdate($x['exam_date']); ?></td>
                    <td><?php echo fdate($x['published_at']); ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="result.php?exam_id=<?php echo $x['id']; ?>">View</a>
                        <a class="btn btn-sm btn-outline-danger" href="<?php echo e(url('pdf/marksheet.php?exam_id=' . $x['id'] . '&student_id=' . $studentId)); ?>"><i class="bi bi-file-pdf"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$publishedExams): ?><tr><td colspan="6" class="text-center py-3 text-muted">No published examinations.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php foreach ($semesters as $r): $rows = result_subject_rows((int) $r['id']); ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><?php echo e($r['semester_name']); ?> — <?php echo e($r['exam_name']); ?></span>
            <span><?php echo status_badge($r['result_status']); ?></span>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead><tr><th>Code</th><th>Subject</th><th class="text-center">Credit</th><th class="text-center">Total</th><th class="text-center">Grade</th><th class="text-center">GP</th><th class="text-center">CP</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $d): ?>
                    <tr>
                        <td><?php echo e($d['subject_code']); ?></td>
                        <td><?php echo e($d['subject_name']); ?></td>
                        <td class="text-center"><?php echo e($d['credit_hours']); ?></td>
                        <td class="text-center fw-semibold"><?php echo marks_round($d['total_marks']); ?>/<?php echo (int) $d['max_marks']; ?></td>
                        <td class="text-center"><?php echo e($d['grade']); ?></td>
                        <td class="text-center"><?php echo e($d['grade_point']); ?></td>
                        <td class="text-center"><?php echo e($d['credit_point']); ?></td>
                        <td><?php echo status_badge($d['subject_status']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light"><tr>
                    <th colspan="2">SGPA: <?php echo fmt_gpa($r['sgpa'], (int) get_setting('sgpa_decimals', 2)); ?> &nbsp;·&nbsp; CGPA: <?php echo fmt_gpa($r['cgpa'], (int) get_setting('sgpa_decimals', 2)); ?></th>
                    <th><?php echo e($r['total_credits']); ?></th>
                    <th colspan="4"></th>
                    <th class="text-center"><?php echo e($r['total_credit_points']); ?></th>
                    <th></th>
                </tr></tfoot>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($backs): ?>
<div class="card">
    <div class="card-header text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Back / Failed Subjects</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead><tr><th>Code</th><th>Subject</th><th>Semester</th><th>Credits</th></tr></thead>
            <tbody>
            <?php foreach ($backs as $b): ?>
                <tr><td><?php echo e($b['code']); ?></td><td class="fw-semibold text-danger"><?php echo e($b['name']); ?></td><td><?php echo e($b['semester_name']); ?></td><td><?php echo e($b['credit_hours']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>