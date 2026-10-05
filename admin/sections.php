<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_SUPERADMIN);
require_module('sections');
$title = 'Sections';
$active = 'sections';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name']);
        if ($name === '') {
            flash('danger', 'Section name is required.');
        } else {
            try {
                if ($id > 0) {
                    db_run('UPDATE sections SET name=? WHERE id=?', [$name, $id]);
                    audit('Edit section', 'Updated section: ' . $name, $id);
                } else {
                    db_run('INSERT INTO sections (name) VALUES (?)', [$name]);
                    audit('Add section', 'Created section: ' . $name, last_id());
                }
                flash('success', 'Section saved.');
            } catch (PDOException $ex) {
                flash('danger', 'Duplicate section name: ' . $ex->getMessage());
            }
        }
    } elseif ($action === 'toggle') {
        db_run('UPDATE sections SET status = 1 - status WHERE id = ?', [(int) $_POST['id']]);
        flash('success', 'Status changed.');
    } elseif ($action === 'delete') {
        db_run('DELETE FROM sections WHERE id = ?', [(int) $_POST['id']]);
        audit('Delete section', 'Deleted section id ' . (int) $_POST['id']);
        flash('success', 'Section deleted.');
    }
    redirect(url('admin/sections.php'));
}

$edit = !empty($_GET['edit']) ? db_one('SELECT * FROM sections WHERE id = ?', [(int) $_GET['edit']]) : null;
$rows = db_all('SELECT s.*, (SELECT COUNT(*) FROM students st WHERE st.section_id = s.id) stu_count FROM sections s ORDER BY s.id');

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Sections</h4>
    <button class="btn btn-rms" data-bs-toggle="modal" data-bs-target="#secModal"><i class="bi bi-plus-lg me-1"></i> Add Section</button>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Section</th><th>Students</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $r): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td class="fw-semibold"><?php echo e($r['name']); ?></td>
                    <td><?php echo $r['stu_count']; ?></td>
                    <td><?php echo bool_badge($r['status']); ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo $r['id']; ?>#modal"><i class="bi bi-pencil"></i></a>
                        <form class="d-inline" method="post" onsubmit="return confirmDelete();">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-power"></i></button>
                        </form>
                        <form class="d-inline" method="post" onsubmit="return confirmDelete('Delete this section permanently?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="5" class="text-center py-4 text-muted">No sections yet (e.g. Section A, Section B, Section C).</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="secModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form class="modal-content" method="post">
            <?php echo csrf_field(); ?>
            <div class="modal-header"><h5 class="modal-title"><?php echo $edit ? 'Edit Section' : 'Add Section'; ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int) ($edit['id'] ?? 0); ?>">
                <label class="form-label">Section Name *</label>
                <input class="form-control" name="name" value="<?php echo e($edit['name'] ?? ''); ?>" placeholder="Section A" required>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-rms">Save</button></div>
        </form>
    </div>
</div>
<?php if ($edit): ?><script>document.addEventListener('DOMContentLoaded', function () { var m = document.getElementById('secModal'); if (m && window.bootstrap) new bootstrap.Modal(m).show(); });</script><?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>