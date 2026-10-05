<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_SUPERADMIN);
require_module('semester_enrollment');
require_module('student_subjects');
$title = 'Semester Subject Enrollment';
$active = 'student_subjects';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'sync') {
        $programId = (int) ($_POST['program_id'] ?? 0);
        $batchId = (int) ($_POST['batch_id'] ?? 0);
        $semesterId = (int) ($_POST['semester_id'] ?? 0);
        if (!db_val('SELECT COUNT(*) FROM semesters WHERE id = ? AND program_id = ?', [$semesterId, $programId])) {
            flash('danger', 'The selected semester does not belong to the selected program.');
        } elseif (!db_val('SELECT COUNT(*) FROM batches WHERE id = ? AND program_id = ?', [$batchId, $programId])) {
            flash('danger', 'The selected batch does not belong to the selected program.');
        } else {
            $r = sync_semester_enrollment($programId, $batchId, $semesterId);
            if ($r['subjects'] === 0) {
                flash('warning', 'No ACTIVE subjects are configured for ' . program_name_of($programId) . ' ' . db_val('SELECT name FROM semesters WHERE id=?', [$semesterId]) . '. Add subjects first from the Subjects page.');
            } elseif ($r['students'] === 0) {
                flash('warning', 'No students found in this program + batch.');
            } else {
                audit('Bulk semester enrolment', 'Synced ' . $r['students'] . ' student(s) x ' . $r['subjects'] . ' subject(s): ' . $r['new'] . ' new, ' . $r['duplicates'] . ' already enrolled (' . program_name_of($programId) . ').');
                flash('success', "Sync complete — {$r['students']} student(s) x {$r['subjects']} subject(s): <b>{$r['new']}</b> new enrolment(s), <b>{$r['duplicates']}</b> already enrolled, <b>{$r['errors']}</b> error(s). Re-run the sync anytime; it never creates duplicates.");
            }
        }
        redirect(url('admin/student_subjects.php' . '?program_id=' . (int) ($_POST['program_id'] ?? 0) . '&batch_id=' . (int) ($_POST['batch_id'] ?? 0) . '&semester_id=' . (int) ($_POST['semester_id'] ?? 0)));
    } elseif ($action === 'autofill') {
        $studentId = (int) ($_POST['student_id'] ?? 0);
        $r = $studentId > 0 ? auto_enroll_student($studentId) : null;
        if ($r === null) {
            flash('danger', 'Select a student first.');
        } elseif (!$r['configured']) {
            flash('warning', 'No ACTIVE subjects configured for this student\'s program + semester, or their program/semester is not set.');
        } else {
            audit('Auto-enrol student', 'Auto-enrolled student id ' . $studentId . ' for current semester: ' . $r['new'] . ' new, ' . $r['duplicates'] . ' already enrolled.', $studentId);
            flash('success', "Auto-enrolled student: <b>{$r['new']}</b> new, <b>{$r['duplicates']}</b> already enrolled.");
        }
        redirect(url('admin/student_subjects.php?student_id=' . (int) ($_POST['student_id'] ?? 0)));
    } elseif ($action === 'remove') {
        db_run('DELETE FROM student_subjects WHERE id=?', [(int) $_POST['id']]);
        flash('success', 'Enrolment removed.');
        redirect(url('admin/student_subjects.php?student_id=' . (int) ($_POST['student_id'] ?? 0)));
    } elseif ($action === 'save') {
        $studentId = (int) $_POST['student_id'];
        $subjectId = (int) ($_POST['subject_id'] ?? 0);
        $student = $studentId > 0 ? db_one('SELECT program_id FROM students WHERE id=?', [$studentId]) : null;
        $subject = $subjectId > 0 ? db_one('SELECT id, program_id, semester_id FROM subjects WHERE id=? AND status=1', [$subjectId]) : null;
        if (!$student || !$subject) {
            flash('danger', 'Select a student and a subject.');
        } elseif ((int) $subject['program_id'] !== (int) $student['program_id']) {
            flash('danger', 'That subject belongs to another program — cross-program enrolment is not allowed.');
        } else {
            $ins = db_run('INSERT IGNORE INTO student_subjects (student_id, subject_id) VALUES (?,?)', [$studentId, $subjectId]);
            audit('Enrol subject', 'Enrolled subject id ' . $subjectId . ' to student id ' . $studentId, $studentId);
            flash($ins > 0 ? 'success' : 'info', $ins > 0 ? 'Subject enrolled.' : 'Student already enrolled in that subject.');
        }
        redirect(url('admin/student_subjects.php?student_id=' . $studentId));
    }
}

$programs = db_all('SELECT * FROM programs WHERE status=1 ORDER BY name');
$batches = db_all('SELECT * FROM batches WHERE status=1 ORDER BY name');
$sems = db_all('SELECT * FROM semesters WHERE status=1 ORDER BY program_id, semester_no');

$pId = (int) ($_GET['program_id'] ?? 0);
$bId = (int) ($_GET['batch_id'] ?? 0);
$sId = (int) ($_GET['semester_id'] ?? 0);
if ($pId > 0) {
    $batchValid = db_val('SELECT COUNT(*) FROM batches WHERE id=? AND program_id=?', [$bId, $pId]) ? true : false;
    $semValid = db_val('SELECT COUNT(*) FROM semesters WHERE id=? AND program_id=?', [$sId, $pId]) ? true : false;
    if (!$batchValid) $bId = 0;
    if (!$semValid) $sId = 0;
}

$configuredSubjects = [];
$eligibleStudents = 0;
if ($pId > 0 && $sId > 0) {
    $configuredSubjects = db_all(
        'SELECT su.*, sm.name AS semester_name, sm.semester_no FROM subjects su JOIN semesters sm ON sm.id = su.semester_id
         WHERE su.status=1 AND su.program_id=? AND su.semester_id=? ORDER BY su.code', [$pId, $sId]);
}
if ($pId > 0 && $bId > 0) {
    $eligibleStudents = (int) db_val('SELECT COUNT(*) FROM students WHERE status=1 AND program_id=? AND batch_id=?', [$pId, $bId]);
}

$exceptionId = (int) ($_GET['student_id'] ?? 0);
$exceptionStudent = $exceptionId > 0 ? db_one('SELECT * FROM students WHERE id=?', [$exceptionId]) : null;
$exceptionEnrolled = $exceptionStudent
    ? db_all('SELECT ss.id AS ssid, su.*, sm.name AS semester_name FROM student_subjects ss
              JOIN subjects su ON su.id = ss.subject_id JOIN semesters sm ON sm.id = su.semester_id
              WHERE ss.student_id = ? ORDER BY sm.semester_no, su.code', [$exceptionId])
    : [];
$addSubjects = $exceptionStudent
    ? db_all('SELECT su.*, sm.name AS semester_name FROM subjects su JOIN semesters sm ON sm.id = su.semester_id
              WHERE su.status=1 AND su.program_id=? AND su.semester_id = ?
              AND su.id NOT IN (SELECT subject_id FROM student_subjects WHERE student_id=?)
              ORDER BY su.code', [(int) $exceptionStudent['program_id'], (int) $exceptionStudent['current_semester_id'], $exceptionId])
    : [];

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-ui-checks-grid me-2"></i>Semester Subject Enrollment</h4>
</div>

<div class="alert alert-light border small mb-3">
    Subjects are configured per <b>Program + Semester</b>. Running sync below assigns every configured subject to
    every student in the chosen <b>Program + Batch</b>. Re-running is safe — the unique
    <code>student + subject</code> rule prevents duplicates, and the program filter guarantees BCA students only ever
    receive BCA subjects and BIM students only BIM subjects. New students are auto-enrolled on creation, and whenever
    a student's semester changes.
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small mb-1">Program</label>
                <select class="form-select program-select" name="program_id" data-target="enroll">
                    <option value="">— Select program —</option>
                    <?php foreach ($programs as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo selected($pId, $p['id']); ?>><?php echo e($p['name']); ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-3"><label class="form-label small mb-1">Batch</label>
                <select class="form-select" name="batch_id" data-scope="enroll">
                    <option value="">— Select batch —</option>
                    <?php foreach ($batches as $b): ?><option value="<?php echo $b['id']; ?>" data-program="<?php echo $b['program_id']; ?>" <?php echo selected($bId, $b['id']); ?>><?php echo e($b['name']); ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-3"><label class="form-label small mb-1">Semester</label>
                <select class="form-select" name="semester_id" data-scope="enroll">
                    <option value="">— Select semester —</option>
                    <?php foreach ($sems as $s): ?><option value="<?php echo $s['id']; ?>" data-program="<?php echo $s['program_id']; ?>" <?php echo selected($sId, $s['id']); ?>><?php echo e('Sem ' . $s['semester_no'] . ' — ' . $s['name']); ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-3"><button class="btn btn-outline-rms w-100"><i class="bi bi-search"></i> Preview</button></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card text-center"><div class="card-body py-3">
        <div class="display-6"><?php echo $eligibleStudents; ?></div><div class="text-muted small">Eligible students (program + batch)</div>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body py-3">
        <div class="display-6"><?php echo count($configuredSubjects); ?></div><div class="text-muted small">Configured subjects (program + semester)</div>
    </div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-body py-3">
        <div class="text-muted small mb-1">Expected new enrolments on sync</div>
        <div class="fw-semibold"><?php echo count($configuredSubjects) * $eligibleStudents; ?> <span class="text-muted fw-normal">total</span> (duplicates are ignored on re-run)</div>
    </div></div></div>
</div>

<?php if ($pId > 0 && $sId > 0): ?>
<div class="card mb-3">
    <div class="card-body">
        <h6 class="mb-2">Subjects configured for <?php echo e(program_name_of($pId) . ' — ' . (db_val('SELECT name FROM semesters WHERE id=?', [$sId]) ?? '')); ?></h6>
        <?php if ($configuredSubjects): ?>
            <div class="row row-cols-1 row-cols-md-3 g-2">
                <?php foreach ($configuredSubjects as $s): ?>
                    <div class="col"><span class="badge text-bg-dark me-1"><?php echo e($s['code']); ?></span><?php echo e($s['name']); ?> <span class="text-muted small">(<?php echo e($s['credit_hours']); ?> cr)</span></div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-warning mb-0 py-2">No ACTIVE subjects configured for this program + semester yet. Use the Subjects page to add them, then sync.</div>
        <?php endif; ?>
        <form method="post" class="mt-3">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="sync">
            <input type="hidden" name="program_id" value="<?php echo $pId; ?>">
            <input type="hidden" name="batch_id" value="<?php echo $bId; ?>">
            <input type="hidden" name="semester_id" value="<?php echo $sId; ?>">
            <button class="btn btn-rms" <?php echo ($configuredSubjects && $eligibleStudents) ? '' : 'disabled'; ?>><i class="bi bi-arrow-repeat me-1"></i> Sync / Assign Subjects</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">Individual exceptions (short adds / removals)</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <form method="get" class="row g-2 align-items-center">
                    <input type="hidden" name="student_id" value="0">
                    <div class="col-9">
                        <select class="form-select" name="student_id" onchange="this.form.submit()">
                            <option value="">— Select student —</option>
                            <?php $students = db_all('SELECT st.*, p.name AS program_name FROM students st LEFT JOIN programs p ON p.id = st.program_id WHERE st.status=1 ORDER BY st.student_code'); ?>
                            <?php foreach ($students as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo selected($exceptionId, $s['id']); ?>><?php echo e($s['student_code'] . ' — ' . $s['full_name'] . ' (' . ($s['program_name'] ?? 'no program') . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-3"><button class="btn btn-outline-rms w-100"><i class="bi bi-search"></i></button></div>
                    <div class="col-12 small text-muted">Also auto-enrols this student's full current semester with one click.</div>
                </form>
            </div>
            <div class="col-md-8">
                <?php if ($exceptionStudent): ?>
                    <div class="row g-2">
                        <div class="col">
                            <form method="post" class="d-flex gap-2">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="autofill">
                                <input type="hidden" name="student_id" value="<?php echo $exceptionId; ?>">
                                <button class="btn btn-outline-rms"><i class="bi bi-magic me-1"></i> Auto-enrol current semester</button>
                            </form>
                        </div>
                        <div class="col">
                            <form method="post" class="d-flex gap-2">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="student_id" value="<?php echo $exceptionId; ?>">
                                <select class="form-select" name="subject_id" required>
                                    <option value="">Add a single subject…</option>
                                    <?php foreach ($addSubjects as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo e($s['code'] . ' — ' . $s['name'] . ' (' . $s['semester_name'] . ')'); ?></option><?php endforeach; ?>
                                </select>
                                <button class="btn btn-rms text-nowrap"><i class="bi bi-plus-lg"></i> Add</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($exceptionEnrolled): ?>
                    <div class="table-responsive mt-2">
                        <table class="table table-hover table-sm mb-0">
                            <thead><tr><th>Code</th><th>Subject</th><th>Semester</th><th>Credits</th><th class="text-end">Remove</th></tr></thead>
                            <tbody>
                            <?php foreach ($exceptionEnrolled as $s): ?>
                                <tr>
                                    <td><span class="badge text-bg-dark"><?php echo e($s['code']); ?></span></td>
                                    <td class="fw-semibold"><?php echo e($s['name']); ?></td>
                                    <td><?php echo e($s['semester_name']); ?></td>
                                    <td><?php echo e($s['credit_hours']); ?></td>
                                    <td class="text-end">
                                        <form class="d-inline" method="post" onsubmit="return confirmDelete('Remove this subject from the student?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?php echo $s['ssid']; ?>">
                                            <input type="hidden" name="student_id" value="<?php echo $exceptionId; ?>">
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($exceptionStudent): ?>
                    <div class="alert alert-light border small mb-0 mt-2">No subjects enrolled for this student yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php echo program_scope_script(); ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>