<?php
require_once '../connect.php';
require_role(['Student']);
set_flash('info', 'Students can only request a return. Admin/Lab Staff must inspect and close returned items.');
redirect('student/dashboard.php');
