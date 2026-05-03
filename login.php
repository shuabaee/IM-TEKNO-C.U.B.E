<?php
require_once 'connect.php';
if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}
$pageTitle = 'Login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = trim($_POST['user_id'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $connection->prepare('SELECT UserID, FirstName, LastName, UserType, PasswordHash FROM `User` WHERE UserID = ? LIMIT 1');
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || !password_verify($password, $user['PasswordHash'])) {
        set_flash('danger', 'Invalid User ID or password.');
    } else {
        $_SESSION['user_id'] = $user['UserID'];
        $_SESSION['user_type'] = $user['UserType'];
        set_flash('success', 'Welcome back, ' . $user['FirstName'] . '!');
        redirect('dashboard.php');
    }
}

require_once ROOT_PATH . '/includes/header.php';
?>
<section class="auth-wrap">
    <form class="auth-card" method="post">
        <h1>Welcome Back</h1>
        <p>Login using your CIT-U User ID.</p>
        <label for="user_id">User ID</label>
        <input id="user_id" name="user_id" type="text" placeholder="ADMIN-001" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" placeholder="Password" required>
        <div class="form-actions form-actions-center">
            <button class="btn btn-gold" type="submit">Login</button>
        </div>
        <p style="margin-top:18px">Default admin: <strong>ADMIN-001</strong> / <strong>admin123</strong></p>
    </form>
</section>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
