<?php
/**
 * Diagnose Student-Group Connections
 * 
 * Check why 41 students aren't showing up in LearnDash groups
 * Access: https://207lilac.local/wp-content/plugins/simple-teacher-dashboard/diagnose-student-connections.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!is_user_logged_in()) {
    wp_die('Please log in first.');
}

$groups = array(10025, 10027, 10028, 10029, 10030);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Diagnose Student Connections</title>
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
        .fix-btn { background: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnose Student-Group Connections</h1>
        
        <div class="card info">
            <h2>📊 System Overview</h2>
            <?php
            global $wpdb;
            
            // Get total students (non-instructors)
            $total_students = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->users} u
                WHERE u.ID NOT IN (
                    SELECT user_id 
                    FROM {$wpdb->usermeta}
                    WHERE meta_key = 'wp_capabilities' 
                    AND meta_value LIKE '%stm_lms_instructor%'
                )
            ");
            
            // Get total instructors
            $total_instructors = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->usermeta}
                WHERE meta_key = 'wp_capabilities' 
                AND meta_value LIKE '%stm_lms_instructor%'
            ");
            
            echo '<p><strong>Total Students:</strong> ' . $total_students . '</p>';
            echo '<p><strong>Total Instructors:</strong> ' . $total_instructors . '</p>';
            ?>
        </div>
        
        <div class="card">
            <h2>👥 All Students in System</h2>
            <?php
            // Get all students with their roles
            $students = $wpdb->get_results("
                SELECT 
                    u.ID,
                    u.user_login,
                    u.display_name,
                    u.user_email,
                    um.meta_value as capabilities
                FROM {$wpdb->users} u
                LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'wp_capabilities'
                WHERE u.ID NOT IN (
                    SELECT user_id 
                    FROM {$wpdb->usermeta}
                    WHERE meta_key = 'wp_capabilities' 
                    AND meta_value LIKE '%stm_lms_instructor%'
                )
                ORDER BY u.display_name
                LIMIT 20
            ");
            
            echo '<p>Showing first 20 students:</p>';
            echo '<table>';
            echo '<tr><th>ID</th><th>Username</th><th>Display Name</th><th>Email</th><th>Capabilities</th></tr>';
            
            foreach ($students as $student) {
                echo '<tr>';
                echo '<td>' . $student->ID . '</td>';
                echo '<td>' . $student->user_login . '</td>';
                echo '<td>' . $student->display_name . '</td>';
                echo '<td>' . $student->user_email . '</td>';
                echo '<td>' . substr($student->capabilities, 0, 50) . '...</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            ?>
        </div>
        
        <div class="card">
            <h2>🔗 LearnDash Group Connections</h2>
            <?php
            foreach ($groups as $group_id) {
                $group_post = get_post($group_id);
                if (!$group_post) continue;
                
                echo '<h3>Group ' . $group_id . ': ' . $group_post->post_title . '</h3>';
                
                // Method 1: learndash_get_groups_users
                if (function_exists('learndash_get_groups_users')) {
                    $ld_users = learndash_get_groups_users($group_id);
                    echo '<p><strong>LearnDash function result:</strong> ' . count($ld_users) . ' users</p>';
                    if (!empty($ld_users)) {
                        echo '<p>User IDs: ' . implode(', ', $ld_users) . '</p>';
                    }
                } else {
                    echo '<p class="error">learndash_get_groups_users() not available</p>';
                }
                
                // Method 2: Direct meta query
                $meta_users = get_post_meta($group_id, 'learndash_group_users', true);
                echo '<p><strong>Post meta result:</strong> ';
                if (is_array($meta_users)) {
                    echo count($meta_users) . ' users';
                    if (!empty($meta_users)) {
                        echo ' (IDs: ' . implode(', ', $meta_users) . ')';
                    }
                } else {
                    echo 'Not an array: ' . gettype($meta_users);
                    if ($meta_users) {
                        echo ' - Value: ' . print_r($meta_users, true);
                    }
                }
                echo '</p>';
                
                // Method 3: All meta for this group
                $all_meta = get_post_meta($group_id);
                echo '<details><summary>All Group Meta (' . count($all_meta) . ' entries)</summary>';
                echo '<pre>';
                foreach ($all_meta as $key => $values) {
                    if (strpos($key, 'user') !== false || strpos($key, 'group') !== false || strpos($key, 'member') !== false) {
                        echo $key . ': ' . print_r($values, true) . "\n";
                    }
                }
                echo '</pre></details>';
                
                echo '<hr>';
            }
            ?>
        </div>
        
        <div class="card">
            <h2>🔍 School Manager Connections</h2>
            <?php
            // Check School Manager tables
            $classes_table = $wpdb->prefix . 'classes';
            $students_table = $wpdb->prefix . 'students';
            $student_classes_table = $wpdb->prefix . 'student_classes';
            
            echo '<h3>School Manager Tables Status</h3>';
            $tables_exist = array();
            foreach (array($classes_table, $students_table, $student_classes_table) as $table) {
                $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
                $tables_exist[basename($table)] = $exists;
                echo '<p><strong>' . basename($table) . ':</strong> ' . ($exists ? '✅ Exists' : '❌ Missing') . '</p>';
            }
            
            if ($tables_exist['classes']) {
                echo '<h3>Classes with Group IDs</h3>';
                $classes = $wpdb->get_results("
                    SELECT class_id, class_name, teacher_id, group_id 
                    FROM $classes_table 
                    WHERE group_id IN (" . implode(',', $groups) . ")
                    ORDER BY class_name
                ");
                
                if (!empty($classes)) {
                    echo '<table>';
                    echo '<tr><th>Class ID</th><th>Class Name</th><th>Teacher ID</th><th>Group ID</th><th>Students</th></tr>';
                    
                    foreach ($classes as $class) {
                        $student_count = 0;
                        if ($tables_exist['student_classes']) {
                            $student_count = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM $student_classes_table WHERE class_id = %d",
                                $class->class_id
                            ));
                        }
                        
                        echo '<tr>';
                        echo '<td>' . $class->class_id . '</td>';
                        echo '<td>' . $class->class_name . '</td>';
                        echo '<td>' . $class->teacher_id . '</td>';
                        echo '<td>' . $class->group_id . '</td>';
                        echo '<td>' . $student_count . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</table>';
                } else {
                    echo '<p class="warning">No classes found for these group IDs</p>';
                }
            }
            
            if ($tables_exist['students'] && $tables_exist['student_classes']) {
                echo '<h3>Student-Class Connections</h3>';
                $connections = $wpdb->get_results("
                    SELECT 
                        sc.class_id,
                        c.class_name,
                        c.group_id,
                        COUNT(sc.student_id) as student_count
                    FROM $student_classes_table sc
                    LEFT JOIN $classes_table c ON sc.class_id = c.class_id
                    WHERE c.group_id IN (" . implode(',', $groups) . ")
                    GROUP BY sc.class_id
                ");
                
                if (!empty($connections)) {
                    echo '<table>';
                    echo '<tr><th>Class ID</th><th>Class Name</th><th>Group ID</th><th>Students</th></tr>';
                    foreach ($connections as $conn) {
                        echo '<tr>';
                        echo '<td>' . $conn->class_id . '</td>';
                        echo '<td>' . $conn->class_name . '</td>';
                        echo '<td>' . $conn->group_id . '</td>';
                        echo '<td>' . $conn->student_count . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<p class="warning">No student-class connections found</p>';
                }
            }
            ?>
        </div>
        
        <div class="card">
            <h2>🔧 Potential Fixes</h2>
            <p>Based on the diagnosis above, here are potential solutions:</p>
            
            <button class="fix-btn" onclick="syncSchoolManagerToLearnDash()">
                Sync School Manager Students to LearnDash Groups
            </button>
            
            <button class="fix-btn" onclick="directAssignStudents()">
                Directly Assign Students to Groups
            </button>
            
            <button onclick="runCompleteSetup()">
                Run Complete Setup System
            </button>
            
            <div id="fix-results"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function syncSchoolManagerToLearnDash() {
        $('#fix-results').html('<div class="info"><p>This would sync School Manager student data to LearnDash groups...</p></div>');
        // This would need to be implemented
        alert('This feature needs implementation. Should I create it?');
    }
    
    function directAssignStudents() {
        window.location.href = 'add-students-to-groups.php';
    }
    
    function runCompleteSetup() {
        window.location.href = '../school-manager-lite/complete-setup.php';
    }
    </script>
</body>
</html>
