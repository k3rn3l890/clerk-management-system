<?php
/**
 * Location Agreement API
 * This endpoint handles recording when a user agrees to the location policy
 */
require_once 'includes/functions.php';

// Check if the user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'error' => 'User is not authenticated']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON data from request body
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true) ?: [];

// Record the agreement
$success = logLocationPolicyAgreement(
    $_SESSION['user_id'], 
    $data['ip_address'] ?? $_SERVER['REMOTE_ADDR']
);

if ($success) {
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Location policy agreement recorded successfully',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} else {
    // Return error response
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'error' => 'Failed to record agreement']);
} 