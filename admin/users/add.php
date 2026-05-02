<?php
require_once '../../connect.php';
require_once ROOT_PATH . '/includes/options.php';
require_role(['Admin']);
$pageTitle = 'Add User';
$active = 'users';

$selectedUserType = $_POST['user_type'] ?? 'Student';
$selectedDepartment = $_POST['college_department'] ?? 'DEPT-CCS';
$selectedCourse = $_POST['course'] ?? 'BS Information Technology';
$selectedInstructorDepartment = $_POST['instructor_department'] ?? 'DEPT-CCS';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = trim($_POST['user_id'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $userType = $_POST['user_type'] ?? 'Student';
    $password = $_POST['password'] ?? 'password123';
    $departments = college_departments();
    $courses = course_options();

    try {
        if (!in_array($userType, ['Student', 'Instructor', 'Admin'], true)) {
            throw new Exception('Please select a valid user type.');
        }
        if ($userType === 'Student' && (!isset($departments[$selectedDepartment]) || !in_array($selectedCourse, $courses[$selectedDepartment] ?? [], true))) {
            throw new Exception('Please select a valid student department and course.');
        }
        if ($userType === 'Instructor' && !isset($departments[$selectedInstructorDepartment])) {
            throw new Exception('Please select a valid instructor department.');
        }

        $connection->begin_transaction();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $connection->prepare('INSERT INTO `User` (UserID, FirstName, LastName, UserType, Email, PasswordHash) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssssss', $userId, $firstName, $lastName, $userType, $email, $hash);
        $stmt->execute();

        if ($userType === 'Student') {
            $status = $_POST['enrollment_status'] ?? 'Officially Enrolled';
            $liability = isset($_POST['has_liability']) ? 1 : 0;
            $stmt = $connection->prepare('INSERT INTO Student (UserID, Course, EnrollmentStatus, HasLiability) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('sssi', $userId, $selectedCourse, $status, $liability);
            $stmt->execute();
        } elseif ($userType === 'Instructor') {
            $stmt = $connection->prepare('INSERT INTO Instructor (UserID, Department) VALUES (?, ?)');
            $stmt->bind_param('ss', $userId, $selectedInstructorDepartment);
            $stmt->execute();
        }

        $connection->commit();
        set_flash('success', 'User created successfully.');
        redirect('admin/users/index.php');
    } catch (Throwable $e) {
        try { $connection->rollback(); } catch (Throwable $ignored) {}
        set_flash('danger', $e instanceof mysqli_sql_exception ? 'Unable to create user. Check duplicate User ID/email and required fields.' : $e->getMessage());
    }
}

require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <?php require ROOT_PATH . '/admin/sidebar.php'; ?>
    <section class="content">
        <div class="page-head"><div><h1>Add User</h1><p>Create a Student, Instructor, or Admin account.</p></div></div>
        <form class="panel" method="post">
            <div class="form-grid">
                <div><label for="user_id">User ID</label><input id="user_id" name="user_id" required placeholder="ADMIN-002" value="<?= h($_POST['user_id'] ?? '') ?>"></div>
                <div><label for="user_type">User Type</label><select id="user_type" name="user_type" data-user-type><option value="Student" <?= $selectedUserType === 'Student' ? 'selected' : '' ?>>Student</option><option value="Instructor" <?= $selectedUserType === 'Instructor' ? 'selected' : '' ?>>Instructor</option><option value="Admin" <?= $selectedUserType === 'Admin' ? 'selected' : '' ?>>Admin</option></select></div>
                <div><label for="first_name">First Name</label><input id="first_name" name="first_name" required value="<?= h($_POST['first_name'] ?? '') ?>"></div>
                <div><label for="last_name">Last Name</label><input id="last_name" name="last_name" required value="<?= h($_POST['last_name'] ?? '') ?>"></div>
                <div><label for="email">Email Address</label><input id="email" type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>"></div>
                <div><label for="password">Temporary Password</label><input id="password" type="password" name="password" value="password123" required></div>

                <div data-student-fields><label for="college_department">College Department</label><select id="college_department" name="college_department" data-department-select><?php render_department_options($selectedDepartment); ?></select></div>
                <div data-student-fields><label for="course">Course</label><select id="course" name="course" data-course-select data-selected-course="<?= h($selectedCourse) ?>"><?php render_course_options($selectedDepartment, $selectedCourse); ?></select></div>
                <div data-student-fields><label for="enrollment_status">Enrollment Status</label><select id="enrollment_status" name="enrollment_status"><option value="Officially Enrolled">Officially Enrolled</option><option value="Inactive">Inactive</option></select></div>
                <div data-student-fields><label style="margin-top:32px;"><input type="checkbox" name="has_liability" style="width:auto"> Has Liability</label></div>

                <div data-instructor-fields class="form-full" style="display:none"><label for="instructor_department">Instructor Department</label><select id="instructor_department" name="instructor_department"><?php render_department_options($selectedInstructorDepartment); ?></select></div>
            </div>
            <div class="form-actions"><a class="btn btn-outline" href="<?= url('admin/users/index.php') ?>">Cancel</a><button class="btn btn-primary">Save User</button></div>
        </form>
    </section>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
