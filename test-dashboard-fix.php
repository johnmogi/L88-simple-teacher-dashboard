<?php
/**
 * Test Dashboard Fix
 * 
 * Quick test to verify the dashboard is working after fixes
 * Access: https://207lilac.local/wp-content/plugins/simple-teacher-dashboard/test-dashboard-fix.php
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
    <title>Test Dashboard Fix</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .dashboard-test { border: 2px solid #007cba; padding: 20px; margin: 20px 0; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>🧪 Test Dashboard Fix</h1>
        
        <div class="card info">
            <h3>Current User: <?php echo esc_html($current_user->display_name); ?> (ID: <?php echo $current_user->ID; ?>)</h3>
            <p><strong>Roles:</strong> <?php echo implode(', ', $current_user->roles); ?></p>
        </div>
        
        <?php
        // Test if Simple Teacher Dashboard class exists
        if (class_exists('Simple_Teacher_Dashboard')) {
            echo '<div class="card success"><h3>✅ Simple Teacher Dashboard Class Found</h3></div>';
            
            // Create instance and test methods
            $dashboard = new Simple_Teacher_Dashboard();
            
            // Test is_teacher method
            $reflection = new ReflectionClass($dashboard);
            $is_teacher_method = $reflection->getMethod('is_teacher');
            $is_teacher_method->setAccessible(true);
            $is_teacher = $is_teacher_method->invoke($dashboard, $current_user);
            
            echo '<div class="card ' . ($is_teacher ? 'success' : 'error') . '">';
            echo '<h3>' . ($is_teacher ? '✅' : '❌') . ' Teacher Role Check</h3>';
            echo '<p>User ' . ($is_teacher ? 'has' : 'does not have') . ' teacher permissions</p>';
            echo '</div>';
            
            // Test get_teacher_groups method
            $get_groups_method = $reflection->getMethod('get_teacher_groups');
            $get_groups_method->setAccessible(true);
            $groups = $get_groups_method->invoke($dashboard, $current_user->ID);
            
            echo '<div class="card ' . (count($groups) > 0 ? 'success' : 'error') . '">';
            echo '<h3>' . (count($groups) > 0 ? '✅' : '❌') . ' Groups Found: ' . count($groups) . '</h3>';
            
            if (!empty($groups)) {
                echo '<table>';
                echo '<tr><th>Group ID</th><th>Group Name</th><th>Status</th><th>Students</th></tr>';
                
                foreach ($groups as $group) {
                    // Test get_group_students method
                    $get_students_method = $reflection->getMethod('get_group_students');
                    $get_students_method->setAccessible(true);
                    $students = $get_students_method->invoke($dashboard, $group->group_id);
                    
                    echo '<tr>';
                    echo '<td>' . $group->group_id . '</td>';
                    echo '<td>' . esc_html($group->group_name) . '</td>';
                    echo '<td>' . $group->post_status . '</td>';
                    echo '<td>' . count($students) . '</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
            }
            echo '</div>';
            
        } else {
            echo '<div class="card error"><h3>❌ Simple Teacher Dashboard Class Not Found</h3></div>';
        }
        ?>
        
        <div class="card">
            <h2>🎯 Live Dashboard Test</h2>
            <p>This will render the actual dashboard shortcode:</p>
            <div class="dashboard-test">
                <?php
                if (class_exists('Simple_Teacher_Dashboard')) {
                    echo do_shortcode('[simple_teacher_dashboard]');
                } else {
                    echo '<p>Dashboard class not available</p>';
                }
                ?>
            </div>
        </div>
        
        <div class="card">
            <h2>📊 System Status</h2>
            <table>
                <tr><th>Component</th><th>Status</th></tr>
                <tr><td>WordPress</td><td>✅ Loaded</td></tr>
                <tr><td>User Logged In</td><td>✅ Yes</td></tr>
                <tr><td>Simple Teacher Dashboard</td><td><?php echo class_exists('Simple_Teacher_Dashboard') ? '✅ Active' : '❌ Missing'; ?></td></tr>
                <tr><td>LearnDash</td><td><?php echo function_exists('learndash_get_groups_users') ? '✅ Active' : '❌ Missing'; ?></td></tr>
                <tr><td>School Manager Lite</td><td><?php 
                    global $wpdb;
                    $classes_table = $wpdb->prefix . 'school_classes';
                    echo ($wpdb->get_var("SHOW TABLES LIKE '$classes_table'") == $classes_table) ? '✅ Active' : '❌ Missing';
                ?></td></tr>
            </table>
        </div>
    </div>
</body>
</html>
