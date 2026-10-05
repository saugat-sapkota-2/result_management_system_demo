<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_SUPERADMIN);
require_module('semesters');
$title = 'Semesters';
$active = 'semesters';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $no = (int) ($_POST['semester_no'] ?? 0);
        $pid = (int) ($_POST['program_id'] ?? 0);
        $name = trim($_POST['name']);
        if ($no < 1 || $pid <= 0) {
            flash('danger', 'Program and semester number are required.');
        } else {
            try {
                if ($id > 0) {
                    db_run('UPDATE semesters SET program_id=?, semester_no=?, name=? WHERE id=?', [$pid, $no, $name, $id]);
                    audit('Edit semester', 'Updated: ' . $name, $id);
                } else {
                    db_run('INSERT INTO semesters (program_id, semester_no, name) VALUES (?,?,?)', [$pid, $no, $name]);
                    audit('Add semester', 'Created: ' . $name, last_id());
                }
                flash('success', 'Semester saved.');
            } catch (PDOException $ex) {
                flash('danger', 'Duplicate semester number or database error: ' . $ex->getMessage());
            }
        }
    } elseif ($action === 'generate') {
        $pid = (int) ($_POST['program_id'] ?? 0);
        $existing = db_all('SELECT semester_no FROM semesters WHERE program_id = ?', [$pid]);
        $have = array_column($existing, 'semester_no');
        $added = 0;
        for ($n = 1; $n <= 8; $n++) {
            if (!in_array($n, $have, true)) {
                db_run('INSERT INTO semesters (program_id, semester_no, name) VALUES (?,?,?)', [$pid, $n, 'Semester ' . $n]);
                $added++;
            }
        }
        audit('Generate semesters', 'Created ' . $added . ' semester(s) for program id ' . $pid, $pid);
        flash($added ? 'success' : 'info', $added > 0 ? "$added semester(s) created." : 'Semesters 1-8 already exist.');
    } elseif ($action === 'toggle') {
        db_run('UPDATE semesters SET status = 1 - status WHERE id = ?', [(int) $_POST['id']]);
        flash('success', 'Status changed.');
    } elseif ($action === 'delete') {
        db_run('DELETE FROM semesters WHERE id = ?', [(int) $_POST['id']]);
        flash('success', 'Semester deleted.');
    }
    redirect(url('admin/semesters.php'));
}

$edit = !empty($_GET['edit']) ? db_one('SELECT * FROM semesters WHERE id = ?', [(int) $_GET['edit']]) : null;
$rows = db_all('SELECT s.*, p.name AS program_name,
                (SELECT COUNT(*) FROM subjects su WHERE su.semester_id = s.id) sub_count
                FROM semesters s JOIN programs p ON p.id = s.program_id
                ORDER BY p.name, s.semester_no');
$programs = db_all('SELECT * FROM programs WHERE status = 1 ORDER BY name');
$programFilter = (int) ($_GET['program_id'] ?? 0);

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-layers me-2"></i>Semesters</h4>
    <div class="d-flex gap-2">
        <form method="post" class="d-flex gap-2" onsubmit="return confirm('Generate all 8 semesters for the selected program?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="generate">
            <select class="form-select form-select-sm" name="program_id" required>
                <option value="">Program…</option>
                <?php foreach ($programs as $p): ?><option value="<?php echo $p['id']; ?>"><?php echo e($p['name']); ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-stars me-1"></i>Generate 1–8</button>
        </form>
        <button class="btn btn-rms" data-bs-toggle="modal" data-bs-target="#semModal"><i class="bi bi-plus-lg"></i> Add Semester</button>
    </div>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Name</th><th>Program</th><th>No.</th><th>Subjects</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $r): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td class="fw-semibold"><?php echo e($r['name']); ?></td>
                    <td><?php echo e($r['program_name']); ?></td>
                    <td><span class="badge text-bg-dark"><?php echo $r['semester_no']; ?></span></td>
                    <td><?php echo $r['sub_count']; ?></td>
                    <td><?php echo bool_badge($r['status']); ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo $r['id']; ?>#modal"><i class="bi bi-pencil"></i></a>
                        <form class="d-inline" method="post" onsubmit="return confirmDelete();">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-power"></i></button>
                        </form>
                        <form class="d-inline" method="post" onsubmit="return confirmDelete('Delete this semester? Its subjects will be deleted.');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7" class="text-center py-4 text-muted">No semesters yet. Use <em>Generate 1–8</em> above.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="semModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post">
            <?php echo csrf_field(); ?>
            <div class="modal-header"><h5 class="modal-title"><?php echo $edit ? 'Edit Semester' : 'Add Semester'; ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int) ($edit['id'] ?? 0); ?>">
                <div class="mb-3"><label class="form-label">Program *</label>
                    <select class="form-select" name="program_id" required>
                        <option value="">— Select —</option>
                        <?php foreach ($programs as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo selected($edit['program_id'] ?? '', $p['id']); ?>><?php echo e($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="row g-2">
                    <div class="col-4"><label class="form-label">Number *</label>
                        <input type="number" class="form-control" name="semester_no" min="1" max="8" value="<?php echo e($edit['semester_no'] ?? ''); ?>" required></div>
                    <div class="col"><label class="form-label">Name *</label>
                        <input class="form-control" name="name" value="<?php echo e($edit['name'] ?? ''); ?>" placeholder="Semester 4" required></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-rms">Save</button></div>
        </form>
    </div>
</div>
<?php if ($edit): ?><script>document.addEventListener('DOMContentLoaded', function () { var m = document.getElementById('semModal'); if (m && window.bootstrap) new bootstrap.Modal(m).show(); });</script><?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>