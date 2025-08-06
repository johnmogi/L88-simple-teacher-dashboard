<?php
/**
 * Check User Roles and Permissions
 * 
 * Quick check of current user's roles and teacher status
 * Access: https://207lilac.local/wp-content/plugins/simple-teacher-dashboard/check-user-roles.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!is_user_logged_in()) {
    wp_die('Please log in first.');
}

$current_user = wp_get_current_user();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Check User Roles</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        button { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; background: #007cba; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>👤 Check User Roles</h1>
        
        <div class="card info">
            <h2>Current User Information</h2>
            <table>
                <tr><th>Property</th><th>Value</th></tr>
                <tr><td>User ID</td><td><?php echo $current_user->ID; ?></td></tr>
                <tr><td>Username</td><td><?php echo $current_user->user_login; ?></td></tr>
                <tr><td>Display Name</td><td><?php echo $current_user->display_name; ?></td></tr>
                <tr><td>Email</td><td><?php echo $current_user->user_email; ?></td></tr>
                <tr><td>Roles</td><td><?php echo implode(', ', $current_user->roles); ?></td></tr>
            </table>
        </div>
        
        <div class="card">
            <h2>🔍 Role Analysis</h2>
            <?php
            $teacher_roles = array(
                'school_teacher', 
                'instructor', 
                'Instructor', 
                'wdm_instructor',
                'stm_lms_instructor',
                'group_leader'
            );
            
            echo '<table>';
            echo '<tr><th>Role</th><th>Has Role</th></tr>';
            
            foreach ($teacher_roles as $role) {
                $has_role = in_array($role, $current_user->roles);
                echo '<tr>';
                echo '<td>' . $role . '</td>';
                echo '<td class="' . ($has_role ? 'success' : 'error') . '">';
                echo ($has_role ? '✅ Yes' : '❌ No');
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            ?>
        </div>
        
        <div class="card">
            <h2>🎯 Teacher Status Check</h2>
            <?php
            if (class_exists('Simple_Teacher_Dashboard')) {
                $dashboard = new Simple_Teacher_Dashboard();
                $reflection = new ReflectionClass($dashboard);
                $is_teacher_method = $reflection->getMethod('is_teacher');
                $is_teacher_method->setAccessible(true);
                $is_teacher = $is_teacher_method->invoke($dashboard, $current_user);
                
                echo '<p class="' . ($is_teacher ? 'success' : 'error') . '">';
                echo '<strong>is_teacher() result:</strong> ' . ($is_teacher ? '✅ True' : '❌ False');
                echo '</p>';
                
                // Check group leader meta
                global $wpdb;
                $has_group_leader_meta = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) 
                    FROM {$wpdb->usermeta}
                    WHERE user_id = %d
                    AND meta_key LIKE '%%group_leader%%'",
                    $current_user->ID
                ));
                
                echo '<p><strong>Group leader meta count:</strong> ' . $has_group_leader_meta . '</p>';
                
            } else {
                echo '<p class="error">❌ Simple_Teacher_Dashboard class not found</p>';
            }
            ?>
        </div>
        
        <div class="card">
            <h2>🔑 Capabilities Check</h2>
            <?php
            $capabilities = array(
                'manage_options',
                'edit_posts',
                'read',
                'edit_courses',
                'edit_lessons',
                'edit_quizzes',
                'group_leader'
            );
            
            echo '<table>';
            echo '<tr><th>Capability</th><th>Has Capability</th></tr>';
            
            foreach ($capabilities as $cap) {
                $has_cap = current_user_can($cap);
                echo '<tr>';
                echo '<td>' . $cap . '</td>';
                echo '<td class="' . ($has_cap ? 'success' : 'error') . '">';
                echo ($has_cap ? '✅ Yes' : '❌ No');
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            ?>
        </div>
        
        <div class="card">
            <h2>🔧 Quick Fixes</h2>
            <p>If you don't have teacher permissions, try these:</p>
            <button onclick="addTeacherRole()">Add wdm_instructor Role</button>
            <button onclick="addGroupLeaderRole()">Add group_leader Role</button>
            <button onclick="testDashboard()">Test Dashboard Now</button>
            
            <div id="fix-results"></div>
        </div>
        
        <div class="card">
            <h2>📋 User Meta</h2>
            <pre><?php
                $user_meta = get_user_meta($current_user->ID);
                foreach ($user_meta as $key => $value) {
                    if (strpos($key, 'group') !== false || strpos($key, 'teacher') !== false || strpos($key, 'instructor') !== false) {
                        echo $key . ': ' . print_r($value, true) . "\n";
                    }
                }
            ?></pre>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function addTeacherRole() {
        $('#fix-results').html('<div class="info"><p>Adding wdm_instructor role...</p></div>');
        // This would need to be implemented via AJAX
        alert('This would add the wdm_instructor role to your user. Implement via AJAX if needed.');
    }
    
    function addGroupLeaderRole() {
        $('#fix-results').html('<div class="info"><p>Adding group_leader role...</p></div>');
        alert('This would add the group_leader role to your user. Implement via AJAX if needed.');
    }
    
    function testDashboard() {
        window.location.href = 'test-click-debug.html';
    }
    </script>
</body>
</html>
