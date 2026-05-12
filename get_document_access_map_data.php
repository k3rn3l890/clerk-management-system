<?php
/**
 * API: Get Document Access Map Data (JSON)
 * Returns geolocated document access logs as map points with filters.
 */
require_once 'includes/functions.php';

header('Content-Type: application/json');

try {
    // Auth check
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    // Permission check (same as document_tracking.php)
    if (!hasRole(['court_clerk', 'admin', 'judge'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }

    $db = new Database();

    // Filters
    $documentId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $caseId     = isset($_GET['case_id']) ? (int)$_GET['case_id'] : null;
    $startDate  = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : '';
    $endDate    = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : '';
    $actionType = isset($_GET['action_type']) ? sanitize($_GET['action_type']) : '';
    $userId     = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $sinceTime  = isset($_GET['since_time']) ? sanitize($_GET['since_time']) : '';

    // Validate action type if provided
    $validActions = ['view','download','edit','delete'];
    if ($actionType !== '' && !in_array(strtolower($actionType), $validActions, true)) {
        $actionType = '';
    }

    // Validate dates (YYYY-MM-DD)
    $dateRegex = '/^\d{4}-\d{2}-\d{2}$/';
    if ($startDate !== '' && !preg_match($dateRegex, $startDate)) $startDate = '';
    if ($endDate !== '' && !preg_match($dateRegex, $endDate)) $endDate = '';
    
    // Build datetime bounds for index-friendly comparisons
    $startDateTime = $startDate !== '' ? $startDate . ' 00:00:00' : '';
    $endDateTime   = $endDate   !== '' ? $endDate   . ' 23:59:59' : '';

    // Validate since_time (Y-m-d H:i:s)
    $sinceRegex = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
    if ($sinceTime !== '' && !preg_match($sinceRegex, $sinceTime)) {
        $sinceTime = '';
    }

    // Build query
    $sql = "SELECT 
                dal.log_id,
                dal.document_id,
                dal.user_id,
                dal.action_type,
                dal.latitude,
                dal.longitude,
                dal.location_name,
                dal.access_time,
                d.document_title,
                CONCAT(u.first_name, ' ', u.last_name) AS user_name
            FROM document_access_logs dal
            JOIN documents d ON dal.document_id = d.document_id
            JOIN cases c ON d.case_id = c.case_id
            JOIN users u ON dal.user_id = u.user_id
            WHERE dal.latitude IS NOT NULL AND dal.longitude IS NOT NULL";

    $params = [];

    if ($documentId) {
        $sql .= " AND dal.document_id = :document_id";
        $params[':document_id'] = $documentId;
    }

    if ($caseId) {
        $sql .= " AND c.case_id = :case_id";
        $params[':case_id'] = $caseId;
    }

    if ($startDateTime !== '') {
        $sql .= " AND dal.access_time >= :start_dt";
        $params[':start_dt'] = $startDateTime;
    }

    if ($endDateTime !== '') {
        $sql .= " AND dal.access_time <= :end_dt";
        $params[':end_dt'] = $endDateTime;
    }

    if ($actionType !== '') {
        $sql .= " AND dal.action_type = :action_type";
        $params[':action_type'] = strtolower($actionType);
    }

    if (!empty($userId)) {
        $sql .= " AND dal.user_id = :user_id";
        $params[':user_id'] = $userId;
    }

    if ($sinceTime !== '') {
        $sql .= " AND dal.access_time > :since_time";
        $params[':since_time'] = $sinceTime;
    }

    $sql .= " ORDER BY dal.access_time ASC LIMIT 500"; // chronological for appending

    $db->query($sql);
    foreach ($params as $k => $v) {
        $db->bind($k, $v);
    }

    $rows = $db->resultSet();

    $points = [];
    foreach ($rows as $row) {
        $points[] = [
            'log_id'     => (int)$row['log_id'],
            'lat'        => isset($row['latitude']) ? (float)$row['latitude'] : null,
            'lng'        => isset($row['longitude']) ? (float)$row['longitude'] : null,
            'title'      => $row['user_name'],
            'action'     => ucfirst($row['action_type']),
            'document'   => $row['document_title'],
            'time'       => date('Y-m-d H:i:s', strtotime($row['access_time'])),
            'documentId' => (int)$row['document_id'],
            'userId'     => (int)$row['user_id'],
            'location'   => $row['location_name']
        ];
    }

    echo json_encode([
        'success' => true,
        'count'   => count($points),
        'points'  => $points
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}
