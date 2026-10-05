<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_TEACHER);
$title = 'Enter Marks';
$active = 'mark_entry';

$teacherId = (int) db_val('SELECT teacher_id FROM users WHERE id = ?', [$user['id']]);

$examId = (int) ($_GET['exam_id'] ?? 0);
$esId = (int) ($_GET['es_id'] ?? 0);

$exams = $teacherId
    ? db_all(
        "SELECT DISTINCT e.id, e.name, e.exam_status, sm.name AS sem_name
         FROM exams e JOIN exam_subjects es ON es.exam_id = e.id AND es.status=1
         JOIN semesters sm ON sm.id = e.semester_id
         WHERE es.teacher_id = ? ORDER BY (e.exam_status = 'marks_entry') DESC, e.id DESC", [$teacherId])
    : [];
if (!$examId && $exams) $examId = (int) $exams[0]['id'];

$mySubjects = [];
if ($examId) {
    $mySubjects = db_all(
        "SELECT es.id es_id, su.code, su.name AS subject_name, es.full_marks
         FROM exam_subjects es JOIN subjects su ON su.id = es.subject_id
         WHERE es.exam_id = ? AND es.teacher_id = ? AND es.status = 1
         ORDER BY su.code", [$examId, $teacherId]);
    if (!$esId && $mySubjects) $esId = (int) $mySubjects[0]['es_id'];
}

function teacher_sheet_students(int $esId): array
{
    return db_all(
        "SELECT st.id, st.student_code, st.exam_roll_no, st.full_name, m.id mid, m.obtained, m.status, m.submitted
         FROM exam_subjects es
         JOIN subjects su ON su.id = es.subject_id
         JOIN student_subjects ss ON ss.subject_id = es.subject_id AND ss.status = 1
         JOIN students st ON st.id = ss.student_id AND st.status = 1
         JOIN exams e ON e.id = es.exam_id
         LEFT JOIN marks m ON m.exam_subject_id = es.id AND m.student_id = st.id
         WHERE es.id = ? AND st.program_id = e.program_id
         ORDER BY st.student_code", [$esId]);
}

$lockedExam = false;
$draftExam = false;
$reopened = false;
$submittedSheet = false;
$currentEs = null;
$table = [];
if ($esId) {
    $currentEs = db_one('SELECT * FROM exam_subjects WHERE id = ?', [$esId]);
    $reopened = (bool) ($currentEs['reopened'] ?? 0);
    $submittedSheet = (bool) ($currentEs['marks_submitted'] ?? 0);
    $esExamId = (int) ($currentEs['exam_id'] ?? 0);
    $examStatus = db_val('SELECT exam_status FROM exams WHERE id = ?', [$esExamId]);
    $lockedExam = $examStatus === 'published';
    $draftExam = $examStatus === 'draft';
    $table = teacher_sheet_students($esId);
}
$canEdit = $reopened || (!$draftExam && !$lockedExam && !$submittedSheet);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $esId = (int) $_POST['es_id'];
    $examId = (int) $_POST['exam_id'];
    $submit = ($_POST['submit_flag'] ?? '0') === '1';

    // ownership + lock checks
    $owner = db_val('SELECT teacher_id FROM exam_subjects WHERE id = ?', [$esId]);
    if ((int) $owner !== $teacherId) { flash('danger', 'You are not assigned to this subject.'); redirect(url('teacher/mark_entry.php')); }
    $sheetRow = db_one('SELECT exam_id, marks_submitted, reopened FROM exam_subjects WHERE id = ?', [$esId]);
    $reopened = (bool) ($sheetRow['reopened'] ?? 0);
    $submittedSheet = (bool) ($sheetRow['marks_submitted'] ?? 0);
    $esExamId = (int) ($sheetRow['exam_id'] ?? 0);
    $examStatus = db_val('SELECT exam_status FROM exams WHERE id = ?', [$esExamId]);
    $lockedExam = $examStatus === 'published';
    $draftExam = $examStatus === 'draft';
    if (!$reopened) {
        if ($draftExam) { flash('danger', 'Marks entry has not been opened yet. The administrator must start marks entry first.'); redirect(url('teacher/mark_entry.php?exam_id=' . $esExamId . '&es_id=' . $esId)); }
        if ($lockedExam) { flash('danger', 'This exam is published — marks are locked.'); redirect(url('teacher/mark_entry.php?exam_id=' . $esExamId . '&es_id=' . $esId)); }
        if ($submittedSheet) { flash('danger', 'This marks sheet has been submitted. Ask the administrator to reopen it if a correction is required.'); redirect(url('teacher/mark_entry.php?exam_id=' . $esExamId . '&es_id=' . $esId)); }
    }

    $max = (float) db_val('SELECT full_marks FROM exam_subjects WHERE id = ?', [$esId]);
    $allowedStatus = ['Present', 'Absent', 'Withheld', 'Incomplete', 'Not Eligible'];
    $errors = [];
    foreach (($_POST['student'] ?? []) as $sid => $row) {
        $sidOk = (int) db_val(
            'SELECT COUNT(*) FROM student_subjects ss JOIN students st ON st.id = ss.student_id JOIN exams e ON e.id = ? WHERE ss.subject_id = (SELECT subject_id FROM exam_subjects WHERE id = ?) AND ss.student_id = ? AND ss.status = 1 AND st.status = 1 AND st.program_id = e.program_id',
            [$esExamId, $esId, (int) $sid]);
        if ($sidOk < 1) { $errors[] = 'Student #' . $sid . ' is not enrolled in this subject — skipped.'; continue; }
        $obtained = trim($row['obtained'] ?? '');
        $status = in_array($row['status'] ?? '', $allowedStatus, true) ? $row['status'] : 'Present';
        if ($status === 'Present' && ($obtained === '' || !is_numeric($obtained) || num($obtained) < 0 || num($obtained) > $max)) {
            $errors[] = 'Invalid marks for student #' . $sid . ' (must be 0–' . $max . ').';
            continue;
        }
        if ($status !== 'Present') $obtained = null;
        db_run(
            'INSERT INTO marks (exam_subject_id, student_id, obtained, full_marks, status, entered_by, submitted)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE obtained = VALUES(obtained), full_marks = VALUES(full_marks), status = VALUES(status),
             entered_by = VALUES(entered_by), submitted = VALUES(submitted)',
            [$esId, (int) $sid, $obtained === null ? null : num($obtained), $max, $status, $user['id'], $submit ? 1 : 0]
        );
    }
    if ($submit && !$errors) {
        db_run('UPDATE exam_subjects SET marks_submitted = 1, reopened = 0 WHERE id = ?', [$esId]);
    } elseif (!$errors && $reopened) {
        db_run('UPDATE exam_subjects SET marks_submitted = 0 WHERE id = ? AND reopened = 1', [$esId]);
    }
    audit(($submit ? 'Submit' : 'Update') . ' marks', 'Marks sheet, exam_subject id ' . $esId, $esId);
    flash(empty($errors) ? 'success' : 'warning',
        $submit ? 'Marks submitted. The administrator will review before publishing.'
                : 'Marks saved as draft.' . ($errors ? ' ' . implode('<br>', array_slice($errors, 0, 5)) : ''));
    redirect(url('teacher/mark_entry.php?exam_id=' . $esExamId . '&es_id=' . $esId . ($submit ? '&submitted=1' : '')));
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Enter Marks</h4>
</div>

<?php if ($reopened): ?>
    <div class="alert alert-warning"><i class="bi bi-arrow-counterclockwise me-1"></i> This sheet has been reopened by the administrator for correction. Edit the marks and <strong>Submit Sheet</strong> again to re-lock it.</div>
<?php endif; ?>

<?php if (!empty($_GET['submitted'])): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i> Marks submitted. The administrator will review before publishing.</div>
<?php endif; ?>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-5"><select class="form-select" name="exam_id" onchange="this.form.submit()">
        <option value="0">— Exam —</option>
        <?php foreach ($exams as $x):
            $statusLabel = ['draft' => 'Closed', 'marks_entry' => 'Open', 'published' => 'Published'][$x['exam_status']] ?? $x['exam_status']; ?>
            <option value="<?php echo $x['id']; ?>" <?php echo selected($examId, $x['id']); ?>><?php echo e($x['name'] . ' (' . $x['sem_name'] . ') — ' . $statusLabel); ?></option>
        <?php endforeach; ?>
    </select></div>
    <div class="col-md-5"><select class="form-select" name="es_id" onchange="this.form.submit()">
        <option value="0">— Subject —</option>
        <?php foreach ($mySubjects as $s): ?><option value="<?php echo $s['es_id']; ?>" <?php echo selected($esId, $s['es_id']); ?>><?php echo e($s['code'] . ' — ' . $s['subject_name'] . ' (full ' . $s['full_marks'] . ')'); ?></option><?php endforeach; ?>
    </select></div>
</form>

<?php if ($currentEs && $canEdit): ?>
<form method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="es_id" value="<?php echo $esId; ?>">
    <input type="hidden" name="exam_id" value="<?php echo $examId; ?>">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <span>Marks Sheet — <strong><?php echo e(db_val('SELECT name FROM subjects WHERE id=?', [db_val('SELECT subject_id FROM exam_subjects WHERE id=?', [$esId])]) ?? ''); ?></strong> (full marks <?php echo e($currentEs['full_marks']); ?>)</span>
            <span class="small text-muted" id="enterTotals"></span>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead><tr><th>Roll No.</th><th>Student</th><th class="text-center" style="width:180px">Obtained Marks</th><th class="text-center" style="width:180px">Status</th></tr></thead>
                <tbody>
                <?php foreach ($table as $st): ?>
                    <tr>
                        <td><span class="badge text-bg-dark"><?php echo e($st['student_code']); ?></span></td>
                        <td class="fw-semibold"><?php echo e($st['full_name']); ?></td>
                        <td class="text-center">
                            <input type="number" step="0.01" min="0" max="<?php echo e($currentEs['full_marks']); ?>"
                                   class="form-control text-center marks-input mx-auto" name="student[<?php echo $st['id']; ?>][obtained]"
                                   value="<?php echo e($st['obtained'] ?? ''); ?>" data-max="<?php echo e($currentEs['full_marks']); ?>">
                        </td>
                        <td class="text-center">
                            <select class="form-select mx-auto marks-status" style="max-width:170px" name="student[<?php echo $st['id']; ?>][status]">
                                <?php foreach (['Present', 'Absent', 'Withheld', 'Incomplete', 'Not Eligible'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo selected($st['status'] ?? 'Present', $s); ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$table): ?><tr><td colspan="4" class="text-center py-4 text-muted">No students enrolled in this subject.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
            <button class="btn btn-outline-secondary" name="submit_flag" value="0"><i class="bi bi-save me-1"></i> Save Draft</button>
            <button class="btn btn-success" name="submit_flag" value="1" onclick="return confirm('Submit this marks sheet? After submission you cannot edit it until the administrator reopens.');">
                <i class="bi bi-check2-circle me-1"></i> Submit Sheet</button>
        </div>
    </div>
</form>
<?php elseif ($currentEs): ?>
    <?php if ($draftExam): ?>
        <div class="alert alert-warning"><i class="bi bi-lock-fill me-1"></i> Marks entry has not been opened yet. The administrator must start marks entry first.</div>
    <?php elseif ($lockedExam): ?>
        <div class="alert alert-warning"><i class="bi bi-lock-fill me-1"></i> This exam is published. Marks are locked; contact the administrator if a correction is required.</div>
    <?php elseif ($submittedSheet): ?>
        <div class="alert alert-info"><i class="bi bi-check2-circle me-1"></i> This marks sheet has been submitted. Contact the administrator if a correction is required.</div>
    <?php endif; ?>
<?php else: ?>
    <div class="alert alert-info">Select an exam and subject sheet to begin.</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>