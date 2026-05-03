document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-menu-toggle]');
    const menu = document.querySelector('[data-menu]');
    if (toggle && menu) {
        toggle.addEventListener('click', () => menu.classList.toggle('is-open'));
    }

    document.querySelectorAll('[data-confirm]').forEach((el) => {
        el.addEventListener('click', (event) => {
            if (!confirm(el.dataset.confirm || 'Are you sure?')) {
                event.preventDefault();
            }
        });
    });

    const coursesByDepartment = {
        'DEPT-CEA': [
            'BS Architecture',
            'BS Chemical Engineering',
            'BS Civil Engineering',
            'BS Computer Engineering',
            'BS Electrical Engineering',
            'BS Electronics Engineering',
            'BS Industrial Engineering',
            'BS Mechanical Engineering with Computational Science',
            'BS Mechanical Engineering with Mechatronics',
            'BS Mining Engineering'
        ],
        'DEPT-CMBA': [
            'BS Accountancy',
            'BS Accounting Information Systems',
            'BS Management Accounting',
            'BS Business Administration',
            'BS Hospitality Management',
            'BS Tourism Management',
            'BS Office Administration',
            'Bachelor in Public Administration'
        ],
        'DEPT-CASE': [
            'AB Communication',
            'AB English with Applied Linguistics',
            'Bachelor of Elementary Education',
            'Bachelor of Secondary Education Major in English, Filipino, Mathematics and Science',
            'Bachelor of Multimedia Arts',
            'BS Biology',
            'BS Math with Applied Industrial Mathematics',
            'BS Psychology',
            'Bachelor of Special Needs Education (Generalist)'
        ],
        'DEPT-CNAHS': [
            'BS Nursing',
            'BS Pharmacy',
            'BS Medical Technology'
        ],
        'DEPT-CCS': [
            'BS Computer Science',
            'BS Information Technology'
        ],
        'DEPT-CCJ': [
            'BS Criminology'
        ]
    };

    function setSectionState(section, show) {
        section.style.display = show ? '' : 'none';
        section.querySelectorAll('input, select, textarea').forEach((control) => {
            control.disabled = !show;
        });
    }

    function updateRoleFields() {
        const userType = document.querySelector('[data-user-type]');
        if (!userType) return;
        const value = userType.value;
        document.querySelectorAll('[data-student-fields]').forEach((section) => setSectionState(section, value === 'Student'));
        document.querySelectorAll('[data-instructor-fields]').forEach((section) => setSectionState(section, value === 'Instructor'));
    }

    const userType = document.querySelector('[data-user-type]');
    if (userType) {
        userType.addEventListener('change', updateRoleFields);
        updateRoleFields();
    }

    const departmentSelect = document.querySelector('[data-department-select]');
    const courseSelect = document.querySelector('[data-course-select]');
    if (departmentSelect && courseSelect) {
        function updateCourseOptions() {
            const selectedCourse = courseSelect.dataset.selectedCourse || courseSelect.value;
            const courses = coursesByDepartment[departmentSelect.value] || [];
            courseSelect.innerHTML = '';
            courses.forEach((course) => {
                const option = document.createElement('option');
                option.value = course;
                option.textContent = course;
                if (course === selectedCourse) option.selected = true;
                courseSelect.appendChild(option);
            });
            courseSelect.dataset.selectedCourse = '';
        }
        departmentSelect.addEventListener('change', updateCourseOptions);
        updateCourseOptions();
    }

    document.querySelectorAll('[data-damage-condition]').forEach((select) => {
        const targetSelector = select.dataset.damageCondition;
        const target = document.querySelector(targetSelector);
        const update = () => {
            if (!target) return;
            target.style.display = select.value === 'Damaged' ? 'block' : 'none';
            target.querySelectorAll('textarea, input, select').forEach((control) => {
                control.disabled = select.value !== 'Damaged';
            });
        };
        select.addEventListener('change', update);
        update();
    });

    document.querySelectorAll('[data-live-search]').forEach((form) => {
        const input = form.querySelector('input[name="q"]');
        if (!input) return;

        form.addEventListener('submit', (event) => event.preventDefault());
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });

        const panel = form.closest('.panel');
        const table = panel ? panel.querySelector('table') : null;
        const tbody = table ? table.querySelector('tbody') : null;
        if (!tbody) return;

        const rows = Array.from(tbody.querySelectorAll('tr')).filter((row) => !row.matches('[data-live-search-empty]'));
        const emptyRow = tbody.querySelector('[data-live-search-empty]');

        const applyFilter = () => {
            const term = input.value.trim().toLowerCase();
            let visibleRows = 0;

            rows.forEach((row) => {
                const match = term === '' || row.textContent.toLowerCase().includes(term);
                row.style.display = match ? '' : 'none';
                if (match) visibleRows += 1;
            });

            if (emptyRow) {
                emptyRow.style.display = visibleRows === 0 ? '' : 'none';
            }
        };

        // 'input' event covers typing, pasting, clearing, making 'keyup' and 'change' redundant.
        input.addEventListener('input', applyFilter);
        applyFilter();
    });
});
