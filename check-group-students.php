<?php
/**
 * Check Group Students
 * 
 * Diagnostic to see what students are actually in the LearnDash groups
 * Access: https://207lilac.local/wp-content/plugins/simple-teacher-dashboard/check-group-students.php
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
    <title>Check Group Students</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        button { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; background: #007cba; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>👥 Check Group Students</h1>
        
        <?php foreach ($groups as $group_id): ?>
        <div class="card">
            <h2>Group <?php echo $group_id; ?></h2>
            
            <?php
            // Check if group exists
            $group_post = get_post($group_id);
            if (!$group_post || $group_post->post_type !== 'groups') {
                echo '<div class="error"><p>❌ Group does not exist or is not a LearnDash group</p></div>';
                continue;
            }
            
            echo '<div class="info"><p>✅ Group exists: ' . $group_post->post_title . '</p></div>';
            
            // Method 1: LearnDash function
            echo '<h3>Method 1: learndash_get_groups_users()</h3>';
            if (function_exists('learndash_get_groups_users')) {
                $users_method1 = learndash_get_groups_users($group_id);
                echo '<p><strong>Users found:</strong> ' . count($users_method1) . '</p>';
                if (!empty($users_method1)) {
                    echo '<table>';
                    echo '<tr><th>User ID</th><th>Name</th><th>Email</th><th>Roles</th></tr>';
                    foreach ($users_method1 as $user_id) {
                        $user = get_user_by('id', $user_id);
                        if ($user) {
                            echo '<tr>';
                            echo '<td>' . $user->ID . '</td>';
                            echo '<td>' . $user->display_name . '</td>';
                            echo '<td>' . $user->user_email . '</td>';
                            echo '<td>' . implode(', ', $user->roles) . '</td>';
                            echo '</tr>';
                        }
                    }
                    echo '</table>';
                } else {
                    echo '<p class="warning">No users found via learndash_get_groups_users()</p>';
                }
            } else {
                echo '<p class="error">learndash_get_groups_users() function not available</p>';
            }
            
            // Method 2: Post meta
            echo '<h3>Method 2: Post Meta (learndash_group_users)</h3>';
            $users_method2 = get_post_meta($group_id, 'learndash_group_users', true);
            if (is_array($users_method2)) {
                echo '<p><strong>Users found:</strong> ' . count($users_method2) . '</p>';
                if (!empty($users_method2)) {
                    echo '<table>';
                    echo '<tr><th>User ID</th><th>Name</th><th>Email</th></tr>';
                    foreach ($users_method2 as $user_id) {
                        $user = get_user_by('id', $user_id);
                        if ($user) {
                            echo '<tr>';
                            echo '<td>' . $user->ID . '</td>';
                            echo '<td>' . $user->display_name . '</td>';
                            echo '<td>' . $user->user_email . '</td>';
                            echo '</tr>';
                        }
                    }
                    echo '</table>';
                }
            } else {
                echo '<p class="warning">No users found in post meta or meta is not an array</p>';
                echo '<pre>Meta value: ' . print_r($users_method2, true) . '</pre>';
            }
            
            // Method 3: All group meta
            echo '<h3>Method 3: All Group Meta</h3>';
            $all_meta = get_post_meta($group_id);
            echo '<pre>';
            foreach ($all_meta as $key => $value) {
                if (strpos($key, 'group') !== false || strpos($key, 'user') !== false || strpos($key, 'student') !== false) {
                    echo $key . ': ' . print_r($value, true) . "\n";
                }
            }
            echo '</pre>';
            
            // Method 4: Check School Manager classes table
            echo '<h3>Method 4: School Manager Classes Table</h3>';
            global $wpdb;
            $classes_table = $wpdb->prefix . 'classes';
            if ($wpdb->get_var("SHOW TABLES LIKE '$classes_table'") == $classes_table) {
                $class_info = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $classes_table WHERE group_id = %d",
                    $group_id
                ));
                
                if ($class_info) {
                    echo '<p><strong>Class found:</strong> ' . $class_info->class_name . '</p>';
                    echo '<p><strong>Teacher ID:</strong> ' . $class_info->teacher_id . '</p>';
                    
                    // Check student-classes table
                    $student_classes_table = $wpdb->prefix . 'student_classes';
                    if ($wpdb->get_var("SHOW TABLES LIKE '$student_classes_table'") == $student_classes_table) {
                        $students = $wpdb->get_results($wpdb->prepare(
                            "SELECT sc.*, s.student_name, s.student_email 
                            FROM $student_classes_table sc
                            LEFT JOIN {$wpdb->prefix}students s ON sc.student_id = s.student_id
                            WHERE sc.class_id = %d",
                            $class_info->class_id
                        ));
                        
                        echo '<p><strong>Students in class:</strong> ' . count($students) . '</p>';
                        if (!empty($students)) {
                            echo '<table>';
                            echo '<tr><th>Student ID</th><th>Name</th><th>Email</th><th>Enrollment Date</th></tr>';
                            foreach ($students as $student) {
                                echo '<tr>';
                                echo '<td>' . $student->student_id . '</td>';
                                echo '<td>' . $student->student_name . '</td>';
                                echo '<td>' . $student->student_email . '</td>';
                                echo '<td>' . $student->enrollment_date . '</td>';
                                echo '</tr>';
                            }
                            echo '</table>';
                        }
                    } else {
                        echo '<p class="warning">student_classes table does not exist</p>';
                    }
                } else {
                    echo '<p class="warning">No class found for this group ID</p>';
                }
            } else {
                echo '<p class="warning">classes table does not exist</p>';
            }
            ?>
        </div>
        <?php endforeach; ?>
        
        <div class="card">
            <h2>🔧 Quick Actions</h2>
            <button onclick="addTestStudents()">Add Test Students to Groups</button>
            <button onclick="syncStudents()">Sync School Manager Students to LearnDash</button>
            <button onclick="checkSetup()">Run Complete Setup</button>
            
            <div id="action-results"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function addTestStudents() {
        $('#action-results').html('<div class="info"><p>This would add test students to the groups...</p></div>');
        alert('This feature needs to be implemented. Would you like me to create it?');
    }
    
    function syncStudents() {
        window.location.href = '../school-manager-lite/complete-setup.php';
    }
    
    function checkSetup() {
        window.location.href = '../school-manager-lite/complete-setup.php';
    }
    </script>
</body>
</html>
