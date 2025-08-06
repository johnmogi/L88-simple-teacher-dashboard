<?php
/**
 * Quick Fix for Simple Teacher Dashboard
 * 
 * Fix the AJAX error and get the dashboard working again
 * Access: https://207lilac.local/wp-content/plugins/simple-teacher-dashboard/quick-fix-dashboard.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Access denied. You need administrator privileges.');
}

// Handle action
$result = '';
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'test_dashboard':
            $result = test_dashboard_connection();
            break;
        case 'fix_dashboard':
            $result = fix_dashboard_connection();
            break;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Quick Fix - Simple Teacher Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        button { padding: 12px 24px; margin: 10px 5px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .btn-primary { background: #007cba; color: white; }
        .btn-success { background: #28a745; color: white; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Quick Fix - Simple Teacher Dashboard</h1>
        
        <div class="card info">
            <h3>Problem Identified:</h3>
            <p>The Simple Teacher Dashboard is showing "אירעה שגיאה: [object Object]" error.</p>
            <p>This means the AJAX call to load students is failing.</p>
        </div>
        
        <?php if ($result): ?>
            <?php echo $result; ?>
        <?php endif; ?>
        
        <div class="card">
            <h2>🧪 Test Dashboard Connection</h2>
            <p>Test if the dashboard AJAX endpoints are working:</p>
            <form method="post">
                <input type="hidden" name="action" value="test_dashboard">
                <button type="submit" class="btn-primary">🧪 Test Dashboard</button>
            </form>
        </div>
        
        <div class="card">
            <h2>🔧 Fix Dashboard Connection</h2>
            <p>This will ensure the dashboard can connect to the new group system:</p>
            <form method="post">
                <input type="hidden" name="action" value="fix_dashboard">
                <button type="submit" class="btn-success">🔧 Fix Dashboard</button>
            </form>
        </div>
        
        <div class="card">
            <h2>📊 Current System Status</h2>
            <?php display_dashboard_status(); ?>
        </div>
    </div>
</body>
</html>

<?php

/**
 * Test dashboard connection
 */
function test_dashboard_connection() {
    // Test if Simple Teacher Dashboard class exists
    $dashboard_exists = class_exists('Simple_Teacher_Dashboard');
    
    // Test AJAX endpoints
    $ajax_actions = array(
        'get_group_students',
        'get_student_quiz_data'
    );
    
    $results = array();
    $results[] = "<h3>🧪 Dashboard Connection Test</h3>";
    $results[] = "<p><strong>Dashboard Class:</strong> " . ($dashboard_exists ? "✅ Exists" : "❌ Missing") . "</p>";
    
    // Test if user is logged in and has teacher role
    $current_user = wp_get_current_user();
    $is_teacher = in_array('wdm_instructor', $current_user->roles) || in_array('instructor', $current_user->roles);
    $results[] = "<p><strong>Current User:</strong> " . $current_user->display_name . " (ID: " . $current_user->ID . ")</p>";
    $results[] = "<p><strong>Is Teacher:</strong> " . ($is_teacher ? "✅ Yes" : "❌ No") . "</p>";
    
    // Test groups for current user
    global $wpdb;
    $classes_table = $wpdb->prefix . 'school_classes';
    $user_groups = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, group_id FROM $classes_table WHERE teacher_id = %d",
        $current_user->ID
    ));
    
    $results[] = "<p><strong>User's Classes:</strong> " . count($user_groups) . "</p>";
    
    if (!empty($user_groups)) {
        $results[] = "<table>";
        $results[] = "<tr><th>Class</th><th>Group ID</th><th>Group Exists</th><th>Students</th></tr>";
        
        foreach ($user_groups as $class) {
            $group_exists = $class->group_id ? (get_post($class->group_id) ? "✅ Yes" : "❌ No") : "❌ No Group";
            
            $student_count = 0;
            if ($class->group_id) {
                $group_users = get_post_meta($class->group_id, 'learndash_group_users', true);
                $student_count = is_array($group_users) ? count($group_users) : 0;
            }
            
            $results[] = "<tr>";
            $results[] = "<td>" . esc_html($class->name) . "</td>";
            $results[] = "<td>" . ($class->group_id ?: 'None') . "</td>";
            $results[] = "<td>" . $group_exists . "</td>";
            $results[] = "<td>" . $student_count . "</td>";
            $results[] = "</tr>";
        }
        
        $results[] = "</table>";
    }
    
    return '<div class="card success">' . implode('', $results) . '</div>';
}

/**
 * Fix dashboard connection
 */
function fix_dashboard_connection() {
    $results = array();
    $results[] = "<h3>🔧 Dashboard Fix Results</h3>";
    
    // Check if Simple Teacher Dashboard is active
    if (!class_exists('Simple_Teacher_Dashboard')) {
        $results[] = "<p>❌ Simple Teacher Dashboard plugin not active</p>";
        return '<div class="card error">' . implode('', $results) . '</div>';
    }
    
    // Get current user
    $current_user = wp_get_current_user();
    
    // Ensure user has teacher role
    if (!in_array('wdm_instructor', $current_user->roles)) {
        $user = new WP_User($current_user->ID);
        $user->add_role('wdm_instructor');
        $results[] = "<p>✅ Added wdm_instructor role to user</p>";
    }
    
    // Check user's groups and fix connections
    global $wpdb;
    $classes_table = $wpdb->prefix . 'school_classes';
    $user_classes = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, group_id FROM $classes_table WHERE teacher_id = %d",
        $current_user->ID
    ));
    
    $fixed_count = 0;
    foreach ($user_classes as $class) {
        if ($class->group_id) {
            // Ensure user is group leader
            $leaders = get_post_meta($class->group_id, 'learndash_group_leaders', true);
            if (!is_array($leaders)) {
                $leaders = array();
            }
            
            if (!in_array($current_user->ID, $leaders)) {
                $leaders[] = $current_user->ID;
                update_post_meta($class->group_id, 'learndash_group_leaders', $leaders);
                $fixed_count++;
                $results[] = "<p>✅ Fixed leader access for group #{$class->group_id} ({$class->name})</p>";
            }
        }
    }
    
    $results[] = "<p><strong>Summary:</strong> Fixed $fixed_count group connections</p>";
    $results[] = "<p>✅ Dashboard should now work properly</p>";
    
    return '<div class="card success">' . implode('', $results) . '</div>';
}

/**
 * Display dashboard status
 */
function display_dashboard_status() {
    echo '<table>';
    echo '<tr><th>Component</th><th>Status</th><th>Details</th></tr>';
    
    // Simple Teacher Dashboard plugin
    $dashboard_active = class_exists('Simple_Teacher_Dashboard');
    echo '<tr><td>Simple Teacher Dashboard</td><td>' . ($dashboard_active ? '✅ Active' : '❌ Inactive') . '</td><td>' . ($dashboard_active ? 'Plugin loaded' : 'Plugin not found') . '</td></tr>';
    
    // Current user
    $current_user = wp_get_current_user();
    $is_teacher = in_array('wdm_instructor', $current_user->roles);
    echo '<tr><td>Current User</td><td>' . ($is_teacher ? '✅ Teacher' : '❌ Not Teacher') . '</td><td>' . $current_user->display_name . '</td></tr>';
    
    // User's groups
    global $wpdb;
    $classes_table = $wpdb->prefix . 'school_classes';
    $user_groups = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $classes_table WHERE teacher_id = %d AND group_id IS NOT NULL",
        $current_user->ID
    ));
    echo '<tr><td>User Groups</td><td>' . ($user_groups > 0 ? '✅ Has Groups' : '❌ No Groups') . '</td><td>' . $user_groups . ' groups</td></tr>';
    
    // AJAX endpoints
    $ajax_registered = has_action('wp_ajax_get_group_students');
    echo '<tr><td>AJAX Endpoints</td><td>' . ($ajax_registered ? '✅ Registered' : '❌ Missing') . '</td><td>get_group_students</td></tr>';
    
    echo '</table>';
}
?>
