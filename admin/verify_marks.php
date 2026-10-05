<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_SUPERADMIN);
require_module('review_marks');
$title = 'Review Marks';
$active = 'verify_marks';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $esId = (int) ($_POST['exam_subject_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $examId = (int) ($_POST['exam_id'] ?? 0);
    $program = (int) ($_POST['program'] ?? 0);

    if ($esId && $action === 'reopen') {
        if ($reason === '') {
            flash('danger', 'A reason is required to reopen a marks sheet.');
        } else {
            $sheet = db_one(
                'SELECT es.exam_id, e.exam_status, e.exam_type_id FROM exam_subjects es JOIN exams e ON e.id = es.exam_id WHERE es.id = ?',
                [$esId]);
            if ($sheet) {
                db_run('UPDATE exam_subjects SET marks_submitted = 0, reopened = 1 WHERE id = ? AND status = 1', [$esId]);
                if ($sheet['exam_status'] === 'published') {
                    db_run("UPDATE exams SET exam_status = 'marks_entry', published_at = NULL WHERE id = ?", [$sheet['exam_id']]);
                    if (!exam_is_terminal($sheet)) {
                        db_run("UPDATE results SET status='calculated', is_locked=0, published_at=NULL, reopen_reason=? WHERE exam_id = ?",
                            [$reason, $sheet['exam_id']]);
                    }
                }
                audit('Reopen marks sheet', 'Reopened exam_subject ' . $esId . ' for correction — reason: ' . $reason, $esId);
                flash('success', 'Marks sheet reopened for correction — the teacher can edit this subject only and must resubmit.');
            }
        }
    }

    if ($esId && $action === 'lock') {
        db_run('UPDATE exam_subjects SET marks_submitted = 1, reopened = 0 WHERE id = ? AND reopened = 1', [$esId]);
        audit('Lock marks sheet', 'Closed correction for exam_subject ' . $esId, $esId);
        flash('success', 'Correction closed — this marks sheet is locked again.');
    }

    redirect(url('admin/verify_marks.php?exam_id=' . $examId . '&program=' . $program));
}

$programs = db_all('SELECT * FROM programs WHERE status=1 ORDER BY name');
$program = (int) ($_GET['program'] ?? 0);
$exams = db_all('SELECT e.*, s.name AS sem_name, p.name AS program_name FROM exams e JOIN semesters s ON s.id = e.semester_id LEFT JOIN programs p ON p.id = e.program_id WHERE (? = 0 OR e.program_id = ?) ORDER BY e.id DESC', [$program, $program]);
$examId = (int) ($_GET['exam_id'] ?? ($exams[0]['id'] ?? 0));
$survey = [];
if ($examId && $exams) {
    $survey = db_all(
        "SELECT es.id exam_subject_id, es.full_marks, es.marks_submitted, es.reopened,
                su.code, su.name subject_name, es.subject_id, es.teacher_id, t.name teacher_name,
                (SELECT COUNT(*) FROM marks m WHERE m.exam_subject_id = es.id) entered,
                (SELECT COUNT(*) FROM marks m WHERE m.exam_subject_id = es.id AND m.submitted = 1) submitted,
                (SELECT COUNT(*) FROM student_subjects ss WHERE ss.subject_id = es.subject_id AND ss.status = 1) expected
         FROM exam_subjects es
         JOIN subjects su ON su.id = es.subject_id
         LEFT JOIN teachers t ON t.id = es.teacher_id
         WHERE es.exam_id = ? AND es.status = 1
         ORDER BY su.code", [$examId]);
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Review Marks</h4>
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

<div class="card">
    <div class="card-header">Subject Marks Sheets <?php if ($examId): ?>— <span class="text-muted">review submitted sheets; reopen a sheet if a correction is required</span><?php endif; ?></div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Subject</th><th>Full Marks</th><th>Teacher</th><th style="min-width:220px">Progress (entered / submitted)</th><th>Sheet Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php foreach ($survey as $row):
                $expected = max(1, (int) $row['expected']);
                $pct = (int) round(((int) $row['submitted'] / $expected) * 100); ?>
                <tr>
                    <td><span class="badge text-bg-dark"><?php echo e($row['code']); ?></span> <?php echo e($row['subject_name']); ?></td>
                    <td><?php echo e($row['full_marks']); ?></td>
                    <td class="small"><?php echo e($row['teacher_name'] ?? 'Unassigned'); ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:12px">
                                <div class="progress-bar bg-<?php echo $pct >= 100 ? 'success' : 'info'; ?>" style="width:<?php echo $pct; ?>%"></div>
                            </div>
                            <div class="small text-nowrap"><?php echo $row['entered']; ?> / <?php echo $row['submitted']; ?></div>
                        </div>
                        <?php if ((int) $row['entered'] === 0): ?><small class="text-muted">Not started</small><?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int) $row['reopened'] === 1): ?>
                            <span class="badge bg-warning text-dark">Reopened for Correction</span>
                        <?php elseif ((int) $row['marks_submitted'] === 1): ?>
                            <span class="badge bg-success">Submitted</span>
                        <?php elseif ((int) $row['entered'] > 0): ?>
                            <span class="badge bg-warning text-dark">In Progress</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if ((int) $row['submitted'] > 0): ?>
                            <a class="btn btn-sm btn-outline-info"
                               href="<?php echo e(url('admin/reports.php?exam_id=' . $examId . '&download=1')); ?>">Print</a>
                        <?php endif; ?>
                        <?php if ((int) $row['reopened'] === 1): ?>
                            <form class="d-inline" method="post" onsubmit="return confirm('Close the correction and lock this marks sheet again?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="lock"><input type="hidden" name="exam_subject_id" value="<?php echo $row['exam_subject_id']; ?>">
                                <input type="hidden" name="exam_id" value="<?php echo $examId; ?>">
                                <input type="hidden" name="program" value="<?php echo $program; ?>">
                                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-lock"></i> Lock Again</button>
                            </form>
                        <?php elseif ((int) $row['marks_submitted'] === 1): ?>
                            <form class="d-inline" method="post"
                                  onsubmit="var r = prompt('Reason (recorded in the audit log):', ''); if (!r) return false; var h = document.createElement('input'); h.type = 'hidden'; h.name = 'reason'; h.value = r; this.appendChild(h); return true;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="reopen"><input type="hidden" name="exam_subject_id" value="<?php echo $row['exam_subject_id']; ?>">
                                <input type="hidden" name="exam_id" value="<?php echo $examId; ?>">
                                <input type="hidden" name="program" value="<?php echo $program; ?>">
                                <button class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-counterclockwise"></i> Reopen</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$survey): ?><tr><td colspan="6" class="text-center py-4 text-muted">No subjects configured for this exam yet. Set them up in <a href="exam_setup.php?exam_id=<?php echo $examId; ?>">Exam Setup</a>.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>