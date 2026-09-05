<?php
/**
 * AMC Visits API & Workflow Engine
 * Handles full 6-step AMC visit workflow:
 * Step 1: ASSIGNED -> ACCEPTED
 * Step 2: REACHED (Arrival Photo + Remark + Timestamp + GPS)
 * Step 3: INSPECTION (Product Condition + Inspection Result + Service Performed)
 * Step 4: ISSUE / MAINTENANCE REQUIREMENT (Record Issue/Part + Follow-up flag if required)
 * Step 5: SERVICE COMPLETION (After-Service Photo + Final Remark)
 * Step 6: DEPARTURE (Departure Photo + Departure Remark + GPS -> COMPLETED)
 */

include __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/image_helper.php';
require_once __DIR__ . '/amc_helper.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$staffName = getStaffName();
$staffRole = getStaffRole();
$isAdmin = isAdmin();

// Automatic Trigger: Run 48-Hour Inactivity Reassignment Check on visit API calls
checkAndApply48HourReassignment($conn);

try {
    // 1. List AMC Visits (Engineer Dashboard View vs Admin Overview)
    if ($action === 'list') {
        $filter_eng = isset($_GET['engineer']) ? trim($_GET['engineer']) : '';
        $filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
        $filter_contract = intval($_GET['contract_id'] ?? 0);
        $mine_only = isset($_GET['mine_only']) && $_GET['mine_only'] == '1';

        $where = ["1=1"];

        // If engineer and mine_only, restrict to logged-in engineer ONLY
        if (!$isAdmin || $mine_only) {
            $eName = $conn->real_escape_string($staffName);
            $where[] = "v.assigned_engineer = '$eName'";
        } elseif ($filter_eng !== '') {
            $eName = $conn->real_escape_string($filter_eng);
            $where[] = "v.assigned_engineer = '$eName'";
        }

        if ($filter_status !== '') {
            $st = $conn->real_escape_string($filter_status);
            $where[] = "v.status = '$st'";
        }

        if ($filter_contract > 0) {
            $where[] = "v.contract_id = {$filter_contract}";
        }

        $whereClause = implode(' AND ', $where);

        $query = "
            SELECT v.*, 
                   c.amc_number, c.customer_name, c.customer_phone, c.customer_email, c.customer_address, c.company_name
            FROM amc_visits v
            JOIN amc_contracts c ON v.contract_id = c.id
            WHERE $whereClause
            ORDER BY 
                CASE 
                    WHEN v.status = 'ASSIGNED' THEN 1
                    WHEN v.status = 'ACCEPTED' THEN 2
                    WHEN v.status = 'REACHED' THEN 3
                    WHEN v.status = 'INSPECTION' THEN 4
                    WHEN v.status = 'FOLLOW-UP REQUIRED' THEN 5
                    WHEN v.status = 'OVERDUE' THEN 6
                    WHEN v.status = 'COMPLETED' THEN 7
                    ELSE 8
                END ASC,
                v.scheduled_date ASC
        ";

        $res = $conn->query($query);
        $visits = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                // Products covered in contract
                $cpRes = $conn->query("SELECT product_name, quantity FROM amc_contract_products WHERE contract_id = {$row['contract_id']}");
                $prods = [];
                if ($cpRes) {
                    while ($cpRow = $cpRes->fetch_assoc()) $prods[] = $cpRow['product_name'] . ($cpRow['quantity'] > 1 ? " (x{$cpRow['quantity']})" : "");
                }
                $row['products_covered'] = implode(', ', $prods);
                $visits[] = $row;
            }
        }

        echo json_encode(['status' => 'success', 'data' => $visits]);
        exit;
    }

    // 2. Get Single Visit Details + PREVIOUS MAINTENANCE HISTORY
    if ($action === 'get_details') {
        $visit_id = intval($_GET['visit_id'] ?? 0);
        if (!$visit_id) {
            echo json_encode(['status' => 'error', 'message' => 'Visit ID is required.']);
            exit;
        }

        // Fetch current visit and contract details
        $stmt = $conn->prepare("
            SELECT v.*, 
                   c.amc_number, c.customer_name, c.customer_phone, c.customer_email, c.customer_address, c.company_name, c.start_date, c.end_date, c.visit_count
            FROM amc_visits v
            JOIN amc_contracts c ON v.contract_id = c.id
            WHERE v.id = ?
        ");
        $stmt->bind_param("i", $visit_id);
        $stmt->execute();
        $vRes = $stmt->get_result();
        if (!$vRes || $vRes->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Visit not found.']);
            exit;
        }
        $visit = $vRes->fetch_assoc();
        $contract_id = $visit['contract_id'];

        // Access security: Engineers can view only assigned visits unless admin
        if (!$isAdmin && $visit['assigned_engineer'] !== $staffName) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized. This visit is assigned to another engineer.']);
            exit;
        }

        // Fetch Products Covered under Contract
        $cpRes = $conn->query("SELECT * FROM amc_contract_products WHERE contract_id = {$contract_id}");
        $products = [];
        if ($cpRes) {
            while ($pRow = $cpRes->fetch_assoc()) $products[] = $pRow;
        }
        $visit['products'] = $products;

        // Fetch Photos for Current Visit
        $pRes = $conn->query("SELECT * FROM amc_visit_photos WHERE visit_id = {$visit_id} ORDER BY id ASC");
        $photos = [];
        if ($pRes) {
            while ($phRow = $pRes->fetch_assoc()) $photos[] = $phRow;
        }
        $visit['photos'] = $photos;

        // Fetch Recorded Issues for Current Visit
        $iRes = $conn->query("SELECT * FROM amc_visit_issues WHERE visit_id = {$visit_id} ORDER BY id DESC");
        $issues = [];
        if ($iRes) {
            while ($issRow = $iRes->fetch_assoc()) $issues[] = $issRow;
        }
        $visit['issues'] = $issues;

        // Fetch Assignment History for Current Visit
        $assRes = $conn->query("SELECT * FROM amc_assignments WHERE visit_id = {$visit_id} ORDER BY id DESC");
        $assignments = [];
        if ($assRes) {
            while ($assRow = $assRes->fetch_assoc()) $assignments[] = $assRow;
        }
        $visit['assignments_history'] = $assignments;

        // ===== CRITICAL FEATURE: PREVIOUS MAINTENANCE HISTORY =====
        // Fetch completed previous visits for this customer / AMC contract
        $prevQuery = "
            SELECT pv.*, 
                   (SELECT COUNT(*) FROM amc_visit_photos WHERE visit_id = pv.id) as photo_count
            FROM amc_visits pv
            WHERE pv.contract_id = {$contract_id}
            AND pv.id != {$visit_id}
            AND pv.status = 'COMPLETED'
            ORDER BY pv.completion_timestamp DESC, pv.visit_number DESC
        ";
        $prevRes = $conn->query($prevQuery);
        $previous_history = [];
        if ($prevRes) {
            while ($prevRow = $prevRes->fetch_assoc()) {
                $pVid = $prevRow['id'];
                
                // Fetch photos of previous visit
                $prevPhotoRes = $conn->query("SELECT * FROM amc_visit_photos WHERE visit_id = {$pVid} ORDER BY id ASC");
                $prevPhotos = [];
                if ($prevPhotoRes) {
                    while ($ph = $prevPhotoRes->fetch_assoc()) $prevPhotos[] = $ph;
                }
                $prevRow['photos'] = $prevPhotos;

                // Fetch issues of previous visit
                $prevIssueRes = $conn->query("SELECT * FROM amc_visit_issues WHERE visit_id = {$pVid}");
                $prevIssues = [];
                if ($prevIssueRes) {
                    while ($pis = $prevIssueRes->fetch_assoc()) $prevIssues[] = $pis;
                }
                $prevRow['issues'] = $prevIssues;

                $previous_history[] = $prevRow;
            }
        }
        $visit['previous_maintenance_history'] = $previous_history;

        echo json_encode(['status' => 'success', 'data' => $visit]);
        exit;
    }

    // ===== STEP 1: ACCEPT ASSIGNMENT =====
    if ($action === 'step_accept') {
        $visit_id = intval($_POST['visit_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE amc_visits SET status = 'ACCEPTED', last_activity_timestamp = NOW() WHERE id = ?");
        $stmt->bind_param("i", $visit_id);
        $stmt->execute();

        logAmcAudit($conn, null, $visit_id, 'Visit Accepted', $staffName, $staffRole, "Engineer accepted AMC visit #{$visit_id}");
        echo json_encode(['status' => 'success', 'message' => 'Visit accepted successfully.']);
        exit;
    }

    // ===== STEP 2: REACH CUSTOMER LOCATION =====
    if ($action === 'step_reach') {
        $visit_id = intval($_POST['visit_id'] ?? 0);
        $arrival_remark = trim($_POST['arrival_remark'] ?? '');
        $lat = trim($_POST['latitude'] ?? '');
        $lng = trim($_POST['longitude'] ?? '');

        if (empty($arrival_remark)) {
            echo json_encode(['status' => 'error', 'message' => 'Arrival remark is mandatory.']);
            exit;
        }

        if (!isset($_FILES['arrival_photo']) || $_FILES['arrival_photo']['error'] !== 0) {
            echo json_encode(['status' => 'error', 'message' => 'Arrival photograph is mandatory.']);
            exit;
        }

        // Process Arrival Photo using AMC Watermark Helper
        $target_dir = __DIR__ . '/../../uploads/amc_photos/';
        $filename = processAndSaveAmcImage($_FILES['arrival_photo'], $target_dir, $lat, $lng, "INFINITY COMPUTER");

        if (!$filename) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to process arrival photo.']);
            exit;
        }

        $photo_path = 'uploads/amc_photos/' . $filename;

        // Fetch contract_id
        $vRes = $conn->query("SELECT contract_id FROM amc_visits WHERE id = {$visit_id}");
        $vRow = $vRes ? $vRes->fetch_assoc() : ['contract_id' => null];
        $contract_id = $vRow['contract_id'];

        // Save Arrival Photo Record
        $stmtP = $conn->prepare("INSERT INTO amc_visit_photos (visit_id, contract_id, engineer_name, photo_type, file_path, latitude, longitude, remark) VALUES (?, ?, ?, 'ARRIVAL', ?, ?, ?, ?)");
        $stmtP->bind_param("iisssss", $visit_id, $contract_id, $staffName, $photo_path, $lat, $lng, $arrival_remark);
        $stmtP->execute();

        // Update Visit Status
        $stmtV = $conn->prepare("UPDATE amc_visits SET status = 'REACHED', arrival_timestamp = NOW(), last_activity_timestamp = NOW(), arrival_lat = ?, arrival_lng = ?, arrival_remark = ? WHERE id = ?");
        $stmtV->bind_param("sssi", $lat, $lng, $arrival_remark, $visit_id);
        $stmtV->execute();

        logAmcAudit($conn, $contract_id, $visit_id, 'Reached Location', $staffName, $staffRole, "Engineer reached location. Photo & location saved.");

        echo json_encode(['status' => 'success', 'message' => 'Arrival location and photo recorded successfully.']);
        exit;
    }

    // ===== STEP 3: INSPECTION =====
    if ($action === 'step_inspection') {
        $visit_id = intval($_POST['visit_id'] ?? 0);
        $product_condition = trim($_POST['product_condition'] ?? 'Normal');
        $inspection_result = trim($_POST['inspection_result'] ?? '');
        $service_performed = trim($_POST['service_performed'] ?? '');

        if (empty($inspection_result)) {
            echo json_encode(['status' => 'error', 'message' => 'Inspection result description is required.']);
            exit;
        }

        // Optional Inspection Photos
        $uploaded_photos = [];
        if (isset($_FILES['inspection_photos'])) {
            $target_dir = __DIR__ . '/../../uploads/amc_photos/';
            $lat = trim($_POST['latitude'] ?? '');
            $lng = trim($_POST['longitude'] ?? '');

            $vRes = $conn->query("SELECT contract_id FROM amc_visits WHERE id = {$visit_id}");
            $contract_id = ($vRes && $r = $vRes->fetch_assoc()) ? $r['contract_id'] : null;

            // Handle single or multiple file uploads
            $files = $_FILES['inspection_photos'];
            if (is_array($files['name'])) {
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === 0) {
                        $singleFile = [
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $files['error'][$i],
                            'size' => $files['size'][$i]
                        ];
                        $filename = processAndSaveAmcImage($singleFile, $target_dir, $lat, $lng, "INFINITY COMPUTER");
                        if ($filename) {
                            $photo_path = 'uploads/amc_photos/' . $filename;
                            $stmtP = $conn->prepare("INSERT INTO amc_visit_photos (visit_id, contract_id, engineer_name, photo_type, file_path, latitude, longitude, remark) VALUES (?, ?, ?, 'BEFORE_SERVICE', ?, ?, ?, ?)");
                            $rem = "Inspection photo (" . $product_condition . ")";
                            $stmtP->bind_param("iisssss", $visit_id, $contract_id, $staffName, $photo_path, $lat, $lng, $rem);
                            $stmtP->execute();
                            $uploaded_photos[] = $photo_path;
                        }
                    }
                }
            } else if ($files['error'] === 0) {
                $filename = processAndSaveAmcImage($files, $target_dir, $lat, $lng, "INFINITY COMPUTER");
                if ($filename) {
                    $photo_path = 'uploads/amc_photos/' . $filename;
                    $stmtP = $conn->prepare("INSERT INTO amc_visit_photos (visit_id, contract_id, engineer_name, photo_type, file_path, latitude, longitude, remark) VALUES (?, ?, ?, 'BEFORE_SERVICE', ?, ?, ?, ?)");
                    $rem = "Inspection photo (" . $product_condition . ")";
                    $stmtP->bind_param("iisssss", $visit_id, $contract_id, $staffName, $photo_path, $lat, $lng, $rem);
                    $stmtP->execute();
                    $uploaded_photos[] = $photo_path;
                }
            }
        }

        $stmtV = $conn->prepare("UPDATE amc_visits SET status = 'INSPECTION', product_condition = ?, inspection_result = ?, service_performed = ?, last_activity_timestamp = NOW() WHERE id = ?");
        $stmtV->bind_param("sssi", $product_condition, $inspection_result, $service_performed, $visit_id);
        $stmtV->execute();

        logAmcAudit($conn, null, $visit_id, 'Inspection Recorded', $staffName, $staffRole, "Recorded inspection: Condition {$product_condition}");

        echo json_encode(['status' => 'success', 'message' => 'Inspection information recorded successfully.']);
        exit;
    }

    // ===== STEP 4: RECORD ISSUE / MAINTENANCE REQUIREMENT =====
    if ($action === 'step_record_issue') {
        $visit_id = intval($_POST['visit_id'] ?? 0);
        $product_name = trim($_POST['product_name'] ?? 'Product');
        $issue_title = trim($_POST['issue_title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $severity = trim($_POST['severity'] ?? 'Medium');
        $required_action = trim($_POST['required_action'] ?? '');
        $part_required = trim($_POST['part_required'] ?? '');
        $quantity = intval($_POST['quantity'] ?? 1);
        $engineer_remark = trim($_POST['engineer_remark'] ?? '');
        $requires_followup = isset($_POST['requires_followup']) && $_POST['requires_followup'] == '1';
        $lat = trim($_POST['latitude'] ?? '');
        $lng = trim($_POST['longitude'] ?? '');

        if (empty($issue_title) || empty($description)) {
            echo json_encode(['status' => 'error', 'message' => 'Issue title and description are required.']);
            exit;
        }

        $vRes = $conn->query("SELECT contract_id FROM amc_visits WHERE id = {$visit_id}");
        $contract_id = ($vRes && $r = $vRes->fetch_assoc()) ? $r['contract_id'] : null;

        $issue_photo_path = null;
        if (isset($_FILES['issue_photo']) && $_FILES['issue_photo']['error'] === 0) {
            $target_dir = __DIR__ . '/../../uploads/amc_photos/';
            $filename = processAndSaveAmcImage($_FILES['issue_photo'], $target_dir, $lat, $lng, "INFINITY COMPUTER");
            if ($filename) {
                $issue_photo_path = 'uploads/amc_photos/' . $filename;
                // Also save to amc_visit_photos
                $stmtP = $conn->prepare("INSERT INTO amc_visit_photos (visit_id, contract_id, engineer_name, photo_type, file_path, latitude, longitude, remark) VALUES (?, ?, ?, 'ISSUE', ?, ?, ?, ?)");
                $rem = "Issue Photo: " . $issue_title;
                $stmtP->bind_param("iisssss", $visit_id, $contract_id, $staffName, $issue_photo_path, $lat, $lng, $rem);
                $stmtP->execute();
            }
        }

        $issue_status = $requires_followup ? 'Follow-up Required' : 'Open';

        $stmtI = $conn->prepare("INSERT INTO amc_visit_issues (visit_id, contract_id, product_name, issue_title, description, severity, issue_photo, required_action, part_required, quantity, engineer_remark, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtI->bind_param("iisssssssiss", $visit_id, $contract_id, $product_name, $issue_title, $description, $severity, $issue_photo_path, $required_action, $part_required, $quantity, $engineer_remark, $issue_status);
        $stmtI->execute();

        if ($requires_followup) {
            $conn->query("UPDATE amc_visits SET status = 'FOLLOW-UP REQUIRED', follow_up_notes = 'Issue reported: {$issue_title} ({$required_action})', last_activity_timestamp = NOW() WHERE id = {$visit_id}");
        }

        logAmcAudit($conn, $contract_id, $visit_id, 'Issue Reported', $staffName, $staffRole, "Reported issue: {$issue_title} ({$severity}) for {$product_name}");

        echo json_encode(['status' => 'success', 'message' => 'Issue details recorded successfully.']);
        exit;
    }

    // ===== STEP 5 & 6: SERVICE COMPLETION & DEPARTURE =====
    if ($action === 'step_completion') {
        $visit_id = intval($_POST['visit_id'] ?? 0);
        $final_remark = trim($_POST['final_remark'] ?? '');
        $service_performed = trim($_POST['service_performed'] ?? '');
        $departure_remark = trim($_POST['departure_remark'] ?? '');
        $lat = trim($_POST['latitude'] ?? '');
        $lng = trim($_POST['longitude'] ?? '');

        // Validation Check
        if (empty($final_remark)) {
            echo json_encode(['status' => 'error', 'message' => 'Final service remark is mandatory before completion.']);
            exit;
        }
        if (empty($departure_remark)) {
            echo json_encode(['status' => 'error', 'message' => 'Departure remark is mandatory.']);
            exit;
        }

        // Verify Arrival photo exists
        $arrRes = $conn->query("SELECT COUNT(*) as cnt FROM amc_visit_photos WHERE visit_id = {$visit_id} AND photo_type = 'ARRIVAL'");
        $arrRow = $arrRes->fetch_assoc();
        if (intval($arrRow['cnt']) === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot complete visit: Arrival photo was not recorded.']);
            exit;
        }

        // Process After-Service Photo (Mandatory)
        if (!isset($_FILES['after_service_photo']) || $_FILES['after_service_photo']['error'] !== 0) {
            echo json_encode(['status' => 'error', 'message' => 'After-service photo is mandatory to complete the visit.']);
            exit;
        }

        // Process Departure Photo (Mandatory)
        if (!isset($_FILES['departure_photo']) || $_FILES['departure_photo']['error'] !== 0) {
            echo json_encode(['status' => 'error', 'message' => 'Departure photo is mandatory to finish the visit.']);
            exit;
        }

        $vRes = $conn->query("SELECT contract_id FROM amc_visits WHERE id = {$visit_id}");
        $contract_id = ($vRes && $r = $vRes->fetch_assoc()) ? $r['contract_id'] : null;
        $target_dir = __DIR__ . '/../../uploads/amc_photos/';

        // Save After-Service Photo
        $after_fn = processAndSaveAmcImage($_FILES['after_service_photo'], $target_dir, $lat, $lng, "INFINITY COMPUTER");
        if ($after_fn) {
            $after_path = 'uploads/amc_photos/' . $after_fn;
            $stmtP1 = $conn->prepare("INSERT INTO amc_visit_photos (visit_id, contract_id, engineer_name, photo_type, file_path, latitude, longitude, remark) VALUES (?, ?, ?, 'AFTER_SERVICE', ?, ?, ?, ?)");
            $stmtP1->bind_param("iisssss", $visit_id, $contract_id, $staffName, $after_path, $lat, $lng, $final_remark);
            $stmtP1->execute();
        }

        // Save Departure Photo
        $dep_fn = processAndSaveAmcImage($_FILES['departure_photo'], $target_dir, $lat, $lng, "INFINITY COMPUTER");
        if ($dep_fn) {
            $dep_path = 'uploads/amc_photos/' . $dep_fn;
            $stmtP2 = $conn->prepare("INSERT INTO amc_visit_photos (visit_id, contract_id, engineer_name, photo_type, file_path, latitude, longitude, remark) VALUES (?, ?, ?, 'DEPARTURE', ?, ?, ?, ?)");
            $stmtP2->bind_param("iisssss", $visit_id, $contract_id, $staffName, $dep_path, $lat, $lng, $departure_remark);
            $stmtP2->execute();
        }

        // Mark Visit COMPLETED
        $stmtV = $conn->prepare("UPDATE amc_visits SET status = 'COMPLETED', completion_timestamp = NOW(), departure_timestamp = NOW(), departure_lat = ?, departure_lng = ?, final_remark = ?, departure_remark = ?, service_performed = COALESCE(NULLIF(?, ''), service_performed), last_activity_timestamp = NOW() WHERE id = ?");
        $stmtV->bind_param("sssssi", $lat, $lng, $final_remark, $departure_remark, $service_performed, $visit_id);
        $stmtV->execute();

        // Check if all visits for contract are now completed -> update contract status if so
        $pendingRes = $conn->query("SELECT COUNT(*) as cnt FROM amc_visits WHERE contract_id = {$contract_id} AND status != 'COMPLETED' AND status != 'CANCELLED'");
        $pendingRow = $pendingRes ? $pendingRes->fetch_assoc() : ['cnt' => 1];
        if (intval($pendingRow['cnt']) === 0) {
            $conn->query("UPDATE amc_contracts SET status = 'Completed' WHERE id = {$contract_id}");
            logAmcAudit($conn, $contract_id, null, 'Contract Completed', 'System', 'System', "All visits completed for contract ID {$contract_id}");
        }

        logAmcAudit($conn, $contract_id, $visit_id, 'Visit Completed', $staffName, $staffRole, "AMC visit #{$visit_id} successfully completed with departure info.");

        echo json_encode(['status' => 'success', 'message' => 'AMC Visit completed successfully!']);
        exit;
    }

    // ===== ADMIN: RESCHEDULE VISIT =====
    if ($action === 'reschedule') {
        if (!$isAdmin) {
            echo json_encode(['status' => 'error', 'message' => 'Only Admin can reschedule visits.']);
            exit;
        }

        $visit_id = intval($_POST['visit_id'] ?? 0);
        $scheduled_date = trim($_POST['scheduled_date'] ?? '');
        $due_date = trim($_POST['due_date'] ?? '');

        if (empty($scheduled_date)) {
            echo json_encode(['status' => 'error', 'message' => 'New scheduled date is required.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE amc_visits SET scheduled_date = ?, due_date = ?, last_activity_timestamp = NOW() WHERE id = ?");
        $stmt->bind_param("ssi", $scheduled_date, $due_date, $visit_id);
        $stmt->execute();

        logAmcAudit($conn, null, $visit_id, 'Visit Rescheduled', $staffName, $staffRole, "Rescheduled visit #{$visit_id} to {$scheduled_date}");

        echo json_encode(['status' => 'success', 'message' => 'Visit rescheduled successfully.']);
        exit;
    }

    // ===== MANUAL / ENGINEER REASSIGNMENT =====
    if ($action === 'reassign' || $action === 'self_reassign') {
        $visit_id = intval($_POST['visit_id'] ?? 0);
        $new_engineer = trim($_POST['new_engineer'] ?? '');
        $reassign_reason = trim($_POST['reason'] ?? 'Reassigned by staff');

        if (empty($new_engineer)) {
            echo json_encode(['status' => 'error', 'message' => 'New engineer name required.']);
            exit;
        }

        $vRes = $conn->query("SELECT contract_id, assigned_engineer FROM amc_visits WHERE id = {$visit_id}");
        if (!$vRes || $vRes->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Visit not found.']);
            exit;
        }
        $vRow = $vRes->fetch_assoc();
        $old_engineer = $vRow['assigned_engineer'];
        $contract_id = $vRow['contract_id'];

        // Access Check: Admin or currently assigned engineer can reassign
        if (!$isAdmin && $old_engineer !== $staffName) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized to reassign this visit.']);
            exit;
        }

        // Update visit assignment and reset notification state
        $stmt = $conn->prepare("
            UPDATE amc_visits 
            SET assigned_engineer = ?, status = 'ASSIGNED', assignment_timestamp = NOW(), 
                last_activity_timestamp = NOW(), scheduled_day_email_sent = 0, 
                last_reminder_sent_at = NULL, reminder_count = 0 
            WHERE id = ?
        ");
        $stmt->bind_param("si", $new_engineer, $visit_id);
        $stmt->execute();

        // Expire previous active assignment log
        $conn->query("UPDATE amc_assignments SET status = 'Reassigned', expired_at = NOW() WHERE visit_id = {$visit_id} AND status = 'Active'");

        // Record new assignment in audit log
        $stmtAss = $conn->prepare("INSERT INTO amc_assignments (visit_id, contract_id, engineer_name, previous_engineer, assigned_by, assignment_reason, status) VALUES (?, ?, ?, ?, ?, ?, 'Active')");
        $stmtAss->bind_param("iissss", $visit_id, $contract_id, $new_engineer, $old_engineer, $staffName, $reassign_reason);
        $stmtAss->execute();

        logAmcAudit($conn, $contract_id, $visit_id, 'Visit Reassigned', $staffName, $staffRole, "Reassigned visit #{$visit_id} from {$old_engineer} to {$new_engineer} ({$reassign_reason})");

        // Send email to the newly assigned engineer
        sendAmcEngineerAssignmentEmail($conn, $visit_id, true);

        echo json_encode(['status' => 'success', 'message' => "Visit reassigned to {$new_engineer} successfully and notification email sent."]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
