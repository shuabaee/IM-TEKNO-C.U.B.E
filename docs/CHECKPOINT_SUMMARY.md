# Increment 1 Checkpoint Summary

## Required Items Addressed

1. **CRUD of the Admin user**
   - Admin can create, view, edit, and delete users through `admin/users/`.
   - Public registration does not create Admin users. Admin users are controlled from the Admin portal.

2. **Different entity CRUD**
   - Department CRUD is available in `admin/departments/`.
   - Inventory CRUD is available in `admin/inventory/`.

3. **Database aligned with ERD**
   - Tables implemented: User, Student, Instructor, Department, Inventory_item, Borrow_transaction, Breakage_report, Reservation_batch, and Reserved_item.
   - The schema follows the submitted ERD but includes practical implementation columns such as Email, PasswordHash, CreatedAt, and QuantityReserved.

4. **Updated ERD-ready changes**
   - Reserved_item now includes QuantityReserved so instructors can reserve specific quantities.
   - Borrow_transaction supports Return Requested status so the Admin/Lab Staff can inspect returned items before closure.

5. **UI and flow improvements**
   - CIT-U inspired maroon and gold theme.
   - No emoji icons, replaced with line-style CSS icons.
   - Registration dropdowns for departments and courses.
   - Student and Instructor dashboard sorting and filtering.
   - Admin return inspection and breakage report generation.
