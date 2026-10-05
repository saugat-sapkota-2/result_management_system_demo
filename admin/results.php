<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/result_engine.php';
$user = require_role(ROLE_SUPERADMIN);
$title = 'Result Processing';
$active = 'results';

$programs = db_all('SELECT * FROM programs WHERE status=1 ORDER BY name');
$program = (int) ($_GET['program'] ?? 0);
$exams = db_all('SELECT e.*, s.name AS sem_name, b.name AS batch_name, p.name AS program_name FROM exams e
                 JOIN semesters s ON s.id = e.semester_id LEFT JOIN batches b ON b.id = e.batch_id
                 LEFT JOIN programs p ON p.id = e.program_id
                 WHERE (? = 0 OR e.program_id = ?)
                 ORDER BY e.id DESC', [$program, $program]);
$examId = (int) ($_GET['exam_id'] ?? ($exams[0]['id'] ?? 0));
$exam = $examId ? db_one('SELECT * FROM exams WHERE id = ?', [$examId]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $examId = (int) ($_POST['exam_id'] ?? $examId);
    $exam = $examId ? db_one('SELECT * FROM exams WHERE id = ?', [$examId]) : null;
    $isTerminal = $exam ? exam_is_terminal($exam) : true;

    if ($action === 'start_marks') {
        db_run("UPDATE exams SET exam_status='marks_entry' WHERE id=?", [$examId]);
        audit('Start marks entry', 'Opened marks entry for exam ' . $examId, $examId);
        flash('success', 'Marks entry is now open. Teachers can enter and submit marks. Already-submitted sheets stay locked until you reopen them individually.');
        redirect(url('admin/results.php?exam_id=' . $examId));
    }

    if ($action === 'close_marks') {
        db_run("UPDATE exams SET exam_status='draft' WHERE id=?", [$examId]);
        audit('Close marks entry', 'Closed marks entry for exam ' . $examId, $examId);
        flash('success', 'Marks entry closed. Teachers can no longer enter or edit marks for this exam.');
        redirect(url('admin/results.php?exam_id=' . $examId));
    }

    if ($action === 'publish') {
        $pending = (int) db_val(
            'SELECT COUNT(*) FROM exam_subjects WHERE exam_id = ? AND status = 1 AND (marks_submitted = 0 OR reopened = 1)',
            [$examId]);
        if ($pending > 0) {
            flash('danger', "Cannot publish yet — $pending subject sheet(s) have not been submitted or are awaiting resubmission after a correction.");
        } elseif ($isTerminal) {
            db_run("UPDATE exams SET exam_status='published', published_at=COALESCE(published_at, NOW()) WHERE id=?", [$examId]);
            audit('Publish terminal exam', 'Published terminal exam ' . $examId, $examId);
            flash('success', 'Terminal exam published. Students can now view their marks sheets.');
        } else {
            $students = exam_students($examId);
            $done = 0; $skipped = 0;
            foreach ($students as $st) {
                $res = compute_student_result((int) $st['id'], $examId);
                if ($res !== null) $done++; else $skipped++;
            }
            db_run("UPDATE results SET status='published', is_locked=1, published_at=COALESCE(published_at, NOW()) WHERE exam_id=?", [$examId]);
            db_run("UPDATE exams SET exam_status='published', published_at=COALESCE(published_at, NOW()) WHERE id=?", [$examId]);
            audit('Publish final exam', 'Calculated ' . $done . ' result(s) and published final exam ' . $examId, $examId);
            flash($done ? 'success' : 'warning', "Results computed for $done student(s) and final exam published.");
        }
        redirect(url('admin/results.php?exam_id=' . $examId));
    }

    if ($action === 'reopen') {
        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') {
            flash('danger', 'A reason is required to reopen an exam.');
        } else {
            db_run("UPDATE exams SET exam_status='marks_entry', published_at=NULL WHERE id=?", [$examId]);
            if (!$isTerminal) {
                db_run("UPDATE results SET status='calculated', is_locked=0, published_at=NULL, reopen_reason=? WHERE exam_id=?",
                    [$reason, $examId]);
            }
            audit('Reopen exam', 'Reopened exam ' . $examId . ' — reason: ' . $reason, $examId);
            flash('success', 'Exam reopened. Sheets stay locked until you reopen individual subjects in Review Marks; computed results were reset for recalculation.');
        }
        redirect(url('admin/results.php?exam_id=' . $examId));
    }
    redirect(url('admin/results.php?exam_id=' . $examId));
}

$exam = $examId ? db_one('SELECT * FROM exams WHERE id = ?', [$examId]) : null;
$isTerminal = $exam ? exam_is_terminal($exam) : false;
$lifecycle = $exam ? exam_display_status($exam) : null;

$students = $examId ? exam_students($examId) : [];
$summary = [];
foreach ($students as $st) {
    if ($isTerminal) {
        $s = terminal_student_summary($examId, (int) $st['id']);
        $summary[] = array_merge($st, ['terminal' => $s, 'result' => null]);
    } else {
        $r = db_one("SELECT * FROM results WHERE student_id = ? AND exam_id = ?", [$st['id'], $examId]);
        $summary[] = array_merge($st, ['terminal' => null, 'result' => $r]);
    }
}

$subjectStats = $examId ? db_one(
    "SELECT COUNT(*) total,
            SUM(CASE WHEN marks_submitted = 1 AND reopened = 0 THEN 1 ELSE 0 END) submitted,
            SUM(reopened) reopened
     FROM exam_subjects WHERE exam_id = ? AND status = 1", [$examId]) : null;

$resultStatusCounts = ['Pass' => 0, 'Fail' => 0, 'Absent' => 0, 'Pending' => 0];
foreach ($summary as $row) {
    $status = $isTerminal
        ? ($row['terminal']['entered'] > 0 ? $row['terminal']['overall'] : 'Pending')
        : ($row['result'] ? $row['result']['result_status'] : 'Pending');
    $key = in_array($status, array_keys($resultStatusCounts), true) ? $status : 'Pending';
    $resultStatusCounts[$key]++;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Result Processing</h4>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-2">
        <select class="form-select" name="program" onchange="this.form.submit()">
            <option value="0">All programs</option>
            <?php foreach ($programs as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo selected($program, $p['id']); ?>><?php echo e($p['name']); ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <select class="form-select" name="exam_id" onchange="this.form.submit()">
            <?php foreach ($exams as $x): ?>
                <option value="<?php echo $x['id']; ?>" <?php echo selected($examId, $x['id']); ?>><?php echo e($x['name'] . ' — ' . $x['sem_name'] . ($x['program_name'] ? ' (' . $x['program_name'] . ')' : '')); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if ($exam): ?>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span><strong>Lifecycle:</strong> <span class="badge <?php echo $lifecycle['class']; ?>"><?php echo e($lifecycle['label']); ?></span></span>
            <span><strong>Students:</strong> <?php echo count($summary); ?></span>
            <span><strong>Subject sheets:</strong> <?php echo e($subjectStats['submitted'] ?? 0); ?>/<?php echo e($subjectStats['total'] ?? 0); ?> submitted<?php if ((int) ($subjectStats['reopened'] ?? 0) > 0): ?> <span class="badge bg-warning text-dark"><?php echo (int) $subjectStats['reopened']; ?> under correction</span><?php endif; ?></span>
            <span><strong><?php echo $isTerminal ? 'Pass' : 'Pass'; ?>:</strong> <span class="text-success"><?php echo $resultStatusCounts['Pass']; ?></span></span>
            <span><strong>Fail:</strong> <span class="text-danger"><?php echo $resultStatusCounts['Fail']; ?></span></span>
            <span><strong>Absent:</strong> <span class="text-warning"><?php echo $resultStatusCounts['Absent']; ?></span></span>
            <span><strong>Pending:</strong> <?php echo $resultStatusCounts['Pending']; ?></span>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <?php if ($exam['exam_status'] === 'draft'): ?>
                <form method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="start_marks"><input type="hidden" name="exam_id" value="<?php echo $examId; ?>">
                    <button class="btn btn-rms"><i class="bi bi-pencil-square me-1"></i> Start Marks Entry</button>
                </form>
            <?php elseif ($exam['exam_status'] === 'marks_entry'): ?>
                <form method="post" onsubmit="return confirm('Close marks entry? Teachers will no longer be able to enter or edit marks until you reopen it.');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="close_marks"><input type="hidden" name="exam_id" value="<?php echo $examId; ?>">
                    <button class="btn btn-outline-secondary"><i class="bi bi-stop-circle me-1"></i> Close Marks Entry</button>
                </form>
            <?php endif; ?>
            <?php if ($exam['exam_status'] !== 'published'): ?>
                <form method="post" onsubmit="return confirm('Publish this <?php echo $isTerminal ? 'terminal exam' : 'final exam'; ?>?<?php echo $isTerminal ? '' : ' All student results will be computed and locked.'; ?>');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="publish"><input type="hidden" name="exam_id" value="<?php echo $examId; ?>">
                    <button class="btn btn-success"><i class="bi bi-check2-circle me-1"></i> Publish <?php echo $isTerminal ? 'Marks' : 'Results'; ?></button>
                </form>
                <a class="btn btn-outline-warning" href="<?php echo e(url('admin/verify_marks.php?exam_id=' . $examId)); ?>"><i class="bi bi-arrow-counterclockwise me-1"></i> Review Subject Sheets</a>
            <?php else: ?>
                <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#reopenModal"><i class="bi bi-unlock me-1"></i> Reopen Exam</button>
            <?php endif; ?>
        </div>
        <div class="small text-muted mt-2">
            <?php if ($isTerminal): ?>
                Terminal exams are marks-only. Teachers enter each subject's marks, submit their sheet, and publishing makes the sheet visible to students. No SGPA/CGPA is computed for terminals.
            <?php else: ?>
                Final examinations compute each student's grade, SGPA and CGPA from the submitted subject marks, then publish and lock the results.
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Roll</th><th>Student</th>
                    <?php if (!$isTerminal): ?><th>Credits</th><th>Credit Pts</th><th>SGPA</th><th>CGPA</th><th>Result</th><?php endif; ?>
                    <th>Status</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($summary as $row): $r = $row['result']; ?>
                <tr>
                    <td><span class="badge text-bg-dark"><?php echo e($row['student_code']); ?></span></td>
                    <td class="fw-semibold"><a class="text-decoration-none" href="student_profile.php?id=<?php echo $row['id']; ?>"><?php echo e($row['full_name']); ?></a>
                        <div class="small text-muted"><?php echo e($row['batch_name'] ?? ''); ?> <?php echo e($row['section_name'] ?? ''); ?></div></td>
                    <?php if (!$isTerminal): ?>
                        <?php if ($r): ?>
                            <td><?php echo e($r['total_credits']); ?></td>
                            <td><?php echo e($r['total_credit_points']); ?></td>
                            <td class="fw-bold"><?php echo fmt_gpa($r['sgpa'], (int) get_setting('sgpa_decimals', 2)); ?></td>
                            <td><?php echo fmt_gpa($r['cgpa'], (int) get_setting('sgpa_decimals', 2)); ?></td>
                            <td><?php echo status_badge($r['result_status']); ?></td>
                            <td><?php echo $r['status'] === 'published' ? '<span class="badge bg-success">Published</span>' : ($r['is_locked'] ? status_badge($r['status']) : '<span class="badge bg-secondary">Draft</span>'); ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($r): ?>
                                    <a class="btn btn-sm btn-outline-info" href="<?php echo e(url('reports/individual_result.php?exam_id=' . $examId . '&student_id=' . $row['id'])); ?>"><i class="bi bi-eye"></i></a>
                                    <a class="btn btn-sm btn-outline-danger" href="<?php echo e(url('pdf/marksheet.php?exam_id=' . $examId . '&student_id=' . $row['id'])); ?>"><i class="bi bi-file-pdf"></i></a>
                                <?php endif; ?>
                            </td>
                        <?php else: ?>
                            <td colspan="5" class="text-muted small">Marks not fully entered for this student.</td>
                            <td>—</td>
                            <td class="text-end"></td>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php $t = $row['terminal']; ?>
                        <td class="small"><?php echo $t['entered']; ?>/<?php echo $t['expected']; ?> subjects</td>
                        <td><span class="badge <?php echo $t['entered'] === 0 ? 'bg-secondary' : ($t['is_pass'] ? 'bg-success' : 'bg-danger'); ?>"><?php echo e($t['entered'] === 0 ? 'Pending' : $t['overall']); ?></span></td>
                        <td class="text-end text-nowrap">
                            <?php if ($t['entered'] > 0): ?>
                                <a class="btn btn-sm btn-outline-info" href="<?php echo e(url('reports/individual_result.php?exam_id=' . $examId . '&student_id=' . $row['id'])); ?>"><i class="bi bi-eye"></i></a>
                                <a class="btn btn-sm btn-outline-danger" href="<?php echo e(url('pdf/marksheet.php?exam_id=' . $examId . '&student_id=' . $row['id'])); ?>"><i class="bi bi-file-pdf"></i></a>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$summary): ?>
                <tr><td colspan="9" class="text-center py-4 text-muted">No students enrolled in the subjects of this exam. <a href="student_subjects.php">Enrol students</a> first.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Reopen modal -->
<div class="modal fade" id="reopenModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="reopen">
    <input type="hidden" name="exam_id" value="<?php echo $examId; ?>">
    <div class="modal-header"><h6 class="modal-title text-warning"><i class="bi bi-unlock me-1"></i>Reopen Exam</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p class="small text-muted">Reopening moves the exam back to marks-entry so its marks/results can be re-processed, and resets any computed final results. Individual sheets stay locked — use <strong>Review Subject Sheets</strong> to reopen specific subjects for correction. This is recorded in the audit log.</p>
        <label class="form-label">Reason *</label>
        <textarea class="form-control" name="reason" rows="2" required placeholder="e.g. Marks correction approved by coordinator"></textarea>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-warning">Reopen Exam</button></div>
</form></div></div>
<?php else: ?>
    <div class="alert alert-info">Select an examination to begin result processing.</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>