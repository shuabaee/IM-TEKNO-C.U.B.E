# TEKNO C.U.B.E. Laboratory Asset Management System

TEKNO C.U.B.E. is a PHP and MySQL project for managing laboratory equipment borrowing, instructor reservation batches, inventory availability, return inspections, and breakage reports.

## XAMPP Setup

1. Copy the `tekno_cube_project` folder into `xampp/htdocs/`.
2. Open XAMPP and start **Apache** and **MySQL**.
3. Open phpMyAdmin.
4. Import `database/tekno_cube.sql`.
5. Open `http://localhost/tekno_cube_project/` in your browser.

## Default Accounts

All seeded accounts use this password: `admin123`

| Role | User ID | Password |
|---|---:|---:|
| Admin / Laboratory Staff | `ADMIN-001` | `admin123` |
| Student | `22-1234-567` | `admin123` |
| Instructor | `INST-404` | `admin123` |

## Main Features Added

- Improved CIT-U inspired maroon and gold UI.
- Public registration for Students and Instructors.
- Student registration now uses College Department and Course dropdowns.
- Instructor registration now uses Department dropdown only.
- Admin User CRUD.
- Department CRUD.
- Inventory CRUD.
- Pre-populated inventory items for all six college departments.
- Student Available Items page with filters for type, condition, and department, plus quantity sorting.
- Student Borrow Transactions page with status filter and sorting for borrowed date, due date, and return date.
- Student return flow changed to **Request Return**.
- Admin/Lab Staff return inspection flow added.
- If the Admin marks an item as Damaged, the system automatically creates a Breakage Report, sets student liability to true, and uses replacement cost as penalty.
- Instructor reservation batch now supports per-item quantities and deducts the reserved quantity from inventory.
- Instructor reservation item selection is grouped by department and includes a department filter.
- Instructor dashboard includes sorting for scheduled date and time.

## Important Flow

Student return is not automatically closed by the student. The student only submits a return request. Admin/Lab Staff must inspect the returned item and mark it as Good, Worn, or Damaged before the transaction is closed.

If the item is marked Damaged, it is not added back to available quantity. A breakage report is created and the student is marked as having liability.
