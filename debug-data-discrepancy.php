<?php
/**
 * Debug Data Discrepancy
 * 
 * Compare what the dashboard shows vs. what's in the database
 * Access: https://207lilac.local/wp-content/plugins/simple-teacher-dashboard/debug-data-discrepancy.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!is_user_logged_in()) {
    wp_die('Please log in first.');
}

$group_id = 10027; // The group showing in your screenshot

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Data Discrepancy</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; font-size: 12px; }
        th { background: #f8f9fa; font-weight: bold; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 11px; max-height: 300px; overflow-y: auto; }
        button { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; background: #007cba; color: white; }
        .compare { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug Data Discrepancy - Group <?php echo $group_id; ?></h1>
        
        <div class="card info">
            <h2>📊 What Dashboard Shows vs. Database Reality</h2>
            <p>Dashboard shows: <strong>Ian Thompson, Julia Roberts</strong> for group 10027 "Class: TEST"</p>
            <p>You report: Query is empty, missing David's test group with 20 students</p>
        </div>
        
        <div class="compare">
            <div class="card">
                <h2>🎯 Current AJAX Method Result</h2>
                <p>Testing the fixed <code>get_group_students()</code> method:</p>
                <button onclick="testCurrentMethod()">Test Current Method</button>
                <div id="current-result"></div>
            </div>
            
            <div class="card">
                <h2>📋 Direct Database Query</h2>
                <p>Raw database query for group <?php echo $group_id; ?>:</p>
                <?php
                global $wpdb;
                
                // Direct query using the exact SQL structure
                $direct_students = $wpdb->get_results($wpdb->prepare("
                    SELECT 
                        u.ID as student_id,
                        u.user_login,
                        u.display_name,
                        u.user_email,
                        c.id as class_id,
                        c.name as class_name
                    FROM {$wpdb->users} u
                    JOIN {$wpdb->prefix}school_student_classes sc ON u.ID = sc.student_id
                    JOIN {$wpdb->prefix}school_classes c ON sc.class_id = c.id
                    WHERE c.group_id = %d
                    ORDER BY u.display_name
                ", $group_id));
                
                echo '<p><strong>Direct query result:</strong> ' . count($direct_students) . ' students</p>';
                
                if (!empty($direct_students)) {
                    echo '<table>';
                    echo '<tr><th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Class</th></tr>';
                    foreach ($direct_students as $student) {
                        echo '<tr>';
                        echo '<td>' . $student->student_id . '</td>';
                        echo '<td>' . $student->user_login . '</td>';
                        echo '<td>' . $student->display_name . '</td>';
                        echo '<td>' . $student->user_email . '</td>';
                        echo '<td>' . $student->class_name . ' (ID: ' . $student->class_id . ')</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<p class="warning">❌ No students found in direct query</p>';
                }
                ?>
            </div>
        </div>
        
        <div class="card">
            <h2>🔍 All Data Sources Investigation</h2>
            
            <h3>1. School Manager Tables</h3>
            <?php
            // Check all School Manager data
            $classes = $wpdb->get_results("
                SELECT c.*, COUNT(sc.student_id) as student_count
                FROM {$wpdb->prefix}school_classes c
                LEFT JOIN {$wpdb->prefix}school_student_classes sc ON c.id = sc.class_id
                WHERE c.group_id = $group_id
                GROUP BY c.id
            ");
            
            if (!empty($classes)) {
                echo '<table>';
                echo '<tr><th>Class ID</th><th>Class Name</th><th>Teacher ID</th><th>Group ID</th><th>Students</th></tr>';
                foreach ($classes as $class) {
                    echo '<tr>';
                    echo '<td>' . $class->id . '</td>';
                    echo '<td>' . $class->name . '</td>';
                    echo '<td>' . $class->teacher_id . '</td>';
                    echo '<td>' . $class->group_id . '</td>';
                    echo '<td>' . $class->student_count . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p class="warning">No classes found for group ' . $group_id . '</p>';
            }
            
            echo '<h3>2. LearnDash Group Data</h3>';
            $group_post = get_post($group_id);
            if ($group_post) {
                echo '<p><strong>Group Title:</strong> ' . $group_post->post_title . '</p>';
                echo '<p><strong>Group Status:</strong> ' . $group_post->post_status . '</p>';
                
                // Check LearnDash meta
                $ld_users = get_post_meta($group_id, 'learndash_group_users', true);
                echo '<p><strong>LearnDash Users Meta:</strong> ';
                if (is_array($ld_users)) {
                    echo count($ld_users) . ' users';
                    if (!empty($ld_users)) {
                        echo ' (IDs: ' . implode(', ', $ld_users) . ')';
                    }
                } else {
                    echo 'Not an array: ' . gettype($ld_users);
                }
                echo '</p>';
            } else {
                echo '<p class="error">Group post does not exist</p>';
            }
            
            echo '<h3>3. Alternative Data Sources</h3>';
            
            // Check for other possible tables
            $tables_to_check = array(
                $wpdb->prefix . 'students',
                $wpdb->prefix . 'student_classes',
                $wpdb->prefix . 'classes',
                $wpdb->prefix . 'learndash_user_activity'
            );
            
            foreach ($tables_to_check as $table) {
                $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
                echo '<p><strong>' . basename($table) . ':</strong> ' . ($exists ? '✅ Exists' : '❌ Missing') . '</p>';
                
                if ($exists && strpos($table, 'student') !== false) {
                    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                    echo '<p style="margin-left: 20px;">Records: ' . $count . '</p>';
                }
            }
            ?>
        </div>
        
        <div class="card">
            <h2>🔍 Search for Missing David's Data</h2>
            <p>Looking for any references to David or test groups with 20 students:</p>
            <?php
            // Search for David in users
            $david_users = $wpdb->get_results("
                SELECT ID, user_login, display_name, user_email
                FROM {$wpdb->users}
                WHERE display_name LIKE '%david%' OR user_login LIKE '%david%'
                ORDER BY display_name
            ");
            
            echo '<h4>Users with "David" in name:</h4>';
            if (!empty($david_users)) {
                echo '<table>';
                echo '<tr><th>ID</th><th>Username</th><th>Display Name</th><th>Email</th></tr>';
                foreach ($david_users as $user) {
                    echo '<tr>';
                    echo '<td>' . $user->ID . '</td>';
                    echo '<td>' . $user->user_login . '</td>';
                    echo '<td>' . $user->display_name . '</td>';
                    echo '<td>' . $user->user_email . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p>No users found with "David" in name</p>';
            }
            
            // Search for classes with many students
            $large_classes = $wpdb->get_results("
                SELECT 
                    c.id,
                    c.name,
                    c.group_id,
                    COUNT(sc.student_id) as student_count
                FROM {$wpdb->prefix}school_classes c
                LEFT JOIN {$wpdb->prefix}school_student_classes sc ON c.id = sc.class_id
                GROUP BY c.id
                HAVING student_count >= 10
                ORDER BY student_count DESC
            ");
            
            echo '<h4>Classes with 10+ students:</h4>';
            if (!empty($large_classes)) {
                echo '<table>';
                echo '<tr><th>Class ID</th><th>Class Name</th><th>Group ID</th><th>Students</th></tr>';
                foreach ($large_classes as $class) {
                    echo '<tr>';
                    echo '<td>' . $class->id . '</td>';
                    echo '<td>' . $class->name . '</td>';
                    echo '<td>' . $class->group_id . '</td>';
                    echo '<td>' . $class->student_count . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p>No classes found with 10+ students</p>';
            }
            ?>
        </div>
        
        <div class="card">
            <h2>🔧 Possible Issues & Solutions</h2>
            <div class="warning">
                <h4>Potential Problems:</h4>
                <ul>
                    <li><strong>Data Migration Issue:</strong> Old data might be in different tables</li>
                    <li><strong>Table Prefix Mismatch:</strong> Some data might use different prefixes</li>
                    <li><strong>Deleted Records:</strong> Data might have been accidentally deleted</li>
                    <li><strong>Multiple Databases:</strong> Data might be split across different databases</li>
                </ul>
            </div>
            
            <button onclick="searchAllTables()">Search All Tables for Student Data</button>
            <button onclick="checkBackups()">Check for Backup Data</button>
            <div id="search-results"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function testCurrentMethod() {
        $('#current-result').html('<div class="info"><p>Testing current AJAX method...</p></div>');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'get_group_students',
                group_id: <?php echo $group_id; ?>,
                nonce: '<?php echo wp_create_nonce('teacher_dashboard_nonce'); ?>'
            },
            success: function(response) {
                let html = '<div class="' + (response.success ? 'success' : 'error') + '">';
                html += '<h4>AJAX Response:</h4>';
                html += '<pre>' + JSON.stringify(response, null, 2) + '</pre>';
                html += '</div>';
                $('#current-result').html(html);
            },
            error: function(xhr, status, error) {
                $('#current-result').html('<div class="error"><p>AJAX Error: ' + status + ' - ' + error + '</p></div>');
            }
        });
    }
    
    function searchAllTables() {
        $('#search-results').html('<div class="info"><p>This would search all database tables for student-related data...</p></div>');
        alert('This feature would need to be implemented to search all tables for student data patterns.');
    }
    
    function checkBackups() {
        $('#search-results').html('<div class="info"><p>This would check for backup data sources...</p></div>');
        alert('This would check for backup databases or exported data files.');
    }
    </script>
</body>
</html>
