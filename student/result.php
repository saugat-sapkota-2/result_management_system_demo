<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/academics.php';
$user = require_role(ROLE_STUDENT);
$title = 'My Result';
$active = 'result';

$studentId = (int) db_val('SELECT student_id FROM users WHERE id = ?', [$user['id']]);
$student = db_one(
    "SELECT st.*, b.name AS batch_name, sec.name AS section_name, p.name AS program_name
     FROM students st LEFT JOIN batches b ON b.id = st.batch_id LEFT JOIN sections sec ON sec.id = st.section_id
     LEFT JOIN programs p ON p.id = st.program_id WHERE st.id = ?", [$studentId]);

$publishedExams = db_all(
    "SELECT DISTINCT e.id, e.name exam_name, e.exam_date, e.published_at, et.name type_name, et.code type_code,
            sm.name semester_name, sm.semester_no
     FROM exams e
     JOIN exam_types et ON et.id = e.exam_type_id
     JOIN exam_subjects es ON es.exam_id = e.id AND es.status = 1
     JOIN student_subjects ss ON ss.subject_id = es.subject_id AND ss.status = 1
     JOIN semesters sm ON sm.id = e.semester_id
     JOIN students st ON st.id = ss.student_id
     WHERE e.exam_status = 'published' AND ss.student_id = ? AND st.program_id = e.program_id
     ORDER BY sm.semester_no", [$studentId]);

$examId = (int) ($_GET['exam_id'] ?? 0);
if (!$examId && $publishedExams) $examId = (int) end($publishedExams)['id'];
$current = null;
foreach ($publishedExams as $p) {
    if ((int) $p['id'] === $examId) { $current = $p; break; }
}
if (!$current && $publishedExams) { $current = end($publishedExams); $examId = (int) $current['id']; }

$cumul = cumulative_gpa(student_semesters_summary($studentId, 'published'));
$isTerminal = $current ? in_array($current['type_code'], ['first_terminal', 'second_terminal', 'third_terminal'], true) : false;
$terminal = $current && $isTerminal ? terminal_student_summary($examId, $studentId) : null;
$result = $current && !$isTerminal ? db_one('SELECT * FROM results WHERE student_id = ? AND exam_id = ?', [$studentId, $examId]) : null;
$rows = $result ? result_subject_rows((int) $result['id']) : [];

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>My Result</h4>
    <?php if ($current): ?>
    <a class="btn btn-sm btn-outline-danger" href="<?php echo e(url('pdf/marksheet.php?exam_id=' . $examId . '&student_id=' . $studentId)); ?>"><i class="bi bi-file-pdf me-1"></i> Download PDF Marksheet</a>
    <?php endif; ?>
</div>

<?php if (count($publishedExams) > 1): ?>
<form method="get" class="row g-2 mb-3">
    <div class="col-md-6"><select class="form-select" name="exam_id" onchange="this.form.submit()">
        <?php foreach ($publishedExams as $p): ?>
            <option value="<?php echo $p['id']; ?>" <?php echo selected($examId, $p['id']); ?>><?php echo e($p['exam_name'] . ' (' . $p['type_name'] . ' — ' . $p['semester_name'] . ')'); ?></option>
        <?php endforeach; ?>
    </select></div>
</form>
<?php endif; ?>

<?php if (!$current): ?>
    <div class="alert alert-info"><i class="bi bi-hourglass-split me-1"></i> No published result is available yet. Check <a href="notices.php">notices</a> for announcements.</div>
<?php elseif ($terminal): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="text-center border-bottom pb-3 mb-3">
            <h4 class="mb-0"><?php echo e(get_setting('institute_name', 'College')); ?></h4>
            <div class="small text-muted"><?php echo e(get_setting('institute_address', '')); ?></div>
            <div class="fw-semibold mt-2"><?php echo e(($student['program_name'] ?? 'BCA') . ' ' . $current['semester_name'] . ' — ' . $current['type_name']); ?></div>
            <span class="result-stamp mt-2 d-inline-block">Published</span>
        </div>

        <div class="row mb-3">
            <div class="col-6 col-md-3"><small class="text-muted">Student Name</small><div class="fw-semibold"><?php echo e($student['full_name']); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">Student ID</small><div class="fw-semibold"><?php echo e($student['student_code']); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">TU Reg. No.</small><div><?php echo e($student['tu_reg_no'] ?: '—'); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">Exam Roll No.</small><div><?php echo e($student['exam_roll_no'] ?: '—'); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">Batch</small><div><?php echo e($student['batch_name'] ?? '—'); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">Section</small><div><?php echo e($student['section_name'] ?? '—'); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">Exam Date</small><div><?php echo fdate($current['exam_date']); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">Published</small><div><?php echo fdate($current['published_at']); ?></div></div>
        </div>

        <table class="table table-bordered table-sm">
            <thead class="table-light"><tr><th>Subject Code</th><th>Subject Name</th><th class="text-center">Credit</th><th class="text-center">Full Marks</th><th class="text-center">Obtained</th><th class="text-center">%</th><th class="text-center">Status</th></tr></thead>
            <tbody>
            <?php foreach ($terminal['subjects'] as $d): ?>
                <tr>
                    <td class="fw-semibold"><?php echo e($d['subject_code']); ?></td>
                    <td><?php echo e($d['subject_name']); ?></td>
                    <td class="text-center"><?php echo e($d['credit_hours']); ?></td>
                    <td class="text-center"><?php echo marks_round($d['full_marks']); ?></td>
                    <td class="text-center fw-semibold"><?php echo marks_round($d['obtained']); ?></td>
                    <td class="text-center"><?php echo round($d['percentage'], 1); ?></td>
                    <td class="text-center"><?php echo status_badge($d['status']); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$terminal['subjects']): ?><tr><td colspan="7" class="text-center text-muted py-3">No marks have been entered for you yet.</td></tr><?php endif; ?>
            </tbody>
            <?php if ($terminal['subjects']): ?>
            <tfoot class="table-light"><tr>
                <th colspan="3" class="text-end">Totals &amp; Overall</th>
                <th class="text-center"><?php echo marks_round($terminal['full']); ?></th>
                <th class="text-center"><?php echo marks_round($terminal['total']); ?></th>
                <th class="text-center"><?php echo round($terminal['percentage'], 1); ?>%</th>
                <th class="text-center"><?php echo status_badge($terminal['overall']); ?></th>
            </tr></tfoot>
            <?php endif; ?>
        </table>

        <div class="small text-muted"><i class="bi bi-info-circle me-1"></i>Terminal examinations are marks-only sheets. Final examination results include grades, SGPA and CGPA.</div>

        <div class="row mt-4 pt-3 border-top">
            <div class="col-6"><div class="border-top pt-1"><small class="text-muted">Prepared By (Authority)</small></div></div>
            <div class="col-6 text-end"><div class="border-top pt-1"><small class="text-muted">Signature / Seal</small></div></div>
        </div>
    </div>
</div>
<?php elseif ($result): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="text-center border-bottom pb-3 mb-3">
            <h4 class="mb-0"><?php echo e(get_setting('institute_name', 'College')); ?></h4>
            <div class="small text-muted"><?php echo e(get_setting('institute_address', '')); ?></div>
            <div class="fw-semibold mt-2"><?php echo e(($student['program_name'] ?? 'BCA') . ' — ' . $current['semester_name']); ?> Final Result</div>
            <span class="result-stamp mt-2 d-inline-block">Published</span>
        </div>

        <div class="row mb-3">
            <div class="col-6 col-md-3"><small class="text-muted">Student Name</small><div class="fw-semibold"><?php echo e($student['full_name']); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">Student ID</small><div class="fw-semibold"><?php echo e($student['student_code']); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">TU Reg. No.</small><div><?php echo e($student['tu_reg_no'] ?: '—'); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">Exam Roll No.</small><div><?php echo e($student['exam_roll_no'] ?: '—'); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">Batch</small><div><?php echo e($student['batch_name'] ?? '—'); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">Section</small><div><?php echo e($student['section_name'] ?? '—'); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">Academic Year</small><div><?php echo e(db_val('SELECT name FROM academic_years WHERE id=?', [db_val('SELECT academic_year_id FROM exams WHERE id=?', [$examId])]) ?? '—'); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">Published</small><div><?php echo fdate($current['published_at']); ?></div></div>
        </div>

        <table class="table table-bordered table-sm">
            <thead class="table-light">
                <tr><th>Subject Code</th><th>Subject Name</th><th class="text-center">Credit</th><th class="text-center">Total</th><th class="text-center">Grade</th><th class="text-center">Grade Pt.</th><th class="text-center">Credit Pt.</th><th class="text-center">Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $d): ?>
                <tr>
                    <td class="fw-semibold"><?php echo e($d['subject_code']); ?></td>
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
                    <th colspan="4" class="text-center">SGPA: <span class="fw-bold"><?php echo fmt_gpa($result['sgpa'], (int) get_setting('sgpa_decimals', 2)); ?></span> &nbsp;·&nbsp; CGPA: <?php echo fmt_gpa($result['cgpa'], (int) get_setting('sgpa_decimals', 2)); ?></th>
                    <th class="text-center"><?php echo status_badge($result['result_status']); ?></th>
                </tr>
            </tfoot>
        </table>

        <div class="small text-muted"><i class="bi bi-info-circle me-1"></i>This is an institution-generated result record for academic/project purposes and is not an official TU-issued document.</div>

        <div class="row mt-4 pt-3 border-top">
            <div class="col-6"><div class="border-top pt-1"><small class="text-muted">Prepared By (Authority)</small></div></div>
            <div class="col-6 text-end"><div class="border-top pt-1"><small class="text-muted">Signature / Seal</small></div></div>
        </div>
    </div>
</div>
<?php else: ?>
    <div class="alert alert-warning"><i class="bi bi-hourglass-split me-1"></i> Your final result is not computed yet. Contact the administration.</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>