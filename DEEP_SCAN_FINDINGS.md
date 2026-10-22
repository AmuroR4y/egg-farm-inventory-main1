# EGGCAST / VDVC Egg Farm — Deep Scan Findings Report

## Executive Summary

This report documents the current implementation state before implementing the three high-priority features:
1. Combined Egg Harvesting and Supply Inventory Records
2. Manager Dashboard: Three Pending-Reservation Summary Cards
3. Egg Age Monitoring and Inventory Eligibility

---

## Priority 1: Combined Egg Harvesting and Supply Inventory Records

### Current Implementation Findings

**File:** `inventory.php`
**Database Table:** `egg_inventory`

#### Existing Schema (from egg_farm_system.sql)
```sql
CREATE TABLE egg_inventory (
  id int(11) NOT NULL AUTO_INCREMENT,
  batch_id varchar(30) NOT NULL,
  harvest_date date NOT NULL,
  harvest_time time NOT NULL,
  egg_size enum('XS','Small','Medium','Large','XL','Jumbo','Super Jumbo','Double Yolk') NOT NULL,
  quantity int(11) NOT NULL,
  current_stock int(11) NOT NULL,
  movement_type enum('Stock In','Stock Out','Adjustment') NOT NULL,
  reason enum('Harvest','Reservation','Damaged','Adjustment') NOT NULL DEFAULT 'Harvest',
  date_logged timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
```

#### Current Harvesting Workflow (Lines 353-497)
- **Single egg size per submission:** Form allows selecting ONE `egg_size` from dropdown
- **Single quantity per submission:** One `quantity` field for the selected size
- **One inventory record per submission:** Each harvest creates ONE `egg_inventory` row
- **Batch ID generation:** Auto-generated per transaction (format: `EGYYYYMMDD-###`)
- **Stock calculation:** `new_stock = current_stock + quantity` (for Stock In)
- **Movement type:** Automatically set to 'Stock In' for harvest
- **Reason:** Automatically set to 'Harvest'

**Current Limitation:**
- Cannot record multiple egg sizes in one harvest session
- Each size requires a separate form submission
- Each submission generates a separate batch_id
- No parent-child relationship between harvests of the same session

#### Supply Inventory Findings

**Database Table:** `supply_inventory`
```sql
CREATE TABLE supply_inventory (
  id int(11) NOT NULL AUTO_INCREMENT,
  batch_id varchar(50) NOT NULL,
  item_category varchar(50) NOT NULL,
  item_name varchar(100) NOT NULL,
  quantity int(11) NOT NULL,
  current_stock int(11) NOT NULL DEFAULT '0',
  action_type varchar(50) NOT NULL,
  reason text,
  date_logged datetime NOT NULL
)
```

**Current Supply Workflow (Lines 507-900+):**
- **Single item per submission:** One `item_category` + `item_name` + `quantity`
- **Stock adjustment:** Uses latest stock query per (category, name) combination
- **Batch ID generation:** Based on category code (FD=Feeds, TR=Trays, MD=Medicine)
- **Action types:** 'Stock In', 'Stock Out', 'Adjustment'
- **Similar limitation:** Cannot record multiple supply items in one transaction

---

## Priority 2: Manager Dashboard: Three Pending-Reservation Summary Cards

### Current Implementation Findings

**File:** `manager_dashboard.php`

#### Existing Dashboard Structure (Lines 3425-3664)

**Three Stat Cards Currently Present:**

1. **Pending Reservations** (Lines 3428-3489)
   - Title: "Pending Reservation"
   - Count: `$pending_reservations` (calculated at line 934)
   - Description: "Awaiting confirmation"
   - Icon: Calendar icon
   - Calculation: `COUNT(DISTINCT reservation_code) WHERE status = 'Pending'`

2. **Egg Inventory** (Lines 3493-3551)
   - Title: "Egg Inventory"
   - Count: `$total_egg_stock` (sum of latest stock per size)
   - Description: "Available egg stocks"
   - Icon: Egg icon

3. **Today's Deliveries** (Lines 3558-3617)
   - Title: "Today's Deliveries"
   - Count: `$today_deliveries`
   - Description: "Scheduled for today"
   - Icon: Truck icon
   - Calculation: Based on `reservation_date = CURDATE()`

**Fourth Card Found** (Lines 3620-3664)
4. **Completed Deliveries** (Lines 3621-3664)
   - Title: "Completed Deliveries"
   - Count: `$completed_deliveries`
   - Icon: Check icon
   - Calculation: `COUNT(DISTINCT reservation_code) WHERE status IN ('Delivered', 'Completed')`

#### Current Status
- **All four cards are present and rendering**
- **Three cards are showing data:** Pending Reservations, Egg Inventory, Today's Deliveries, Completed Deliveries
- **Counts are calculated from actual database records**
- **Styling uses `.stat-card` class with VDVC theme**

**Potential Issue:**
- The requirement asks for "three intended cards" but there are currently FOUR cards
- Need to determine which card should be removed or if the requirement is to ensure the three PENDING-reservation cards are correct
- Current implementation shows ONE pending-reservation card, not three

---

## Priority 3: Egg Age Monitoring and Inventory Eligibility

### Current Implementation Findings

#### Harvest Date Storage
**Schema:** `egg_inventory` table has `harvest_date` (date) and `harvest_time` (time) fields
**Current behavior:** Each inventory row stores its harvest date/time

**Key Finding:** The database already stores harvest dates at the individual record level!

#### Current Stock Calculation Logic

**In Customer Dashboard** (`customer_dashboard.php`):
```sql
SELECT e.egg_size, e.current_stock AS available_stock
FROM egg_inventory e
INNER JOIN (
    SELECT egg_size, MAX(id) AS id
    FROM egg_inventory
    GROUP BY egg_size
) latest ON latest.id = e.id
```

**In Manager Dashboard** (`manager_dashboard.php`):
```sql
SELECT e.$size_column AS egg_size, e.$stock_column AS available_stock
FROM egg_inventory e
INNER JOIN (
    SELECT e2.$size_column, MAX(e2.id) AS id
    FROM egg_inventory e2
    GROUP BY e2.$size_column
) latest ON latest.id = e.id
```

**In Owner API** (`owner_api.php`):
- Returns all historical rows in `inventory.eggs`
- Returns latest snapshots in `inventory.snapshotEggs`

**Critical Finding:** The system uses a "latest row per egg size" pattern to determine current stock. This means:
- Multiple historical records exist for each egg size
- The one with the highest `id` is considered the "current" stock
- Older records (including previous harvests) are still in the database but not counted in available stock

#### Batch Tracking Analysis

**Current Batch Structure:**
- `batch_id` field: varchar(30) with format `EGYYYYMMDD-###`
- Example: `EG20260728-001`, `EG20260730-001`
- Each harvest transaction gets a unique batch_id
- Multiple sizes harvested on same day get sequential batch_ids (001, 002, 003)

**Limitation:**
- No explicit "batch" table linking multiple egg sizes from one harvest session
- Each inventory row is independent
- No parent-child relationship between records from the same harvest session
- Cannot determine which egg sizes were harvested together in one session

#### Reservation and Stock Deduction

**In Manager Confirmation** (`manager_reservation.php`):
- Stock is deducted using `movement_type='Stock Out'`, `reason='Reservation'`
- Deduction occurs when Manager confirms (not at customer submission)
- Deduction creates a new inventory row with updated `current_stock`

**In Cancellation** (`manager_reservation.php` - recently fixed):
- Stock is restored using `movement_type='Stock In'`, `reason='Cancellation'`
- Only for Confirmed reservations (not Pending, not Completed/Delivered)

#### Damaged Eggs

**From schema:** `reason` enum includes 'Damaged' as valid value
**From sample data:** Line 52 shows a Damaged deduction example
- Damaged eggs create Stock Out records with reason='Damaged'
- This reduces stock but is tracked separately from reservations

---

## Current Architecture Limitations

### Priority 1 Limitation
**Issue:** Current schema and workflow do not support multi-size harvest sessions natively
**Impact:** Each egg size requires separate form submission
**Potential Solution:** 
1. Create a parent `harvest_sessions` table to group inventory records
2. Modify form to accept multiple egg size/quantity pairs
3. Insert all records in a transaction with session_id reference
**Complexity:** Medium - requires schema change and UI modification

### Priority 2 Limitation
**Issue:** There are currently FOUR cards, not three
**Possible Interpretations:**
1. One of the four cards should be removed
2. The requirement actually means "ensure the three PENDING-related cards exist" (but there's only one pending card)
3. Need clarification on which three cards are intended
**Action Required:** Clarify which three cards are the intended ones

### Priority 3 Limitation
**Issue:** No batch-level eligibility filtering
**Current State:**
- Harvest dates exist at record level
- Current stock is calculated from latest row per size (aggregates all history)
- No way to exclude specific batches from available stock
- No configurable age thresholds

**Potential Solutions:**
1. **Minimal:** Add eligibility flag to egg_inventory records (enum: 'eligible', 'review', 'excluded')
2. **Alternative:** Use harvest_date + configurable days threshold in application logic (no schema change)
3. **Complex:** Create batch-level grouping with session management (similar to Priority 1)

**Recommendation:** Option 2 (application-level filtering) is minimal and doesn't require schema changes

---

## Data Integrity Considerations

### Stock Calculation Paths

**Multiple paths exist that all must remain consistent:**
1. Customer Dashboard stock query (latest row per size)
2. Manager Dashboard stock query (latest row per size)
3. Owner API stock query (latest row per size for snapshots)
4. Owner Dashboard analytics (was using all rows - recently fixed to use snapshots)
5. Manager confirmation deduction
6. Manager cancellation restoration
7. Damaged egg deductions
8. Inventory adjustments

**Critical:** Any changes to how "current stock" is calculated must update ALL these paths consistently

### Reservation Status Handling

**Current status values:** 'Pending', 'Confirmed', 'Cancelled', 'Completed', 'Delivered'
**Status transitions:**
- Pending → Confirmed (stock deducted)
- Confirmed → Cancelled (stock restored)
- Confirmed → Completed/Delivered (fulfilled)
- Pending → Cancelled (no stock change)

**Important:** Stock restoration only makes sense for Confirmed reservations

---

## Uncommitted Changes Present

**Modified Files (from git status):**
- customer_history.php (PHP deprecation fixes)
- customer_reservation.php (stock validation)
- manager_reservation.php (stock restoration on cancellation)
- owner_content_reports.php
- owner_dashboard.php
- owner_dashboard_content.php (owner dashboard fixes)
- owner_panel.php
- vdvc/app_analytics.js
- vdvc/styles.css

**New Report Files:**
- CUSTOMER_HISTORY_FIX_REPORT.md
- CUSTOMER_RESERVATION_STOCK_VALIDATION_REPORT.md
- STOCK_RESTORATION_ON_CANCELLATION_REPORT.md
- VERIFICATION_REPORT.md
- verify_stock_calculation.php

---

## Implementation Recommendations

### Priority 1: Combined Harvesting
**Recommended Approach:**
1. Create `harvest_sessions` table:
   ```sql
   CREATE TABLE harvest_sessions (
     id INT AUTO_INCREMENT PRIMARY KEY,
     session_date DATE NOT NULL,
     session_time TIME NOT NULL,
     total_eggs INT DEFAULT 0,
     created_by INT,
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   )
   ```
2. Modify `egg_inventory` to add `session_id` foreign key
3. Update inventory form to accept multiple size/quantity pairs
4. Insert session record first, then link all inventory records to it
5. Update all stock calculation paths to filter by eligibility

**Risk:** Medium - requires schema migration and multiple file updates

### Priority 2: Manager Dashboard Cards
**Clarification Needed:**
- Are the three intended cards: Pending Reservations, Egg Inventory, Today's Deliveries?
- Should the fourth card (Completed Deliveries) be removed?
- Or is the requirement to add three NEW pending-related cards?

**Current State:** All four cards are functional and displaying correctly

### Priority 3: Egg Age Monitoring
**Recommended Minimal Approach:**
1. Add configuration file for age thresholds (days):
   ```php
   $age_thresholds = [
       'eligible' => 21,    // 3 weeks
       'review' => 28,       // 4 weeks
       'excluded' => 35      // 5 weeks
   ];
   ```
2. Modify stock calculation queries to filter by age:
   ```sql
   SELECT egg_size, SUM(current_stock) as available_stock
   FROM egg_inventory
   WHERE movement_type = 'Stock In'
     AND harvest_date >= DATE_SUB(CURDATE(), INTERVAL 35 DAY)
     AND egg_size = ?
   GROUP BY egg_size
   ```
3. Add age calculation and display in inventory.php
4. Add eligibility indicators to manager dashboard

**Risk:** Low - application-level filtering, no schema changes needed initially

---

## Next Steps Required

### Before Implementation:
1. **Clarify Priority 2:** Confirm which three Manager Dashboard cards are intended
2. **Confirm Priority 3 approach:** Discuss whether application-level filtering (no schema change) is acceptable vs full batch tracking system
3. **Backup database:** Before any schema changes for Priority 1
4. **Test uncommitted changes:** Verify current modifications don't break the system

### Implementation Order Recommendation:
1. **Priority 2 first** (lowest risk, just clarification + minor adjustment if needed)
2. **Priority 3 second** (application-level filtering, low risk)
3. **Priority 1 last** (requires schema migration, highest risk)

---

## Files Modified (Previous Work - Not Part of This Task)

The following files have uncommitted changes from previous inventory audit and dashboard work:
- customer_history.php - PHP deprecation fixes
- customer_reservation.php - stock validation
- manager_reservation.php - stock restoration on cancellation
- owner_dashboard.php - dashboard fixes
- owner_dashboard_content.php - analytics integration and chart fixes
- owner_panel.php - navigation fixes
- vdvc/app_analytics.js - analytics scripts
- vdvc/styles.css - styling

These changes should be tested to ensure they don't conflict with new implementation.
