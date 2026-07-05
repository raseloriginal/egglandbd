# Add Warehouse Lots and Price History Tracking

This plan outlines the changes needed to fully revamp the admin inventory page to support adding incoming warehouse lots, tracking providers (companies/farms), and maintaining a comprehensive product price history.

## User Review Required

> [!IMPORTANT]
> **Database Schema Changes**
> This change will add new tables to your database (`providers`, `warehouse_lots`, `product_price_history`) and add a `buying_price` column to your existing `products` table. 

> [!WARNING]
> **Provider Management**
> I will add a quick "Add Provider" feature inside the inventory page so you can easily create companies/farms on the fly when adding a lot. Let me know if you prefer a completely separate "Providers" management page instead.

## Open Questions

1. Do you want to keep the manual "Update Stock" button as it is (which just overwrites the stock qty), or should all stock updates strictly happen through adding new lots?
2. Do you want to see the product price history inside the Inventory page or inside the Products page (e.g., a "View History" button next to each product)?

## Proposed Changes

### Database Changes
#### [MODIFY] [schema.sql](file:///c:/xampp/htdocs/egglandbd/db/schema.sql)
- Add `providers` table to store companies and farms.
- Add `warehouse_lots` table to store incoming lots with quantity, buying price, and selling price.
- Add `product_price_history` table to track changes to buying and selling prices.
- Add `buying_price` column to the `products` table.
- I will execute ALTER TABLE and CREATE TABLE commands to update your live database seamlessly without data loss.

---

### Inventory Management
#### [MODIFY] [inventory.php](file:///c:/xampp/htdocs/egglandbd/admin/inventory.php)
- Replace the current simple stock update with an **"Add Lot"** button.
- Create a new popup modal for "Add Lot":
  - Select Provider (with inline add).
  - Select Product.
  - Quantity Come.
  - Buying Price (per qty).
  - Selling Price to Agent.
- Backend logic to handle the lot submission:
  - Insert into `warehouse_lots`.
  - Update `inventory` (adding the new quantity to the existing stock).
  - Update `products` with the new buying/selling prices.
  - Insert a record into `product_price_history` if the prices are different from before.
- Add a new table in the view to show the recent incoming warehouse lots.

---

### Product Management
#### [MODIFY] [products.php](file:///c:/xampp/htdocs/egglandbd/admin/products.php)
- Update the Add/Edit modals to include `buying_price`.
- Ensure that if prices are edited directly from the Products page, it also logs the change into the `product_price_history` table.

## Verification Plan

### Automated Tests
- No automated tests for the UI, but I will manually verify database queries.

### Manual Verification
- Go to Admin -> Inventory. Add a new Provider inline, add a new lot with updated prices.
- Verify that stock goes up.
- Verify that product prices change.
- Verify that a history record is created.
- Edit a product in Admin -> Products and verify a history record is created.