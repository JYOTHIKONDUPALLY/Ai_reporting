# Schema Review: SchemaDictionary.php vs createTableSrcipt.sql

## Critical Issues Found

### 1. **invoice_items_detail Table - Missing Columns**

**Problem**: The `LoadInvoiceItems.js` script is trying to insert columns that don't exist in the SQL schema:

- `resource_id` (UInt64) - Being inserted but NOT in SQL schema
- `department_id` (UInt64) - Being inserted but NOT in SQL schema

**Impact**: These inserts will fail or the columns will be silently ignored.

**Fix Required**: Add these columns to the SQL schema:
```sql
resource_id UInt64 DEFAULT 0,
department_id UInt64 DEFAULT 0,
```

### 2. **Column Name Casing Mismatch**

**Problem**: ClickHouse is case-sensitive. The script uses lowercase but SQL schema uses uppercase:

- Script uses: `cogs` → SQL schema has: `COGS`
- Script uses: `commission` → SQL schema has: `Commission`

**Impact**: Data insertion will fail or columns will be null.

**Fix Required**: Update `LoadInvoiceItems.js` to match SQL schema casing:
```javascript
COGS: Cogs,  // instead of cogs: Cogs
Commission: Commission,  // instead of commission: Commission
```

### 3. **SQL Syntax Error**

**Problem**: Line 104 in `createTableSrcipt.sql` has a double comma:
```sql
co_faet_tax UInt64 DEFAULT 0,,  // ❌ Double comma
```

**Fix Required**: Remove the extra comma:
```sql
co_faet_tax UInt64 DEFAULT 0,  // ✅ Single comma
```

### 4. **SchemaDictionary.php Accuracy**

**Status**: ✅ The SchemaDictionary.php is **mostly accurate** and correctly documents:
- All column names (including typos like `deaprtment`)
- Data types
- Relationships
- Business semantics

**Missing Documentation**: The SchemaDictionary doesn't mention the missing `resource_id` and `department_id` columns, but that's because they don't exist in the SQL schema yet.

## Detailed Comparison

### invoice_items_detail Table

| Column | SQL Schema | LoadInvoiceItems.js | SchemaDictionary | Status |
|--------|-----------|---------------------|------------------|--------|
| id | ✅ UInt64 | ✅ | ✅ | OK |
| invoice_id | ✅ UInt64 | ✅ | ✅ | OK |
| item_type | ✅ String | ✅ | ✅ | OK |
| item_type_id | ✅ UInt64 | ✅ | ✅ | OK |
| category | ✅ String | ✅ | ✅ | OK |
| subcategory | ✅ String | ✅ (as sub_category) | ✅ | ⚠️ Name mismatch |
| brand | ✅ String | ✅ | ✅ | OK |
| deaprtment | ✅ String | ❌ Not inserted | ✅ | ⚠️ Script missing |
| SKU | ✅ String | ✅ | ✅ | OK |
| UPC | ✅ String | ✅ | ✅ | OK |
| item_id | ✅ UInt64 | ✅ | ✅ | OK |
| item_name | ✅ String | ✅ | ✅ | OK |
| COGS | ✅ UInt64 | ❌ Uses `cogs` | ✅ | ❌ Casing mismatch |
| Commission | ✅ String | ❌ Uses `commission` | ✅ | ❌ Casing mismatch |
| co_faet_tax | ✅ UInt64 | ✅ | ✅ | OK |
| guest_pass_discount | ✅ UInt64 | ✅ | ✅ | OK |
| membership_discount | ✅ UInt64 | ✅ | ✅ | OK |
| package_discount | ✅ UInt64 | ✅ | ✅ | OK |
| refund_amount | ✅ UInt64 | ✅ | ✅ | OK |
| refund_co_faet_tax | ✅ UInt64 | ✅ | ✅ | OK |
| refund_tax | ✅ UInt64 | ✅ | ✅ | OK |
| quantity | ✅ Int32 | ✅ | ✅ | OK |
| unit_price | ✅ Decimal(12,2) | ✅ | ✅ | OK |
| discount_value | ✅ Decimal(12,2) | ✅ | ✅ | OK |
| discount_amount | ✅ Decimal(12,2) | ✅ | ✅ | OK |
| tax_rate | ✅ Decimal(5,2) | ✅ | ✅ | OK |
| total_price | ✅ Decimal(12,2) | ✅ | ✅ | OK |
| created_at | ✅ DateTime | ✅ | ✅ | OK |
| updated_at | ✅ DateTime | ✅ | ✅ | OK |
| resource_id | ❌ **MISSING** | ✅ Being inserted | ❌ Not documented | ❌ **CRITICAL** |
| department_id | ❌ **MISSING** | ✅ Being inserted | ❌ Not documented | ❌ **CRITICAL** |

### Other Tables

All other tables (invoice_details, customers, serviceprovider, paymentDetails, product_inventory, class_sessions, memberships, Range_appointments) appear to match correctly between SQL schema and SchemaDictionary.

## Recommendations

### Priority 1 (Critical - Fix Immediately)
1. **Add missing columns to SQL schema**:
   ```sql
   ALTER TABLE invoice_items_detail 
   ADD COLUMN resource_id UInt64 DEFAULT 0,
   ADD COLUMN department_id UInt64 DEFAULT 0;
   ```

2. **Fix column name casing in LoadInvoiceItems.js**:
   - Change `cogs` → `COGS`
   - Change `commission` → `Commission`

3. **Fix SQL syntax error** (line 104):
   - Remove double comma: `co_faet_tax UInt64 DEFAULT 0,`

### Priority 2 (Important - Fix Soon)
4. **Fix subcategory column name**:
   - Script uses `sub_category` but SQL schema has `subcategory`
   - Either update script or add alias

5. **Add deaprtment column to script**:
   - Script doesn't insert `deaprtment` column
   - Add if needed, or document why it's not used

### Priority 3 (Nice to Have)
6. **Update SchemaDictionary.php** after fixing SQL schema:
   - Add `resource_id` and `department_id` to documentation
   - Note the column name casing requirements

## Summary

The SchemaDictionary.php is **accurate** for what exists in the SQL schema. However, there are **critical mismatches** between:
- What the SQL schema defines
- What the LoadInvoiceItems.js script is trying to insert

These need to be fixed to ensure data loads correctly.

