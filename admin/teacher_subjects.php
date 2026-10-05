<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_SUPERADMIN);
require_module('subject_assignments');
$title = 'Subject Assignments';
$active = 'teacher_subjects';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $teacherId = (int) $_POST['teacher_id'];
        $ay = (int) ($_POST['academic_year_id'] ?? 0) ?: null;
        $subjectIds = array_values(array_unique(array_filter(array_map('intval', $_POST['subject_ids'] ?? []), fn($v) => $v > 0)));
        if ($teacherId <= 0 || !db_val('SELECT COUNT(*) FROM teachers WHERE id = ? AND status=1', [$teacherId])) {
            flash('danger', 'Select a valid teacher.');
        } else {
            // Replace the teacher's assignments inside the chosen academic-year scope.
            $removed = 0;
            $removedSubjectIds = [];
            if ($ay) {
                $scope = 'academic_year_id = ?';
                $scopeParams = [$teacherId, $ay];
            } else {
                $scope = 'academic_year_id IS NULL';
                $scopeParams = [$teacherId];
            }
            // Capture the ids being removed so we can release their exam sheets below.
            $existing = $subjectIds
                ? db_all('SELECT subject_id FROM teacher_subjects WHERE teacher_id = ? AND ' . $scope . ' AND subject_id NOT IN (' . implode(',', array_fill(0, count($subjectIds), '?')) . ')',
                    array_merge($scopeParams, $subjectIds))
                : db_all('SELECT subject_id FROM teacher_subjects WHERE teacher_id = ? AND ' . $scope, $scopeParams);
            foreach ($existing as $row) $removedSubjectIds[] = (int) $row['subject_id'];

            if ($subjectIds) {
                $in = implode(',', array_fill(0, count($subjectIds), '?'));
                $removed = db_run('DELETE FROM teacher_subjects WHERE teacher_id = ? AND ' . $scope . ' AND subject_id NOT IN (' . $in . ')',
                    array_merge([$teacherId, ...($ay ? [$ay] : [])], $subjectIds));
            } else {
                $removed = db_run('DELETE FROM teacher_subjects WHERE teacher_id = ? AND ' . $scope, $scopeParams);
            }
            $added = 0;
            foreach ($subjectIds as $sid) {
                try {
                    // Duplicate-proof insert: skip if the same (teacher, subject, year-scope) already exists.
                    $shot = db_run(
                        'INSERT INTO teacher_subjects (teacher_id, subject_id, academic_year_id)
                         SELECT ?, ?, ? WHERE NOT EXISTS (
                             SELECT 1 FROM teacher_subjects
                              WHERE teacher_id = ? AND subject_id = ? AND COALESCE(academic_year_id, 0) = COALESCE(?, 0)
                         )',
                        [$teacherId, $sid, $ay, $teacherId, $sid, $ay]
                    );
                    $added += $shot;
                } catch (PDOException $ex) {}
            }
            // Keep exam sheets in sync: sheets with no teacher get this teacher; unassigned
            // subjects that still belong to this teacher (and are not submitted) are released.
            if ($subjectIds) {
                $in = implode(',', array_fill(0, count($subjectIds), '?'));
                db_run(
                    'UPDATE exam_subjects es
                        JOIN exams e ON e.id = es.exam_id AND e.status = 1
                       SET es.teacher_id = ?
                     WHERE es.status = 1 AND es.teacher_id IS NULL AND es.subject_id IN (' . $in . ')',
                    array_merge([$teacherId], $subjectIds)
                );
            }
            if ($removedSubjectIds) {
                $in = implode(',', array_fill(0, count($removedSubjectIds), '?'));
                db_run(
                    'UPDATE exam_subjects es
                        JOIN exams e ON e.id = es.exam_id AND e.status = 1
                       SET es.teacher_id = NULL
                     WHERE es.status = 1 AND es.teacher_id = ? AND es.marks_submitted = 0 AND es.subject_id IN (' . $in . ')',
                    array_merge([$teacherId], $removedSubjectIds)
                );
            }
            audit('Assign subjects to teacher', 'Teacher ' . $teacherId . ': +' . $added . ' assigned, ' . $removed . ' removed (' . ($ay ? 'AY ' . $ay : 'no year') . ')', $teacherId);
            flash($added || $removed ? 'success' : 'info',
                ($added ? "$added subject(s) assigned." : '') . ($removed ? " $removed unassigned." : '') . (!$added && !$removed ? ' No changes made.' : ''));
        }
        redirect(url('admin/teacher_subjects.php?teacher_id=' . (int) ($_POST['teacher_id'] ?? 0) . '&program_id=' . (int) ($_POST['program_id'] ?? 0)));
    } elseif ($action === 'delete') {
        db_run('DELETE FROM teacher_subjects WHERE id=?', [(int) $_POST['id']]);
        audit('Remove subject assignment', 'Removed assignment id ' . (int) $_POST['id']);
        flash('success', 'Assignment removed.');
        redirect(url('admin/teacher_subjects.php?teacher_id=' . (int) ($_POST['teacher_id'] ?? 0) . '&program_id=' . (int) ($_POST['program_id'] ?? 0)));
    } else {
        redirect(url('admin/teacher_subjects.php'));
    }
}

$years = db_all('SELECT * FROM academic_years ORDER BY start_date DESC');
$teachers = db_all(
    'SELECT t.id, t.name, t.teacher_code, t.program_id, p.name AS program_name,
            (SELECT COUNT(*) FROM teacher_subjects ts JOIN subjects su ON su.id = ts.subject_id WHERE ts.teacher_id = t.id AND su.status=1) AS assigned
     FROM teachers t LEFT JOIN programs p ON p.id = t.program_id
     WHERE t.status = 1 ORDER BY t.name');
$teacherCount = count($teachers);
$subjectCount = (int) db_val('SELECT COUNT(*) FROM subjects WHERE status=1');

$filterTeacher = (int) ($_GET['teacher_id'] ?? 0);
if ($filterTeacher <= 0 && $teachers) $filterTeacher = (int) $teachers[0]['id'];

$teacher = $filterTeacher ? db_one('SELECT * FROM teachers WHERE id=?', [$filterTeacher]) : null;
if ($teacher) {
    $teacherIndicators = db_one(
        'SELECT (SELECT COUNT(*) FROM teacher_subjects ts JOIN subjects su ON su.id = ts.subject_id WHERE ts.teacher_id=? AND su.status=1) AS assigned,
                (SELECT COUNT(DISTINCT su.program_id) FROM teacher_subjects ts JOIN subjects su ON su.id = ts.subject_id WHERE ts.teacher_id=?) AS programs,
                (SELECT COUNT(DISTINCT su.semester_id) FROM teacher_subjects ts JOIN subjects su ON su.id = ts.subject_id WHERE ts.teacher_id=?) AS semesters',
        [$filterTeacher, $filterTeacher, $filterTeacher]);
}

// Academic-year scope being edited.
$scopeAy = (int) ($_GET['academic_year_id'] ?? 0) ?: null;

// Subject ids already assigned to this teacher within the current scope.
$scopeAssignedIds = [];
if ($teacher) {
    if ($scopeAy) {
        $scopeAssignedIds = array_column(db_all('SELECT subject_id FROM teacher_subjects WHERE teacher_id=? AND academic_year_id=?', [$filterTeacher, $scopeAy]), 'subject_id');
    } else {
        $scopeAssignedIds = array_column(db_all('SELECT subject_id FROM teacher_subjects WHERE teacher_id=? AND academic_year_id IS NULL', [$filterTeacher]), 'subject_id');
    }
}

// Full picture of what the teacher teaches (all years, for the assigned panel).
$assignments = $filterTeacher
    ? db_all('SELECT ts.*, su.code, su.name AS subject_name, su.credit_hours, sm.name AS semester_name, sm.semester_no, p.name AS program_name, a.name AS ac_year_name
              FROM teacher_subjects ts JOIN subjects su ON su.id = ts.subject_id AND su.status=1
              JOIN semesters sm ON sm.id = su.semester_id
              JOIN programs p ON p.id = su.program_id
              LEFT JOIN academic_years a ON a.id = ts.academic_year_id
              WHERE ts.teacher_id = ? ORDER BY p.name, sm.semester_no, su.code', [$filterTeacher])
    : [];

// Subject picker: grouped by program then semester, plus optional program filter + live search.
$programFilter = (int) ($_GET['program_id'] ?? 0);
$savedSort = '';
$subjectGroups = [];
$subjects = db_all(
    'SELECT su.*, p.name AS program_name, sm.name AS semester_name, sm.semester_no, sm.id AS semester_id
     FROM subjects su JOIN programs p ON p.id = su.program_id JOIN semesters sm ON sm.id = su.semester_id
     WHERE su.status = 1 AND (? = 0 OR su.program_id = ?)
     ORDER BY p.name, sm.semester_no, su.code', [$programFilter, $programFilter]);
foreach ($subjects as $s) {
    $key = $s['program_name'] . '|' . $s['semester_name'];
    $subjectGroups[$key]['program_id'] = (int) $s['program_id'];
    $subjectGroups[$key]['program_name'] = $s['program_name'];
    $subjectGroups[$key]['semester_name'] = $s['semester_name'];
    $subjectGroups[$key]['semester_no'] = (int) $s['semester_no'];
    $subjectGroups[$key]['items'][] = $s;
}

$programsNav = db_all('SELECT DISTINCT su.program_id, p.name FROM subjects su JOIN programs p ON p.id = su.program_id WHERE su.status=1 ORDER BY p.name');

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-person-check me-2"></i>Teacher ⇄ Subject Assignments</h4>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="teachers.php"><i class="bi bi-person-video3 me-1"></i> Manage Teachers</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card text-center"><div class="card-body py-3">
        <div class="display-6"><?php echo $teacherCount; ?></div><div class="text-muted small">Active teachers</div>
    </div></div></div>
    <div class="col-md-4"><div class="card text-center"><div class="card-body py-3">
        <div class="display-6"><?php echo $subjectCount; ?></div><div class="text-muted small">Total subjects</div>
    </div></div></div>
    <div class="col-md-4"><div class="card text-center"><div class="card-body py-3">
        <div class="display-6"><?php echo $teacher ? (int) $teacherIndicators['assigned'] : 0; ?></div><div class="text-muted small">Assigned to selected teacher</div>
    </div></div></div>
</div>

<div class="card mb-3">
    <div class="card-header">1. Select a teacher</div>
    <div class="card-body">
        <?php if (!$teachers): ?>
            <div class="alert alert-warning mb-0">No active teachers found. <a href="teachers.php">Create a teacher</a> first.</div>
        <?php else: ?>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($teachers as $t): ?>
                    <?php $activeT = (int) $t['id'] === (int) ($teacher['id'] ?? 0); ?>
                    <a class="btn <?php echo $activeT ? 'btn-rms' : 'btn-outline-rms'; ?> d-inline-flex align-items-center gap-2 px-3 py-2" href="?teacher_id=<?php echo $t['id']; ?>&academic_year_id=<?php echo (int) ($scopeAy ?? 0); ?>&program_id=<?php echo $programFilter; ?>">
                        <i class="bi bi-person"></i>
                        <span>
                            <?php echo e($t['name']); ?>
                            <div class="small opacity-75"><?php echo e($t['teacher_code'] . ' · ' . ($t['program_name'] ?? 'No program')); ?></div>
                        </span>
                        <span class="badge <?php echo $activeT ? 'text-bg-light' : 'bg-secondary'; ?>"><?php echo (int) $t['assigned']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($teacher): ?>
<form method="get" class="card mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small mb-1">Academic Year</label>
                <select class="form-select" name="academic_year_id" onchange="this.form.submit()">
                    <option value="0">— Current / no year —</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?php echo $y['id']; ?>" <?php echo selected($scopeAy ?? 0, $y['id']); ?>><?php echo e($y['name']); ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="col-md-3"><label class="form-label small mb-1">Program</label>
                <select class="form-select" name="program_id" onchange="this.form.submit()">
                    <option value="0">All programs</option>
                    <?php foreach ($programsNav as $p): ?>
                        <option value="<?php echo $p['program_id']; ?>" <?php echo selected($programFilter, $p['program_id']); ?>><?php echo e($p['name']); ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="col-md-3"><label class="form-label small mb-1">Quick search</label>
                <input class="form-control" id="subjectSearch" type="search" placeholder="code or name…"></div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-outline-rms w-100"><i class="bi bi-search me-1"></i> Filter</button>
            </div>
            <input type="hidden" name="teacher_id" value="<?php echo $filterTeacher; ?>">
        </div>
    </div>
</form>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>2. Subjects to assign — <strong><?php echo e($teacher['name']); ?></strong></span>
                <span class="small text-muted" id="selCount">0 selected</span>
            </div>
            <div class="card-body" style="max-height:600px;overflow-y:auto">
                <?php if (!$subjectGroups): ?>
                    <div class="alert alert-light border mb-0">No matching subjects for this filter.</div>
                <?php else: ?>
                <form method="post" id="assignForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="teacher_id" value="<?php echo $filterTeacher; ?>">
                    <input type="hidden" name="academic_year_id" value="<?php echo (int) ($scopeAy ?? 0); ?>">
                    <input type="hidden" name="program_id" value="<?php echo $programFilter; ?>">
                    <div class="vstack gap-3">
                        <?php $gi = 0; foreach ($subjectGroups as $sg): $gi++; ?>
                            <div class="subj-group" data-search="<?php echo e(strtolower($sg['program_name'] . ' ' . $sg['semester_name'])); ?>">
                                <div class="d-flex align-items-center justify-content-between border-bottom pb-1 mb-1">
                                    <strong class="small">
                                        <i class="bi bi-bookmark me-1"></i><?php echo e($sg['program_name']); ?>
                                        — <?php echo e('Sem ' . $sg['semester_no'] . ' (' . $sg['semester_name'] . ')'); ?>
                                    </strong>
                                    <div class="form-check form-switch form-check-inline">
                                        <input class="form-check-input group-toggle" type="checkbox" role="switch" id="g-<?php echo $gi; ?>">
                                        <label class="form-check-label small text-muted" for="g-<?php echo $gi; ?>">All</label>
                                    </div>
                                </div>
                                <?php foreach ($sg['items'] as $s): ?>
                                    <?php $isAssigned = in_array((int) $s['id'], array_map('intval', $scopeAssignedIds), true); ?>
                                    <div class="form-check ps-0 mb-1 subj-row" data-search="<?php echo e(strtolower($s['code'] . ' ' . $s['name'] . ' ' . $sg['program_name'] . ' ' . $sg['semester_name'])); ?>">
                                        <label class="d-flex align-items-center gap-2 p-1 rounded border <?php echo $isAssigned ? 'border-primary bg-primary-subtle' : 'border-light-subtle'; ?>" style="cursor:pointer">
                                            <input class="form-check-input subj-cb" type="checkbox" name="subject_ids[]" value="<?php echo $s['id']; ?>" <?php echo $isAssigned ? 'checked' : ''; ?>>
                                            <span class="badge text-bg-dark"><?php echo e($s['code']); ?></span>
                                            <span class="fw-semibold flex-grow-1"><?php echo e($s['name']); ?></span>
                                            <span class="small text-muted"><?php echo e($s['credit_hours']); ?> cr</span>
                                            <?php if ($isAssigned): ?><span class="badge bg-primary">assigned</span><?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3 d-flex gap-2 align-items-center">
                        <button class="btn btn-rms"><i class="bi bi-check2-circle me-1"></i> Save Assignments</button>
                        <span class="small text-muted">Checked subjects replace the teacher's current set for the selected academic year. Unchecked ones are removed.</span>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Assigned Subjects (<?php echo count($assignments); ?>)</span>
                <span class="small text-muted"><?php echo e($teacherIndicators['programs']); ?> program(s) · <?php echo e($teacherIndicators['semesters']); ?> semester(s)</span>
            </div>
            <div class="card-body p-0" style="max-height:600px;overflow-y:auto">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Subject</th><th>Program</th><th>Semester</th><th>Year</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($assignments as $a): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?php echo e($a['subject_name']); ?></div>
                                <span class="badge text-bg-dark"><?php echo e($a['code']); ?></span>
                            </td>
                            <td class="small"><?php echo e($a['program_name']); ?></td>
                            <td class="small"><?php echo e($a['semester_no']); ?></td>
                            <td class="small"><?php echo e($a['ac_year_name'] ?? '—'); ?></td>
                            <td class="text-end">
                                <form class="d-inline" method="post" onsubmit="return confirmDelete('Remove this assignment?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                    <input type="hidden" name="teacher_id" value="<?php echo $filterTeacher; ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$assignments): ?><tr><td colspan="5" class="text-center py-4 text-muted">Nothing assigned yet. Check subjects on the left and save.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var search = document.getElementById('subjectSearch');
    function applySearch() {
        var q = (search ? search.value : '').toLowerCase().trim();
        document.querySelectorAll('.subj-group').forEach(function (g) {
            var anyVisible = false;
            g.querySelectorAll('.subj-row').forEach(function (r) {
                var hit = !q || (r.getAttribute('data-search') || '').indexOf(q) !== -1;
                r.style.display = hit ? '' : 'none';
                if (hit) anyVisible = true;
            });
            g.style.display = anyVisible ? '' : 'none';
        });
    }
    if (search) search.addEventListener('input', applySearch);

    function updateSelCount() {
        var n = document.querySelectorAll('.subj-cb:checked').length;
        var el = document.getElementById('selCount');
        if (el) el.textContent = n + ' selected';
    }
    document.querySelectorAll('.subj-cb').forEach(function (cb) { cb.addEventListener('change', updateSelCount); });
    document.querySelectorAll('.group-toggle').forEach(function (tgl) {
        tgl.addEventListener('change', function () {
            var group = this.closest('.subj-group');
            var cbs = group.querySelectorAll('.subj-row:not([style*="display: none"]) .subj-cb');
            cbs.forEach(function (cb) { cb.checked = this.checked; }, this);
            updateSelCount();
        });
    });
    updateSelCount();
})();
</script>
<?php else: ?>
    <div class="alert alert-info">Select a teacher above to manage subject assignments.</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>