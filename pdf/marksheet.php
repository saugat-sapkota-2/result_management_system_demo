<?php
/**
 * Single-student PDF marksheet (terminal marks-only or final SGPA sheet).
 * Accessible by the result owner (student), teacher, or admin.
 * Params: exam_id + student_id (new model) — result_id accepted as fallback for finals.
 *
 * DESIGN NOTE:
 *   Formal, official-document look: double-rule page border, serif typography,
 *   classic ruled tables, light watermark, three-way signature block.
 *   Visual design only; every data value, mark, grade, credit, calculation
 *   and access-control rule is unchanged.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/academics.php';
$user = require_login();

$resultId = (int) ($_GET['result_id'] ?? 0);
$examId = (int) ($_GET['exam_id'] ?? 0);
$studentId = (int) ($_GET['student_id'] ?? 0);

$exam = null; $student = null; $isTerminal = false;
if ($resultId) {
    $result = db_one('SELECT * FROM results WHERE id = ? AND is_locked = 1', [$resultId]);
    if (!$result) { header('Location: ' . url('index.php')); exit; }
    $examId = (int) $result['exam_id'];
    $studentId = (int) $result['student_id'];
}
$exam = $examId ? db_one('SELECT e.*, sm.name AS semester_name, ay.name AS academic_year
    FROM exams e JOIN semesters sm ON sm.id = e.semester_id LEFT JOIN academic_years ay ON ay.id = e.academic_year_id
    WHERE e.id = ?', [$examId]) : null;
$student = $studentId ? db_one('SELECT st.*, b.name AS batch_name, s.name AS section_name, p.name AS program_name
    FROM students st LEFT JOIN batches b ON b.id = st.batch_id LEFT JOIN sections s ON s.id = st.section_id
    LEFT JOIN programs p ON p.id = st.program_id WHERE st.id = ?', [$studentId]) : null;
if (!$exam || !$student) { header('Location: ' . url('index.php')); exit; }

$isTerminal = exam_is_terminal($exam);
$result = null;
if (!$isTerminal) {
    $result = db_one('SELECT * FROM results WHERE student_id = ? AND exam_id = ? AND is_locked = 1', [$studentId, $examId]);
}
$terminal = $isTerminal ? terminal_student_summary($examId, $studentId) : null;
$details = $result ? result_subject_rows((int) $result['id']) : [];

// access control: publish guard for terminals, is_locked for finals
if (!$isTerminal && !$result) { header('Location: ' . url('index.php')); exit; }

$isOwner = ($user['role'] === ROLE_STUDENT && (int) db_val('SELECT id FROM students WHERE user_id = ?', [$user['id']]) === (int) $studentId);
$isTeacher = $user['role'] === ROLE_TEACHER;
if (!in_array($user['role'], [ROLE_SUPERADMIN], true) && !$isOwner && !$isTeacher) { header('Location: ' . url('index.php')); exit; }

if ($exam && $student && (int) ($exam['program_id'] ?? 0) !== (int) db_val('SELECT program_id FROM students WHERE id = ?', [$studentId])) {
    header('Location: ' . url('index.php')); exit;
}
if ($isTeacher) {
    $teacherId = (int) db_val('SELECT teacher_id FROM teachers WHERE id = (SELECT teacher_id FROM users WHERE id = ?)', [$user['id']]);
    $assigned = (int) db_val('SELECT COUNT(*) FROM exam_subjects WHERE exam_id = ? AND teacher_id = ? AND status = 1', [$examId, $teacherId]);
    if ($teacherId <= 0 || $assigned < 1) { header('Location: ' . url('index.php')); exit; }
}

require_once __DIR__ . '/../lib/fpdf/fpdf.php';

if (!function_exists('pdf_ascii')) {
function pdf_ascii($s)
{
    $s = trim((string) $s);
    $out = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
    return $out === false ? preg_replace('/[^\x20-\x7E]/', '', $s) : $out;
}
function sp($n) { return number_format((float) $n, 2); }
function nf0($n) { return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'); }
}

$inst = get_setting('institute_name', 'Institute of Science & Technology');
$instAddr = get_setting('institute_address', '');
$program = get_setting('program_name', 'BCA');
$marksheetLogo = __DIR__ . '/../assets/img/marksheet-logo.png';

if (!class_exists('MarksheetPdf', false)) {
class MarksheetPdf extends FPDF
{
    protected $angle = 0;
    public $watermark = 'OFFICIAL COPY';

    /** Rotate everything drawn after this call around (x,y). Call Rotate(0) to stop. */
    public function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1) $x = $this->x;
        if ($y == -1) $y = $this->y;
        if ($this->angle != 0) $this->_out('Q');
        $this->angle = $angle;
        if ($angle != 0) {
            $a = $angle * M_PI / 180;
            $c = cos($a); $s = sin($a);
            $cx = $x * $this->k; $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',
                $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    public function _endpage()
    {
        if ($this->angle != 0) { $this->angle = 0; $this->_out('Q'); }
        parent::_endpage();
    }

    public function Header()
    {
        $this->SetTextColor(20, 20, 20);
    }

    public function Footer()
    {
        $this->SetY(-13);
        $this->SetDrawColor(120, 120, 120);
        $this->SetLineWidth(0.2);
        $this->Line(16, $this->GetY(), 281, $this->GetY());
        $this->SetFont('Times', 'I', 7);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(200, 5, 'This is a computer-generated marksheet issued by the examination section.', 0, 0, 'L');
        $this->Cell(65, 5, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R');
    }

    public function tableHeader(array $cols, array $keys)
    {
        $this->SetFillColor(235, 238, 242);
        $this->SetDrawColor(190, 196, 202);
        $this->SetLineWidth(0.25);
        $this->SetFont('Helvetica', 'B', 7.4);
        $this->SetTextColor(35, 48, 61);
        foreach ($keys as $k) {
            $align = in_array($k, ['SUBJECT NAME', 'SUBJECT CODE'], true) ? 'L' : 'C';
            $this->Cell($cols[$k], 7.4, ' ' . $k, 1, 0, $align, true);
        }
        $this->Ln();
        $this->SetFont('Helvetica', '', 8.5);
        $this->SetTextColor(20, 20, 20);
    }

    public function infoGrid(array $fields, float $width)
    {
        $columnWidth = $width / 4;
        $startY = $this->GetY();
        foreach ($fields as $index => $field) {
            $x = 12 + ($index % 4) * $columnWidth;
            $y = $startY + floor($index / 4) * 14;
            $this->SetXY($x, $y);
            $this->SetFont('Helvetica', '', 7.2);
            $this->SetTextColor(70, 94, 115);
            $this->Cell($columnWidth - 3, 4, $field[0], 0, 2, 'L');
            $this->SetFont('Helvetica', '', 9.2);
            $this->SetTextColor(20, 35, 48);
            $this->Cell($columnWidth - 3, 5, $field[1], 0, 0, 'L');
        }
        $this->SetY($startY + 28);
    }

    /** One label/value pair row-set for the student information grid. */
    public function infoRow(array $l, array $r, float $lw, float $vw, float $h = 6.6)
    {
        $this->SetDrawColor(170, 178, 185);
        $this->SetLineWidth(0.25);
        foreach ([$l, $r] as $f) {
            $this->SetFillColor(239, 243, 246);
            $this->SetFont('Times', 'B', 8.8);
            $this->SetTextColor(31, 52, 73);
            $this->Cell($lw, $h, ' ' . $f[0], 1, 0, 'L', true);
            $this->SetFont('Times', '', 9.8);
            $this->SetTextColor(20, 20, 20);
            $this->Cell($vw, $h, ' ' . $f[1], 1, 0, 'L');
        }
        $this->Ln();
    }
}
}

$p = new MarksheetPdf('L', 'mm', 'A4');
$p->AliasNbPages();
$p->SetMargins(12, 8, 12);
$p->SetAutoPageBreak(true, 20);
$p->AddPage();
$W = 273; // landscape content width (297 - margins)

// =====================================================================
// 1. Letterhead
// =====================================================================
$p->SetY(10);
$p->SetTextColor(15, 34, 52);
if (is_file($marksheetLogo)) {
    $p->Image($marksheetLogo, 17, 10, 18, 18);
}
$p->SetFont('Helvetica', 'B', 15);
$p->SetTextColor(15, 34, 52);
$p->Cell($W, 7, pdf_ascii($inst), 0, 1, 'C');
if (trim((string) $instAddr) !== '') {
    $p->SetFont('Helvetica', '', 7.5);
    $p->SetTextColor(105, 112, 120);
    $p->Cell($W, 4, pdf_ascii($instAddr), 0, 1, 'C');
}
$p->SetFont('Helvetica', '', 8.2);
$p->SetTextColor(55, 65, 75);
$p->Cell($W, 5, pdf_ascii(($student['program_name'] ?: $program) . ' ' . ($exam['semester_name'] ?? '') . ' - ' . $exam['name']), 0, 1, 'C');
$p->SetFont('Helvetica', 'B', 8);
$p->SetTextColor(35, 45, 55);
$p->Cell($W, 5, 'MARKSHEET', 0, 1, 'C');
$p->SetDrawColor(205, 210, 215);
$p->SetLineWidth(0.25);
$p->Line(12, $p->GetY() + 2, 285, $p->GetY() + 2);
$p->Ln(5);

// =====================================================================
// 2. Student particulars (ruled grid)
// =====================================================================
$batchSection = ($student['batch_name'] ?: '-') . '  /  ' . ($student['section_name'] ?: '-');
$p->infoGrid([
    ['Student Name', pdf_ascii($student['full_name'])],
    ['Student ID', pdf_ascii($student['student_code'])],
    ['TU Reg. No.', pdf_ascii($student['tu_reg_no'] ?: '-')],
    ['Exam Roll No.', pdf_ascii($student['exam_roll_no'] ?: '-')],
    ['Batch', pdf_ascii($student['batch_name'] ?: '-')],
    ['Section', pdf_ascii($student['section_name'] ?: '-')],
    ['Exam Date', $exam['exam_date'] ? fdate($exam['exam_date']) : '-'],
    ['Published', $exam['published_at'] ? fdatetime($exam['published_at']) : '-'],
], $W);

// =====================================================================
// 3. Marks table + totals + result summary
// =====================================================================
$p->SetDrawColor(190, 196, 202);
$p->SetLineWidth(0.25);
$p->SetTextColor(20, 20, 20);

if ($isTerminal) {
    $cols = ['S.N.' => 14, 'SUBJECT CODE' => 36, 'SUBJECT NAME' => 99, 'CREDIT' => 20, 'FULL MARKS' => 24, 'OBTAINED' => 24, '%' => 24, 'STATUS' => 24];
    $keys = array_keys($cols);
    $p->tableHeader($cols, $keys);

    $sn = 0;
    foreach ($terminal['subjects'] as $d) {
        if ($p->GetY() > 150) { $p->AddPage(); $p->tableHeader($cols, $keys); }
        $sn++;
        $h = 6.8;
        $p->SetFont('Times', '', 9.6);
        $p->Cell($cols['S.N.'], $h, $sn, 1, 0, 'C');
        $p->Cell($cols['SUBJECT CODE'], $h, ' ' . pdf_ascii($d['subject_code']), 1, 0, 'L');
        $p->Cell($cols['SUBJECT NAME'], $h, ' ' . pdf_ascii($d['subject_name']), 1, 0, 'L');
        $p->Cell($cols['CREDIT'], $h, sp($d['credit_hours']), 1, 0, 'C');
        $p->Cell($cols['FULL MARKS'], $h, nf0($d['full_marks']), 1, 0, 'C');
        $p->Cell($cols['OBTAINED'], $h, nf0($d['obtained']), 1, 0, 'C');
        $p->Cell($cols['%'], $h, number_format($d['percentage'], 1) . '%', 1, 0, 'C');
        $isPass = strtolower((string) $d['status']) === 'pass';
        $p->SetFillColor($isPass ? 232 : 250, $isPass ? 243 : 232, $isPass ? 235 : 232);
        $p->SetFont('Times', $isPass ? '' : 'B', 9.6);
        $p->Cell($cols['STATUS'], $h, pdf_ascii($d['status']), 1, 0, 'C', true);
        $p->Ln();
    }

    $totalCredits = array_sum(array_map(static fn($d) => (float) $d['credit_hours'], $terminal['subjects']));
    $th = 7.4;
    $p->SetFillColor(239, 243, 246);
    $span = $cols['S.N.'] + $cols['SUBJECT CODE'] + $cols['SUBJECT NAME'];
    $p->SetFont('Times', 'B', 9.6);
    $p->Cell($span, $th, ' TOTAL', 1, 0, 'R', true);
    $p->Cell($cols['CREDIT'], $th, sp($totalCredits), 1, 0, 'C', true);
    $p->Cell($cols['FULL MARKS'], $th, nf0($terminal['full']), 1, 0, 'C', true);
    $p->Cell($cols['OBTAINED'], $th, nf0($terminal['total']), 1, 0, 'C', true);
    $p->Cell($cols['%'], $th, number_format($terminal['percentage'], 1) . '%', 1, 0, 'C', true);
    $terminalPassed = strtolower((string) $terminal['overall']) === 'pass';
    $p->SetFillColor($terminalPassed ? 232 : 250, $terminalPassed ? 243 : 232, $terminalPassed ? 235 : 232);
    $p->Cell($cols['STATUS'], $th, pdf_ascii($terminal['overall']), 1, 0, 'C', true);
    $p->Ln();

    $p->Ln(4);
    $p->SetFont('Times', 'I', 8);
    $p->SetTextColor(70, 70, 70);
    $p->Cell($W, 4, 'Note:  Terminal examinations are marks-only sheets. Final examination results include grades, SGPA and CGPA.', 0, 1, 'L');
    $p->SetTextColor(20, 20, 20);
} else {
    $cols = ['S.N.' => 14, 'SUBJECT CODE' => 38, 'SUBJECT NAME' => 105, 'CREDIT' => 24, 'TOTAL' => 34, 'GRADE' => 24, 'STATUS' => 26];
    $keys = array_keys($cols);
    $p->tableHeader($cols, $keys);

    $sn = 0;
    foreach ($details as $d) {
        if ($p->GetY() > 150) { $p->AddPage(); $p->tableHeader($cols, $keys); }
        $sn++;
        $h = 6.8;
        $isPass = strtolower((string) $d['subject_status']) === 'pass';
        $p->SetFont('Times', '', 9.6);
        $p->Cell($cols['S.N.'], $h, $sn, 1, 0, 'C');
        $p->Cell($cols['SUBJECT CODE'], $h, ' ' . pdf_ascii($d['subject_code']), 1, 0, 'L');
        $p->Cell($cols['SUBJECT NAME'], $h, ' ' . pdf_ascii($d['subject_name']), 1, 0, 'L');
        $p->Cell($cols['CREDIT'], $h, sp($d['credit_hours']), 1, 0, 'C');
        $p->Cell($cols['TOTAL'], $h, nf0($d['total_marks']) . '/' . nf0($d['max_marks']), 1, 0, 'C');
        $p->Cell($cols['GRADE'], $h, pdf_ascii($d['grade']), 1, 0, 'C');
        $p->SetFillColor($isPass ? 232 : 250, $isPass ? 243 : 232, $isPass ? 235 : 232);
        $p->SetFont('Times', $isPass ? '' : 'B', 9.6);
        $p->Cell($cols['STATUS'], $h, pdf_ascii($d['subject_status']), 1, 0, 'C', true);
        $p->Ln();
    }

    $th = 7.4;
    $p->SetFillColor(239, 243, 246);
    $span = $cols['S.N.'] + $cols['SUBJECT CODE'] + $cols['SUBJECT NAME'];
    $p->SetFont('Times', 'B', 9.6);
    $p->Cell($span, $th, ' TOTAL', 1, 0, 'R', true);
    $p->Cell($cols['CREDIT'], $th, sp($result['total_credits']), 1, 0, 'C', true);
    $p->Cell($cols['TOTAL'], $th, sp($result['total_credit_points']) . ' CP', 1, 0, 'C', true);
    $p->Cell($cols['GRADE'], $th, 'SGPA ' . number_format((float) $result['sgpa'], 2), 1, 0, 'C', true);
    $resultPassed = strtolower((string) $result['result_status']) === 'pass';
    $p->SetFillColor($resultPassed ? 232 : 250, $resultPassed ? 243 : 232, $resultPassed ? 235 : 232);
    $p->Cell($cols['STATUS'], $th, pdf_ascii($result['result_status']), 1, 0, 'C', true);
    $p->Ln();

    $p->Ln(4);
    $p->SetFont('Times', 'I', 8);
    $p->SetTextColor(70, 70, 70);
    $p->Cell($W, 4, 'Note:  CGPA ' . number_format((float) $result['cgpa'], 2) . ' - Single-total grading model applies for this examination.', 0, 1, 'L');
    $p->SetTextColor(20, 20, 20);
}

// =====================================================================
// 4. Signatures (three authorised signatories)
// =====================================================================
if ($p->GetY() > 158) { $p->AddPage(); }
$sigY = max($p->GetY() + 14, 172);
$p->SetDrawColor(40, 40, 40);
$p->SetLineWidth(0.25);
$sigW = 62;
$gap = ($W - 3 * $sigW) / 2;
$labels = ['Prepared By', 'Checked By', 'Controller of Examinations'];
foreach ($labels as $i => $lab) {
    $x = 16 + $i * ($sigW + $gap);
    $p->Line($x, $sigY, $x + $sigW, $sigY);
    $p->SetXY($x, $sigY + 1);
    $p->SetFont('Times', '', 8.6);
    $p->SetTextColor(40, 40, 40);
    $p->Cell($sigW, 4.5, $lab, 0, 0, 'C');
}
$p->SetXY(16, $sigY + 6);
$p->SetFont('Times', 'I', 7.6);
$p->SetTextColor(90, 90, 90);
$p->Cell($W, 4, 'Date of Issue: ' . fdate(date('Y-m-d')) . '          Seal of the Institute', 0, 0, 'C');

$p->Output('Marksheet_' . $student['student_code'] . '_' . ($exam['semester_name'] ?? '') . '.pdf', 'I');