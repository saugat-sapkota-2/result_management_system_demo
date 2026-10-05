<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_STUDENT);
$title = 'My Profile';
$active = 'profile';

$studentId = (int) db_val('SELECT student_id FROM users WHERE id = ?', [$user['id']]);
$student = db_one(
    "SELECT st.*, b.name AS batch_name, s.name AS sem_name, sec.name AS section_name, p.name AS program_name
     FROM students st
     LEFT JOIN batches b ON b.id = st.batch_id
     LEFT JOIN semesters s ON s.id = st.current_semester_id
     LEFT JOIN sections sec ON sec.id = st.section_id
     LEFT JOIN programs p ON p.id = st.program_id
     WHERE st.id = ?", [$studentId]);

include __DIR__ . '/../includes/header.php';
?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="text-center mb-4">
            <?php if ($student['photo']): ?>
                <img src="<?php echo e(url($student['photo'])); ?>" class="rounded-circle border" width="110" height="110" alt="">
            <?php else: ?>
                <div class="avatar-circle bg-warning text-dark mx-auto" style="width:110px;height:110px;font-size:2.8rem;"><?php echo e(strtoupper(substr($student['full_name'], 0, 1))); ?></div>
            <?php endif; ?>
            <h5 class="mt-3 mb-1"><?php echo e($student['full_name']); ?></h5>
            <div class="badge text-bg-dark"><?php echo e($student['student_code']); ?></div>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <h6 class="section-label"><i class="bi bi-person-gear me-1"></i>Personal Information</h6>
                <dl class="row mb-0">
                    <dt class="col-sm-5">Date of Birth</dt><dd class="col-sm-7"><?php echo fdate($student['dob']); ?></dd>
                    <dt class="col-sm-5">Gender</dt><dd class="col-sm-7"><?php echo e($student['gender'] ?: '—'); ?></dd>
                    <dt class="col-sm-5">Email</dt><dd class="col-sm-7"><?php echo e($student['email'] ?: '—'); ?></dd>
                    <dt class="col-sm-5">Phone</dt><dd class="col-sm-7"><?php echo e($student['phone'] ?: '—'); ?></dd>
                    <dt class="col-sm-5">Permanent Address</dt><dd class="col-sm-7"><?php echo e($student['address'] ?: '—'); ?></dd>
                </dl>
            </div>
            <div class="col-md-6">
                <h6 class="section-label"><i class="bi bi-mortarboard me-1"></i>Academic Information</h6>
                <dl class="row mb-0">
                    <dt class="col-sm-5">Program</dt><dd class="col-sm-7"><?php echo e($student['program_name'] ?? '—'); ?></dd>
                    <dt class="col-sm-5">Current Semester</dt><dd class="col-sm-7"><?php echo e($student['sem_name'] ?? '—'); ?></dd>
                    <dt class="col-sm-5">Batch</dt><dd class="col-sm-7"><?php echo e($student['batch_name'] ?? '—'); ?></dd>
                    <dt class="col-sm-5">Section</dt><dd class="col-sm-7"><?php echo e($student['section_name'] ?? '—'); ?></dd>
                    <dt class="col-sm-5">TU Registration</dt><dd class="col-sm-7"><?php echo e($student['tu_reg_no'] ?: '—'); ?></dd>
                    <dt class="col-sm-5">Exam Roll No.</dt><dd class="col-sm-7"><?php echo e($student['exam_roll_no'] ?: '—'); ?></dd>
                    <dt class="col-sm-5">Admission Year</dt><dd class="col-sm-7"><?php echo e($student['admission_year'] ?: '—'); ?></dd>
                </dl>
            </div>
        </div>

        <hr>
        <div class="text-muted small">Profile details are maintained by the college administration. Contact the office for corrections.</div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>