<?php
/**
 * AJAX Database Backups & System Maintenance Controller
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce authentication
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$can_edit = has_role(['Administrator', 'Manager']);
if (!$can_edit) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized action.']);
    exit();
}

$backup_dir = __DIR__ . '/../backups/files/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

$action = clean_input($_GET['action'] ?? 'list');

switch ($action) {
    
    // --- LIST BACKUP FILES ---
    case 'list':
        $files = glob($backup_dir . '*.sql');
        $backups = [];
        
        foreach ($files as $file) {
            $filename = basename($file);
            $backups[] = [
                'filename' => $filename,
                'created_at' => date('Y-m-d H:i:s', filemtime($file)),
                'size' => filesize($file)
            ];
        }
        
        // Sort by created_at DESC
        usort($backups, function($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });
        
        echo json_encode([
            'success' => true,
            'backups' => $backups
        ]);
        exit();
        break;
        
    // --- PURE PHP DATABASE SQL DUMP GENERATOR ---
    case 'generate':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit();
        }
        
        // Verify CSRF
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'CSRF verification failed.']);
            exit();
        }
        
        try {
            // Get all tables
            $tables = [];
            $res = mysqli_query($conn, "SHOW TABLES");
            while ($row = mysqli_fetch_row($res)) {
                $tables[] = $row[0];
            }
            
            $sql_dump = "-- Shop Management System SQL Backup\n";
            $sql_dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $sql_dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
            
            foreach ($tables as $table) {
                // Drop Table
                $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
                
                // Show Create Table
                $create_res = mysqli_query($conn, "SHOW CREATE TABLE `$table`");
                $create_row = mysqli_fetch_row($create_res);
                $sql_dump .= $create_row[1] . ";\n\n";
                
                // Fetch rows
                $rows_res = mysqli_query($conn, "SELECT * FROM `$table`");
                $num_fields = mysqli_num_fields($rows_res);
                
                while ($row = mysqli_fetch_row($rows_res)) {
                    $sql_dump .= "INSERT INTO `$table` VALUES (";
                    for ($i = 0; $i < $num_fields; $i++) {
                        if (is_null($row[$i])) {
                            $sql_dump .= "NULL";
                        } elseif (isset($row[$i])) {
                            $escaped = mysqli_real_escape_string($conn, $row[$i]);
                            $sql_dump .= "'" . $escaped . "'";
                        } else {
                            $sql_dump .= "''";
                        }
                        
                        if ($i < ($num_fields - 1)) {
                            $sql_dump .= ",";
                        }
                    }
                    $sql_dump .= ");\n";
                }
                $sql_dump .= "\n";
            }
            
            $sql_dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
            
            $backup_filename = 'backup_' . date('Ymd_His') . '_' . rand(1000, 9999) . '.sql';
            $file_path = $backup_dir . $backup_filename;
            
            if (file_put_contents($file_path, $sql_dump) === false) {
                throw new Exception("Failed to write SQL backup to disk.");
            }
            
            log_activity($conn, 'Database Backup', "Generated backup file: $backup_filename");
            
            echo json_encode([
                'success' => true,
                'message' => 'Database backup generated successfully!',
                'filename' => $backup_filename
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Backup failed: ' . $e->getMessage()]);
        }
        exit();
        break;
        
    // --- DELETE BACKUP FILE ---
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit();
        }
        
        $filename = clean_input($_POST['filename'] ?? '');
        $safe_filename = basename($filename);
        
        if (empty($safe_filename) || !file_exists($backup_dir . $safe_filename)) {
            echo json_encode(['success' => false, 'message' => 'Backup file does not exist.']);
            exit();
        }
        
        if (unlink($backup_dir . $safe_filename)) {
            log_activity($conn, 'Delete Backup', "Deleted backup file: $safe_filename");
            echo json_encode(['success' => true, 'message' => 'Backup file deleted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete backup file.']);
        }
        exit();
        break;
        
    // --- RESTORE SELECT FILE ---
    case 'restore':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit();
        }
        
        // Verify CSRF
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'CSRF verification failed.']);
            exit();
        }
        
        $filename = clean_input($_POST['filename'] ?? '');
        $safe_filename = basename($filename);
        $file_path = $backup_dir . $safe_filename;
        
        if (empty($safe_filename) || !file_exists($file_path)) {
            echo json_encode(['success' => false, 'message' => 'Selected backup file not found.']);
            exit();
        }
        
        $sql_content = file_get_contents($file_path);
        if (execute_sql_restoration($conn, $sql_content)) {
            log_activity($conn, 'Database Restore', "Restored database state from backup: $safe_filename");
            echo json_encode(['success' => true, 'message' => 'Database restored successfully! All tables reconstructed.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to restore database. See database logs.']);
        }
        exit();
        break;
        
    // --- UPLOAD & RESTORE SQL FILE ---
    case 'upload_restore':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit();
        }
        
        // Verify CSRF
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'CSRF verification failed.']);
            exit();
        }
        
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'File upload error. Check file limits.']);
            exit();
        }
        
        $file_name = $_FILES['backup_file']['name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if ($ext !== 'sql') {
            echo json_encode(['success' => false, 'message' => 'Invalid file format. Please upload a .sql file.']);
            exit();
        }
        
        $sql_content = file_get_contents($_FILES['backup_file']['tmp_name']);
        
        if (execute_sql_restoration($conn, $sql_content)) {
            log_activity($conn, 'Database Restore Upload', "Restored database state from uploaded file: $file_name");
            echo json_encode(['success' => true, 'message' => 'Uploaded database restored successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Restoration failed. SQL parsing error.']);
        }
        exit();
        break;
        
    // --- FORCE DOWNLOAD BACKUP FILE ---
    case 'download':
        $filename = clean_input($_GET['filename'] ?? '');
        $safe_filename = basename($filename);
        $file_path = $backup_dir . $safe_filename;
        
        if (empty($safe_filename) || !file_exists($file_path)) {
            die("Backup file not found.");
        }
        
        // Output headers
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $safe_filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        
        // Clean buffer output to avoid prepended notices
        ob_clean();
        flush();
        
        readfile($file_path);
        exit();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid backup action parameter.']);
        exit();
}

/**
 * Parses and executes SQL restoration queries sequentially
 */
function execute_sql_restoration($conn, $sql_content) {
    // Strip comments
    $sql_clean = preg_replace('/--(.*)\n/', '', $sql_content);
    $sql_clean = preg_replace('/\/\*(.*)\*\//s', '', $sql_clean);
    
    // Split queries by semicolon (avoid splitting on strings with semicolons inside by executing carefully)
    $queries = explode(";\n", $sql_clean);
    
    // Disable constraints
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0;");
    
    $success = true;
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) continue;
        
        if (!mysqli_query($conn, $query)) {
            $success = false;
            // Capture errors
            error_log("SQL Restore Error: " . mysqli_error($conn) . " | Query: " . $query);
        }
    }
    
    // Enable constraints
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1;");
    
    return $success;
}
