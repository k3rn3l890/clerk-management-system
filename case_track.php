<?php
/**
 * Case tracking endpoint
 * Receives geolocation data and logs case activity
 */
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Only process POST requests with JSON content
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user is logged in
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    // Get JSON data from request body
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);
    
    // Validate required fields
    if (!isset($data['case_id']) || !isset($data['activity_type']) || !isset($data['description'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    
    // Initialize database
    $db = new Database();
    
    try {
        // Log case activity
        $db->query("INSERT INTO case_activity_logs (
                        case_id, 
                        user_id, 
                        activity_type, 
                        description, 
                        ip_address,
                        latitude, 
                        longitude, 
                        location_name
                    ) VALUES (
                        :case_id, 
                        :user_id, 
                        :activity_type, 
                        :description, 
                        :ip_address,
                        :latitude, 
                        :longitude, 
                        :location_name
                    )");
        
        $db->bind(':case_id', (int)$data['case_id']);
        $db->bind(':user_id', $_SESSION['user_id']);
        $db->bind(':activity_type', $data['activity_type']);
        $db->bind(':description', $data['description']);
        $db->bind(':ip_address', $_SERVER['REMOTE_ADDR']);
        $db->bind(':latitude', $data['latitude'] ?? null);
        $db->bind(':longitude', $data['longitude'] ?? null);
        $db->bind(':location_name', $data['location_name'] ?? null);
        
        $db->execute();
        $logId = $db->lastInsertId();
        
        // Return success response
        echo json_encode([
            'success' => true,
            'log_id' => $logId,
            'message' => 'Case activity logged successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
