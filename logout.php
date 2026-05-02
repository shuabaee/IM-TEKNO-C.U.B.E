<?php
require_once 'connect.php';
$_SESSION = [];
session_destroy();
session_write_close();
session_start();
set_flash('info', 'You have been logged out.');
redirect('index.php');
?>
