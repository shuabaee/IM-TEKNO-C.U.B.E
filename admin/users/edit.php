<?php
require_once '../../connect.php';
require_once ROOT_PATH . '/includes/options.php';
require_role(['Admin']);
$pageTitle = 'Edit User';
$active = 'users';
$userId = $_GET['id'] ?? '';

$stmt = $connection->prepare('SELECT * FROM `User` WHERE UserID = ? LIMIT 1');
$stmt->bind_param('s', $userId);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
if (!$record) { set_flash('danger', 'User not found.'); redirect('admin/users/index.php'); }

$stmt = $connection->prepare('SELECT * FROM Student WHERE UserID = ? LIMIT 1');
$stmt->bind_param('s', $userId); $stmt->execute(); $student = $stmt->get_result()->fetch_assoc();
$stmt = $connection->prepare('SELECT * FROM Instructor WHERE UserID = ? LIMIT 1');
$stmt->bind_param('s', $userId); $stmt->execute(); $instructor = $stmt->get_result()->fetch_assoc();

function department_for_course(?string $course): string
{
    foreach (course_options() as $departmentId => $courses) {
        if ($course && in_array($course, $courses, true)) {
            return $departmentId;
        }
    }
    return 'DEPT-CCS';
}

$selectedUserType = $_POST['user_type'] ?? $record['UserType'];
$selectedDepartment = $_POST['college_department'] ?? department_for_course($student['Course'] ?? null);
$selectedCourse = $_POST['course'] ?? ($student['Course'] ?? 'BS Information Technology');
$selectedInstructorDepartment = $_POST['instructor_department'] ?? ($instructor['Department'] ?? 'DEPT-CCS');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $newType = $_POST['user_type'] ?? 'Student';
    $newPassword = trim($_POST['password'] ?? '');
    $departments = college_departments();
    $courses = course_options();

    try {
        if (!in_array($newType, ['Student', 'Instructor', 'Admin'], true)) {
            throw new Exception('Please select a valid user type.');
        }
        if ($newType === 'Student' && (!isset($departments[$selectedDepartment]) || !in_array($selectedCourse, $courses[$selectedDepartment] ?? [], true))) {
            throw new Exception('Please select a valid student department and course.');
        }
        if ($newType === 'Instructor' && !isset($departments[$selectedInstructorDepartment])) {
            throw new Exception('Please select a valid instructor department.');
        }

        $connection->begin_transaction();
        if ($newPassword !== '') {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $connection->prepare('UPDATE `User` SET FirstName=?, LastName=?, UserType=?, Email=?, PasswordHash=? WHERE UserID=?');
            $stmt->bind_param('ssssss', $firstName, $lastName, $newType, $email, $hash, $userId);
        } else {
            $stmt = $connection->prepare('UPDATE `User` SET FirstName=?, LastName=?, UserType=?, Email=? WHERE UserID=?');
            $stmt->bind_param('sssss', $firstName, $lastName, $newType, $email, $userId);
        }
        $stmt->execute();

        $stmt = $connection->prepare('DELETE FROM Student WHERE UserID=?'); $stmt->bind_param('s', $userId); $stmt->execute();
        $stmt = $connection->prepare('DELETE FROM Instructor WHERE UserID=?'); $stmt->bind_param('s', $userId); $stmt->execute();

        if ($newType === 'Student') {
            $status = $_POST['enrollment_status'] ?? 'Officially Enrolled';
            $liability = isset($_POST['has_liability']) ? 1 : 0;
            $stmt = $connection->prepare('INSERT INTO Student (UserID, Course, EnrollmentStatus, HasLiability) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('sssi', $userId, $selectedCourse, $status, $liability);
            $stmt->execute();
        } elseif ($newType === 'Instructor') {
            $stmt = $connection->prepare('INSERT INTO Instructor (UserID, Department) VALUES (?, ?)');
            $stmt->bind_param('ss', $userId, $selectedInstructorDepartment);
            $stmt->execute();
        }

        $connection->commit();
        set_flash('success', 'User updated successfully.');
        redirect('admin/users/index.php');
    } catch (Throwable $e) {
        try { $connection->rollback(); } catch (Throwable $ignored) {}
        set_flash('danger', $e instanceof mysqli_sql_exception ? 'Unable to update user. Check duplicate email and required fields.' : $e->getMessage());
    }
}

require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <?php require ROOT_PATH . '/admin/sidebar.php'; ?>
    <section class="content">
        <div class="page-head"><div><h1>Edit User</h1><p>Update account details and role-specific information.</p></div></div>
        <form class="panel" method="post">
            <div class="form-grid">
                <div><label>User ID</label><input value="<?= h($record['UserID']) ?>" disabled></div>
                <div><label for="user_type">User Type</label><select id="user_type" name="user_type" data-user-type><?php foreach(['Student','Instructor','Admin'] as $type): ?><option value="<?= h($type) ?>" <?= $selectedUserType === $type ? 'selected' : '' ?>><?= h($type) ?></option><?php endforeach; ?></select></div>
                <div><label for="first_name">First Name</label><input id="first_name" name="first_name" value="<?= h($_POST['first_name'] ?? $record['FirstName']) ?>" required></div>
                <div><label for="last_name">Last Name</label><input id="last_name" name="last_name" value="<?= h($_POST['last_name'] ?? $record['LastName']) ?>" required></div>
                <div><label for="email">Email Address</label><input id="email" type="email" name="email" value="<?= h($_POST['email'] ?? $record['Email']) ?>" required></div>
                <div><label for="password">New Password</label><input id="password" type="password" name="password" placeholder="Leave blank to keep current password"></div>

                <div data-student-fields><label for="college_department">College Department</label><select id="college_department" name="college_department" data-department-select><?php render_department_options($selectedDepartment); ?></select></div>
                <div data-student-fields><label for="course">Course</label><select id="course" name="course" data-course-select data-selected-course="<?= h($selectedCourse) ?>"><?php render_course_options($selectedDepartment, $selectedCourse); ?></select></div>
                <div data-student-fields><label for="enrollment_status">Enrollment Status</label><select id="enrollment_status" name="enrollment_status"><option value="Officially Enrolled" <?= ($_POST['enrollment_status'] ?? ($student['EnrollmentStatus'] ?? 'Officially Enrolled')) === 'Officially Enrolled' ? 'selected' : '' ?>>Officially Enrolled</option><option value="Inactive" <?= ($_POST['enrollment_status'] ?? ($student['EnrollmentStatus'] ?? '')) === 'Inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
                <div data-student-fields><label style="margin-top:32px;"><input type="checkbox" name="has_liability" style="width:auto" <?= isset($_POST['has_liability']) || (!$_POST && (int)($student['HasLiability'] ?? 0) === 1) ? 'checked' : '' ?>> Has Liability</label></div>

                <div data-instructor-fields class="form-full" style="display:none"><label for="instructor_department">Instructor Department</label><select id="instructor_department" name="instructor_department"><?php render_department_options($selectedInstructorDepartment); ?></select></div>
            </div>
            <div class="form-actions"><a class="btn btn-outline" href="<?= url('admin/users/index.php') ?>">Cancel</a><button class="btn btn-primary">Update User</button></div>
        </form>
    </section>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
