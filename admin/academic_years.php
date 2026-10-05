<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_SUPERADMIN);
require_module('academic_years');
$title = 'Academic Years';
$active = 'academic_years';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name']);
        $start = bs_parse_input($_POST['start_date'] ?? '');
        $end = bs_parse_input($_POST['end_date'] ?? '');
        if ($start === false || $end === false) {
            flash('danger', 'Start/end dates must be valid Bikram Sambat dates (format YYYY/MM/DD).');
        } elseif ($name === '') {
            flash('danger', 'Academic year name is required.');
        } else {
            try {
                if ($id > 0) {
                    db_run('UPDATE academic_years SET name=?, start_date=?, end_date=? WHERE id=?', [$name, $start, $end, $id]);
                    audit('Edit academic year', 'Updated: ' . $name, $id);
                } else {
                    db_run('INSERT INTO academic_years (name, start_date, end_date) VALUES (?,?,?)', [$name, $start, $end]);
                    audit('Add academic year', 'Created: ' . $name, last_id());
                }
                flash('success', 'Academic year saved.');
            } catch (PDOException $ex) {
                flash('danger', 'Duplicate name or database error: ' . $ex->getMessage());
            }
        }
    } elseif ($action === 'set_current') {
        $id = (int) $_POST['id'];
        db_run('UPDATE academic_years SET is_current = 0');
        db_run('UPDATE academic_years SET is_current = 1 WHERE id = ?', [$id]);
        audit('Set current academic year', 'Active year changed to id ' . $id, $id);
        flash('success', 'Current academic year updated.');
    } elseif ($action === 'toggle') {
        $id = (int) $_POST['id'];
        db_run('UPDATE academic_years SET status = 1 - status WHERE id = ?', [$id]);
        flash('success', 'Status changed.');
    } elseif ($action === 'delete') {
        $id = (int) $_POST['id'];
        db_run('DELETE FROM academic_years WHERE id = ?', [$id]);
        audit('Delete academic year', 'Deleted id ' . $id, $id);
        flash('success', 'Academic year deleted.');
    }
    redirect(url('admin/academic_years.php'));
}

$edit = !empty($_GET['edit']) ? db_one('SELECT * FROM academic_years WHERE id = ?', [(int) $_GET['edit']]) : null;
$rows = db_all('SELECT * FROM academic_years ORDER BY is_current DESC, start_date DESC, id DESC');

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Academic Years</h4>
    <button class="btn btn-rms" data-bs-toggle="modal" data-bs-target="#ayModal"><i class="bi bi-plus-lg me-1"></i> Add Year</button>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Name</th><th>Start</th><th>End</th><th>Current</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $r): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td class="fw-semibold"><?php echo e($r['name']); ?></td>
                    <td><?php echo fdate($r['start_date']); ?></td>
                    <td><?php echo fdate($r['end_date']); ?></td>
                    <td><?php echo $r['is_current'] ? '<span class="badge bg-success">Current</span>' : ''; ?></td>
                    <td><?php echo bool_badge($r['status']); ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo $r['id']; ?>#modal"><i class="bi bi-pencil"></i></a>
                        <?php if (!$r['is_current']): ?>
                        <form class="d-inline" method="post">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="set_current"><input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <button class="btn btn-sm btn-outline-success" title="Set as current"><i class="bi bi-check2-circle"></i></button>
                        </form>
                        <?php endif; ?>
                        <form class="d-inline" method="post" onsubmit="return confirmDelete();">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-power"></i></button>
                        </form>
                        <form class="d-inline" method="post" onsubmit="return confirmDelete('Delete this academic year permanently?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7" class="text-center py-4 text-muted">No academic years yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="ayModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post">
            <?php echo csrf_field(); ?>
            <div class="modal-header"><h5 class="modal-title"><?php echo $edit ? 'Edit Academic Year' : 'Add Academic Year'; ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int) ($edit['id'] ?? 0); ?>">
                <div class="mb-3"><label class="form-label">Year Name *</label>
                    <input class="form-control" name="name" value="<?php echo e($edit['name'] ?? ''); ?>" placeholder="e.g. 2082 / 2083" required></div>
                <div class="row g-2">
                    <div class="col"><label class="form-label">Start Date (BS)</label>
                        <input type="text" class="form-control bs-date" name="start_date" value="<?php echo e(bs_input_value($edit['start_date'] ?? '')); ?>" placeholder="YYYY/MM/DD" maxlength="10"></div>
                    <div class="col"><label class="form-label">End Date (BS)</label>
                        <input type="text" class="form-control bs-date" name="end_date" value="<?php echo e(bs_input_value($edit['end_date'] ?? '')); ?>" placeholder="YYYY/MM/DD" maxlength="10"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-rms">Save</button></div>
        </form>
    </div>
</div>
<?php if ($edit): ?><script>document.addEventListener('DOMContentLoaded', function () { var m = document.getElementById('ayModal'); if (m && window.bootstrap) new bootstrap.Modal(m).show(); });</script><?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>