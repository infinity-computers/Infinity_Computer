<?php
/**
 * AMC Contracts API
 * Manages AMC Contract creation, listing, details retrieval, update, status change, renewal, and dynamic products link.
 */

include __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/amc_helper.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$staffName = getStaffName();
$staffRole = getStaffRole();
$isAdmin = isAdmin();

try {
    if ($action === 'list') {
        // List contracts with filters
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';
        
        $where = ["1=1"];
        if ($search !== '') {
            $s = $conn->real_escape_string($search);
            $where[] = "(c.amc_number LIKE '%$s%' OR c.customer_name LIKE '%$s%' OR c.customer_phone LIKE '%$s%' OR c.company_name LIKE '%$s%')";
        }
        if ($status !== '') {
            $st = $conn->real_escape_string($status);
            $where[] = "c.status = '$st'";
        }
        
        $whereClause = implode(' AND ', $where);
        
        $query = "
            SELECT c.*, 
                   COUNT(DISTINCT v.id) as total_visits,
                   SUM(CASE WHEN v.status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_visits,
                   SUM(CASE WHEN v.status = 'OVERDUE' THEN 1 ELSE 0 END) as overdue_visits
            FROM amc_contracts c
            LEFT JOIN amc_visits v ON c.id = v.contract_id
            WHERE $whereClause
            GROUP BY c.id
            ORDER BY c.id DESC
        ";
        
        $res = $conn->query($query);
        $contracts = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                // Fetch connected products
                $cpRes = $conn->query("SELECT product_name, quantity, serial_number FROM amc_contract_products WHERE contract_id = {$row['id']}");
                $prods = [];
                if ($cpRes) {
                    while ($cpRow = $cpRes->fetch_assoc()) {
                        $prods[] = $cpRow;
                    }
                }
                $row['products'] = $prods;
                $contracts[] = $row;
            }
        }
        
        echo json_encode(['status' => 'success', 'data' => $contracts]);
        exit;
    }

    if ($action === 'get') {
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'Contract ID is required.']);
            exit;
        }

        // Fetch Contract Details
        $stmt = $conn->prepare("SELECT * FROM amc_contracts WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $contractRes = $stmt->get_result();
        if (!$contractRes || $contractRes->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Contract not found.']);
            exit;
        }
        $contract = $contractRes->fetch_assoc();

        // Products
        $cpRes = $conn->query("SELECT * FROM amc_contract_products WHERE contract_id = {$id}");
        $products = [];
        if ($cpRes) {
            while ($row = $cpRes->fetch_assoc()) $products[] = $row;
        }
        $contract['products'] = $products;

        // Visits Schedule
        $vRes = $conn->query("SELECT * FROM amc_visits WHERE contract_id = {$id} ORDER BY visit_number ASC");
        $visits = [];
        if ($vRes) {
            while ($vRow = $vRes->fetch_assoc()) {
                // Attach visit photos count and open issue count
                $pCountRes = $conn->query("SELECT COUNT(*) as cnt FROM amc_visit_photos WHERE visit_id = {$vRow['id']}");
                $pRow = $pCountRes ? $pCountRes->fetch_assoc() : ['cnt' => 0];
                $vRow['photos_count'] = intval($pRow['cnt']);

                $iCountRes = $conn->query("SELECT COUNT(*) as cnt FROM amc_visit_issues WHERE visit_id = {$vRow['id']} AND status != 'Resolved'");
                $iRow = $iCountRes ? $iCountRes->fetch_assoc() : ['cnt' => 0];
                $vRow['open_issues_count'] = intval($iRow['cnt']);

                $visits[] = $vRow;
            }
        }
        $contract['visits'] = $visits;

        // Complete Audit Log
        $aRes = $conn->query("SELECT * FROM amc_audit_logs WHERE contract_id = {$id} ORDER BY id DESC");
        $logs = [];
        if ($aRes) {
            while ($lRow = $aRes->fetch_assoc()) $logs[] = $lRow;
        }
        $contract['audit_logs'] = $logs;

        echo json_encode(['status' => 'success', 'data' => $contract]);
        exit;
    }

    if ($action === 'create') {
        if (!$isAdmin) {
            echo json_encode(['status' => 'error', 'message' => 'Only Admin can create AMC contracts.']);
            exit;
        }

        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $customer_email = trim($_POST['customer_email'] ?? '');
        $customer_address = trim($_POST['customer_address'] ?? '');
        $company_name = trim($_POST['company_name'] ?? '');
        $start_date = trim($_POST['start_date'] ?? date('Y-m-d'));
        $visit_count = intval($_POST['visit_count'] ?? 4);
        $visit_frequency = trim($_POST['visit_frequency'] ?? 'Quarterly');
        $remarks = trim($_POST['remarks'] ?? '');
        
        // Calculate End Date (Default 12 Months)
        $end_date_obj = new DateTime($start_date);
        $end_date_obj->modify('+1 year');
        $end_date = trim($_POST['end_date'] ?? $end_date_obj->format('Y-m-d'));

        if (empty($customer_name) || empty($customer_phone) || empty($customer_address)) {
            echo json_encode(['status' => 'error', 'message' => 'Customer name, phone, and address are required.']);
            exit;
        }

        $assigned_engineers_arr = isset($_POST['assigned_engineers']) ? (array)$_POST['assigned_engineers'] : [];
        $assigned_engineers_json = json_encode($assigned_engineers_arr);

        $amc_number = generateAmcNumber($conn);

        $stmt = $conn->prepare("INSERT INTO amc_contracts (amc_number, customer_name, customer_phone, customer_email, customer_address, company_name, start_date, end_date, visit_count, visit_frequency, status, assigned_engineers, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?, ?)");
        $stmt->bind_param("ssssssssissss", $amc_number, $customer_name, $customer_phone, $customer_email, $customer_address, $company_name, $start_date, $end_date, $visit_count, $visit_frequency, $assigned_engineers_json, $remarks, $staffName);
        
        if (!$stmt->execute()) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create AMC contract: ' . $conn->error]);
            exit;
        }

        $contract_id = $conn->insert_id;

        // Save Customer in main customers table if not existing
        $conn->query("INSERT IGNORE INTO customers (name, phone, email, company) VALUES ('" . $conn->real_escape_string($customer_name) . "', '" . $conn->real_escape_string($customer_phone) . "', '" . $conn->real_escape_string($customer_email) . "', '" . $conn->real_escape_string($company_name) . "')");

        // Insert Contract Products
        if (isset($_POST['products']) && is_array($_POST['products'])) {
            $stmtProd = $conn->prepare("INSERT INTO amc_contract_products (contract_id, product_id, product_name, quantity, serial_number, location_details, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($_POST['products'] as $prod) {
                $p_id = !empty($prod['product_id']) ? intval($prod['product_id']) : null;
                $p_name = trim($prod['product_name'] ?? 'Product');
                $p_qty = intval($prod['quantity'] ?? 1);
                $p_sn = trim($prod['serial_number'] ?? '');
                $p_loc = trim($prod['location_details'] ?? '');
                $p_notes = trim($prod['notes'] ?? '');

                if (!empty($p_name)) {
                    $stmtProd->bind_param("iisisss", $contract_id, $p_id, $p_name, $p_qty, $p_sn, $p_loc, $p_notes);
                    $stmtProd->execute();
                }
            }
        }

        // Generate Visits automatically across 12-month period
        $createdVisits = generateAmcVisits($conn, $contract_id, $start_date, $end_date, $visit_count, $assigned_engineers_arr, $staffName);

        logAmcAudit($conn, $contract_id, null, 'Contract Created', $staffName, $staffRole, "Created AMC contract {$amc_number} with {$visit_count} visits for {$customer_name}");

        echo json_encode([
            'status' => 'success',
            'message' => "AMC Contract {$amc_number} created successfully with {$visit_count} visits scheduled.",
            'contract_id' => $contract_id,
            'amc_number' => $amc_number,
            'visits' => $createdVisits
        ]);
        exit;
    }

    if ($action === 'update') {
        if (!$isAdmin) {
            echo json_encode(['status' => 'error', 'message' => 'Only Admin can update AMC contracts.']);
            exit;
        }

        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'Contract ID required.']);
            exit;
        }

        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $customer_email = trim($_POST['customer_email'] ?? '');
        $customer_address = trim($_POST['customer_address'] ?? '');
        $company_name = trim($_POST['company_name'] ?? '');
        $status = trim($_POST['status'] ?? 'Active');
        $remarks = trim($_POST['remarks'] ?? '');

        $stmt = $conn->prepare("UPDATE amc_contracts SET customer_name = ?, customer_phone = ?, customer_email = ?, customer_address = ?, company_name = ?, status = ?, remarks = ? WHERE id = ?");
        $stmt->bind_param("sssssssi", $customer_name, $customer_phone, $customer_email, $customer_address, $company_name, $status, $remarks, $id);
        $stmt->execute();

        logAmcAudit($conn, $id, null, 'Contract Updated', $staffName, $staffRole, "Updated details for contract ID {$id}");

        echo json_encode(['status' => 'success', 'message' => 'Contract updated successfully.']);
        exit;
    }

    if ($action === 'renew') {
        if (!$isAdmin) {
            echo json_encode(['status' => 'error', 'message' => 'Only Admin can renew contracts.']);
            exit;
        }

        $id = intval($_POST['id'] ?? 0);
        $oldRes = $conn->query("SELECT * FROM amc_contracts WHERE id = {$id}");
        if (!$oldRes || $oldRes->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Contract not found.']);
            exit;
        }
        $oldContract = $oldRes->fetch_assoc();

        // Calculate Renewal Dates
        $new_start_date = date('Y-m-d');
        $new_end_obj = new DateTime($new_start_date);
        $new_end_obj->modify('+1 year');
        $new_end_date = $new_end_obj->format('Y-m-d');

        $new_amc_number = generateAmcNumber($conn);
        $visit_count = intval($oldContract['visit_count']);

        $stmt = $conn->prepare("INSERT INTO amc_contracts (amc_number, customer_name, customer_phone, customer_email, customer_address, company_name, start_date, end_date, visit_count, visit_frequency, status, assigned_engineers, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?, ?)");
        $remarks = "Renewal of " . $oldContract['amc_number'];
        $stmt->bind_param("ssssssssissss", $new_amc_number, $oldContract['customer_name'], $oldContract['customer_phone'], $oldContract['customer_email'], $oldContract['customer_address'], $oldContract['company_name'], $new_start_date, $new_end_date, $visit_count, $oldContract['visit_frequency'], $oldContract['assigned_engineers'], $remarks, $staffName);
        $stmt->execute();

        $new_contract_id = $conn->insert_id;

        // Copy Products over to renewed contract
        $oldProds = $conn->query("SELECT * FROM amc_contract_products WHERE contract_id = {$id}");
        if ($oldProds) {
            $stmtP = $conn->prepare("INSERT INTO amc_contract_products (contract_id, product_id, product_name, quantity, serial_number, location_details, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            while ($pRow = $oldProds->fetch_assoc()) {
                $stmtP->bind_param("iisisss", $new_contract_id, $pRow['product_id'], $pRow['product_name'], $pRow['quantity'], $pRow['serial_number'], $pRow['location_details'], $pRow['notes']);
                $stmtP->execute();
            }
        }

        // Generate Visits for renewed contract
        $engineers = json_decode($oldContract['assigned_engineers'], true) ?: [];
        generateAmcVisits($conn, $new_contract_id, $new_start_date, $new_end_date, $visit_count, $engineers, $staffName);

        // Mark old contract completed / renewed
        $conn->query("UPDATE amc_contracts SET status = 'Completed' WHERE id = {$id}");

        logAmcAudit($conn, $id, null, 'Contract Renewed', $staffName, $staffRole, "Renewed contract {$oldContract['amc_number']} as new contract {$new_amc_number}");
        logAmcAudit($conn, $new_contract_id, null, 'Contract Created (Renewal)', $staffName, $staffRole, "Created renewed contract {$new_amc_number} from previous contract {$oldContract['amc_number']}");

        echo json_encode([
            'status' => 'success',
            'message' => "Contract {$oldContract['amc_number']} successfully renewed as {$new_amc_number}.",
            'new_contract_id' => $new_contract_id,
            'new_amc_number' => $new_amc_number
        ]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
