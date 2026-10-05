<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_SUPERADMIN);
$title = 'Grading Rules';
$active = 'grades';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $min = (float) $_POST['min_percent'];
        $max = (float) $_POST['max_percent'];
        $grade = strtoupper(trim($_POST['grade']));
        $gp = (float) $_POST['grade_point'];
        if ($grade === '' || $min < 0 || $max > 100 || $min >= $max) {
            flash('danger', 'Invalid range or grade.');
        } else {
            try {
                if ($id > 0) {
                    db_run('UPDATE grades SET min_percent=?, max_percent=?, grade=?, grade_point=? WHERE id=?', [$min, $max, $grade, $gp, $id]);
                    audit('Edit grading rule', 'Updated rule: ' . $grade, $id);
                } else {
                    db_run('INSERT INTO grades (min_percent, max_percent, grade, grade_point) VALUES (?,?,?,?)', [$min, $max, $grade, $gp]);
                    audit('Add grading rule', 'Added rule: ' . $grade, last_id());
                }
                flash('success', 'Grading rule saved.');
            } catch (PDOException $ex) {
                flash('danger', 'Database error: ' . $ex->getMessage());
            }
        }
    } elseif ($action === 'delete') {
        db_run('DELETE FROM grades WHERE id=?', [(int) $_POST['id']]);
        flash('success', 'Rule deleted.');
    }
    redirect(url('admin/grades.php'));
}

$edit = !empty($_GET['edit']) ? db_one('SELECT * FROM grades WHERE id=?', [(int) $_GET['edit']]) : null;
$rows = db_all('SELECT * FROM grades ORDER BY min_percent DESC');

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-trophy me-2"></i>Grading Rules</h4>
    <button class="btn btn-rms" data-bs-toggle="modal" data-bs-target="#gradeModal"><i class="bi bi-plus-lg"></i> Add Rule</button>
</div>

<div class="alert alert-light border">
    <strong>TU-inspired scale (configurable).</strong> Ranges are inclusive on both ends. Grades are looked up after the system computes the subject percentage.
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>From %</th><th>To %</th><th>Grade</th><th>Grade Point</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo e($r['min_percent']); ?>%</td>
                    <td><?php echo e($r['max_percent']); ?>%</td>
                    <td><span class="badge text-bg-primary fs-6"><?php echo e($r['grade']); ?></span></td>
                    <td class="fw-bold"><?php echo e($r['grade_point']); ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo $r['id']; ?>#modal"><i class="bi bi-pencil"></i></a>
                        <?php if (strtoupper($r['grade']) !== 'F'): ?>
                            <form class="d-inline" method="post" onsubmit="return confirmDelete('Delete this rule?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="gradeModal" tabindex="-1"><div class="modal-dialog modal-sm"><form class="modal-content" method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int) ($edit['id'] ?? 0); ?>">
    <div class="modal-header"><h6 class="modal-title"><?php echo $edit ? 'Edit Rule' : 'Add Rule'; ?></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">From %</label>
                <input type="number" step="0.01" class="form-control" name="min_percent" value="<?php echo e($edit['min_percent'] ?? ''); ?>" required></div>
            <div class="col-6"><label class="form-label">To %</label>
                <input type="number" step="0.01" class="form-control" name="max_percent" value="<?php echo e($edit['max_percent'] ?? ''); ?>" required></div>
            <div class="col-6"><label class="form-label">Grade</label>
                <input class="form-control" name="grade" value="<?php echo e($edit['grade'] ?? ''); ?>" required></div>
            <div class="col-6"><label class="form-label">Grade Point</label>
                <input type="number" step="0.01" class="form-control" name="grade_point" value="<?php echo e($edit['grade_point'] ?? ''); ?>" required></div>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-rms">Save</button></div>
</form></div></div>
<?php if ($edit && !empty($_GET['edit'])): ?><script>document.addEventListener('DOMContentLoaded', function () { var m = document.getElementById('gradeModal'); if (m && window.bootstrap) new bootstrap.Modal(m).show(); });</script><?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>