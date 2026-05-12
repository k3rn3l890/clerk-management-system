<?php
/**
 * Migration to add last access tracking fields to documents table
 */

class AddDocumentAccessFields {
    public function up($db) {
        try {
            // First, check if the columns already exist
            $columnsExist = false;
            try {
                $checkSql = "
                    SELECT COUNT(*) as column_exists 
                    FROM information_schema.COLUMNS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'documents' 
                    AND COLUMN_NAME IN ('last_accessed_by', 'last_accessed_at')
                ";
                
                $db->query($checkSql);
                $result = $db->single();
                $columnsExist = !empty($result) && $result['column_exists'] > 0;
            } catch (Exception $e) {
                // If there's an error, assume columns don't exist
                error_log("Check columns error: " . $e->getMessage());
            }
            
            // Only add columns if they don't exist
            if (!$columnsExist) {
                // Add last_accessed_by and last_accessed_at columns
                $sql = [
                    "ALTER TABLE documents ADD COLUMN last_accessed_by INT NULL",
                    "ALTER TABLE documents ADD COLUMN last_accessed_at DATETIME NULL",
                    "ALTER TABLE documents ADD CONSTRAINT fk_documents_last_accessed_by 
                        FOREIGN KEY (last_accessed_by) REFERENCES users(user_id) ON DELETE SET NULL"
                ];
                
                // Execute each statement separately
                foreach ($sql as $query) {
                    $db->query($query);
                    $db->execute();
                }
                error_log("Successfully added document access tracking fields");
                return true;
            } else {
                error_log("Document access tracking fields already exist");
                return true;
            }
            
        } catch (PDOException $e) {
            error_log("Error in AddDocumentAccessFields migration: " . $e->getMessage());
            throw $e; // Re-throw to be caught by the migration runner
        }
    }
    
    public function down($db) {
        try {
            // Check if the columns exist before trying to drop them
            $columnsExist = false;
            try {
                $checkSql = "
                    SELECT COUNT(*) as column_exists 
                    FROM information_schema.COLUMNS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'documents' 
                    AND COLUMN_NAME IN ('last_accessed_by', 'last_accessed_at')
                ";
                
                $db->query($checkSql);
                $result = $db->single();
                $columnsExist = !empty($result) && $result['column_exists'] > 0;
            } catch (Exception $e) {
                // If there's an error, assume columns don't exist
                error_log("Check columns error (down): " . $e->getMessage());
            }
            
            if ($columnsExist) {
                // Drop the foreign key constraint first if it exists
                try {
                    $db->query("ALTER TABLE documents DROP FOREIGN KEY fk_documents_last_accessed_by");
                    $db->execute();
                } catch (Exception $e) {
                    // Ignore errors if the constraint doesn't exist
                    error_log("Note: Could not drop foreign key (might not exist): " . $e->getMessage());
                }
                
                // Then drop the columns
                $dropColumns = [
                    "ALTER TABLE documents DROP COLUMN IF EXISTS last_accessed_by",
                    "ALTER TABLE documents DROP COLUMN IF EXISTS last_accessed_at"
                ];
                
                foreach ($dropColumns as $query) {
                    try {
                        $db->query($query);
                        $db->execute();
                    } catch (Exception $e) {
                        error_log("Error dropping column: " . $e->getMessage());
                    }
                }
                
                error_log("Successfully removed document access tracking fields");
            } else {
                error_log("Document access tracking fields do not exist, nothing to drop");
            }
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error in AddDocumentAccessFields migration (down): " . $e->getMessage());
            throw $e; // Re-throw to be caught by the migration runner
        }
    }
}

// Check if this file is being executed directly (for testing)
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($argv[0])) {
    require_once __DIR__ . '/../../config/database.php';
    $migration = new AddDocumentAccessFields();
    $migration->up($db);
}
