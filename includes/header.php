<?php
/**
 * RMS -- Layout header + topbar + role sidebar
 * Expected: $title, $active (menu key), $user
 */
if (empty($title)) $title = APP_NAME;
if (empty($active)) $active = 'dashboard';
$role = $user['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($title); ?> | <?php echo e(APP_NAME); ?></title>
<link rel="stylesheet" href="<?php echo e(url('assets/vendor/bootstrap/bootstrap.min.css')); ?>">
<link rel="stylesheet" href="<?php echo e(url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')); ?>">
<link rel="stylesheet" href="<?php echo e(url('assets/css/style.css')); ?>">
</head>
<body>
<div class="app-wrapper">
    <!-- Sidebar -->
    <aside class="sidebar bg-dark" id="appSidebar">
        <div class="sidebar-brand">
            <a href="<?php echo e(url($role === 'student' ? 'student/index.php' : ($role === 'teacher' ? 'teacher/index.php' : 'admin/index.php'))); ?>" class="text-decoration-none d-flex align-items-center gap-2 text-white">
                <i class="bi bi-mortarboard-fill fs-3 text-warning"></i>
                <span class="fw-bold lh-sm"><?php echo e(APP_NAME); ?></span>
            </a>
        </div>
        <nav class="sidebar-menu">
        <?php
        // Define menu per role
        if ($role === ROLE_SUPERADMIN) { ?>
            <?php if (module_enabled('dashboard')): ?><a class="menu-link <?php echo $active === 'dashboard' ? 'active' : ''; ?>" href="<?php echo e(url('admin/index.php')); ?>"><i class="bi bi-speedometer2"></i> Dashboard</a><?php endif; ?>
            <?php if (module_enabled('about')): ?><a class="menu-link <?php echo $active === 'about' ? 'active' : ''; ?>" href="<?php echo e(url('about.php')); ?>"><i class="bi bi-info-circle"></i> About This Project</a><?php endif; ?>
            <div class="menu-head">Academic Structure</div>
            <?php if (module_enabled('programs')): ?><a class="menu-link <?php echo $active === 'programs' ? 'active' : ''; ?>" href="<?php echo e(url('admin/programs.php')); ?>"><i class="bi bi-bank"></i> Programs</a><?php endif; ?>
            <?php if (module_enabled('academic_years')): ?><a class="menu-link <?php echo $active === 'academic_years' ? 'active' : ''; ?>" href="<?php echo e(url('admin/academic_years.php')); ?>"><i class="bi bi-calendar3"></i> Academic Years</a><?php endif; ?>
            <?php if (module_enabled('batches')): ?><a class="menu-link <?php echo $active === 'batches' ? 'active' : ''; ?>" href="<?php echo e(url('admin/batches.php')); ?>"><i class="bi bi-people-fill"></i> Batches</a><?php endif; ?>
            <?php if (module_enabled('semesters')): ?><a class="menu-link <?php echo $active === 'semesters' ? 'active' : ''; ?>" href="<?php echo e(url('admin/semesters.php')); ?>"><i class="bi bi-layers"></i> Semesters</a><?php endif; ?>
            <?php if (module_enabled('sections')): ?><a class="menu-link <?php echo $active === 'sections' ? 'active' : ''; ?>" href="<?php echo e(url('admin/sections.php')); ?>"><i class="bi bi-grid-3x3-gap"></i> Sections</a><?php endif; ?>

            <div class="menu-head">Teaching & Learning</div>
            <?php if (module_enabled('subjects')): ?><a class="menu-link <?php echo $active === 'subjects' ? 'active' : ''; ?>" href="<?php echo e(url('admin/subjects.php')); ?>"><i class="bi bi-book"></i> Subjects</a><?php endif; ?>
            <?php if (module_enabled('teachers')): ?><a class="menu-link <?php echo $active === 'teachers' ? 'active' : ''; ?>" href="<?php echo e(url('admin/teachers.php')); ?>"><i class="bi bi-person-video3"></i> Teachers</a><?php endif; ?>
            <?php if (module_enabled('subject_assignments')): ?><a class="menu-link <?php echo $active === 'teacher_subjects' ? 'active' : ''; ?>" href="<?php echo e(url('admin/teacher_subjects.php')); ?>"><i class="bi bi-person-check"></i> Subject Assignments</a><?php endif; ?>
            <?php if (module_enabled('students')): ?><a class="menu-link <?php echo $active === 'students' ? 'active' : ''; ?>" href="<?php echo e(url('admin/students.php')); ?>"><i class="bi bi-person-lines-fill"></i> Students</a><?php endif; ?>
            <?php if (module_enabled('semester_enrollment') && module_enabled('student_subjects')): ?><a class="menu-link <?php echo $active === 'student_subjects' ? 'active' : ''; ?>" href="<?php echo e(url('admin/student_subjects.php')); ?>"><i class="bi bi-ui-checks-grid"></i> Semester Enrollment</a><?php endif; ?>

            <div class="menu-head">Examination</div>
            <?php if (module_enabled('examinations')): ?><a class="menu-link <?php echo $active === 'exams' ? 'active' : ''; ?>" href="<?php echo e(url('admin/exams.php')); ?>"><i class="bi bi-patch-check"></i> Examinations</a><?php endif; ?>
            <?php if (module_enabled('review_marks')): ?><a class="menu-link <?php echo $active === 'verify_marks' ? 'active' : ''; ?>" href="<?php echo e(url('admin/verify_marks.php')); ?>"><i class="bi bi-clipboard-check"></i> Review Marks</a><?php endif; ?>
            <?php if (module_enabled('view_student_result')): ?><a class="menu-link <?php echo $active === 'view_result' ? 'active' : ''; ?>" href="<?php echo e(url('admin/view_result.php')); ?>"><i class="bi bi-search"></i> View Student Result</a><?php endif; ?>

            <div class="menu-head">Results</div>
            <a class="menu-link <?php echo $active === 'results' ? 'active' : ''; ?>" href="<?php echo e(url('admin/results.php')); ?>"><i class="bi bi-file-earmark-spreadsheet"></i> Result Processing</a>
            <?php if (module_enabled('reports')): ?><a class="menu-link <?php echo $active === 'reports' ? 'active' : ''; ?>" href="<?php echo e(url('admin/reports.php')); ?>"><i class="bi bi-flag"></i> Reports</a><?php endif; ?>
            <?php if (module_enabled('analytics')): ?><a class="menu-link <?php echo $active === 'analytics' ? 'active' : ''; ?>" href="<?php echo e(url('admin/analytics.php')); ?>"><i class="bi bi-bar-chart-line"></i> Analytics</a><?php endif; ?>

            <div class="menu-head">Administration</div>
            <a class="menu-link <?php echo $active === 'notices' ? 'active' : ''; ?>" href="<?php echo e(url('admin/notices.php')); ?>"><i class="bi bi-megaphone"></i> Notices</a>
            <a class="menu-link <?php echo $active === 'grades' ? 'active' : ''; ?>" href="<?php echo e(url('admin/grades.php')); ?>"><i class="bi bi-trophy"></i> Grading Rules</a>
            <a class="menu-link <?php echo $active === 'audit' ? 'active' : ''; ?>" href="<?php echo e(url('admin/audit_logs.php')); ?>"><i class="bi bi-clock-history"></i> Audit Log</a>
            <a class="menu-link <?php echo $active === 'settings' ? 'active' : ''; ?>" href="<?php echo e(url('admin/settings.php')); ?>"><i class="bi bi-gear"></i> Settings</a>
        <?php }
        elseif ($role === ROLE_TEACHER) { ?>
            <a class="menu-link <?php echo $active === 'dashboard' ? 'active' : ''; ?>" href="<?php echo e(url('teacher/index.php')); ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <?php if (module_enabled('about')): ?><a class="menu-link <?php echo $active === 'about' ? 'active' : ''; ?>" href="<?php echo e(url('about.php')); ?>"><i class="bi bi-info-circle"></i> About This Project</a><?php endif; ?>
            <a class="menu-link <?php echo $active === 'mark_entry' ? 'active' : ''; ?>" href="<?php echo e(url('teacher/mark_entry.php')); ?>"><i class="bi bi-pencil-square"></i> Enter Marks</a>
            <a class="menu-link <?php echo $active === 'students' ? 'active' : ''; ?>" href="<?php echo e(url('teacher/students.php')); ?>"><i class="bi bi-people"></i> My Students</a>
            <a class="menu-link <?php echo $active === 'submitted' ? 'active' : ''; ?>" href="<?php echo e(url('teacher/submitted_marks.php')); ?>"><i class="bi bi-clipboard-check"></i> Submitted Marks</a>
            <a class="menu-link <?php echo $active === 'preview' ? 'active' : ''; ?>" href="<?php echo e(url('teacher/result_preview.php')); ?>"><i class="bi bi-eye"></i> Result Preview</a>
        <?php }
        else { ?>
            <a class="menu-link <?php echo $active === 'dashboard' ? 'active' : ''; ?>" href="<?php echo e(url('student/index.php')); ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <?php if (module_enabled('about')): ?><a class="menu-link <?php echo $active === 'about' ? 'active' : ''; ?>" href="<?php echo e(url('about.php')); ?>"><i class="bi bi-info-circle"></i> About This Project</a><?php endif; ?>
            <a class="menu-link <?php echo $active === 'result' ? 'active' : ''; ?>" href="<?php echo e(url('student/result.php')); ?>"><i class="bi bi-file-earmark-text"></i> My Result</a>
            <a class="menu-link <?php echo $active === 'history' ? 'active' : ''; ?>" href="<?php echo e(url('student/history.php')); ?>"><i class="bi bi-book-half"></i> Academic History</a>
            <a class="menu-link <?php echo $active === 'notices' ? 'active' : ''; ?>" href="<?php echo e(url('student/notices.php')); ?>"><i class="bi bi-megaphone"></i> Notices</a>
            <a class="menu-link <?php echo $active === 'profile' ? 'active' : ''; ?>" href="<?php echo e(url('student/profile.php')); ?>"><i class="bi bi-person-circle"></i> Profile</a>
        <?php } ?>
        </nav>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Main -->
    <div class="main-area">
        <header class="topbar">
            <button class="btn btn-outline-secondary d-lg-none" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
            <h1 class="topbar-title mb-0 d-none d-md-block"><?php echo e($title); ?></h1>
            <div class="ms-auto d-flex align-items-center gap-3">
                <a href="<?php echo e(url('auth/logout.php')); ?>" class="btn btn-sm btn-outline-danger" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                        <span class="avatar-circle bg-primary text-white"><?php echo e(strtoupper(substr($user['name'] ?? 'U', 0, 1))); ?></span>
                        <span class="d-none d-sm-inline"><?php echo e($user['name'] ?? ''); ?></span>
                        <span class="badge text-bg-<?php echo $role === 'superadmin' ? 'warning' : ($role === 'teacher' ? 'info' : 'success'); ?> text-uppercase"><?php echo e($role); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><a class="dropdown-item" href="<?php echo e(url('index.php')); ?>"><i class="bi bi-house me-2"></i> Home</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?php echo e(url('auth/logout.php')); ?>"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>
        <main class="page-content">
            <div class="container-fluid">
                <?php render_flash(); ?>