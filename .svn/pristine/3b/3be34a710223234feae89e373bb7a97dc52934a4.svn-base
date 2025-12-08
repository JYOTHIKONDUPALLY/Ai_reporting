import mysql from 'mysql2/promise';
import { createClient } from '@clickhouse/client';

const CONFIG = {
    serviceProviderId: 2087,
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

/**
 * BATCH MIGRATION
 */
async function migrateCustomers(mysqlConn, clickhouse, batchSize = 2000) {
    let offset = 0;
    let totalInserted = 0;

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
            WHERE scd.serviceProviderId = ?
            LIMIT ? OFFSET ?
        `, [CONFIG.serviceProviderId, batchSize, offset]);

        if (rows.length === 0) {
            console.log("🎉 All customers fully migrated.");
            break;
        }

        const batchValues = [];

        for (const row of rows) {
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

            batchValues.push({
                id: row.id,
                franchise_id: 0,
                franchise: "88 Tactical",
                provider_id: serviceProviderId,
                provider: serviceProviderName[0]?.name || '',
                CustomerName: `${row.firstName || ''} ${row.middleName || ''} ${row.lastName || ''}`.trim(),
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
            table: "customers",
            values: batchValues,
            format: "JSONEachRow"
        });

        totalInserted += batchValues.length;
        offset += batchSize;
    }

    console.log(`✅ TOTAL INSERTED INTO CLICKHOUSE: ${totalInserted}`);
}

async function migrateData() {
   const mysqlConn = await mysql.createConnection({
        host: 'bizzflo-production-aurora3-cluster.cluster-ro-cs3e3cx0hfys.us-west-2.rds.amazonaws.com',
        user: 'bizzflo',
        password: 'my5qlskeedazz!!',
        database: 'bizzflo'
    });

    const clickhouse = createClient({
        url: "http://localhost:8123",
        database: "clickHouseInvoice"
    });

    try {
        await migrateCustomers(mysqlConn, clickhouse);
    } finally {
        await mysqlConn.end();
        await clickhouse.close();
    }
}

migrateData();
