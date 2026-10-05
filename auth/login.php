<?php
require_once __DIR__ . '/../includes/init.php';

if (!empty($_SESSION['user_id'])) {
    redirect(url(($_SESSION['role'] === 'student' ? 'student' : ($_SESSION['role'] === 'teacher' ? 'teacher' : 'admin')) . '/index.php'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $user = db_one('SELECT * FROM users WHERE username = ?', [$username]);
        if ($user && (int) $user['status'] === 1 && password_verify($password, $user['password'])) {
            $name = $user['username'];
            if ($user['role'] === 'student' && $user['student_id']) {
                $name = db_val('SELECT full_name FROM students WHERE id = ?', [$user['student_id']]) ?: $name;
            } elseif ($user['role'] === 'teacher' && $user['teacher_id']) {
                $name = db_val('SELECT name FROM teachers WHERE id = ?', [$user['teacher_id']]) ?: $name;
            }
            perform_login((int) $user['id'], $user['role'], $name, $user['username']);
            redirect(url(($user['role'] === 'student' ? 'student' : ($user['role'] === 'teacher' ? 'teacher' : 'admin')) . '/index.php'));
        }
        $error = $user && (int) $user['status'] !== 1
            ? 'Your account is inactive. Please contact the administrator.'
            : 'Invalid username or password.';
    }
}
$inactive = isset($_GET['inactive']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | <?php echo e(APP_NAME); ?></title>
<link rel="stylesheet" href="<?php echo e(url('assets/vendor/bootstrap/bootstrap.min.css')); ?>">
<link rel="stylesheet" href="<?php echo e(url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')); ?>">
<link rel="stylesheet" href="<?php echo e(url('assets/css/style.css')); ?>">
</head>
<body class="login-bg">
<div class="container d-flex align-items-center justify-content-center py-4" style="min-height:100vh">
    <div class="row align-items-center justify-content-center g-4 w-100">
    <div class="col-11 col-sm-8 col-md-6 col-lg-4">
        <div class="card shadow-lg border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <img src="<?php echo e(url('assets/img/marksheet-logo.png')); ?>" class="login-logo mb-2" alt="Certified Excellence">
                    <h3 class="mt-2 fw-bold text-rms-primary"><?php echo e(APP_NAME); ?></h3>
                    <p class="text-muted small mb-0">College-Level Result Management System</p>
                </div>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php endif; ?>
                <?php if ($inactive): ?>
                    <div class="alert alert-warning">Your session has expired. Please login again.</div>
                <?php endif; ?>
                <form method="post" autocomplete="off" id="loginForm">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="username" name="username" placeholder="Enter username / student ID" required autofocus autocomplete="username">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required autocomplete="current-password">
                            <button type="button" class="btn btn-outline-secondary" id="togglePassword" aria-label="Show password" title="Show password">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-rms w-100 py-2 fw-semibold"><i class="bi bi-box-arrow-in-right me-1"></i> Sign In</button>
                    <div class="border-top mt-4 pt-3">
                        <div class="demo-login-copy mb-3">
                            <span class="small text-muted">Choose a role to enter the project demo</span>
                        </div>
                        <div class="row g-2">
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-primary w-100 demo-login" data-username="<?php echo e(DEMO_USERNAME); ?>" data-password="<?php echo e(DEMO_PASSWORD); ?>">
                                    <i class="bi bi-shield-lock-fill me-1"></i> Admin Demo
                                    <span class="d-block small opacity-75"><?php echo e(DEMO_USERNAME); ?> / <?php echo e(DEMO_PASSWORD); ?></span>
                                </button>
                            </div>
                            <div class="col-sm-6">
                                <button type="button" class="btn btn-outline-success w-100 demo-login" data-username="<?php echo e(DEMO_TEACHER_USERNAME); ?>" data-password="<?php echo e(DEMO_TEACHER_PASSWORD); ?>">
                                    <i class="bi bi-person-video3 me-1"></i> Teacher
                                    <span class="d-block small opacity-75"><?php echo e(DEMO_TEACHER_USERNAME); ?></span>
                                </button>
                            </div>
                            <div class="col-sm-6">
                                <button type="button" class="btn btn-outline-warning w-100 demo-login" data-username="<?php echo e(DEMO_STUDENT_USERNAME); ?>" data-password="<?php echo e(DEMO_STUDENT_PASSWORD); ?>">
                                    <i class="bi bi-mortarboard-fill me-1"></i> Student
                                    <span class="d-block small opacity-75"><?php echo e(DEMO_STUDENT_USERNAME); ?></span>
                                </button>
                            </div>
                        </div>
                        <div class="col-11 col-sm-8 col-md-6 col-lg-4 col-xl-3">
                            <aside class="login-project-card card border-0 shadow-lg">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center gap-3 mb-3">
                                        <div class="project-builder-avatar">SS</div>
                                        <div>
                                            <div class="small text-uppercase text-muted fw-semibold">Project Builder</div>
                                            <h5 class="mb-0 text-rms-primary"><?php echo e(PROJECT_BUILDER); ?></h5>
                                        </div>
                                    </div>
                                    <p class="small text-muted mb-3">Built as a college-level academic result management project.</p>
                                    <dl class="row small mb-3">
                                        <dt class="col-5">Programme</dt>
                                        <dd class="col-7"><?php echo e(PROJECT_PROGRAMME); ?></dd>
                                        <dt class="col-5">Project</dt>
                                        <dd class="col-7">Result Management System</dd>
                                    </dl>
                                    <div class="d-grid gap-2">
                                        <a class="btn btn-sm btn-outline-dark" href="<?php echo e(PROJECT_GITHUB); ?>" target="_blank" rel="noopener">
                                            <i class="bi bi-github me-1"></i> View my GitHub
                                        </a>
                                        <a class="btn btn-sm btn-outline-primary" href="<?php echo e(PROJECT_WEBSITE); ?>" target="_blank" rel="noopener">
                                            <i class="bi bi-globe2 me-1"></i> Visit my website
                                        </a>
                                    </div>
                                </div>
                            </aside>
                        </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const password = document.getElementById('password');
        const icon = this.querySelector('i');
        const isHidden = password.type === 'password';

        password.type = isHidden ? 'text' : 'password';
        icon.classList.toggle('bi-eye', !isHidden);
        icon.classList.toggle('bi-eye-slash', isHidden);
        this.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        this.setAttribute('title', isHidden ? 'Hide password' : 'Show password');
    });

    document.querySelectorAll('.demo-login').forEach(function (button) {
        button.addEventListener('click', function () {
        document.getElementById('username').value = this.dataset.username;
        document.getElementById('password').value = this.dataset.password;
        document.getElementById('loginForm').submit();
        });
    });
</script>
</body>
</html>