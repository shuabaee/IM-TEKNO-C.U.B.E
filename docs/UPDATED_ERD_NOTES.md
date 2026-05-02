# Updated ERD Notes

The original ERD remains the main basis of the database structure. The following implementation refinements were added to support the required application behavior:

## User

Additional fields:
- Email
- PasswordHash
- CreatedAt

These are needed for login and account management.

## Borrow_transaction

Additional status value:
- Return Requested

Reason: Students should not be allowed to directly close a returned item. They only request return. The Admin/Lab Staff inspects the item and then closes the transaction.

## Reserved_item

Additional field:
- QuantityReserved

Reason: Instructors must be able to reserve a specific quantity of an inventory item. This also allows inventory quantity to be updated correctly when multiple instructors reserve the same item.

## Breakage_report

Used when Admin/Lab Staff marks a returned item as Damaged. The system creates exactly one breakage report per damaged transaction, uses the item ReplacementCost as the penalty amount, and sets the student's HasLiability flag to true.
