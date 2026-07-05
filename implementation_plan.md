# Demands Feature Implementation Plan

We need to add a "Demands" feature that allows supervisors to create demands for agents, and admins to manage and invoice them. 

## Proposed Changes

### Database Schema Updates
We will create two new tables to store demands and their associated products.
- `demands`: Stores `supervisor_id`, `agent_id`, `total_qty`, `total_amount`, `status` ('pending', 'approved', 'invoiced', 'cancelled'), and `is_deleted` (for soft deletion).
- `demand_items`: Stores `demand_id`, `product_id`, `qty`, `price`, `amount`.

### Backend APIs
#### [NEW] api/demands.php
Will handle AJAX requests for:
- Creating a new demand (from supervisor)
- Editing a demand
- Soft deleting a demand
- Updating status (from admin)

### Supervisor Panel
#### [NEW] supervisor/demands.php
- **Table**: Displays all demands with details (Agent, Total Qty, Total Value, Status).
- **Actions**: View, Edit, Delete (but no invoice action and no status change).
- **Add New Demand Popup**: 
  - Select an agent.
  - Add product rows dynamically (select product, enter qty, auto-calculate price).
  - Grand total summary at the bottom.
  - Save button.
  
### Admin Panel
#### [NEW] admin/demands.php
- **Table**: Similar to the supervisor panel but with a filter to search demands. Displays Agent name (address below name), Demand Number, Total Qty, Total Value, Status.
- **Actions**: Edit, View, Delete (soft delete), Invoice.
- Can change demand status and generate an invoice.

#### [MODIFY] includes/admin-sidebar.php
- Add link to `demands.php`.

#### [MODIFY] includes/supervisor-sidebar.php
- Add link to `demands.php`.

## Open Questions

> [!WARNING]
> 1. For the "Invoice" action in the admin panel, should this just generate a printable invoice page, or does it also need to push data to the `deliveries` / `orders` table?
> 2. Do you want soft delete (record stays in DB but hidden) for both admins and supervisors, or just admins?

## Verification Plan
1. Create a demand as a supervisor and verify the totals and agent selection.
2. Edit and delete the demand as a supervisor.
3. Login as an admin, view the demand, use the filter, and test the invoice and edit actions.