<?php
/**
 * RMS -- Shared helper functions
 */

/** HTML escape */
function e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Redirect to a local URL */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Redirect using the app base path */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Set a one-time flash message */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Render and clear flash messages */
function render_flash(): void
{
    if (empty($_SESSION['flash'])) return;
    foreach ($_SESSION['flash'] as $f) {
        $allowed = ['success', 'danger', 'warning', 'info'];
        $type = in_array($f['type'], $allowed, true) ? $f['type'] : 'info';
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show shadow-sm" role="alert">'
            . e($f['message'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['flash']);
}

/** Log an action to the audit trail */
function audit(string $action, string $description = '', ?int $recordId = null): void
{
    try {
        db_run(
            'INSERT INTO audit_logs (user_id, role, action, description, record_id, ip_address) VALUES (?,?,?,?,?,?)',
            [user_id_or_null(), user_role_or_null(), $action, $description, $recordId, $_SERVER['REMOTE_ADDR'] ?? null]
        );
    } catch (Throwable $ex) {
        // audit should never crash the page
    }
}

function user_id_or_null(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function user_role_or_null(): ?string
{
    return $_SESSION['role'] ?? null;
}

/** Get a setting value (cached per request) */
function get_setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all('SELECT setting_key, setting_value FROM system_settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

/** Save a setting value */
function set_setting(string $key, $value): void
{
    $sql = 'INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)';
    db_run($sql, [$key, (string) $value]);
}

/** Controlled admin modules. Values are stored as module_<key> system settings. */
function module_definitions(): array
{
    static $modules = null;
    if ($modules === null) {
        $modules = [
            'dashboard' => 'Dashboard',
            'programs' => 'Programs',
            'academic_years' => 'Academic Years',
            'batches' => 'Batches',
            'semesters' => 'Semesters',
            'sections' => 'Sections',
            'subjects' => 'Subjects',
            'teachers' => 'Teachers',
            'subject_assignments' => 'Subject Assignments',
            'students' => 'Students',
            'semester_enrollment' => 'Semester Enrollment',
            'examinations' => 'Examinations',
            'review_marks' => 'Review Marks',
            'view_student_result' => 'View Student Result',
            'reports' => 'Reports',
            'analytics' => 'Analytics',
            'student_profile' => 'Student Profile',
            'student_subjects' => 'Student Subjects',
            'about' => 'About This Project',
            'settings' => 'Settings',
        ];
    }
    return $modules;
}

/** Load all module flags once per request. Missing flags default to enabled. */
function module_visibility(): array
{
    static $visibility = null;
    if ($visibility === null) {
        $visibility = [];
        foreach (module_definitions() as $key => $label) {
            $visibility[$key] = (int) get_setting('module_' . $key, 1) === 1;
        }
        // Settings is the recovery/control plane and must remain reachable.
        $visibility['settings'] = true;
    }
    return $visibility;
}

function module_enabled(string $key): bool
{
    return array_key_exists($key, module_definitions()) && module_visibility()[$key];
}

/** Block a disabled module with a small, reusable response. */
function require_module(string $key): void
{
    if (module_enabled($key)) return;
    http_response_code(404);
    exit('<!DOCTYPE html><html><head><title>Module Disabled</title><link rel="stylesheet" href="' . e(url('assets/vendor/bootstrap/bootstrap.min.css')) . '"></head>
        <body class="bg-light d-flex align-items-center" style="min-height:100vh"><div class="container text-center">
        <h1 class="h2 fw-bold text-secondary">Module Disabled</h1><p class="lead">This module is currently disabled by the administrator.</p>
        <a class="btn btn-primary" href="' . e(url('admin/settings.php')) . '">Back to Settings</a></div></body></html>');
}

/**
 * Resolve a letter grade + grade point for a percentage.
 * Uses the configurable `grades` table.
 */
function get_grade(float $percentage): array
{
    $row = db_one(
        'SELECT grade, grade_point FROM grades
         WHERE status = 1 AND min_percent <= ? AND ? <= max_percent
         ORDER BY min_percent DESC LIMIT 1',
        [$percentage, $percentage]
    );
    return $row ? ['grade' => $row['grade'], 'grade_point' => (float) $row['grade_point']] : ['grade' => 'F', 'grade_point' => 0.0];
}

/** Round a numeric mark cleanly */
function marks_round($v): string
{
    if ($v === null || $v === '') return '0';
    return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
}

/** Safe number input */
function num($v): float
{
    return is_numeric($v) ? (float) $v : 0.0;
}

/** Format SGPA / CGPA */
function fmt_gpa($v, int $decimals = 2): string
{
    return number_format((float) $v, $decimals, '.', '');
}

/** Pagination helper. Returns [rows, paginationHtml] */
function paginate(PDOStatement $countStmt, array $countParams, int $page, int $perPage = 20): array
{
    $countStmt->execute($countParams);
    $total = (int) $countStmt->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;

    $html = '<nav aria-label="Pagination"><ul class="pagination pagination-sm mb-0">';
    $base = strtok($_SERVER['REQUEST_URI'], '?');
    $qs = $_GET;
    for ($i = 1; $i <= $pages; $i++) {
        $qs['page'] = $i;
        $qsStr = http_build_query($qs);
        $active = $i === $page ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . e($base . '?' . $qsStr) . '">' . $i . '</a></li>';
    }
    $html .= '</ul></nav>';

    return ['total' => $total, 'page' => $page, 'pages' => $pages, 'offset' => $offset, 'pagination' => $html];
}

/** Friendly date — BS format (BD → BS). Recognizes AD date or datetime stored in DB. */
function fdate($d): string
{
    if ($d === null || $d === '' || $d === '0000-00-00' || $d === '0000-00-00 00:00:00') return '—';
    return bsDisplay($d);
}

/** Friendly date+time — BS format, suitable for created_at/updated_at/published timestamps. */
function fdatetime($d): string
{
    if ($d === null || $d === '' || $d === '0000-00-00 00:00:00' || $d === '0000-00-00') return '—';
    return formatBsDateTime($d);
}

/** Badge-style label for result / mark status */
function status_badge(string $status): string
{
    $map = [
        'Pass' => 'success',
        'Fail' => 'danger',
        'Pending' => 'secondary',
        'Absent' => 'warning',
        'Withheld' => 'dark',
        'Incomplete' => 'info',
        'Not Eligible' => 'danger',
        'Present' => 'success',
        'calculated' => 'primary',
        'verified' => 'info',
        'approved' => 'warning',
        'published' => 'success',
        'locked' => 'dark',
        'Reopen' => 'warning',
    ];
    return '<span class="badge bg-' . ($map[$status] ?? 'secondary') . '">' . e($status) . '</span>';
}

function bool_badge($v): string
{
    return $v ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
}

/** Checked state helper */
function checked($condition): string
{
    return $condition ? 'checked' : '';
}

/** Program name lookup (cached per request) */
function program_name_of(int $programId): string
{
    static $cache = [];
    if (!isset($cache[$programId])) {
        $cache[$programId] = (string) (db_val('SELECT name FROM programs WHERE id = ?', [$programId]) ?? '—');
    }
    return $cache[$programId];
}

/**
 * Shared client-side scoping: when a `<select.program-select>` changes, options of
 * each `[data-scope]` select are enabled/disabled by their `data-program`.
 */
function program_scope_script(): string
{
    return <<<JS
<script>
(function () {
    function apply(sel) {
        var pid = sel.value;
        var target = sel.getAttribute('data-target') || '';
        document.querySelectorAll('select[data-scope="' + target + '"]').forEach(function (s) {
            var any = false;
            Array.prototype.forEach.call(s.options, function (o) {
                var show = o.value === '' || (o.getAttribute('data-program') === pid);
                o.disabled = !show;
                if (show && o.selected) any = true;
                if (!show && o.selected) o.selected = false;
            });
            if (!any && s.options.length) s.options[0].selected = true;
        });
    }
    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('program-select')) apply(e.target);
    });
    document.querySelectorAll('select.program-select').forEach(apply);
})();
</script>
JS;
}

function selected($a, $b): string
{
    return (string) $a === (string) $b ? 'selected' : '';
}

/**
 * Auto-enrol a single student into every ACTIVE subject configured for their
 * program + current semester. Program isolation is enforced inside the query,
 * and the UNIQUE(student_id, subject_id) key prevents duplicates.
 * Returns ['new','duplicates','subjects'] counts.
 */
function auto_enroll_student(int $studentId): array
{
    $st = db_one('SELECT program_id, current_semester_id FROM students WHERE id = ?', [$studentId]);
    if (!$st || !(int) $st['program_id'] || !(int) $st['current_semester_id']) {
        return ['new' => 0, 'duplicates' => 0, 'subjects' => 0, 'configured' => false];
    }
    $subjects = db_all(
        'SELECT id FROM subjects WHERE status = 1 AND program_id = ? AND semester_id = ?',
        [(int) $st['program_id'], (int) $st['current_semester_id']]);
    if (!$subjects) {
        return ['new' => 0, 'duplicates' => 0, 'subjects' => 0, 'configured' => false];
    }
    $new = 0; $dup = 0;
    foreach ($subjects as $s) {
        $ins = db_run('INSERT IGNORE INTO student_subjects (student_id, subject_id) VALUES (?,?)', [$studentId, (int) $s['id']]);
        $ins > 0 ? $new++ : $dup++;
    }
    return ['new' => $new, 'duplicates' => $dup, 'subjects' => count($subjects), 'configured' => true];
}

/**
 * Bulk-enrol an entire batch/program cohort with the subjects configured for a
 * given semester. Students are matched on program_id + batch_id ONLY, so the
 * same subjects are never assigned to another program's students.
 * Returns ['students','subjects','new','duplicates','errors'] counts.
 */
function sync_semester_enrollment(int $programId, int $batchId, int $semesterId): array
{
    $programId = (int) $programId; $batchId = (int) $batchId; $semesterId = (int) $semesterId;
    if ($programId <= 0 || $batchId <= 0 || $semesterId <= 0) {
        return ['students' => 0, 'subjects' => 0, 'new' => 0, 'duplicates' => 0, 'errors' => 0];
    }
    $subjects = db_all(
        'SELECT id FROM subjects WHERE status = 1 AND program_id = ? AND semester_id = ?',
        [$programId, $semesterId]);
    $students = db_all(
        'SELECT id FROM students WHERE status = 1 AND program_id = ? AND batch_id = ?',
        [$programId, $batchId]);
    $new = 0; $dup = 0; $errors = 0;
    foreach ($students as $st) {
        foreach ($subjects as $s) {
            try {
                $ins = db_run('INSERT IGNORE INTO student_subjects (student_id, subject_id) VALUES (?,?)', [(int) $st['id'], (int) $s['id']]);
                $ins > 0 ? $new++ : $dup++;
            } catch (PDOException $ex) {
                $errors++;
            }
        }
    }
    return ['students' => count($students), 'subjects' => count($subjects), 'new' => $new, 'duplicates' => $dup, 'errors' => $errors];
}

/** Supervisor token for CSRF */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        flash('danger', 'Security token mismatch. Please refresh the page and try again.');
        redirect(url($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    }
}

/** Current active academic year id */
function current_academic_year_id(): ?int
{
    $id = db_val('SELECT id FROM academic_years WHERE is_current = 1 AND status = 1 LIMIT 1');
    return $id ? (int) $id : null;
}

/** Exam types dropdown data (ordered: 1st, 2nd, 3rd Terminal, Final). */
function exam_types(): array
{
    return db_all('SELECT * FROM exam_types WHERE status = 1 ORDER BY FIELD(id, 1,2,4,3), id');
}

/** Get an exam by id or 404 */
function exam_or_fail(int $id): array
{
    $exam = db_one('SELECT * FROM exams WHERE id = ?', [$id]);
    if (!$exam) {
        http_response_code(404);
        exit('Exam not found.');
    }
    return $exam;
}

/** Does a published/locked result exist for this student + exam? */
function result_is_locked(int $studentId, int $examId): bool
{
    return (bool) db_val(
        'SELECT COUNT(*) FROM results WHERE student_id = ? AND exam_id = ? AND is_locked = 1',
        [$studentId, $examId]
    );
}

/** Returned grading info: percent needed to pass a subject. */
function grading_rules(): array
{
    return [
        'pass_percent' => (float) get_setting('pass_percent', 40), // 40% overall pass
    ];
}

/** Exam lifecycle helpers (simple model) */

/** Exam type code ('first_terminal' | 'second_terminal' | 'third_terminal' | 'final'). */
function exam_type_code(?int $typeId): string
{
    if (!$typeId) return '';
    return (string) db_val('SELECT code FROM exam_types WHERE id = ?', [$typeId]);
}

/** True when the exam is a terminal (non-final) exam. */
function exam_is_terminal(mixed $exam): bool
{
    $code = is_array($exam) ? ($exam['exam_type_code'] ?? exam_type_code((int) ($exam['exam_type_id'] ?? 0))) : exam_type_code((int) ($exam ?? 0));
    return in_array($code, ['first_terminal', 'second_terminal', 'third_terminal'], true);
}

/** Derived exam status for display: draft / marks_entry / submitted / published. */
function exam_display_status(mixed $exam): array
{
    $row = is_array($exam) ? $exam : (db_one('SELECT * FROM exams WHERE id = ?', [(int) $exam]) ?: []);
    $es = $row['exam_status'] ?? 'draft';
    if ($es === 'published') return ['key' => 'published', 'label' => 'Published', 'class' => 'bg-success'];
    if ($es === 'marks_entry') {
        // 'submitted' when every active subject sheet has been submitted AND no sheet is under correction
        if ((int) db_val(
            'SELECT COUNT(*) FROM exam_subjects WHERE exam_id = ? AND status = 1 AND (marks_submitted = 0 OR reopened = 1)',
            [(int) ($row['id'] ?? 0)]
        ) === 0) {
            return ['key' => 'submitted', 'label' => 'Submitted', 'class' => 'bg-info text-dark'];
        }
        return ['key' => 'marks_entry', 'label' => 'Marks Entry', 'class' => 'bg-warning text-dark'];
    }
    return ['key' => 'draft', 'label' => 'Draft', 'class' => 'bg-secondary'];
}

/** Human label for a marks row status. */
function marks_status_label(array $row): array
{
    if ($row['status'] === 'Absent') return ['Absent', 'text-danger'];
    if ($row['status'] === 'Withheld') return ['Withheld', 'text-warning'];
    if ($row['status'] === 'Incomplete') return ['Incomplete', 'text-info'];
    if ($row['status'] === 'Not Eligible') return ['Not Eligible', 'text-muted'];
    return ['Present', ''];
}

/**
 * One subject block of a terminal marksheet for a student.
 * Returns keys: subject info + obtained/full/pct/status/grades.
 */
function terminal_subject_row(array $es, array $mark): array
{
    $full = (float) $es['full_marks'];
    $obtained = $mark ? (float) $mark['obtained'] : 0;
    $status = $mark['status'] ?? 'Present';
    $pct = $full > 0 ? ($obtained / $full) * 100 : 0;

    $hasIssues = in_array($status, ['Absent', 'Withheld', 'Incomplete', 'Not Eligible'], true);
    $passed = !$hasIssues && $pct >= (grading_rules()['pass_percent']);
    $subjStatus = $hasIssues ? $status : ($passed ? 'Pass' : 'Fail');
    $g = get_grade($pct);

    return [
        'subject_code' => $es['subject_code'],
        'subject_name' => $es['subject_name'],
        'credit_hours' => (float) ($es['credit_hours'] ?? 0),
        'full_marks' => $full,
        'obtained' => $obtained,
        'percentage' => $pct,
        'status' => $subjStatus,
        'marks_status' => $status,
        'grade' => $passed ? $g['grade'] : 'F',
        'grade_point' => $passed ? (float) $g['grade_point'] : 0.0,
    ];
}

/**
 * Terminal marksheet summary for a student across an exam's subjects.
 * Requires marks rows per (exam_subject, student) — rows may not all exist yet.
 */
function terminal_student_summary(int $examId, int $studentId): array
{
    $rows = db_all(
        "SELECT su.code subject_code, su.name subject_name, su.credit_hours,
                es.id es_id, es.full_marks, m.obtained, m.status marks_status
         FROM exam_subjects es
         JOIN subjects su ON su.id = es.subject_id
         LEFT JOIN marks m ON m.exam_subject_id = es.id AND m.student_id = ?
         WHERE es.exam_id = ? AND es.status = 1
         ORDER BY su.code", [$studentId, $examId]);

    $subjects = [];
    $total = 0.0; $full = 0.0;
    $hasAbsent = false; $hasWithheld = false; $hasIncomplete = false; $hasNonEligible = false; $hasFail = false;
    foreach ($rows as $es) {
        if ($es['marks_status'] === null) continue; // not yet entered
        $row = terminal_subject_row($es, $es);
        $subjects[] = $row;
        $total += $row['obtained'];
        $full += $row['full_marks'];
        if ($row['status'] === 'Absent') $hasAbsent = true;
        if ($row['status'] === 'Withheld') $hasWithheld = true;
        if ($row['status'] === 'Incomplete') $hasIncomplete = true;
        if ($row['status'] === 'Not Eligible') $hasNonEligible = true;
        if ($row['status'] === 'Fail') $hasFail = true;
    }
    $pct = $full > 0 ? ($total / $full) * 100 : 0;
    if ($hasAbsent) $overall = 'Absent';
    elseif ($hasWithheld) $overall = 'Withheld';
    elseif ($hasIncomplete) $overall = 'Incomplete';
    elseif ($hasNonEligible) $overall = 'Not Eligible';
    elseif ($hasFail) $overall = 'Fail';
    else $overall = 'Pass';

    return [
        'subjects' => $subjects,
        'total' => $total,
        'full' => $full,
        'percentage' => $pct,
        'overall' => $overall,
        'is_pass' => $overall === 'Pass',
        'entered' => count($subjects),
        'expected' => count($rows),
    ];
}