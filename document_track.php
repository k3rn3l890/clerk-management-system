<?php
/**
 * Document tracking endpoint
 * Receives geolocation data and logs document access
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
    if (!isset($data['document_id']) || !isset($data['action_type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    
    // Initialize database
    $db = new Database();
    
    try {
        // Log document access
        $db->query("INSERT INTO document_access_logs (
                        document_id, 
                        user_id, 
                        action_type, 
                        ip_address, 
                        user_agent,
                        latitude, 
                        longitude, 
                        location_accuracy, 
                        location_name, 
                        device_type
                    ) VALUES (
                        :document_id, 
                        :user_id, 
                        :action_type, 
                        :ip_address, 
                        :user_agent,
                        :latitude, 
                        :longitude, 
                        :location_accuracy, 
                        :location_name, 
                        :device_type
                    )");
        
        $db->bind(':document_id', (int)$data['document_id']);
        $db->bind(':user_id', $_SESSION['user_id']);
        $db->bind(':action_type', $data['action_type']);
        $db->bind(':ip_address', $_SERVER['REMOTE_ADDR']);
        $db->bind(':user_agent', $_SERVER['HTTP_USER_AGENT']);
        $db->bind(':latitude', $data['latitude'] ?? null);
        $db->bind(':longitude', $data['longitude'] ?? null);
        $db->bind(':location_accuracy', $data['location_accuracy'] ?? null);
        $db->bind(':location_name', $data['location_name'] ?? null);
        $db->bind(':device_type', $data['device_type'] ?? null);
        
        $db->execute();
        $logId = $db->lastInsertId();
        
        // Update the document's last accessed info
        $db->query("UPDATE documents SET 
                    last_accessed_at = NOW(), 
                    last_accessed_by = :user_id 
                    WHERE document_id = :document_id");
        $db->bind(':user_id', $_SESSION['user_id']);
        $db->bind(':document_id', (int)$data['document_id']);
        $db->execute();
        
        // Return success response
        echo json_encode([
            'success' => true,
            'log_id' => $logId,
            'message' => 'Document access logged successfully'
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
