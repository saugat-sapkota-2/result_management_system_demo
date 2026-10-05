<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/result_engine.php';
$user = require_role(ROLE_TEACHER);
$title = 'Teacher Dashboard';
$active = 'dashboard';

$teacherId = (int) ($_SESSION['teacher_id'] ?? 0);
if ($teacherId <= 0) {
    $teacherId = (int) db_val('SELECT teacher_id FROM users WHERE id = ?', [$user['id']]);
}
$teacher = db_one('SELECT * FROM teachers WHERE id = ?', [$teacherId]);
if (!$teacher) { flash('danger', 'Teacher profile not found.'); redirect(url('auth/logout.php')); }

// Optional semester filter: only sheets of the requested semester.
$semesterFilter = (int) ($_GET['semester_id'] ?? 0);

// Semesters the teacher has sheets in (for the filter dropdown).
$semesterOptions = db_all(
    "SELECT DISTINCT sm.id AS semester_id, sm.semester_no, sm.name AS semester_name
     FROM exam_subjects es
     JOIN exams e ON e.id = es.exam_id AND e.status = 1
     JOIN semesters sm ON sm.id = e.semester_id
     WHERE es.teacher_id = ? AND es.status = 1
     ORDER BY sm.semester_no", [$teacherId]);

$myExams = db_all(
    "SELECT es.id es_id, es.subject_id, es.full_marks, es.marks_submitted,
            es.exam_id, e.name exam_name, e.exam_status, et.name type_name, et.code type_code,
            es.reopened,
            su.code, su.name subject_name, sm.id semester_id, sm.semester_no, sm.name semester_name,
            (SELECT COUNT(*) FROM student_subjects ss WHERE ss.subject_id = es.subject_id AND ss.status=1) student_count,
            (SELECT COUNT(*) FROM marks m WHERE m.exam_subject_id = es.id) entered_count,
            (SELECT COUNT(*) FROM marks m WHERE m.exam_subject_id = es.id AND m.submitted=1) submitted_count
     FROM exam_subjects es
     JOIN exams e ON e.id = es.exam_id AND e.status = 1
     LEFT JOIN exam_types et ON et.id = e.exam_type_id
     JOIN subjects su ON su.id = es.subject_id
     JOIN semesters sm ON sm.id = e.semester_id
     WHERE es.teacher_id = ? AND es.status = 1
       AND (? = 0 OR sm.id = ?)
     ORDER BY sm.semester_no, e.id DESC, su.code", [$teacherId, $semesterFilter, $semesterFilter]);

// Group the sheets by semester for a neat layout.
$groups = [];
foreach ($myExams as $x) {
    $gid = (int) $x['semester_id'];
    if (!isset($groups[$gid])) {
        $groups[$gid] = ['semester_id' => $gid, 'semester_no' => $x['semester_no'], 'semester_name' => $x['semester_name'], 'items' => []];
    }
    $groups[$gid]['items'][] = $x;
}

$pendingSheets = 0; $submittedSheets = 0;
foreach ($myExams as $x) {
    if ((int) $x['marks_submitted'] === 1 && (int) $x['reopened'] === 0) $submittedSheets++;
    elseif ((int) $x['entered_count'] < (int) $x['student_count'] || (int) $x['reopened'] === 1) $pendingSheets++;
}

$inProgress = count(array_filter($myExams, fn($x) => (int) $x['marks_submitted'] === 0 && (int) $x['entered_count'] > 0 && (int) $x['reopened'] === 0));

include __DIR__ . '/../includes/header.php';
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body"><div class="stat-title">My Sheets</div><div class="stat-value"><?php echo count($myExams); ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#16a34a"><div class="card-body"><div class="stat-title">Submitted</div><div class="stat-value text-success"><?php echo $submittedSheets; ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#d97706"><div class="card-body"><div class="stat-title">In Progress</div><div class="stat-value text-warning"><?php echo $inProgress; ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#2563eb"><div class="card-body"><div class="stat-title">Pending</div><div class="stat-value text-primary"><?php echo $pendingSheets; ?></div></div></div></div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h6 class="mb-3">Quick Actions</h6>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-rms" href="mark_entry.php"><i class="bi bi-pencil-square me-1"></i> Enter Marks</a>
            <a class="btn btn-outline-primary" href="submitted_marks.php"><i class="bi bi-clipboard-check me-1"></i> Submitted Marks</a>
            <a class="btn btn-outline-primary" href="students.php"><i class="bi bi-people me-1"></i> My Students</a>
            <a class="btn btn-outline-primary" href="result_preview.php"><i class="bi bi-eye me-1"></i> Result Preview</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-journal-text me-1"></i> My Marks Sheets</span>
        <form method="get" class="d-flex align-items-center gap-2">
            <label class="small text-muted" for="semesterFilter">Semester</label>
            <select class="form-select form-select-sm w-auto" name="semester_id" id="semesterFilter" onchange="this.form.submit()">
                <option value="0">All semesters</option>
                <?php foreach ($semesterOptions as $so): ?>
                    <option value="<?php echo $so['semester_id']; ?>" <?php echo selected($semesterFilter, $so['semester_id']); ?>>
                        <?php echo e('Sem ' . $so['semester_no'] . ' (' . $so['semester_name'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="card-body p-0">
        <?php if (!$groups): ?>
            <div class="text-center py-4 text-muted">No exam subject sheets assigned to you yet.</div>
        <?php else: foreach ($groups as $g): ?>
            <div class="d-flex align-items-center gap-2 px-3 py-2 bg-light border-top border-bottom" style="background:#f8f9fa">
                <span class="badge text-bg-secondary">Sem <?php echo $g['semester_no']; ?></span>
                <span class="fw-semibold small"><?php echo e($g['semester_name']); ?></span>
                <span class="small text-muted">(<?php echo count($g['items']); ?> sheet<?php echo count($g['items']) === 1 ? '' : 's'; ?>)</span>
            </div>
            <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead><tr><th>Exam</th><th>Type</th><th>Subject</th><th class="text-end">Full</th><th class="text-center">Students</th><th class="text-center">Entered</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($g['items'] as $x): ?>
                    <tr>
                        <td><?php echo e($x['exam_name']); ?></td>
                        <td><span class="badge text-bg-light border"><?php echo e($x['type_name'] ?? '—'); ?></span></td>
                        <td><span class="badge text-bg-dark"><?php echo e($x['code']); ?></span> <?php echo e($x['subject_name']); ?></td>
                        <td class="text-end"><?php echo e($x['full_marks']); ?></td>
                        <td class="text-center"><?php echo (int) $x['student_count']; ?></td>
                        <td class="text-center">
                            <?php echo (int) $x['entered_count']; ?>/<?php echo (int) $x['student_count']; ?>
                            <?php if ((int) $x['reopened'] === 1): ?>
                                <span class="badge bg-warning text-dark ms-1">Correction Open</span>
                            <?php elseif ((int) $x['marks_submitted'] === 1): ?>
                                <span class="badge bg-success ms-1">Done</span>
                            <?php elseif ((int) $x['entered_count'] > 0): ?>
                                <span class="badge bg-warning text-dark ms-1">In progress</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <?php if ((int) $x['reopened'] === 1 || ($x['exam_status'] === 'marks_entry' && (int) $x['marks_submitted'] === 0)): ?>
                                <a class="btn btn-sm <?php echo (int) $x['reopened'] === 1 ? 'btn-outline-warning' : 'btn-rms'; ?>" href="mark_entry.php?exam_id=<?php echo $x['exam_id']; ?>&es_id=<?php echo $x['es_id']; ?>"><i class="bi bi-pencil-square me-1"></i><?php echo (int) $x['reopened'] === 1 ? 'Correction' : 'Enter'; ?></a>
                            <?php else: ?>
                                <a class="btn btn-sm btn-outline-secondary" href="result_preview.php?exam_id=<?php echo $x['exam_id']; ?>&es_id=<?php echo $x['es_id']; ?>"><i class="bi bi-eye me-1"></i>View</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>