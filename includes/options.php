<?php
function college_departments(): array
{
    return [
        'DEPT-CEA' => ['code' => 'CEA', 'name' => 'College of Engineering and Architecture', 'short' => 'Engineering and Architecture', 'location' => 'Engineering and Architecture Building'],
        'DEPT-CMBA' => ['code' => 'CMBA', 'name' => 'College of Management, Business & Accountancy', 'short' => 'Management, Business & Accountancy', 'location' => 'Management and Business Building'],
        'DEPT-CASE' => ['code' => 'CASE', 'name' => 'College of Arts, Sciences, & Education', 'short' => 'Arts, Sciences, & Education', 'location' => 'Arts, Sciences, and Education Building'],
        'DEPT-CNAHS' => ['code' => 'CNAHS', 'name' => 'College of Nursing & Allied Health Sciences', 'short' => 'Nursing & Allied Health Sciences', 'location' => 'Nursing and Allied Health Sciences Building'],
        'DEPT-CCS' => ['code' => 'CCS', 'name' => 'College of Computer Studies', 'short' => 'Computer Studies', 'location' => 'NGE Building'],
        'DEPT-CCJ' => ['code' => 'CCJ', 'name' => 'College of Criminal Justice', 'short' => 'Criminal Justice', 'location' => 'Criminal Justice Building'],
    ];
}

function course_options(): array
{
    return [
        'DEPT-CEA' => [
            'BS Architecture',
            'BS Chemical Engineering',
            'BS Civil Engineering',
            'BS Computer Engineering',
            'BS Electrical Engineering',
            'BS Electronics Engineering',
            'BS Industrial Engineering',
            'BS Mechanical Engineering with Computational Science',
            'BS Mechanical Engineering with Mechatronics',
            'BS Mining Engineering',
        ],
        'DEPT-CMBA' => [
            'BS Accountancy',
            'BS Accounting Information Systems',
            'BS Management Accounting',
            'BS Business Administration',
            'BS Hospitality Management',
            'BS Tourism Management',
            'BS Office Administration',
            'Bachelor in Public Administration',
        ],
        'DEPT-CASE' => [
            'AB Communication',
            'AB English with Applied Linguistics',
            'Bachelor of Elementary Education',
            'Bachelor of Secondary Education Major in English, Filipino, Mathematics and Science',
            'Bachelor of Multimedia Arts',
            'BS Biology',
            'BS Math with Applied Industrial Mathematics',
            'BS Psychology',
            'Bachelor of Special Needs Education (Generalist)',
        ],
        'DEPT-CNAHS' => [
            'BS Nursing',
            'BS Pharmacy',
            'BS Medical Technology',
        ],
        'DEPT-CCS' => [
            'BS Computer Science',
            'BS Information Technology',
        ],
        'DEPT-CCJ' => [
            'BS Criminology',
        ],
    ];
}

function department_name(string $departmentId): string
{
    $departments = college_departments();
    return $departments[$departmentId]['name'] ?? $departmentId;
}

function department_code(string $departmentId): string
{
    $departments = college_departments();
    return $departments[$departmentId]['code'] ?? $departmentId;
}

function department_short_name(string $departmentId): string
{
    $departments = college_departments();
    return $departments[$departmentId]['short'] ?? $departmentId;
}

function render_department_options(?string $selected = null): void
{
    foreach (college_departments() as $id => $department) {
        $isSelected = $selected === $id ? 'selected' : '';
        echo '<option value="' . h($id) . '" ' . $isSelected . '>' . h($department['code'] . ' | ' . $department['short']) . '</option>';
    }
}

function render_course_options(?string $departmentId = null, ?string $selected = null): void
{
    $courses = course_options();
    $departmentId = $departmentId && isset($courses[$departmentId]) ? $departmentId : array_key_first($courses);
    foreach ($courses[$departmentId] as $course) {
        $isSelected = $selected === $course ? 'selected' : '';
        echo '<option value="' . h($course) . '" ' . $isSelected . '>' . h($course) . '</option>';
    }
}
?>
