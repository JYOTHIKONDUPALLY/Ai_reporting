import mysql from 'mysql2/promise';
import { createClient } from '@clickhouse/client';

const CONFIG = {
  serviceProviderId: 2087, //todo: add serviecprovider id
  dateFrom: process.env.DATE_FROM || null,
  dateTo: process.env.DATE_TO || null,
  batchSize: 2000
};


function formatDate(dateValue) {
  if (!dateValue) return '1970-01-01 00:00:00';

  let date;
  try {
    if (dateValue instanceof Date) {
      date = dateValue;
    } else {
      const dateStr = String(dateValue).trim();
      if (dateStr.includes(' ') && !dateStr.includes('T')) {
        date = new Date(dateStr.replace(' ', 'T') + 'Z');
      } else {
        date = new Date(dateStr);
      }
    }
    if (isNaN(date.getTime())) return '1970-01-01 00:00:00';

    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    const h = String(date.getHours()).padStart(2, '0');
    const mi = String(date.getMinutes()).padStart(2, '0');
    const s = String(date.getSeconds()).padStart(2, '0');
    return `${y}-${m}-${d} ${h}:${mi}:${s}`;
  } catch {
    return '1970-01-01 00:00:00';
  }
}

function formatDateOnly(dateValue) {
  if (!dateValue) return '1970-01-01';

  let date;
  try {
    if (dateValue instanceof Date) {
      date = dateValue;
    } else {
      date = new Date(String(dateValue).trim());
    }
    if (isNaN(date.getTime())) return '1970-01-01';

    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  } catch {
    return '1970-01-01';
  }
}

async function getLastMigratedId(clickhouse, tableName) {
  const result = await clickhouse.query({
    query: `SELECT last_migrated_id 
            FROM migration_progress 
            WHERE table_name = {table_name:String}
            ORDER BY updated_at DESC 
            LIMIT 1`,
    format: 'JSONEachRow',
    query_params: { table_name: tableName }
  });

  const rows = await result.json();
  return rows.length ? rows[0].last_migrated_id : 0;
}

async function updateLastMigratedId(clickhouse, tableName, lastId) {
  await clickhouse.insert({
    table: 'migration_progress',
    values: [{
      table_name: tableName,
      last_migrated_id: lastId,
      updated_at: new Date().toISOString().slice(0, 19).replace("T", " ")
    }],
    format: 'JSONEachRow'
  });
  console.log("Updated the last migrated ID");
}

// Helper function to fetch data in batches and create lookup maps
async function fetchLookupData(mysqlConnection, table, ids, idField = 'id') {
  if (!ids || ids.length === 0) return new Map();
  
  const uniqueIds = [...new Set(ids)].filter(id => id > 0);
  if (uniqueIds.length === 0) return new Map();

  const placeholders = uniqueIds.map(() => '?').join(',');
  const [rows] = await mysqlConnection.query(
    `SELECT * FROM ${table} WHERE ${idField} IN (${placeholders})`,
    uniqueIds
  );

  const map = new Map();
  rows.forEach(row => map.set(row[idField], row));
  return map;
}

// Fetch related data with compound keys
async function fetchInvoiceItems(mysqlConnection, invoiceIds, classIds) {
  if (!invoiceIds || invoiceIds.length === 0) return new Map();
  
  const uniqueInvoiceIds = [...new Set(invoiceIds)].filter(id => id > 0);
  if (uniqueInvoiceIds.length === 0) return new Map();

  const placeholders = uniqueInvoiceIds.map(() => '?').join(',');
  const [rows] = await mysqlConnection.query(
    `SELECT * FROM invoiceItemNew 
     WHERE invoiceId IN (${placeholders}) AND type = 'class'`,
    uniqueInvoiceIds
  );

  const map = new Map();
  rows.forEach(row => {
    const key = `${row.invoiceId}_${row.itemId}`;
    map.set(key, row);
  });
  return map;
}

// Fetch customer details with compound key
async function fetchCustomerDetails(mysqlConnection, customerIds, serviceProviderId) {
  if (!customerIds || customerIds.length === 0) return new Map();
  
  const uniqueCustomerIds = [...new Set(customerIds)].filter(id => id > 0);
  if (uniqueCustomerIds.length === 0) return new Map();

  const placeholders = uniqueCustomerIds.map(() => '?').join(',');
  const [rows] = await mysqlConnection.query(
    `SELECT * FROM serviceProviderCustomerDetails 
     WHERE customerId IN (${placeholders}) AND serviceProviderId = ?`,
    [...uniqueCustomerIds, serviceProviderId]
  );

  const map = new Map();
  rows.forEach(row => map.set(row.customerId, row));
  return map;
}

export async function migrateClassSession(mysqlConnection, clickhouseClient, batchSize = 2000) {
  const TABLE_KEY = "class_sessions";
  let lastId = await getLastMigratedId(clickhouseClient, TABLE_KEY);
  console.log(`▶ Resuming migration from class.id > ${lastId}`);

  // Step 1: First, get all valid class IDs that match our criteria
  console.log("⏳ Fetching valid class IDs...");
  let classWhereClause = `WHERE status > 0 AND id > ${lastId}`;
  if (CONFIG.serviceProviderId) {
    classWhereClause += ` AND serviceProviderId = ${CONFIG.serviceProviderId}`;
  }
  
  const [validClasses] = await mysqlConnection.query(
    `SELECT id FROM class ${classWhereClause}`
  );
  
  if (validClasses.length === 0) {
    console.log("✅ No valid classes found for the given service provider");
    return;
  }
  
  const validClassIds = validClasses.map(c => c.id);
  console.log(`📦 Found ${validClassIds.length} valid classes`);

  // Step 2: Get enrollments for these classes
  console.log("⏳ Fetching enrollments for valid classes...");
  const classIdPlaceholders = validClassIds.map(() => '?').join(',');
  const [validEnrollments] = await mysqlConnection.query(
    `SELECT id, classId FROM classEnrollment WHERE classId IN (${classIdPlaceholders})`,
    validClassIds
  );
  
  if (validEnrollments.length === 0) {
    console.log("✅ No enrollments found for valid classes");
    return;
  }
  
  const validEnrollmentIds = validEnrollments.map(e => e.id);
  console.log(`📦 Found ${validEnrollmentIds.length} valid enrollments`);

  // Step 3: Now fetch enrollment sessions for these valid enrollments
  console.log("⏳ Fetching enrollment sessions...");
  const enrollmentIdPlaceholders = validEnrollmentIds.map(() => '?').join(',');
  const [enrollmentSessions] = await mysqlConnection.query(
    `SELECT * FROM classEnrollmentSessions 
     WHERE status = 1 AND classEnrollmentId IN (${enrollmentIdPlaceholders})
     ORDER BY id DESC`,
    validEnrollmentIds
  );
  console.log(`📦 ${enrollmentSessions.length} enrollment sessions fetched`);

  if (enrollmentSessions.length === 0) {
    console.log("✅ No new records to migrate");
    return;
  }

  // Extract IDs for batch fetching
  const enrollmentIds = enrollmentSessions.map(ces => ces.classEnrollmentId);
  const sessionIds = enrollmentSessions.map(ces => ces.sessionId);

  // Step 2: Fetch all enrollments
  console.log("⏳ Fetching enrollments...");
  const enrollmentsMap = await fetchLookupData(mysqlConnection, 'classEnrollment', enrollmentIds);

  // Extract more IDs from enrollments
  const classIds = [...enrollmentsMap.values()].map(ce => ce.classId);
  const customerIds = [...enrollmentsMap.values()].map(ce => ce.customerId);
  const locationIds = [...enrollmentsMap.values()].map(ce => ce.locationId).filter(id => id > 0);
  const invoiceIds = [...enrollmentsMap.values()].map(ce => ce.invoiceId).filter(id => id > 0);
  const customerMemberIds = [...enrollmentsMap.values()].map(ce => ce.customerMemberId).filter(id => id > 0);

  // Step 3: Fetch classes and filter by serviceProviderId and status
  console.log("⏳ Fetching classes...");
  let classesMap = await fetchLookupData(mysqlConnection, 'class', classIds);
  
  // Filter classes by serviceProviderId and status
  const filteredClassesMap = new Map();
  for (const [id, cls] of classesMap) {
    if (cls.status > 0 && cls.serviceProviderId === CONFIG.serviceProviderId) {
      filteredClassesMap.set(id, cls);
    }
  }
  classesMap = filteredClassesMap;

  if (classesMap.size === 0) {
    console.log("✅ No classes match the service provider filter");
    return;
  }

  // Extract more IDs from classes
  const classTypeIds = [...classesMap.values()].map(c => c.classTypeId).filter(id => id > 0);
  const classCategoryIds = [...classesMap.values()].map(c => c.classCategoryId).filter(id => id > 0);

  // Step 4: Fetch sessions and filter by date if needed
  console.log("⏳ Fetching sessions...");
  let sessionsMap = await fetchLookupData(mysqlConnection, 'classSession', sessionIds);
  
  // Filter sessions by date if configured
  if (CONFIG.dateFrom || CONFIG.dateTo) {
    const filteredSessionsMap = new Map();
    for (const [id, session] of sessionsMap) {
      let include = true;
      if (CONFIG.dateFrom && session.date < CONFIG.dateFrom) include = false;
      if (CONFIG.dateTo && session.date > CONFIG.dateTo) include = false;
      if (include) filteredSessionsMap.set(id, session);
    }
    sessionsMap = filteredSessionsMap;
  }

  // Extract resource IDs from sessions
  const resourceIds = [...sessionsMap.values()].map(s => s.resourceId).filter(id => id > 0);

  // Step 5: Fetch all related data
  console.log("⏳ Fetching related data...");
  const [classTypesMap, classCategoriesMap, resourcesMap, locationsMap, 
         customerDetailsMap, customerMembersMap, invoicesMap, invoiceItemsMap] = await Promise.all([
    fetchLookupData(mysqlConnection, 'classType', classTypeIds),
    fetchLookupData(mysqlConnection, 'classCategory', classCategoryIds),
    fetchLookupData(mysqlConnection, 'resource', resourceIds),
    fetchLookupData(mysqlConnection, 'location', locationIds),
    fetchCustomerDetails(mysqlConnection, customerIds, CONFIG.serviceProviderId),
    fetchLookupData(mysqlConnection, 'customerMembers', customerMemberIds),
    fetchLookupData(mysqlConnection, 'invoiceNew', invoiceIds),
    fetchInvoiceItems(mysqlConnection, invoiceIds, classIds)
  ]);

  console.log("✅ All related data fetched");

  // Step 6: Process and transform data
  const transformedRows = [];
  let processedCount = 0;

  for (const ces of enrollmentSessions) {
    const enrollment = enrollmentsMap.get(ces.classEnrollmentId);
    if (!enrollment) continue;

    const cls = classesMap.get(enrollment.classId);
    if (!cls) continue; // Skip if class doesn't match filters

    const session = sessionsMap.get(ces.sessionId);
    if (!session) continue; // Skip if session doesn't match date filters

    const classType = classTypesMap.get(cls.classTypeId);
    const classCategory = classCategoriesMap.get(cls.classCategoryId);
    const resource = resourcesMap.get(session.resourceId);
    const location = locationsMap.get(enrollment.locationId);
    const customerDetails = customerDetailsMap.get(enrollment.customerId);
    const customerMember = customerMembersMap.get(enrollment.customerMemberId);
    const invoice = invoicesMap.get(enrollment.invoiceId);
    const invoiceItemKey = `${enrollment.invoiceId}_${cls.id}`;
    const invoiceItem = invoiceItemsMap.get(invoiceItemKey);

    // Map to ClickHouse format
    const row = {
      session_id: session.id || 0,
      class_id: cls.id || 0,
      enrollment_id: enrollment.id || 0,
      enrollment_session_id: ces.id || 0,
      customer_id: enrollment.customerId || 0,
      invoice_id: enrollment.invoiceId || 0,
      service_provider_id: cls.serviceProviderId || 0,
      location_id: enrollment.locationId || 0,
      location_name: location?.name || '',
      class_name: cls.name || '',
      class_type_id: cls.classTypeId || 0,
      class_type_name: classType?.name || '',
      class_category_id: cls.classCategoryId || 0,
      class_category_name: classCategory?.name || '',
      class_capacity: cls.attendies || 0,
      class_status: cls.status || 0,
      class_enrollment_status: cls.enrollmentStatus === 1 ? 'Enrollment Open' :
                                cls.enrollmentStatus === 2 ? 'Do Not Publish' :
                                cls.enrollmentStatus === 0 ? 'Enrollment Closed' : 'Unknown',
      session_name: session.name || '',
      session_date: formatDateOnly(session.date),
      session_start_time: session.startTime || '',
      session_end_time: session.endTime || '',
      session_duration: session.duration || '',
      session_status: session.status === 1 ? 'Active' :
                      session.status === 0 ? 'deleted' : 'Unknown',
      is_parent_session: (session.parentId || 0) === 0 ? 1 : 0,
      resource_id: session.resourceId || 0,
      resource_name: resource ? `${resource.firstName || ''} ${resource.lastName || ''}`.trim() : '',
      additional_resource_ids: session.additionalResourceId || '',
      customer_member_id: enrollment.customerMemberId || 0,
      member_name: customerMember ? `${customerMember.firstName || ''} ${customerMember.lastName || ''}`.trim() : 'Self',
      customer_name: customerDetails ? `${customerDetails.firstName || ''} ${customerDetails.lastName || ''}`.trim() : '',
      enrollment_status: enrollment.status === 1 ? 'Enrolled' :
                         enrollment.status === 3 ? 'Waiting List' :
                         enrollment.status === 0 ? 'deleted' : 'Unknown',
      enrollment_quantity: enrollment.quantity || 1,
      enrollment_creation_date: formatDate(enrollment.creationDate),
      booking_method: !invoice ? 'Online' :
                      invoice.bookingType === 1 ? 'Online' :
                      invoice.bookingType === 3 ? 'Walk_in' :
                      invoice.bookingType === 2 ? 'phone_in' :
                      invoice.bookingType === 4 ? 'Mobile_app' : 'Unknown',
      payment_status: enrollment.payment || 0,
      payment_type_id: enrollment.paymentId || 0,
      is_checked_in: ces.checkedin || 0,
      item_price: invoiceItem ? parseFloat(invoiceItem.price) || 0 : 0,
      quantity: invoiceItem ? invoiceItem.qty || 0 : 0,
      total_price: invoiceItem ? parseFloat(invoiceItem.totalPrice) || 0 : 0,
      sale_discount: 0,
      discount_amount: invoiceItem ? parseFloat(invoiceItem.discount) || 0 : 0,
      tax_amount: invoiceItem ? parseFloat(invoiceItem.tax) || 0 : 0,
      net_amount: invoiceItem ? parseFloat(invoiceItem.totalPrice - invoiceItem.discount) || 0 : 0,
      promotion_id: 0,
      promotion_name: '',
      invoice_created_at: formatDate(invoice?.invoiceDate || enrollment.creationDate),
      created_at: formatDate(new Date()),
      updated_at: formatDate(new Date())
    };

    transformedRows.push(row);
    lastId = Math.max(lastId, cls.id);
    processedCount++;
  }

  console.log(`📊 Total records to migrate: ${transformedRows.length}`);

  // Step 7: Insert in batches
  let inserted = 0;
  for (let i = 0; i < transformedRows.length; i += batchSize) {
    const batch = transformedRows.slice(i, i + batchSize);
    
    await clickhouseClient.insert({
      table: "class_sessions",
      values: batch,
      format: "JSONEachRow",
    });

    inserted += batch.length;
    console.log(`✔ Inserted ${inserted} of ${transformedRows.length}`);
  }

  await updateLastMigratedId(clickhouseClient, TABLE_KEY, lastId);
  console.log(`✔ Migrated up to class ID: ${lastId}`);
  console.log(`\n🎉 Migration completed. Total inserted: ${inserted}`);
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
    username: "default",
    password: "",
    database: "clickHouseInvoice",
  });

  try {
    await migrateClassSession(mysqlConn, clickhouse);
  } finally {
    await mysqlConn.end();
    await clickhouse.close();
  }
}

migrateData();