import mysql from 'mysql2/promise';
import { createClient } from '@clickhouse/client';

const CONFIG = {
    batchSize: 2000
};

function formatDate(dateValue) {
    if (!dateValue) return null;
    if (dateValue === '0000-00-00' || dateValue === '0000-00-00 00:00:00') return null;

    const date = new Date(dateValue);
    if (isNaN(date.getTime())) return null;

    return date.toISOString().replace("T", " ").substring(0, 19);
}

function formatDateOnly(dateValue) {
    if (!dateValue) return null;
    if (dateValue === '0000-00-00') return null;

    const date = new Date(dateValue);
    if (isNaN(date.getTime())) return null;

    return date.toISOString().substring(0, 10);
}

function mapStatus(status) {
    switch (status) {
        case 1: return 'active';
        case 2: return 'inactive';
        case 3: return 'prospect';
        case 4: return 'suspend';
        default: return 'unknown';
    }
}

function mapUnsubscribe(pref) {
    if (!pref) return '';
    if ((pref.emailNewsletter === 0 && pref.unsubscribeAutoresponder === 1) || pref.unsubscribeAllEmail === 1) {
        return 'N&A';
    } else if (pref.emailNewsletter === 0) {
        return 'N';
    } else if (pref.unsubscribeAutoresponder === 1) {
        return 'A';
    }
    return '';
}

function Aquire(data) {
    if (!data) return '';
    return {
        1: "DataLoad",
        2: "Walk-In",
        3: "Phone-In",
        4: "Online"
    }[data] || '';
}
async function getLastMigratedId(clickhouse, tableName) {
   const result = await clickhouse.query({
    query: `SELECT last_migrated_id 
            FROM migration_progress 
            WHERE table_name = {table_name:String}
            order by updated_at desc 
            LIMIT 1`,
    format: 'JSONEachRow',
    query_params: { table_name: tableName }
  });

  const rows = await result.json();

  return rows.length ? rows[0].last_migrated_id : 0;
}

async function updateLastMigratedId(clickhouse, tableName, lastId, totalRecords) {
  
  if(totalRecords >0){
  await clickhouse.insert({
    table: 'migration_progress',
    values: [{
      table_name: tableName,
      last_migrated_id: lastId,
      updated_at: new Date().toISOString().slice(0, 19).replace("T"," ")
    }],
    format: 'JSONEachRow'
  });     
  }
  console.log("updated the last migrated id");
}

// async function getDistinctServiceProviders(mysqlConn) {
//   const [rows] = await mysqlConn.execute(`
//     SELECT DISTINCT serviceProviderId
//     FROM invoiceNew
//     WHERE status = 1
//   `);
//   return rows.map(r => r.serviceProviderId);
// }

async function createInvoiceTable(clickhouse, tableName) {
  const createQuery = `
    CREATE TABLE IF NOT EXISTS ${tableName}
    (
         -- Primary Keys
    session_id UInt32,
    class_id UInt32,
    enrollment_id UInt32,
    enrollment_session_id UInt32,
    customer_id UInt32,
    invoice_id UInt32,
    
    -- Service Provider & Location
    service_provider_id UInt32,
    location_id UInt32,
    location_name String,
    
    -- Class Information
    class_name String,
    class_type_id UInt32,
    class_type_name String,
    class_category_id UInt32,
    class_category_name String,
    class_capacity UInt16,
    class_status String,  -- 0=Inactive, 1=Active
    class_enrollment_status String,  -- 1=Enrollment Open, 2=Do Not Publish, 3=Enrollment Closed
    
    -- Session Information
    session_name String,
    session_date Date,
    session_start_time String,
    session_end_time String,
    session_duration String,
    session_status String,  -- 1=Active, 0=Cancelled
    is_parent_session UInt8,
    
    -- Instructor/Resource Information
    resource_id UInt32,
    resource_name String,
    additional_resource_ids String,
    
    -- Customer/Member Information (Simplified)
    customer_member_id UInt32,
    member_name String,
    customer_name String,  -- Combined first + last name
    
    -- Enrollment Details
    enrollment_status String,  -- 0=Deleted, 1=Enrolled, 3=Waiting List
    enrollment_quantity UInt16,
    enrollment_creation_date DateTime,
    booking_method String,  -- 1=Online, 2=PhoneIn, 3=WalkIn
    payment_status UInt8,
    payment_type_id UInt32,
    is_checked_in UInt8,
    
    -- Financial Information (from invoice_items_detail)
    item_price Decimal(10, 2),
    quantity UInt16,
    total_price Decimal(10, 2),
    sale_discount Decimal(10, 2),
    discount_amount Decimal(10, 2),
    tax_amount Decimal(10, 2),
    net_amount Decimal(10, 2),  -- total_price - sale_discount
    
    -- Promotion Information
    promotion_id UInt32,
    promotion_name String,
    
    -- Timestamps
    invoice_created_at DateTime,
    created_at DateTime DEFAULT now(),
    updated_at DateTime DEFAULT now()
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(session_date)
ORDER BY (service_provider_id, session_date, class_id, session_id)
SETTINGS index_granularity = 8192;
  `;

  await clickhouse.exec({ query: createQuery });
  console.log(`📦 Table ready: ${tableName}`);
}
/**
 * BATCH MIGRATION
 */
async function migrateCustomers(mysqlConn, clickhouse, serviceProviderId, batchSize = 2000) {
    let offset = 0;
    let totalInserted = 0;
    const TABLE_KEY = `customers_${serviceProviderId}`;
    let lastId = await getLastMigratedId(clickhouse, TABLE_KEY);
        console.log(`▶ Resuming migration from customer.id > ${lastId}`);
        const [count]= await mysqlConn.execute(`
            SELECT count(*) as count FROM customer c
            INNER JOIN serviceProviderCustomerDetails scd 
                ON scd.customerId = c.id 
            WHERE scd.serviceProviderId = ? and c.id > ?
        `, [serviceProviderId, lastId]);
    const totalRecords = count[0].count;
    console.log(`📊 Total records to migrate: ${totalRecords}`);

    while (true) {
        console.log(`📦 Fetching batch OFFSET ${offset} LIMIT ${batchSize}`);

        const [rows] = await mysqlConn.execute(`
            SELECT 
                c.id, c.email, c.firstName, c.middleName, c.lastName,
                c.mobile, c.phone, c.dob, c.status, c.gender,
                c.serviceLocation, c.creationDate, c.idNumber, c.acquired
            FROM customer c
            INNER JOIN serviceProviderCustomerDetails scd 
                ON scd.customerId = c.id 
            WHERE scd.serviceProviderId = ? and c.id > ?
            LIMIT ? 
        `, [serviceProviderId,lastId, batchSize]);

        if (rows.length === 0) {
            console.log("🎉 All customers fully migrated.");
            break;
        }

        const batchValues = [];

        for (const row of rows) {
            lastId = row.id;
            const serviceProviderId = CONFIG.serviceProviderId;

            const [serviceProviderName] = await mysqlConn.query(
                `SELECT legalName AS name FROM serviceProvider WHERE id = ?`,
                [serviceProviderId]
            );

            const [prefs] = await mysqlConn.execute(
                `SELECT * FROM customerPreferences WHERE customerId = ? LIMIT 1`,
                [row.id]
            );

            const [tagRows] = await mysqlConn.execute(
                `SELECT t.tagName 
                 FROM customerTags ct
                 JOIN tags t ON ct.tagId = t.id
                 WHERE ct.customerId = ? AND ct.status=1 AND t.status=1`,
                [row.id]
            );

            const [referralRows] = await mysqlConn.execute(
                `SELECT referralText FROM serviceProviderCustomerDetails WHERE customerId = ? LIMIT 1`,
                [row.id]
            );

            const [points] = await mysqlConn.execute(
                `SELECT SUM(availablePoints) AS points 
                 FROM rewardPoints 
                 WHERE customerId = ? 
                   AND dateExpire >= CURDATE() 
                   AND status IN (1,6)`,
                [row.id]
            );
            const CustomerName = [row.firstName, row.middleName, row.lastName]
  .map(v => v?.trim())
  .filter(Boolean)
  .join(' ');

            batchValues.push({
                id: row.id,
                franchise_id: 0,
                franchise: "88 Tactical",
                provider_id: serviceProviderId,
                provider: serviceProviderName[0]?.name || '',
                CustomerName:  CustomerName.replace(/\s+/g, ' '),
                FirstName: row.firstName || '',
                MiddleName: row.middleName || '',
                LastName: row.lastName || '',
                Email: row.email || '',
                Phone: row.phone || '',
                Mobile: row.mobile || '',
                DateOfBirth: formatDateOnly(row.dob),
                Gender: row.gender,
                IsMember: 'No',
                MemberId: row.idNumber || '',
                Status: mapStatus(row.status),
                Acquisition: Aquire(row.acquired),
                Address: '',
                City: '',
                State: '',
                Country: '',
                Zipcode: '',
                Unsubscribed: mapUnsubscribe(prefs[0]),
                Tag: tagRows.map(t => t.tagName).join(','),
                LoyaltyPoints: points[0]?.points || 0,
                Referral: referralRows[0]?.referralText || '',
                created_at: formatDate(row.creationDate),
                updated_at: formatDate(new Date()),
                deleted_at: null
            });
        }

        // INSERT BATCH INTO CLICKHOUSE
        console.log(`⬆️ Inserting ${batchValues.length} rows into ClickHouse`);
        await clickhouse.insert({
            table: TABLE_KEY,
            values: batchValues,
            format: "JSONEachRow"
        });

        totalInserted += batchValues.length;
        offset += batchSize;
    }
    if(totalRecords >0 && totalInserted == totalRecords){
           await updateLastMigratedId(clickhouse, TABLE_KEY, lastId, totalRecords);
console.log(`✔ Migrated up to ID: ${lastId}`);
    console.log(`✅ TOTAL INSERTED INTO CLICKHOUSE: ${totalInserted}`); 
    }

}

async function migrateData() {
   const mysqlConn = await mysql.createConnection({
        host: 'reader-temp.cs3e3cx0hfys.us-west-2.rds.amazonaws.com',
        user: 'bizzflo',
        password: 'my5qlskeedazz!!',
        database: 'bizzflo'
    });
     console.log("✅ Connected to MySQL!");

    const [resultRows] = await mysqlConn.execute('SELECT NOW() AS now');
    console.log("DB Time:", resultRows[0].now);

    const clickhouse = createClient({
      url: 'http://localhost:8123',
    username: 'default',
    password: '',
    database: 'clickHouseInvoice',
    });

    try {
//          const providerIds = await getDistinctServiceProviders(mysqlConn);
//       console.log(`🔑 Found ${providerIds.length} service providers`);
//    for (const providerId of providerIds) {
const providerId = 2087;
      const tableName = `customers_${providerId}`;
    //   console.log(`\n🚀 Migrating provider ${providerId}`);

      await createInvoiceTable(clickhouse, tableName);
       await migrateCustomers(mysqlConn, clickhouse, providerId);
    // }
      
    } finally {
        await mysqlConn.end();
        await clickhouse.close();
    }
}

migrateData();
