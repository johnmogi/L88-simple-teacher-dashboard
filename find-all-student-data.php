<?php
/**
 * Find All Student Data
 * 
 * Search for all possible student data sources in the database
 * Access: https://207lilac.local/wp-content/plugins/simple-teacher-dashboard/find-all-student-data.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Access denied. Please log in as administrator.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Find All Student Data</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; font-size: 11px; }
        th { background: #f8f9fa; font-weight: bold; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 10px; max-height: 200px; overflow-y: auto; }
        .table-info { margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Find All Student Data Sources</h1>
        
        <div class="card">
            <h2>📊 All Database Tables</h2>
            <?php
            global $wpdb;
            
            // Get all tables
            $all_tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
            $student_related_tables = array();
            $class_related_tables = array();
            $other_tables = array();
            
            foreach ($all_tables as $table) {
                $table_name = $table[0];
                if (strpos(strtolower($table_name), 'student') !== false) {
                    $student_related_tables[] = $table_name;
                } elseif (strpos(strtolower($table_name), 'class') !== false || 
                          strpos(strtolower($table_name), 'group') !== false ||
                          strpos(strtolower($table_name), 'course') !== false) {
                    $class_related_tables[] = $table_name;
                } else {
                    $other_tables[] = $table_name;
                }
            }
            
            echo '<h3>Student-Related Tables (' . count($student_related_tables) . '):</h3>';
            foreach ($student_related_tables as $table) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
                echo '<div class="table-info">';
                echo '<strong>' . $table . '</strong> - ' . $count . ' records';
                
                // Show sample data
                $sample = $wpdb->get_results("SELECT * FROM `$table` LIMIT 3", ARRAY_A);
                if (!empty($sample)) {
                    echo '<details><summary>Sample Data</summary>';
                    echo '<pre>' . print_r($sample, true) . '</pre>';
                    echo '</details>';
                }
                echo '</div>';
            }
            
            echo '<h3>Class/Group-Related Tables (' . count($class_related_tables) . '):</h3>';
            foreach ($class_related_tables as $table) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
                echo '<div class="table-info">';
                echo '<strong>' . $table . '</strong> - ' . $count . ' records';
                
                // Show sample data
                $sample = $wpdb->get_results("SELECT * FROM `$table` LIMIT 3", ARRAY_A);
                if (!empty($sample)) {
                    echo '<details><summary>Sample Data</summary>';
                    echo '<pre>' . print_r($sample, true) . '</pre>';
                    echo '</details>';
                }
                echo '</div>';
            }
            ?>
        </div>
        
        <div class="card">
            <h2>🔍 Search for David's Data</h2>
            <?php
            // Search in all student-related tables for David
            echo '<h3>Searching for "David" across all tables:</h3>';
            
            foreach ($student_related_tables as $table) {
                // Get table structure
                $columns = $wpdb->get_results("DESCRIBE `$table`");
                $text_columns = array();
                
                foreach ($columns as $column) {
                    if (strpos(strtolower($column->Type), 'varchar') !== false || 
                        strpos(strtolower($column->Type), 'text') !== false ||
                        strpos(strtolower($column->Type), 'char') !== false) {
                        $text_columns[] = $column->Field;
                    }
                }
                
                if (!empty($text_columns)) {
                    $where_conditions = array();
                    foreach ($text_columns as $col) {
                        $where_conditions[] = "`$col` LIKE '%david%'";
                    }
                    
                    $query = "SELECT * FROM `$table` WHERE " . implode(' OR ', $where_conditions);
                    $results = $wpdb->get_results($query, ARRAY_A);
                    
                    if (!empty($results)) {
                        echo '<div class="success">';
                        echo '<h4>Found in ' . $table . ' (' . count($results) . ' records):</h4>';
                        echo '<pre>' . print_r($results, true) . '</pre>';
                        echo '</div>';
                    }
                }
            }
            
            // Also search in users table
            $david_users = $wpdb->get_results("
                SELECT * FROM {$wpdb->users} 
                WHERE display_name LIKE '%david%' 
                OR user_login LIKE '%david%' 
                OR user_email LIKE '%david%'
            ", ARRAY_A);
            
            if (!empty($david_users)) {
                echo '<div class="success">';
                echo '<h4>Found David in users table:</h4>';
                echo '<pre>' . print_r($david_users, true) . '</pre>';
                echo '</div>';
            }
            ?>
        </div>
        
        <div class="card">
            <h2>📈 Large Student Groups</h2>
            <p>Looking for classes/groups with many students (like the missing 20-student group):</p>
            <?php
            // Check all possible student-class relationship tables
            $relationship_queries = array(
                'school_student_classes' => "
                    SELECT 
                        c.id as class_id,
                        c.name as class_name,
                        c.group_id,
                        COUNT(sc.student_id) as student_count
                    FROM {$wpdb->prefix}school_classes c
                    LEFT JOIN {$wpdb->prefix}school_student_classes sc ON c.id = sc.class_id
                    GROUP BY c.id
                    HAVING student_count > 5
                    ORDER BY student_count DESC
                ",
                'student_classes' => "
                    SELECT 
                        class_id,
                        COUNT(student_id) as student_count
                    FROM {$wpdb->prefix}student_classes
                    GROUP BY class_id
                    HAVING student_count > 5
                    ORDER BY student_count DESC
                ",
                'learndash_groups' => "
                    SELECT 
                        p.ID as group_id,
                        p.post_title as group_name,
                        pm.meta_value as users_meta
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'learndash_group_users'
                    WHERE p.post_type = 'groups'
                    AND p.post_status = 'publish'
                "
            );
            
            foreach ($relationship_queries as $source => $query) {
                echo '<h4>Source: ' . $source . '</h4>';
                
                $table_exists = true;
                if (strpos($source, 'school_') === 0) {
                    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}school_classes'") == $wpdb->prefix . 'school_classes';
                } elseif ($source === 'student_classes') {
                    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}student_classes'") == $wpdb->prefix . 'student_classes';
                }
                
                if ($table_exists) {
                    $results = $wpdb->get_results($query, ARRAY_A);
                    
                    if (!empty($results)) {
                        echo '<table>';
                        echo '<tr>';
                        foreach (array_keys($results[0]) as $header) {
                            echo '<th>' . $header . '</th>';
                        }
                        echo '</tr>';
                        
                        foreach ($results as $row) {
                            echo '<tr>';
                            foreach ($row as $value) {
                                if (is_array($value) || is_object($value)) {
                                    echo '<td>' . print_r($value, true) . '</td>';
                                } else {
                                    echo '<td>' . htmlspecialchars($value) . '</td>';
                                }
                            }
                            echo '</tr>';
                        }
                        echo '</table>';
                    } else {
                        echo '<p class="warning">No large groups found in ' . $source . '</p>';
                    }
                } else {
                    echo '<p class="error">Table for ' . $source . ' does not exist</p>';
                }
            }
            ?>
        </div>
        
        <div class="card">
            <h2>🔧 Recommendations</h2>
            <div class="info">
                <h4>Based on the investigation:</h4>
                <ol>
                    <li><strong>Check table prefixes:</strong> Data might be in tables with different prefixes</li>
                    <li><strong>Look for backup data:</strong> Missing data might be in backup tables</li>
                    <li><strong>Verify data migration:</strong> Data might not have been properly migrated</li>
                    <li><strong>Check for soft deletes:</strong> Data might be marked as deleted rather than actually deleted</li>
                </ol>
            </div>
        </div>
    </div>
</body>
</html>
