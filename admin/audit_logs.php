<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_SUPERADMIN);
$title = 'Audit Log';
$active = 'audit';

$q = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 30;
$where = '1=1'; $params = [];
if ($q !== '') { $where = '(a.action LIKE ? OR a.description LIKE ? OR u.username LIKE ?)';
    $like = "%$q%"; $params = [$like, $like, $like]; }

$countStmt = db()->prepare("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE $where");
$p = paginate($countStmt, $params, $page, $per);
$rows = db_all(
    "SELECT a.*, u.username
     FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
     WHERE $where ORDER BY a.id DESC LIMIT {$p['offset']}, {$per}", $params);

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-clock-history me-2"></i>Audit Log</h4>
</div>

<div class="card">
    <div class="card-header">
        <form class="d-flex gap-2" method="get">
            <input type="text" class="form-control form-control-sm" name="q" value="<?php echo e($q); ?>" placeholder="Search action, description, user...">
            <button class="btn btn-sm btn-rms">Search</button>
        </form>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover table-sm mb-0">
            <thead><tr><th>#</th><th>User</th><th>Role</th><th>Action</th><th>Description</th><th>IP</th><th>Date / Time</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $a): ?>
                <tr>
                    <td><?php echo $p['offset'] + $i + 1; ?></td>
                    <td><?php echo e($a['username'] ?? 'System'); ?></td>
                    <td><span class="badge text-bg-secondary"><?php echo e(ucfirst($a['role'] ?? 'system')); ?></span></td>
                    <td class="fw-semibold"><?php echo e($a['action']); ?></td>
                    <td class="small"><?php echo e($a['description']); ?></td>
                    <td class="small text-muted"><?php echo e($a['ip_address'] ?? '—'); ?></td>
                    <td class="small"><?php echo fdate($a['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7" class="text-center py-4 text-muted">No audit records found.</td></tr><?php endif; ?>
            </tbody>
        </table>
        <?php if ($p['pages'] > 1): ?><div class="card-footer"><?php echo $p['pagination']; ?></div><?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>