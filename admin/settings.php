<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_SUPERADMIN);
$title = 'Settings';
$active = 'settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fields = ['institute_name', 'institute_address', 'institute_email', 'institute_phone', 'program_name',
        'pass_percent', 'sgpa_decimals'];
    foreach ($fields as $f) {
        $v = trim($_POST[$f] ?? '');
        if ($f === 'pass_percent') {
            $v = $v === '' ? '0' : (string) max(0, num($v));
        }
        set_setting($f, $v);
    }
    try {
        $before = module_visibility();
        foreach (module_definitions() as $key => $label) {
            $enabled = $key === 'settings' || isset($_POST['module_' . $key]);
            set_setting('module_' . $key, $enabled ? '1' : '0');
            if ($before[$key] !== $enabled) {
                audit('Update module visibility', $label . ': ' . ($before[$key] ? 'ON' : 'OFF') . ' -> ' . ($enabled ? 'ON' : 'OFF'));
            }
        }
        audit('Update settings', 'System settings and module visibility updated');
        flash('success', 'Module settings updated successfully.');
    } catch (Throwable $ex) {
        flash('danger', 'Unable to update module settings.');
    }
    redirect(url('admin/settings.php'));
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-gear me-2"></i>System Settings</h4>
</div>

<form method="post">
    <?php echo csrf_field(); ?>
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Institution</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Institute Name</label>
                        <input class="form-control" name="institute_name" value="<?php echo e(get_setting('institute_name')); ?>"></div>
                    <div class="mb-3"><label class="form-label">Address</label>
                        <input class="form-control" name="institute_address" value="<?php echo e(get_setting('institute_address')); ?>"></div>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label">Email</label>
                            <input class="form-control" name="institute_email" value="<?php echo e(get_setting('institute_email')); ?>"></div>
                        <div class="col-6"><label class="form-label">Phone</label>
                            <input class="form-control" name="institute_phone" value="<?php echo e(get_setting('institute_phone')); ?>"></div>
                    </div>
                    <div class="mt-3"><label class="form-label">Program Name</label>
                        <input class="form-control" name="program_name" value="<?php echo e(get_setting('program_name')); ?>"></div>
                </div>

            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Passing Rules</div>
                <div class="card-body">
                    <p class="small text-muted">A student passes a subject when the percentage is at least the pass percent. Terminal exams are marks-only; Final exams also compute grades / SGPA / CGPA.</p>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label">Pass Percent (%)</label>
                            <input type="number" step="0.5" class="form-control" name="pass_percent" value="<?php echo e(get_setting('pass_percent', 40)); ?>"></div>
                        <div class="col-6"><label class="form-label">SGPA/CGPA Decimals</label>
                            <input type="number" min="0" max="4" class="form-control" name="sgpa_decimals" value="<?php echo e(get_setting('sgpa_decimals')); ?>"></div>
                    </div>
                    <div class="alert alert-light border small mb-0">
                        <i class="bi bi-shield-check me-1"></i> Grades are configured in <a href="grades.php">Grading Rules</a>.
                        Marks sheets submitted by teachers cannot be edited by teachers once submitted; reopening a subject requires an administrator.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">Navigation &amp; Module Visibility</div>
            <div class="card-body">
                <p class="text-muted small">Enable or disable system modules from the navigation. Disabled modules are hidden from navigation and cannot be accessed directly. Existing data is preserved.</p>
                <div class="row g-2">
                    <?php foreach (module_definitions() as $key => $label):
                        $isSettings = $key === 'settings';
                        $enabled = module_enabled($key);
                    ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center justify-content-between border rounded px-3 py-2">
                                <label class="form-check-label" for="module_<?php echo e($key); ?>"><?php echo e($label); ?></label>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="module_<?php echo e($key); ?>" name="module_<?php echo e($key); ?>" value="1" <?php echo checked($enabled); ?> <?php echo $isSettings ? 'disabled title="Settings must remain available for recovery"' : ''; ?>>
                                    <span class="small text-muted ms-1"><?php echo $enabled ? 'ON' : 'OFF'; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="alert alert-light border small mt-3 mb-0">Settings remains available as the administrator control plane so disabled modules can be enabled again.</div>
            </div>
        </div>
    </div>

    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-rms px-4"><i class="bi bi-save me-1"></i> Save Settings</button>
        <a class="btn btn-outline-secondary" href="audit_logs.php">View Audit Log</a>
        <a class="btn btn-outline-danger ms-auto" href="grades.php">Grading Rules</a>
    </div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>