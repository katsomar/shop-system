<?php
/**
 * Database Connection using MySQLi
 */

require_once __DIR__ . '/config.php';

// Establish connection to MySQL server
$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS);

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// Try selecting the database
try {
    $db_selected = @mysqli_select_db($conn, DB_NAME);
} catch (mysqli_sql_exception $e) {
    $db_selected = false;
}

if (!$db_selected) {
    // Database doesn't exist, try to create it
    $sql_create_db = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if (mysqli_query($conn, $sql_create_db)) {
        try {
            mysqli_select_db($conn, DB_NAME);
        } catch (mysqli_sql_exception $e) {
            die("Database selection failed after creation: " . $e->getMessage());
        }
        
        // Let's import the schema automatically if we can find it
        $schema_path = dirname(__DIR__) . '/database/schema.sql';
        if (file_exists($schema_path)) {
            $schema_sql = file_get_contents($schema_path);
            
            // Execute multi-query to import schema
            if (mysqli_multi_query($conn, $schema_sql)) {
                // Clear mysqli_multi_query results buffer
                do {
                    if ($result = mysqli_store_result($conn)) {
                        mysqli_free_result($result);
                    }
                } while (mysqli_more_results($conn) && mysqli_next_result($conn));
            }
        }
    } else {
        die("Database selection and automatic creation failed: " . mysqli_error($conn));
    }
}

// Set charset to utf8mb4
mysqli_set_charset($conn, 'utf8mb4');
