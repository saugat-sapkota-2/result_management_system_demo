<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_SUPERADMIN);
require_module('programs');
$title = 'Programs';
$active = 'programs';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $data = [
            'name' => trim($_POST['name']),
            'code' => strtoupper(trim($_POST['code'])),
            'duration_years' => (int) ($_POST['duration_years'] ?? 4),
        ];
        if ($data['name'] === '' || $data['code'] === '') {
            flash('danger', 'Name and code are required.');
        } else {
            try {
                if ($id > 0) {
                    db_run('UPDATE programs SET name=?, code=?, duration_years=? WHERE id=?', [$data['name'], $data['code'], $data['duration_years'], $id]);
                    audit('Edit program', 'Updated program: ' . $data['name'], $id);
                    flash('success', 'Program updated.');
                } else {
                    db_run('INSERT INTO programs (name, code, duration_years) VALUES (?,?,?)', [$data['name'], $data['code'], $data['duration_years']]);
                    audit('Add program', 'Created program: ' . $data['name'], last_id());
                    flash('success', 'Program added.');
                }
            } catch (PDOException $ex) {
                flash('danger', 'Duplicate code or database error: ' . $ex->getMessage());
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int) $_POST['id'];
        db_run('UPDATE programs SET status = 1 - status WHERE id = ?', [$id]);
        audit('Toggle program', 'Activated/deactivated program id ' . $id, $id);
        flash('success', 'Program status changed.');
    } elseif ($action === 'delete') {
        $id = (int) $_POST['id'];
        db_run('DELETE FROM programs WHERE id = ?', [$id]);
        audit('Delete program', 'Deleted program id ' . $id, $id);
        flash('success', 'Program deleted.');
    }
    redirect(url('admin/programs.php'));
}

$edit = null;
if (!empty($_GET['edit'])) {
    $edit = db_one('SELECT * FROM programs WHERE id = ?', [(int) $_GET['edit']]);
}
$rows = db_all('SELECT p.*, (SELECT COUNT(*) FROM batches b WHERE b.program_id = p.id) batch_count,
                (SELECT COUNT(*) FROM semesters s WHERE s.program_id = p.id) sem_count
                FROM programs p ORDER BY p.created_at DESC');

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-bank me-2"></i>Programs</h4>
    <button class="btn btn-rms" data-bs-toggle="modal" data-bs-target="#programModal"><i class="bi bi-plus-lg me-1"></i> Add Program</button>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Name</th><th>Code</th><th>Duration</th><th>Batches</th><th>Semesters</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $r): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td class="fw-semibold"><?php echo e($r['name']); ?></td>
                    <td><span class="badge text-bg-dark"><?php echo e($r['code']); ?></span></td>
                    <td><?php echo (int) $r['duration_years']; ?> years</td>
                    <td><?php echo $r['batch_count']; ?></td>
                    <td><?php echo $r['sem_count']; ?></td>
                    <td><?php echo bool_badge($r['status']); ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo $r['id']; ?>#modal"><i class="bi bi-pencil"></i></a>
                        <form class="d-inline" method="post" onsubmit="return confirmDelete();">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <button class="btn btn-sm btn-outline-secondary" title="Activate / deactivate"><i class="bi bi-power"></i></button>
                        </form>
                        <form class="d-inline" method="post" onsubmit="return confirmDelete('Delete this program permanently? Related records may be removed.');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No programs found yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="programModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post">
            <?php echo csrf_field(); ?>
            <div class="modal-header"><h5 class="modal-title"><?php echo $edit ? 'Edit Program' : 'Add Program'; ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo (int) ($edit['id'] ?? 0); ?>">
                <div class="mb-3"><label class="form-label">Program Name *</label>
                    <input type="text" class="form-control" name="name" value="<?php echo e($edit['name'] ?? ''); ?>" required></div>
                <div class="mb-3"><label class="form-label">Code *</label>
                    <input type="text" class="form-control" name="code" value="<?php echo e($edit['code'] ?? ''); ?>" placeholder="e.g. BCA" required></div>
                <div class="mb-3"><label class="form-label">Duration (years)</label>
                    <input type="number" class="form-control" name="duration_years" value="<?php echo e($edit['duration_years'] ?? 4); ?>" min="1" max="8"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-rms">Save</button></div>
        </form>
    </div>
</div>
<?php if ($edit): ?><script>document.addEventListener('DOMContentLoaded', function () { var m = document.getElementById('programModal'); if (m && window.bootstrap) new bootstrap.Modal(m).show(); });</script><?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>