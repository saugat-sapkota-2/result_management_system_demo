<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_STUDENT);
$title = 'Notices';
$active = 'notices';

$q = trim($_GET['q'] ?? '');
$sql = 'SELECT n.*, u.username, n.created_at FROM notices n LEFT JOIN users u ON u.id = n.created_by WHERE n.status = 1';
$params = [];
if ($q !== '') { $sql .= ' AND (n.title LIKE ? OR n.description LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
$sql .= ' ORDER BY n.notice_date DESC, n.id DESC';
$notices = db_all($sql, $params);

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-megaphone me-2"></i>Notices</h4>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-5"><input type="text" class="form-control" name="q" value="<?php echo e($q); ?>" placeholder="Search notices..."></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="bi bi-search me-1"></i>Search</button></div>
</form>

<?php foreach ($notices as $n): ?>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <h6 class="mb-1"><i class="bi bi-pin-angle text-danger me-1"></i><?php echo e($n['title']); ?></h6>
                <span class="badge text-bg-light"><?php echo fdate($n['notice_date']); ?></span>
            </div>
            <div class="small text-muted mb-2">Posted by <?php echo e($n['username'] ?? 'Admin'); ?></div>
            <div class="notices-body"><?php echo nl2br(e($n['description'])); ?></div>
        </div>
    </div>
<?php endforeach; ?>
<?php if (!$notices): ?>
    <div class="alert alert-info">No notices found.<?php echo $q !== '' ? ' Try a different search.' : ''; ?></div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>