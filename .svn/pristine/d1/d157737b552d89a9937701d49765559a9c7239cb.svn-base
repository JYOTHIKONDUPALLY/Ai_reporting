<?php

namespace App\Services;

class SchemaDictionary
{
    public static function get(): string
    {
        return <<<EOT
ClickHouse Schema: clickHouseInvoice

Key Rules:
- IDs: UInt32/UInt64. Dates: Date/DateTime. Money: Decimal/Decimal64.
- Use invoice_date for sales, payment_date for payments, session_date for classes.
- Join: invoice_items_detail.invoice_id = invoice_details.id
- Use lowerUTF8() for case-insensitive filters.
- Note: customers table uses PascalCase (CustomerName) while others use snake_case (customer_name).

Item Types: invoice_items_detail.item_type - Use lowerUTF8() for case-insensitive filtering.

CRITICAL: For ALL sales/revenue queries, ONLY include these saleable item types:
- product, Product
- service, Service
- class
- membership
- package
- rental
- giftcard, GiftCard
- appointment, Appointment
- subscription
- Any type starting with "misc" or "Misc" (includes "Misc :" prefixed types)

SQL Filter Pattern (MUST use for revenue/sales):
WHERE (lowerUTF8(iid.item_type) IN ('product', 'service', 'class', 'membership', 'package', 'rental', 'giftcard', 'appointment', 'subscription') 
      OR lowerUTF8(iid.item_type) LIKE 'misc%' 
      OR lowerUTF8(iid.item_type) LIKE 'Misc%')

Exclude from sales: membershipRedeem, packageRedeem, giftcardRedeem, membershipRegistrationFee, processingFee, soDeposit, advancePayment, itemException, invoiceException, proration, trialperiod, instore, discountVoucher, forfeitedDeposit, guestpass, loyaltypoints, promotion, tradein.
Firearms: lowerUTF8(item_type) IN ('product') AND lowerUTF8(category) IN ('firearm','firearms')
Membership: invoice_details.is_member Enum8('no'=0, 'yes'=1)

invoice_details (id UInt64 PK, invoice_date Date, customer_id UInt64, customer_name String, is_member Enum8, total_amount Decimal(12,2), franchise String, franchise_id UInt64, provider String, provider_id UInt64, location String, location_id UInt64, tax Decimal(12,2), status String - '1'=Active, '0'=Inactive, created_at DateTime)
→ invoice_items_detail.invoice_id, paymentDetails.invoice_id, customers.id, class_sessions.invoice_id, memberships.invoice_id, Range_appointments.invoiceId

invoice_items_detail (id UInt64 PK, invoice_id UInt64 FK, item_type String, item_name String, category String, subcategory String, brand String, SKU String, UPC String, item_id UInt64, resource_id UInt64, department_id UInt64, quantity Int32, unit_price Decimal(12,2), total_price Decimal(12,2), discount_amount Decimal(12,2), tax_rate Decimal(5,2), COGS UInt64, Commission String, co_faet_tax UInt64, membership_discount UInt64, package_discount UInt64, refund_amount UInt64, created_at DateTime)
→ invoice_details.id, product_inventory.id/SKU

customers (id UInt32 PK, CustomerName String, Email String, FirstName String, LastName String, IsMember String, Status String, franchise_id UInt32, provider_id UInt32, LoyaltyPoints Decimal(18,2), created_at DateTime)
→ invoice_details.customer_id, class_sessions.customer_id, memberships.customer_id

paymentDetails (id UInt64 PK, invoice_id UInt64 FK, payment_date Date, amount_paid Decimal(12,2), payment_method String, franchise_id UInt64, provider_id UInt64, location_id UInt64, refund_amount Decimal(12,2), created_at DateTime)
→ invoice_details.id

product_inventory (id UInt64 PK, name String, sku String, upc String, category String, sub_category String, brand_name String, department_name String, price Decimal64(2), cost Decimal64(2), qoh Int32, franchise_id UInt64, provider_id UInt64, created_at DateTime)
→ invoice_items_detail.item_id/SKU

class_sessions (session_id UInt32 PK, invoice_id UInt32 FK, customer_id UInt32 FK, class_id UInt32, class_name String, session_date Date, enrollment_quantity UInt16, total_price Decimal(10,2), service_provider_id UInt32, location_id UInt32, location_name String, created_at DateTime)
→ invoice_details.id, customers.id, serviceprovider.id

memberships (enrollment_id UInt64 PK, customer_id UInt32 FK, invoice_id UInt64 FK, membership_name String, membership_status String, enrollment_date DateTime, start_date Date, expiration_date Date, is_active UInt8, auto_renew UInt8, total_amount Decimal(10,2), service_provider_id UInt32, created_at DateTime)
→ customers.id, invoice_details.id

Range_appointments (id Int32 PK, invoiceId Int32 FK, customerId Int32 FK, customerName String, appointmentDate Date, serviceProviderId Int32, providerName String, locationId Int32, locationName String, membersCount Int32, nonMembersCount Int32, totalVisitors Int32, sessionDuration Int32, hasFirearms Int8, hasAmmo Int8, created_at DateTime)
→ invoice_details.id, customers.id, serviceprovider.id

serviceprovider (id UInt32 PK, serviceCategoryName String, legalName String, hasMembership String)
EOT;
    }
}
