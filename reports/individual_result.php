<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/result_engine.php';
require_once __DIR__ . '/../includes/academics.php';
$user = require_role(ROLE_SUPERADMIN);
$title = 'Individual Student Result';
$active = 'results';

$examId = (int) ($_GET['exam_id'] ?? 0);
$studentId = (int) ($_GET['student_id'] ?? 0);
$resultId = (int) ($_GET['result_id'] ?? 0);

$exam = null; $student = null; $isTerminal = false; $result = null;
if ($resultId) {
    $result = db_one('SELECT * FROM results WHERE id = ?', [$resultId]);
    if ($result) { $examId = (int) $result['exam_id']; $studentId = (int) $result['student_id']; }
}
$exam = $examId ? db_one('SELECT e.*, sm.name AS semester_name, ay.name AS academic_year FROM exams e
    JOIN semesters sm ON sm.id = e.semester_id LEFT JOIN academic_years ay ON ay.id = e.academic_year_id WHERE e.id = ?', [$examId]) : null;
$student = $studentId ? db_one('SELECT st.*, b.name AS batch_name, sec.name AS section_name, p.name AS program_name
    FROM students st LEFT JOIN batches b ON b.id = st.batch_id LEFT JOIN sections sec ON sec.id = st.section_id
    LEFT JOIN programs p ON p.id = st.program_id WHERE st.id = ?', [$studentId]) : null;
if (!$exam || !$student) { flash('danger', 'Result not found.'); redirect(url('admin/results.php')); }

$isTerminal = exam_is_terminal($exam);
if ($isTerminal) {
    $terminal = terminal_student_summary($examId, $studentId);
} else {
    if (!$result) $result = db_one('SELECT * FROM results WHERE student_id = ? AND exam_id = ?', [$studentId, $examId]);
    if (!$result) { flash('danger', 'Result not found for this student.'); redirect(url('admin/results.php')); }
    $rows = result_subject_rows((int) $result['id']);
}

include __DIR__ . '/../includes/header.php';

$examLabel = $isTerminal ? 'TERMINAL MARKS SHEET' : 'SINGLE-TOTAL RESULT';
$statusVal = $isTerminal ? ($terminal['overall'] ?? 'Pending') : $result['result_status'];
?>
<div class="page-title-row no-print">
    <h4 class="mb-0"><i class="bi bi-eye me-2"></i><?php echo $isTerminal ? 'Terminal Marks' : 'Individual Result'; ?> — <?php echo e($student['full_name']); ?></h4>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        <a class="btn btn-sm btn-outline-danger" href="<?php echo e(url('pdf/marksheet.php?exam_id=' . $examId . '&student_id=' . $studentId)); ?>"><i class="bi bi-file-pdf"></i> PDF Marksheet</a>
        <a class="btn btn-sm btn-outline-primary" href="<?php echo e(url('admin/results.php?exam_id=' . $examId)); ?>"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="text-center border-bottom pb-3 mb-3 d-print-block">
            <h4 class="mb-0"><?php echo e(get_setting('institute_name', 'College')); ?></h4>
            <div class="small text-muted"><?php echo e(get_setting('institute_address', '')); ?></div>
            <div class="fw-semibold mt-2"><?php echo e($student['program_name'] ?? 'BCA'); ?> — <?php echo e($exam['semester_name']); ?> (<?php echo e($exam['name']); ?>)</div>
            <?php if ($exam['exam_status'] === 'published'): ?>
                <span class="result-stamp mt-2 d-inline-block"><i class="bi bi-check2-circle"></i> Published</span>
            <?php endif; ?>
        </div>

        <div class="row mb-3">
            <div class="col-6 col-md-3"><small class="text-muted">Student Name</small><div class="fw-semibold"><?php echo e($student['full_name']); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">Student ID</small><div class="fw-semibold"><?php echo e($student['student_code']); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">TU Reg. No.</small><div><?php echo e($student['tu_reg_no'] ?: '—'); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">Exam Roll No.</small><div><?php echo e($student['exam_roll_no'] ?: '—'); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">Batch</small><div><?php echo e($student['batch_name'] ?? '—'); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">Section</small><div><?php echo e($student['section_name'] ?? '—'); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">Academic Year</small><div><?php echo e($exam['academic_year'] ?? '—'); ?></div></div>
            <div class="col-6 col-md-3"><small class="text-muted">Published On</small><div><?php echo fdate($exam['published_at']); ?></div></div>
        </div>

        <?php if ($isTerminal): ?>
        <table class="table table-bordered table-sm align-middle">
            <thead class="table-light">
                <tr><th>#</th><th>Code</th><th>Subject Name</th><th class="text-center">Credit</th><th class="text-center">Full</th><th class="text-center">Obtained</th><th class="text-center">%</th><th class="text-center">Status</th></tr>
            </thead>
            <tbody>
            <?php $i = 0; foreach ($terminal['subjects'] as $d): $i++; ?>
                <tr>
                    <td><?php echo $i; ?></td>
                    <td class="fw-semibold"><?php echo e($d['subject_code']); ?></td>
                    <td><?php echo e($d['subject_name']); ?></td>
                    <td class="text-center"><?php echo e($d['credit_hours']); ?></td>
                    <td class="text-center"><?php echo marks_round($d['full_marks']); ?></td>
                    <td class="text-center fw-semibold"><?php echo marks_round($d['obtained']); ?></td>
                    <td class="text-center"><?php echo round($d['percentage'], 1); ?></td>
                    <td class="text-center"><?php echo status_badge($d['status']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="4" class="text-end">Overall</th>
                    <th class="text-center"><?php echo marks_round($terminal['full']); ?></th>
                    <th class="text-center"><?php echo marks_round($terminal['total']); ?></th>
                    <th class="text-center"><?php echo round($terminal['percentage'], 1); ?>%</th>
                    <th class="text-center"><?php echo status_badge($terminal['overall']); ?></th>
                </tr>
            </tfoot>
        </table>
        <?php else: ?>
        <table class="table table-bordered table-sm align-middle">
            <thead class="table-light">
                <tr><th>Subject Code</th><th>Subject Name</th><th class="text-center">Credit</th><th class="text-center">Total</th><th class="text-center">Grade</th><th class="text-center">Grade Pt.</th><th class="text-center">Credit Pt.</th><th class="text-center">Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $d): ?>
                <tr>
                    <td class="fw-semibold"><?php echo e($d['subject_code']); ?></td>
                    <td><?php echo e($d['subject_name']); ?></td>
                    <td class="text-center"><?php echo e($d['credit_hours']); ?></td>
                    <td class="text-center fw-semibold"><?php echo marks_round($d['total_marks']); ?>/<?php echo e((int) $d['max_marks']); ?></td>
                    <td class="text-center"><span class="badge text-bg-primary"><?php echo e($d['grade']); ?></span></td>
                    <td class="text-center"><?php echo e($d['grade_point']); ?></td>
                    <td class="text-center"><?php echo e($d['credit_point']); ?></td>
                    <td class="text-center"><?php echo status_badge($d['subject_status']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="2" class="text-end">Total / Summary</th>
                    <th class="text-center"><?php echo e($result['total_credits']); ?></th>
                    <th></th>
                    <th class="text-center">SGPA: <span class="fw-bold"><?php echo fmt_gpa($result['sgpa']); ?></span></th>
                    <th class="text-center"><?php echo e($result['total_credit_points']); ?></th>
                    <th></th>
                    <th class="text-center"><?php echo status_badge($result['result_status']); ?></th>
                </tr>
                <tr><th colspan="8" class="text-center">CGPA: <?php echo fmt_gpa($result['cgpa']); ?> &nbsp;|&nbsp; Status: <?php echo $result['result_status']; ?> &nbsp;|&nbsp; Reopen reason: <?php echo e($result['reopen_reason'] ?: '—'); ?></th></tr>
            </tfoot>
        </table>
        <?php endif; ?>

        <div class="small text-muted mt-3"><i class="bi bi-info-circle"></i>
            This is an institution-generated academic record for project/demonstration purposes and is not an official TU-issued transcript.</div>

        <div class="row mt-4 pt-3 border-top no-print">
            <div class="col-6"><div class="border-top pt-1"><small class="text-muted">Prepared By (Authority)</small><br><br></div></div>
            <div class="col-6 text-end"><div class="border-top pt-1"><small class="text-muted">Signature / Seal</small></div></div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>