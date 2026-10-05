<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_SUPERADMIN);
$title = 'Exam Setup';
$active = 'exams';

$exam = exam_or_fail((int) ($_GET['exam_id'] ?? 0));
$examInfo = db_one('SELECT e.*, s.name AS sem_name, b.name AS batch_name, a.name AS ac_year, et.name AS type_name
                    FROM exams e JOIN semesters s ON s.id = e.semester_id
                    LEFT JOIN batches b ON b.id = e.batch_id LEFT JOIN academic_years a ON a.id = e.academic_year_id
                    LEFT JOIN exam_types et ON et.id = e.exam_type_id WHERE e.id = ?', [$exam['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $isTerminal = exam_is_terminal($exam);

    if ($action === 'set_subject') {
        $esId = (int) $_POST['exam_subject_id'];
        $tid = (int) ($_POST['teacher_id'] ?? 0) ?: null;
        $full = (float) ($_POST['full_marks'] ?? 0);
        if ($tid && !db_val('SELECT COUNT(*) FROM teachers WHERE id = ? AND status = 1', [$tid])) {
            flash('danger', 'Selected teacher is invalid.');
            redirect(url('admin/exam_setup.php?exam_id=' . $exam['id']));
        }
        $data = ['teacher_id = ?'];
        $params = [$tid];
        if ($full > 0) { $data[] = 'full_marks = ?'; $params[] = $full; }
        $params[] = $esId;
        db_run('UPDATE exam_subjects SET ' . implode(', ', $data) . ' WHERE id = ?', $params);
        audit(($isTerminal ? 'Terminal' : 'Final') . ' subject setup', 'Updated exam_subject ' . $esId, $esId);
        flash('success', 'Subject configuration saved.');
        redirect(url('admin/exam_setup.php?exam_id=' . $exam['id']));
    }

    if ($action === 'add_subject') {
        $sid = (int) $_POST['subject_id'];
        if ($sid > 0) {
            $subjectProgram = db_val('SELECT program_id FROM subjects WHERE id = ?', [$sid]);
            if ((int) $subjectProgram !== (int) $exam['program_id']) {
                flash('danger', 'Subject does not belong to this exam\'s program.');
                redirect(url('admin/exam_setup.php?exam_id=' . $exam['id']));
            }
            try {
                $tid = db_val('SELECT teacher_id FROM teacher_subjects WHERE subject_id = ? AND status=1 ORDER BY id DESC LIMIT 1', [$sid]);
                $full = $isTerminal ? 20 : 100;
                db_run('INSERT IGNORE INTO exam_subjects (exam_id, subject_id, teacher_id, full_marks) VALUES (?,?,?,?)',
                    [$exam['id'], $sid, $tid ? (int) $tid : null, $full]);
                flash('success', 'Subject added to exam.');
            } catch (PDOException $ex) {
                flash('danger', 'Subject already in exam.');
            }
            audit('Add subject to exam', 'Subject id ' . $sid . ' -> exam ' . $exam['id'], $exam['id']);
        }
        redirect(url('admin/exam_setup.php?exam_id=' . $exam['id']));
    }

    if ($action === 'remove_subject') {
        db_run('DELETE FROM exam_subjects WHERE id=?', [(int) $_POST['id']]);
        audit('Remove exam subject', 'Removed exam_subject id ' . (int) $_POST['id']);
        flash('success', 'Subject removed from exam.');
        redirect(url('admin/exam_setup.php?exam_id=' . $exam['id']));
    }
}

$examSubjects = db_all(
    "SELECT es.*, su.code, su.name AS subject_name, su.credit_hours, t.name AS teacher_name,
            (SELECT COUNT(*) FROM student_subjects ss WHERE ss.subject_id = es.subject_id AND ss.status=1) student_count,
            (SELECT COUNT(*) FROM marks m WHERE m.exam_subject_id = es.id) marks_count,
            (SELECT COUNT(*) FROM marks m WHERE m.exam_subject_id = es.id AND m.submitted = 1) submitted_count
     FROM exam_subjects es JOIN subjects su ON su.id = es.subject_id
     LEFT JOIN teachers t ON t.id = es.teacher_id
     WHERE es.exam_id = ? AND es.status = 1
     ORDER BY su.code", [$exam['id']]);

$teachers = db_all('SELECT id, name, program_id FROM teachers WHERE status=1 ORDER BY name');
$allSubjects = db_all(
    "SELECT su.* FROM subjects su JOIN semesters sm ON sm.id = su.semester_id
     WHERE su.program_id = ? AND su.semester_id = ? AND su.status=1 AND su.id NOT IN (SELECT subject_id FROM exam_subjects WHERE exam_id = ? AND status=1)
     ORDER BY su.code", [$exam['program_id'], $exam['semester_id'], $exam['id']]);

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-gear me-2"></i>Exam Setup — <?php echo e($examInfo['name']); ?></h4>
    <a class="btn btn-sm btn-outline-secondary" href="exams.php"><i class="bi bi-arrow-left"></i> All Exams</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row small">
            <div class="col-md-3"><strong>Type:</strong> <?php echo e($examInfo['type_name'] ?? '—'); ?></div>
            <div class="col-md-3"><strong>Program:</strong> <?php echo e(program_name_of((int) $exam['program_id'])); ?></div>
            <div class="col-md-3"><strong>Semester:</strong> <?php echo e($examInfo['sem_name']); ?></div>
            <div class="col-md-3"><strong>Batch:</strong> <?php echo e($examInfo['batch_name'] ?? '—'); ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Subjects in Exam (<?php echo count($examSubjects); ?>)</span>
        <form method="post" class="d-flex gap-2">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_subject">
            <select class="form-select form-select-sm" name="subject_id">
                <option value="0">Add subject…</option>
                <?php foreach ($allSubjects as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo e($s['code'] . ' — ' . $s['name']); ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-primary">Add</button>
        </form>
    </div>
    <table class="table table-hover mb-0">
        <thead><tr><th>Code</th><th>Subject</th><th>Full Marks</th><th>Teacher</th><th>Enrolled</th><th>Marks Sheet</th><th class="text-end">Action</th></tr></thead>
        <tbody>
        <?php foreach ($examSubjects as $es): ?>
            <tr>
                <td><span class="badge text-bg-dark"><?php echo e($es['code']); ?></span></td>
                <td class="fw-semibold"><?php echo e($es['subject_name']); ?></td>
                <td>
                    <form method="post" class="d-flex gap-1 align-items-center" style="min-width:180px">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="set_subject"><input type="hidden" name="exam_subject_id" value="<?php echo $es['id']; ?>">
                        <input type="number" step="0.5" min="1" class="form-control form-control-sm" name="full_marks" value="<?php echo e($es['full_marks']); ?>" style="width:85px">
                        <select class="form-select form-select-sm" name="teacher_id" style="width:150px">
                            <option value="0">— Teacher —</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo selected($es['teacher_id'] ?? 0, $t['id']); ?>><?php echo e($t['name'] . ' (' . program_name_of((int) $t['program_id']) . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-outline-primary">Save</button>
                    </form>
                </td>
                <td><?php echo $es['student_count']; ?></td>
                <td>
                    <?php if ((int) $es['reopened'] === 1): ?>
                        <span class="badge bg-warning text-dark">Correction Open</span>
                    <?php elseif ((int) $es['marks_submitted'] === 1): ?>
                        <span class="badge bg-success">Submitted</span>
                    <?php elseif ((int) $es['marks_count'] > 0): ?>
                        <span class="badge bg-warning text-dark"><?php echo $es['marks_count']; ?>/<?php echo $es['student_count']; ?> entered</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Not entered</span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="verify_marks.php?exam_id=<?php echo $exam['id']; ?>&subject=<?php echo $es['id']; ?>">Review Marks</a>
                    <form class="d-inline" method="post" onsubmit="return confirmDelete('Remove this subject from the exam? Its marks will be deleted.');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="remove_subject"><input type="hidden" name="id" value="<?php echo $es['id']; ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$examSubjects): ?><tr><td colspan="7" class="text-center py-4 text-muted">No subjects in this exam yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>