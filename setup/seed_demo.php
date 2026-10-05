<?php
/**
 * Rebuild the isolated bca_rms_demo database with synthetic demo data.
 * Run from the project root with: C:\xampp\php\php.exe setup\seed_demo.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$hash = static fn(string $password): string => password_hash($password, PASSWORD_DEFAULT);
$studentPasswordHash = $hash('student123');
$teacherPasswordHash = $hash('teacher123');
$insert = static function (PDO $pdo, string $table, array $data): int {
    $columns = array_keys($data);
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES ('
        . implode(',', array_fill(0, count($columns), '?')) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($data));
    return (int) $pdo->lastInsertId();
};

$firstNames = ['Aarav', 'Aashish', 'Aayusha', 'Anisha', 'Bibek', 'Bimala', 'Deepak', 'Diya', 'Gaurav', 'Isha',
    'Kiran', 'Manish', 'Maya', 'Nabin', 'Nikita', 'Pooja', 'Prabin', 'Prakriti', 'Rajan', 'Riya',
    'Roshan', 'Sagar', 'Samir', 'Sarita', 'Shreya', 'Suman', 'Sunita', 'Ujjwal', 'Anil', 'Kabita',
    'Nischal', 'Rupesh', 'Sneha', 'Bikash', 'Asmita', 'Ramesh', 'Sushmita', 'Binod', 'Elina', 'Kushal',
    'Nirajan', 'Sabina', 'Sanjay', 'Sweta', 'Dipesh', 'Manju', 'Pradeep', 'Alisha', 'Milan', 'Saraswati'];
$lastNames = ['Adhikari', 'Bhandari', 'Bhattarai', 'Gurung', 'Karki', 'Khadka', 'Lama', 'Magar', 'Maharjan', 'Poudel',
    'Rai', 'Sharma', 'Shrestha', 'Thapa', 'Tamang', 'Basnet', 'Dahal', 'Ghimire', 'Joshi', 'Regmi'];
$teacherNames = ['Dr. Ramesh Shrestha', 'Er. Sushil Karki', 'Anita Poudel', 'Bikram Thapa', 'Dr. Manisha Rai',
    'Prakash Adhikari', 'Sita Bhandari', 'Nabin Gurung', 'Kusum Maharjan', 'Rajendra Bhattarai'];
$programs = [
    ['Bachelor of Computer Applications', 'BCA'], ['Bachelor of Information Technology', 'BIT'],
    ['Bachelor of Business Administration', 'BBA'], ['Bachelor of Information Management', 'BIM'],
    ['Bachelor of Social Work', 'BSW'], ['Bachelor of Arts', 'BA'], ['Bachelor of Education', 'BEd'],
    ['Bachelor of Business Management', 'BBM'], ['Bachelor of Science in Computer Science', 'BScCSIT'],
    ['Master of Computer Science', 'MCS'],
];
$subjectTopics = ['Foundations', 'Communication', 'Programming', 'Database Systems', 'Web Technology',
    'Data Structures', 'Project Management', 'Research Methods'];
$subjectKinds = ['Theory', 'Theory', 'Practical', 'Theory', 'Elective'];
$sectionNames = ['Section A', 'Section B', 'Section C'];

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['audit_logs', 'result_details', 'results', 'marks', 'exam_subjects', 'exams', 'student_subjects',
        'teacher_subjects', 'users', 'students', 'teachers', 'subjects', 'semesters', 'batches', 'sections',
        'programs', 'academic_years', 'exam_types', 'grades', 'notices', 'system_settings'] as $table) {
        $pdo->exec('TRUNCATE TABLE `' . $table . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $pdo->beginTransaction();

    $yearIds = [];
    for ($i = 0; $i < 5; $i++) {
        $year = 2022 + $i;
        $yearIds[] = $insert($pdo, 'academic_years', [
            'name' => $year . '/' . substr((string) ($year + 1), -2),
            'start_date' => ($year - 57) . '-04-14',
            'end_date' => ($year - 56) . '-04-13',
            'is_current' => $i === 4 ? 1 : 0,
            'status' => 1,
        ]);
    }
    $currentYearId = end($yearIds);
    $adminId = $insert($pdo, 'users', [
        'username' => 'admin', 'password' => $hash('admin123'), 'role' => 'superadmin', 'status' => 1,
    ]);

    $sectionIds = [];
    foreach ($sectionNames as $section) {
        $sectionIds[] = $insert($pdo, 'sections', ['name' => $section, 'status' => 1]);
    }
    $examTypeIds = [];
    foreach ([['First Terminal', 'TERM1'], ['Second Terminal', 'TERM2'], ['Pre-Board', 'PRE'], ['Final Examination', 'FINAL']] as $type) {
        $examTypeIds[] = $insert($pdo, 'exam_types', ['name' => $type[0], 'code' => $type[1], 'status' => 1]);
    }
    foreach ([[0, 100, 'A+', 4], [90, 99.99, 'A', 3.7], [80, 89.99, 'B+', 3.3], [70, 79.99, 'B', 3],
        [60, 69.99, 'C+', 2.7], [50, 59.99, 'C', 2], [40, 49.99, 'D', 1], [0, 39.99, 'F', 0]] as $grade) {
        $insert($pdo, 'grades', ['min_percent' => $grade[0], 'max_percent' => $grade[1], 'grade' => $grade[2], 'grade_point' => $grade[3], 'status' => 1]);
    }

    $programIds = [];
    $semesterIds = [];
    $subjectIds = [];
    foreach ($programs as $pIndex => $program) {
        $programId = $insert($pdo, 'programs', [
            'name' => $program[0], 'code' => $program[1], 'duration_years' => 4, 'status' => 1,
        ]);
        $programIds[] = $programId;
        $semesterIds[$programId] = [];
        $subjectIds[$programId] = [];
        for ($semesterNo = 1; $semesterNo <= 8; $semesterNo++) {
            $semesterId = $insert($pdo, 'semesters', [
                'program_id' => $programId, 'semester_no' => $semesterNo,
                'name' => 'Semester ' . $semesterNo, 'status' => 1,
            ]);
            $semesterIds[$programId][$semesterNo] = $semesterId;
            $subjectIds[$programId][$semesterNo] = [];
            for ($subjectNo = 1; $subjectNo <= 5; $subjectNo++) {
                $subjectId = $insert($pdo, 'subjects', [
                    'program_id' => $programId, 'semester_id' => $semesterId,
                    'code' => $program[1] . $semesterNo . str_pad((string) $subjectNo, 2, '0', STR_PAD_LEFT),
                    'name' => $subjectTopics[($semesterNo + $subjectNo + $pIndex) % count($subjectTopics)] . ' ' . $semesterNo . '.' . $subjectNo,
                    'credit_hours' => $subjectNo === 3 ? 2 : 3,
                    'subject_type' => $subjectKinds[$subjectNo - 1],
                    'has_theory' => $subjectNo === 3 ? 0 : 1,
                    'has_practical' => $subjectNo === 3 ? 1 : 0,
                    'status' => 1,
                ]);
                $subjectIds[$programId][$semesterNo][] = $subjectId;
            }
        }
    }

    $teacherIds = [];
    foreach ($teacherNames as $index => $name) {
        $teacherId = $insert($pdo, 'teachers', [
            'teacher_code' => 'TCH' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            'name' => $name, 'email' => 'teacher' . ($index + 1) . '@demo-rms.edu.np',
            'program_id' => $programIds[$index], 'phone' => '98' . str_pad((string) (10000000 + $index * 173), 8, '0', STR_PAD_LEFT),
            'address' => 'Kathmandu, Nepal', 'qualification' => $index % 2 === 0 ? 'MSc. Computer Science' : 'MBA',
            'status' => 1,
        ]);
        $teacherIds[] = $teacherId;
        $userId = $insert($pdo, 'users', [
            'username' => $index === 0 ? 'demo_teacher' : 'teacher' . ($index + 1), 'password' => $teacherPasswordHash,
            'role' => 'teacher', 'teacher_id' => $teacherId, 'status' => 1,
        ]);
        $pdo->prepare('UPDATE teachers SET user_id = ? WHERE id = ?')->execute([$userId, $teacherId]);
    }

    $batchIds = [];
    foreach ($programIds as $index => $programId) {
        $batchIds[] = $insert($pdo, 'batches', [
            'program_id' => $programId, 'academic_year_id' => $yearIds[$index % 5],
            'name' => $programs[$index][1] . ' Batch ' . (2022 + ($index % 5)), 'start_year' => 2022 + ($index % 5),
            'end_year' => 2026 + ($index % 5), 'status' => 1,
        ]);
    }

    $studentIds = [];
    foreach ($programIds as $programIndex => $programId) {
        for ($semesterNo = 1; $semesterNo <= 8; $semesterNo++) {
            for ($studentNo = 1; $studentNo <= 50; $studentNo++) {
            $studentIndex = (($programIndex * 8 + ($semesterNo - 1)) * 50) + $studentNo - 1;
            $studentCode = $programs[$programIndex][1] . '2022'
                . str_pad((string) $semesterNo, 2, '0', STR_PAD_LEFT)
                . str_pad((string) $studentNo, 2, '0', STR_PAD_LEFT);
            $studentId = $insert($pdo, 'students', [
                'student_code' => $studentCode,
                'full_name' => $firstNames[$studentIndex % count($firstNames)] . ' ' . $lastNames[($studentIndex + $programIndex) % count($lastNames)],
                'dob' => (2001 + ($studentIndex % 4)) . '-' . str_pad((string) (($studentIndex % 9) + 1), 2, '0', STR_PAD_LEFT) . '-' . str_pad((string) (($studentIndex % 20) + 1), 2, '0', STR_PAD_LEFT),
                'gender' => $studentIndex % 3 === 0 ? 'Female' : 'Male',
                'email' => strtolower($studentCode) . '@student.demo-rms.edu.np',
                'phone' => '97' . str_pad((string) (10000000 + $studentIndex * 191), 8, '0', STR_PAD_LEFT),
                'address' => ['Kathmandu', 'Lalitpur', 'Bhaktapur', 'Pokhara'][$studentIndex % 4] . ', Nepal',
                'tu_reg_no' => 'TU-' . (22000 + $studentIndex),
                'exam_roll_no' => 'EX-' . (50000 + $studentIndex),
                'batch_id' => $batchIds[$programIndex],
                'section_id' => $sectionIds[$studentIndex % count($sectionIds)],
                'program_id' => $programId,
                'current_semester_id' => $semesterIds[$programId][$semesterNo],
                'admission_year' => 2022 + ($programIndex % 5),
                'status' => 1,
            ]);
            $studentIds[] = $studentId;
            $userId = $insert($pdo, 'users', [
                'username' => $studentIndex === 0 ? 'demo_student' : $studentCode, 'password' => $studentPasswordHash,
                'role' => 'student', 'student_id' => $studentId, 'status' => 1,
            ]);
            $pdo->prepare('UPDATE students SET user_id = ? WHERE id = ?')->execute([$userId, $studentId]);
            foreach ($subjectIds[$programId][$semesterNo] as $subjectId) {
                $insert($pdo, 'student_subjects', ['student_id' => $studentId, 'subject_id' => $subjectId, 'status' => 1]);
            }
            }
        }
    }

    foreach ($programIds as $programIndex => $programId) {
        foreach ($subjectIds[$programId] as $semesterSubjects) {
            foreach ($semesterSubjects as $subjectId) {
            $insert($pdo, 'teacher_subjects', [
                'teacher_id' => $teacherIds[0],
                'subject_id' => $subjectId, 'academic_year_id' => $currentYearId, 'status' => 1,
                'ay_key' => (string) $currentYearId,
            ]);
            }
        }
    }

    foreach ($programIds as $programIndex => $programId) {
        for ($semesterNo = 1; $semesterNo <= 8; $semesterNo++) {
            $examId = $insert($pdo, 'exams', [
                'name' => $semesterNo === 8 ? 'Final Examination 2026' : 'Regular Examination ' . $semesterNo . ' - 2026',
                'exam_type_id' => $examTypeIds[3], 'program_id' => $programId,
                'academic_year_id' => $currentYearId, 'batch_id' => $batchIds[$programIndex],
                'semester_id' => $semesterIds[$programId][$semesterNo], 'exam_date' => '2026-04-20',
                'exam_status' => 'published', 'published_at' => '2026-05-15 10:00:00',
                'start_date' => '2026-04-20', 'end_date' => '2026-05-05', 'status' => 1,
            ]);
            foreach ($subjectIds[$programId][$semesterNo] as $subjectIndex => $subjectId) {
                $teacherId = $teacherIds[0];
                $examSubjectId = $insert($pdo, 'exam_subjects', [
                    'exam_id' => $examId, 'subject_id' => $subjectId, 'teacher_id' => $teacherId,
                    'full_marks' => 100, 'marks_submitted' => 1, 'reopened' => 0, 'status' => 1,
                ]);
                $programStudents = array_slice($studentIds, $programIndex * 400 + ($semesterNo - 1) * 50, 50);
                foreach ($programStudents as $studentOffset => $studentId) {
                    $obtained = 52 + (($studentOffset * 7 + $subjectIndex * 5 + $programIndex * 3) % 45);
                    $insert($pdo, 'marks', [
                        'exam_subject_id' => $examSubjectId, 'student_id' => $studentId,
                        'obtained' => $obtained, 'full_marks' => 100, 'status' => 'Present',
                        'entered_by' => 1, 'submitted' => 1, 'entered_at' => '2026-05-10 09:00:00',
                    ]);
                }
            }
        }
    }

    foreach ($studentIds as $index => $studentId) {
        $programIndex = intdiv($index, 400);
        $programId = $programIds[$programIndex];
        $semesterNo = intdiv($index % 400, 50) + 1;
        $semesterId = $semesterIds[$programId][$semesterNo];
        $examId = (int) $pdo->query('SELECT id FROM exams WHERE program_id = ' . $programId . ' AND semester_id = ' . $semesterId . ' LIMIT 1')->fetchColumn();
        $details = $pdo->prepare(
            'SELECT es.subject_id, es.full_marks, s.credit_hours, m.obtained
             FROM exam_subjects es JOIN subjects s ON s.id = es.subject_id
             JOIN marks m ON m.exam_subject_id = es.id AND m.student_id = ?
             WHERE es.exam_id = ?'
        );
        $details->execute([$studentId, $examId]);
        $totalCredits = 0.0; $creditPoints = 0.0;
        $detailRows = [];
        foreach ($details as $row) {
            $percentage = (float) $row['obtained'];
            $grade = $percentage >= 90 ? ['A+', 4] : ($percentage >= 80 ? ['A', 3.7] : ($percentage >= 70 ? ['B+', 3.3] : ($percentage >= 60 ? ['B', 3] : ['C+', 2.7])));
            $credits = (float) $row['credit_hours'];
            $creditPoint = $credits * $grade[1];
            $totalCredits += $credits; $creditPoints += $creditPoint;
            $detailRows[] = [$row, $percentage, $grade, $credits, $creditPoint];
        }
        $sgpa = round($creditPoints / $totalCredits, 2);
        $resultId = $insert($pdo, 'results', [
            'student_id' => $studentId, 'exam_id' => $examId, 'semester_id' => $semesterId,
            'total_credits' => $totalCredits, 'total_credit_points' => $creditPoints,
            'sgpa' => $sgpa, 'cgpa' => $sgpa, 'result_status' => 'Pass', 'status' => 1,
            'is_locked' => 1, 'calculated_by' => 1, 'calculated_at' => '2026-05-12 12:00:00',
            'verified_at' => '2026-05-13 12:00:00', 'approved_at' => '2026-05-14 12:00:00',
            'published_at' => '2026-05-15 10:00:00',
        ]);
        foreach ($detailRows as [$row, $percentage, $grade, $credits, $creditPoint]) {
            $insert($pdo, 'result_details', [
                'result_id' => $resultId, 'student_id' => $studentId, 'subject_id' => $row['subject_id'],
                'internal_max' => 0, 'internal_total' => 0, 'external_max' => 100, 'external_total' => $row['obtained'],
                'max_marks' => 100, 'total_marks' => $row['obtained'], 'percentage' => $percentage,
                'grade' => $grade[0], 'grade_point' => $grade[1], 'credit_hours' => $credits,
                'credit_point' => $creditPoint, 'subject_status' => 'Pass',
            ]);
        }
    }

    $insert($pdo, 'notices', [
        'title' => 'Welcome to the 2026 Academic Session', 'description' => 'The new academic session is now open. Students can view their published results from the dashboard.',
        'notice_date' => '2026-05-16', 'status' => 1, 'created_by' => $adminId,
    ]);
    $insert($pdo, 'notices', [
        'title' => 'Final Examination Results Published', 'description' => 'Final semester results for the 2026 academic session are available in the result section.',
        'notice_date' => '2026-05-15', 'status' => 1, 'created_by' => $adminId,
    ]);
    foreach ([
        ['pass_percent', '40', 'Minimum percentage required to pass a subject'],
        ['credit_system', 'semester', 'Credit calculation model'],
    ] as $setting) {
        $insert($pdo, 'system_settings', ['setting_key' => $setting[0], 'setting_value' => $setting[1], 'description' => $setting[2]]);
    }
    $pdo->commit();
    echo "Demo database rebuilt successfully.\n";
    echo "Programs: 10 | Semesters: 80 | Subjects: 400 | Teachers: 10 | Students: 4000 | Results: 4000\n";
    echo "Admin: admin / admin123 | Demo teacher: demo_teacher / teacher123 | Demo student: demo_student / student123\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    fwrite(STDERR, "Demo seed failed: " . $exception->getMessage() . "\n");
    exit(1);
}
