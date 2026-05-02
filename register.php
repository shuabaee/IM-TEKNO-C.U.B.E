<?php
require_once 'connect.php';
require_once ROOT_PATH . '/includes/options.php';
$pageTitle = 'Register';

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
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $departments = college_departments();
    $courses = course_options();

    if ($password !== $confirmPassword) {
        set_flash('danger', 'Passwords do not match.');
    } elseif (!in_array($userType, ['Student', 'Instructor'], true)) {
        set_flash('danger', 'Public registration only allows Student or Instructor accounts.');
    } elseif ($userType === 'Student' && (!isset($departments[$selectedDepartment]) || !in_array($selectedCourse, $courses[$selectedDepartment] ?? [], true))) {
        set_flash('danger', 'Please select a valid department and course combination.');
    } elseif ($userType === 'Instructor' && !isset($departments[$selectedInstructorDepartment])) {
        set_flash('danger', 'Please select a valid instructor department.');
    } else {
        try {
            $connection->begin_transaction();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $connection->prepare('INSERT INTO `User` (UserID, FirstName, LastName, UserType, Email, PasswordHash) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssss', $userId, $firstName, $lastName, $userType, $email, $hash);
            $stmt->execute();

            if ($userType === 'Student') {
                $status = $_POST['enrollment_status'] ?? 'Officially Enrolled';
                $hasLiability = 0;
                $stmt = $connection->prepare('INSERT INTO Student (UserID, Course, EnrollmentStatus, HasLiability) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('sssi', $userId, $selectedCourse, $status, $hasLiability);
                $stmt->execute();
            } else {
                $stmt = $connection->prepare('INSERT INTO Instructor (UserID, Department) VALUES (?, ?)');
                $stmt->bind_param('ss', $userId, $selectedInstructorDepartment);
                $stmt->execute();
            }

            $connection->commit();
            set_flash('success', 'Account registered successfully. You may now login.');
            redirect('login.php');
        } catch (mysqli_sql_exception $e) {
            $connection->rollback();
            set_flash('danger', 'Registration failed. User ID or email may already exist.');
        }
    }
}

require_once ROOT_PATH . '/includes/header.php';
?>
<section class="auth-wrap">
    <form class="auth-card auth-card-wide" method="post">
        <h1>Create Account</h1>
        <p>Register as a student or instructor. Admin accounts are created only by an existing admin.</p>
        <div class="form-grid">
            <div>
                <label for="user_id">User ID</label>
                <input id="user_id" name="user_id" type="text" placeholder="22-1234-567" value="<?= h($_POST['user_id'] ?? '') ?>" required>
            </div>
            <div>
                <label for="user_type">User Type</label>
                <select id="user_type" name="user_type" data-user-type>
                    <option value="Student" <?= $selectedUserType === 'Student' ? 'selected' : '' ?>>Student</option>
                    <option value="Instructor" <?= $selectedUserType === 'Instructor' ? 'selected' : '' ?>>Instructor</option>
                </select>
            </div>
            <div>
                <label for="first_name">First Name</label>
                <input id="first_name" name="first_name" type="text" value="<?= h($_POST['first_name'] ?? '') ?>" required>
            </div>
            <div>
                <label for="last_name">Last Name</label>
                <input id="last_name" name="last_name" type="text" value="<?= h($_POST['last_name'] ?? '') ?>" required>
            </div>
            <div class="form-full">
                <label for="email">Email Address</label>
                <input id="email" name="email" type="email" value="<?= h($_POST['email'] ?? '') ?>" required>
            </div>

            <div data-student-fields>
                <label for="college_department">College Department</label>
                <select id="college_department" name="college_department" data-department-select>
                    <?php render_department_options($selectedDepartment); ?>
                </select>
                <div class="help-text">Choose your college department first to load the matching courses.</div>
            </div>
            <div data-student-fields class="form-full">
                <label for="course">Course</label>
                <select id="course" name="course" data-course-select data-selected-course="<?= h($selectedCourse) ?>">
                    <?php render_course_options($selectedDepartment, $selectedCourse); ?>
                </select>
            </div>
            <div data-student-fields>
                <label for="enrollment_status">Enrollment Status</label>
                <select id="enrollment_status" name="enrollment_status">
                    <option value="Officially Enrolled" <?= ($_POST['enrollment_status'] ?? '') === 'Officially Enrolled' ? 'selected' : '' ?>>Officially Enrolled</option>
                    <option value="Inactive" <?= ($_POST['enrollment_status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div data-instructor-fields class="form-full" style="display:none">
                <label for="instructor_department">Instructor Department</label>
                <select id="instructor_department" name="instructor_department">
                    <?php render_department_options($selectedInstructorDepartment); ?>
                </select>
            </div>

            <div>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>
            </div>
            <div>
                <label for="confirm_password">Confirm Password</label>
                <input id="confirm_password" name="confirm_password" type="password" required>
            </div>
        </div>
        <div class="form-actions">
            <a class="btn btn-outline" href="<?= url('login.php') ?>">Back to Login</a>
            <button class="btn btn-primary" type="submit">Register Account</button>
        </div>
    </form>
</section>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
