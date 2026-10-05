<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_SUPERADMIN);
require_module('subjects');
$title = 'Subjects';
$active = 'subjects';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $data = [
            'program_id' => (int) ($_POST['program_id'] ?? 0),
            'semester_id' => (int) ($_POST['semester_id'] ?? 0),
            'code' => strtoupper(trim($_POST['code'])),
            'name' => trim($_POST['name']),
            'credit_hours' => (float) ($_POST['credit_hours'] ?? 3),
            'subject_type' => $_POST['subject_type'] ?? 'Theory',
            'has_theory' => isset($_POST['has_theory']) ? 1 : 0,
            'has_practical' => isset($_POST['has_practical']) ? 1 : 0,
        ];
        if ($data['program_id'] <= 0 || $data['semester_id'] <= 0 || $data['code'] === '' || $data['name'] === '') {
            flash('danger', 'Program, semester, code and name are required.');
        } elseif (!db_val('SELECT COUNT(*) FROM semesters WHERE id = ? AND program_id = ?', [$data['semester_id'], $data['program_id']])) {
            flash('danger', 'Selected semester does not belong to the program.');
        } else {
            try {
                if ($id > 0) {
                    db_run('UPDATE subjects SET program_id=?, semester_id=?, code=?, name=?, credit_hours=?, subject_type=?, has_theory=?, has_practical=? WHERE id=?',
                        [$data['program_id'], $data['semester_id'], $data['code'], $data['name'], $data['credit_hours'], $data['subject_type'], $data['has_theory'], $data['has_practical'], $id]);
                    audit('Edit subject', 'Updated subject: ' . $data['code'], $id);
                } else {
                    db_run('INSERT INTO subjects (program_id, semester_id, code, name, credit_hours, subject_type, has_theory, has_practical) VALUES (?,?,?,?,?,?,?,?)',
                        [$data['program_id'], $data['semester_id'], $data['code'], $data['name'], $data['credit_hours'], $data['subject_type'], $data['has_theory'], $data['has_practical']]);
                    audit('Add subject', 'Created subject: ' . $data['code'], last_id());
                }
                flash('success', 'Subject saved.');
            } catch (PDOException $ex) {
                flash('danger', 'Duplicate code in semester or database error: ' . $ex->getMessage());
            }
        }
    } elseif ($action === 'toggle') {
        db_run('UPDATE subjects SET status = 1 - status WHERE id=?', [(int) $_POST['id']]);
        flash('success', 'Status changed.');
    } elseif ($action === 'delete') {
        $id = (int) $_POST['id'];
        db_run('DELETE FROM subjects WHERE id=?', [$id]);
        audit('Delete subject', 'Deleted subject id ' . $id, $id);
        flash('success', 'Subject deleted.');
    }
    redirect(url('admin/subjects.php'));
}

$edit = !empty($_GET['edit']) ? db_one('SELECT * FROM subjects WHERE id=?', [(int) $_GET['edit']]) : null;
$semFilter = (int) ($_GET['semester_id'] ?? 0);
$programFilter = (int) ($_GET['program_id'] ?? 0);
$q = trim($_GET['q'] ?? '');
$baseRows = db_all(
    "SELECT su.*, p.name AS program_name, sm.name AS semester_name
     FROM subjects su JOIN programs p ON p.id = su.program_id JOIN semesters sm ON sm.id = su.semester_id
     WHERE (? = 0 OR su.semester_id = ?) AND (? = 0 OR su.program_id = ?)
     ORDER BY sm.semester_no, su.code", [$semFilter, $semFilter, $programFilter, $programFilter]);
$rows = $baseRows;
if ($q !== '') {
    $like = "%$q%";
    $rows = array_filter($rows, static fn($r) =>
        stripos($r['code'], $q) !== false || stripos($r['name'], $q) !== false || stripos($r['credit_hours'], $q) !== false);
}
$programs = db_all('SELECT * FROM programs WHERE status=1 ORDER BY name');
$sems = db_all('SELECT * FROM semesters WHERE status=1 ORDER BY program_id, semester_no');

$totalSubjects = (int) db_val('SELECT COUNT(*) FROM subjects');
$theoryCount = (int) db_val("SELECT COUNT(*) FROM subjects WHERE subject_type='Theory'");
$practicalCount = (int) db_val("SELECT COUNT(*) FROM subjects WHERE subject_type != 'Theory'");

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-book me-2"></i>Subjects <span class="text-muted fs-6">(<?php echo count($baseRows); ?>)</span></h4>
    <button class="btn btn-rms" data-bs-toggle="modal" data-bs-target="#subjectModal"><i class="bi bi-plus-lg me-1"></i> Add Subject</button>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body">
        <div class="stat-title">Total Subjects</div><div class="stat-value"><?php echo $totalSubjects; ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#2563eb"><div class="card-body">
        <div class="stat-title">Theory Subjects</div><div class="stat-value"><?php echo $theoryCount; ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#16a34a"><div class="card-body">
        <div class="stat-title">Theory + Practical</div><div class="stat-value"><?php echo $practicalCount; ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#d97706"><div class="card-body">
        <div class="stat-title">In Current View</div><div class="stat-value"><?php echo count($rows); ?></div></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2" method="get" id="subjectFilterForm">
            <div class="col-md-4">
                <input type="text" class="form-control" name="q" id="subjectSearchBox" value="<?php echo e($q); ?>" placeholder="Live search code or name..." autocomplete="off">
            </div>
            <div class="col-md-2">
                <select class="form-select" name="program_id" onchange="this.form.submit()">
                    <option value="0">All programs</option>
                    <?php foreach ($programs as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo selected($programFilter, $p['id']); ?>><?php echo e($p['name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="semester_id" onchange="this.form.submit()">
                    <option value="0">All semesters</option>
                    <?php foreach ($sems as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo selected($semFilter, $s['id']); ?>><?php echo e(program_name_of((int) $s['program_id']) . ' — ' . $s['name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-outline-secondary flex-fill"><i class="bi bi-search"></i> Search</button>
                <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/subjects.php')); ?>">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Code</th><th>Subject</th><th>Program</th><th>Semester</th><th>Credits</th><th>Type</th><th>Theory</th><th>Practical</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><span class="badge text-bg-dark"><?php echo e($r['code']); ?></span></td>
                    <td class="fw-semibold"><?php echo e($r['name']); ?></td>
                    <td><span class="badge text-bg-light border"><?php echo e($r['program_name']); ?></span></td>
                    <td><?php echo e($r['semester_name']); ?></td>
                    <td><span class="badge text-bg-secondary-subtle text-secondary-emphasis border"><?php echo e($r['credit_hours']); ?> cr</span></td>
                    <td>
                        <?php if ($r['subject_type'] === 'Theory'): ?><span class="badge bg-primary">Theory</span>
                        <?php elseif ($r['subject_type'] === 'Practical'): ?><span class="badge bg-info text-dark">Practical</span>
                        <?php else: ?><span class="badge bg-success">Theory + Practical</span><?php endif; ?>
                    </td>
                    <td><?php echo $r['has_theory'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>'; ?></td>
                    <td><?php echo $r['has_practical'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>'; ?></td>
                    <td><?php echo bool_badge($r['status']); ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo $r['id']; ?>#modal" title="Edit"><i class="bi bi-pencil"></i></a>
                        <form class="d-inline" method="post" onsubmit="return confirmDelete();">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <button class="btn btn-sm btn-outline-secondary" title="Toggle status"><i class="bi bi-power"></i></button>
                        </form>
                        <form class="d-inline" method="post" onsubmit="return confirmDelete('Delete this subject permanently? Marks/results referencing it will be removed.');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="10" class="text-center py-4 text-muted">No subjects found<?php echo $q !== '' ? ' for "' . e($q) . '"' : ''; ?>.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="subjectModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post">
            <?php echo csrf_field(); ?>
            <div class="modal-header"><h5 class="modal-title"><?php echo $edit ? 'Edit Subject' : 'Add Subject'; ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int) ($edit['id'] ?? 0); ?>">
                <div class="row g-3">
                    <div class="col-6"><label class="form-label">Program *</label>
                        <select class="form-select program-select" name="program_id" required data-target="subjects">
                            <option value="">—</option>
                            <?php foreach ($programs as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo selected($edit['program_id'] ?? '', $p['id']); ?>><?php echo e($p['name']); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-6"><label class="form-label">Semester *</label>
                        <select class="form-select" name="semester_id" required data-scope="subjects">
                            <option value="">—</option>
                            <?php foreach ($sems as $s): ?><option value="<?php echo $s['id']; ?>" data-program="<?php echo $s['program_id']; ?>" <?php echo selected($edit['semester_id'] ?? '', $s['id']); ?>><?php echo e(program_name_of((int) $s['program_id']) . ' — ' . $s['name']); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-6"><label class="form-label">Code *</label>
                        <input class="form-control" name="code" value="<?php echo e($edit['code'] ?? ''); ?>" placeholder="CACS251" required></div>
                    <div class="col-6"><label class="form-label">Credit Hours *</label>
                        <input type="number" step="0.5" min="0.5" max="8" class="form-control" name="credit_hours" value="<?php echo e($edit['credit_hours'] ?? 3); ?>" required></div>
                    <div class="col-12"><label class="form-label">Subject Name *</label>
                        <input class="form-control" name="name" value="<?php echo e($edit['name'] ?? ''); ?>" placeholder="Operating System" required></div>
                    <div class="col-12"><label class="form-label">Subject Type</label>
                        <select class="form-select" name="subject_type">
                            <?php foreach (['Theory', 'Practical', 'Theory + Practical'] as $t): ?>
                                <option value="<?php echo $t; ?>" <?php echo selected($edit['subject_type'] ?? 'Theory', $t); ?>><?php echo $t; ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-6"><div class="form-check">
                        <input class="form-check-input" type="checkbox" name="has_theory" id="hasThe" <?php echo checked($edit['has_theory'] ?? 1); ?>>
                        <label class="form-check-label" for="hasThe">Has Theory</label></div></div>
                    <div class="col-6"><div class="form-check">
                        <input class="form-check-input" type="checkbox" name="has_practical" id="hasPr" <?php echo checked($edit['has_practical'] ?? 0); ?>>
                        <label class="form-check-label" for="hasPr">Has Practical</label></div></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-rms">Save</button></div>
        </form>
    </div>
</div>
<?php if ($edit && !empty($_GET['edit'])): ?><script>document.addEventListener('DOMContentLoaded', function () { var m = document.getElementById('subjectModal'); if (m && window.bootstrap) new bootstrap.Modal(m).show(); });</script><?php endif; ?>
<script>
(function () {
    var box = document.getElementById('subjectSearchBox');
    var form = document.getElementById('subjectFilterForm');
    if (!box || !form) return;
    var timer = null;
    box.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { form.submit(); }, 300);
    });
})();
</script>
<?php echo program_scope_script(); ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>