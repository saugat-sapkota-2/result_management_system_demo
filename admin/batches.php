<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_SUPERADMIN);
require_module('batches');
$title = 'Batches';
$active = 'batches';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $data = [
            'program_id' => (int) ($_POST['program_id'] ?? 0),
            'academic_year_id' => (int) ($_POST['academic_year_id'] ?? 0) ?: null,
            'name' => trim($_POST['name']),
            'start_year' => (int) ($_POST['start_year'] ?? 0) ?: null,
            'end_year' => (int) ($_POST['end_year'] ?? 0) ?: null,
        ];
        if ($data['program_id'] <= 0 || $data['name'] === '') {
            flash('danger', 'Program and batch name are required.');
        } else {
            try {
                if ($id > 0) {
                    db_run('UPDATE batches SET program_id=?, academic_year_id=?, name=?, start_year=?, end_year=? WHERE id=?',
                        [$data['program_id'], $data['academic_year_id'], $data['name'], $data['start_year'], $data['end_year'], $id]);
                    audit('Edit batch', 'Updated batch: ' . $data['name'], $id);
                } else {
                    db_run('INSERT INTO batches (program_id, academic_year_id, name, start_year, end_year) VALUES (?,?,?,?,?)',
                        [$data['program_id'], $data['academic_year_id'], $data['name'], $data['start_year'], $data['end_year']]);
                    audit('Add batch', 'Created batch: ' . $data['name'], last_id());
                }
                flash('success', 'Batch saved.');
            } catch (PDOException $ex) {
                flash('danger', 'Duplicate name or database error: ' . $ex->getMessage());
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int) $_POST['id'];
        db_run('UPDATE batches SET status = 1 - status WHERE id = ?', [$id]);
        flash('success', 'Status changed.');
    } elseif ($action === 'delete') {
        $id = (int) $_POST['id'];
        db_run('DELETE FROM batches WHERE id = ?', [$id]);
        audit('Delete batch', 'Deleted batch id ' . $id, $id);
        flash('success', 'Batch deleted.');
    }
    redirect(url('admin/batches.php'));
}

$edit = !empty($_GET['edit']) ? db_one('SELECT * FROM batches WHERE id = ?', [(int) $_GET['edit']]) : null;
$rows = db_all('SELECT b.*, p.name AS program_name, a.name AS ac_year
                FROM batches b JOIN programs p ON p.id = b.program_id
                LEFT JOIN academic_years a ON a.id = b.academic_year_id
                ORDER BY b.start_year DESC, b.id DESC');
$programs = db_all('SELECT * FROM programs WHERE status = 1 ORDER BY name');
$years = db_all('SELECT * FROM academic_years ORDER BY start_date DESC');

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-people-fill me-2"></i>Batches</h4>
    <button class="btn btn-rms" data-bs-toggle="modal" data-bs-target="#batchModal"><i class="bi bi-plus-lg me-1"></i> Add Batch</button>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Batch</th><th>Program</th><th>Start Year</th><th>End Year</th><th>Academic Year</th><th>Students</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $r): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td class="fw-semibold"><?php echo e($r['name']); ?></td>
                    <td><?php echo e($r['program_name']); ?></td>
                    <td><?php echo $r['start_year'] ?: '—'; ?></td>
                    <td><?php echo $r['end_year'] ?: '—'; ?></td>
                    <td><?php echo e($r['ac_year'] ?? '—'); ?></td>
                    <td><?php echo (int) db_val('SELECT COUNT(*) FROM students WHERE batch_id = ?', [$r['id']]); ?></td>
                    <td><?php echo bool_badge($r['status']); ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo $r['id']; ?>#modal"><i class="bi bi-pencil"></i></a>
                        <form class="d-inline" method="post" onsubmit="return confirmDelete();">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-power"></i></button>
                        </form>
                        <form class="d-inline" method="post" onsubmit="return confirmDelete('Delete this batch permanently?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="9" class="text-center py-4 text-muted">No batches yet. Example: BCA 2081 Batch.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="batchModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post">
            <?php echo csrf_field(); ?>
            <div class="modal-header"><h5 class="modal-title"><?php echo $edit ? 'Edit Batch' : 'Add Batch'; ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int) ($edit['id'] ?? 0); ?>">
                <div class="mb-3"><label class="form-label">Batch Name *</label>
                    <input class="form-control" name="name" value="<?php echo e($edit['name'] ?? ''); ?>" placeholder="e.g. BCA 2081 Batch" required></div>
                <div class="mb-3"><label class="form-label">Program *</label>
                    <select class="form-select" name="program_id" required>
                        <option value="">— Select —</option>
                        <?php foreach ($programs as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo selected($edit['program_id'] ?? '', $p['id']); ?>><?php echo e($p['name'] . ' (' . $p['code'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="row g-2">
                    <div class="col"><label class="form-label">Start Year</label>
                        <input type="number" class="form-control" name="start_year" min="2000" max="2100" value="<?php echo e($edit['start_year'] ?? ''); ?>"></div>
                    <div class="col"><label class="form-label">End Year</label>
                        <input type="number" class="form-control" name="end_year" min="2000" max="2100" value="<?php echo e($edit['end_year'] ?? ''); ?>"></div>
                </div>
                <div class="mt-3"><label class="form-label">Academic Year</label>
                    <select class="form-select" name="academic_year_id">
                        <option value="">— None —</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y['id']; ?>" <?php echo selected($edit['academic_year_id'] ?? '', $y['id']); ?>><?php echo e($y['name']); ?></option>
                        <?php endforeach; ?>
                    </select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-rms">Save</button></div>
        </form>
    </div>
</div>
<?php if ($edit): ?><script>document.addEventListener('DOMContentLoaded', function () { var m = document.getElementById('batchModal'); if (m && window.bootstrap) new bootstrap.Modal(m).show(); });</script><?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>