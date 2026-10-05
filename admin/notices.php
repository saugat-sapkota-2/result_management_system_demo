<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_SUPERADMIN);
$title = 'Notices';
$active = 'notices';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title']);
        $desc = trim($_POST['description'] ?? '');
        $date = bs_parse_input($_POST['notice_date'] ?? '');
        if ($date === false) {
            flash('danger', 'Notice date must be a valid Bikram Sambat date (format YYYY/MM/DD).');
        } elseif ($title === '') {
            flash('danger', 'Notice title is required.');
        } else {
            if ($date === '') $date = todayAd();
            if ($id > 0) {
                db_run('UPDATE notices SET title=?, description=?, notice_date=? WHERE id=?', [$title, $desc, $date, $id]);
                audit('Edit notice', 'Updated: ' . $title, $id);
            } else {
                db_run('INSERT INTO notices (title, description, notice_date, created_by) VALUES (?,?,?,?)', [$title, $desc, $date, $user['id']]);
                audit('Add notice', 'Created: ' . $title, last_id());
            }
            flash('success', 'Notice saved.');
        }
    } elseif ($action === 'toggle') {
        db_run('UPDATE notices SET status = 1 - status WHERE id=?', [(int) $_POST['id']]);
        flash('success', 'Notice ' . ((int) db_val('SELECT status FROM notices WHERE id=?', [(int) $_POST['id']]) ? 'published' : 'unpublished') . '.');
    } elseif ($action === 'delete') {
        db_run('DELETE FROM notices WHERE id=?', [(int) $_POST['id']]);
        audit('Delete notice', 'Deleted notice id ' . (int) $_POST['id']);
        flash('success', 'Notice deleted.');
    }
    redirect(url('admin/notices.php'));
}

$edit = !empty($_GET['edit']) ? db_one('SELECT * FROM notices WHERE id=?', [(int) $_GET['edit']]) : null;
$rows = db_all('SELECT n.*, u.username created_by_name FROM notices n LEFT JOIN users u ON u.id = n.created_by ORDER BY n.notice_date DESC, n.id DESC');

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-megaphone me-2"></i>Notices</h4>
    <button class="btn btn-rms" data-bs-toggle="modal" data-bs-target="#noticeModal"><i class="bi bi-plus-lg me-1"></i> Add Notice</button>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Title</th><th>Date</th><th>Description</th><th>Posted By</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $n): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td class="fw-semibold"><?php echo e($n['title']); ?></td>
                    <td><?php echo fdate($n['notice_date']); ?></td>
                    <td class="text-truncate" style="max-width:320px"><?php echo e($n['description']); ?></td>
                    <td class="small"><?php echo e($n['created_by_name'] ?? '—'); ?></td>
                    <td><?php echo $n['status'] ? '<span class="badge bg-success">Published</span>' : '<span class="badge bg-secondary">Draft</span>'; ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo $n['id']; ?>#modal"><i class="bi bi-pencil"></i></a>
                        <form class="d-inline" method="post">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                            <button class="btn btn-sm btn-outline-<?php echo $n['status'] ? 'secondary' : 'success'; ?>" title="Publish / unpublish">
                                <i class="bi bi-<?php echo $n['status'] ? 'eye-slash' : 'eye'; ?>"></i></button>
                        </form>
                        <form class="d-inline" method="post" onsubmit="return confirmDelete('Delete this notice?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7" class="text-center py-4 text-muted">No notices yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="noticeModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int) ($edit['id'] ?? 0); ?>">
    <div class="modal-header"><h5 class="modal-title"><?php echo $edit ? 'Edit Notice' : 'Add Notice'; ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Title *</label>
            <input class="form-control" name="title" value="<?php echo e($edit['title'] ?? ''); ?>" placeholder="Result Published" required></div>
        <div class="mb-3"><label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="4" data-maxlength="1000"><?php echo e($edit['description'] ?? ''); ?></textarea></div>
        <div><label class="form-label">Notice Date</label>
            <input type="text" class="form-control bs-date" name="notice_date" value="<?php echo e(bs_input_value($edit['notice_date'] ?? todayAd())); ?>" placeholder="YYYY/MM/DD" maxlength="10"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-rms">Save</button></div>
</form></div></div>
<?php if ($edit && !empty($_GET['edit'])): ?><script>document.addEventListener('DOMContentLoaded', function () { var m = document.getElementById('noticeModal'); if (m && window.bootstrap) new bootstrap.Modal(m).show(); });</script><?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>