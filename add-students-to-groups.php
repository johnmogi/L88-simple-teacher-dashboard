<?php
/**
 * Add Students to Groups
 * 
 * Quick script to add students to the existing LearnDash groups
 * Access: https://207lilac.local/wp-content/plugins/simple-teacher-dashboard/add-students-to-groups.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Access denied. Please log in as administrator.');
}

$groups = array(10025, 10027, 10028, 10029, 10030);
$action = isset($_POST['action']) ? $_POST['action'] : '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Students to Groups</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        button { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; background: #007cba; color: white; }
        button.danger { background: #dc3545; }
        button.success { background: #28a745; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>👥 Add Students to Groups</h1>
        
        <?php if ($action === 'create_demo_students'): ?>
        <div class="card">
            <h2>Creating Demo Students...</h2>
            <?php
            $demo_students = array();
            for ($i = 1; $i <= 15; $i++) {
                $username = 'demo_student_' . $i;
                $email = 'student' . $i . '@demo.local';
                $password = 'demo123';
                
                // Check if user already exists
                if (!username_exists($username) && !email_exists($email)) {
                    $user_id = wp_create_user($username, $password, $email);
                    if (!is_wp_error($user_id)) {
                        // Set display name
                        wp_update_user(array(
                            'ID' => $user_id,
                            'display_name' => 'Demo Student ' . $i,
                            'first_name' => 'Demo',
                            'last_name' => 'Student ' . $i
                        ));
                        
                        // Add student role
                        $user = new WP_User($user_id);
                        $user->set_role('subscriber'); // Default student role
                        
                        $demo_students[] = $user_id;
                        echo '<p class="success">✅ Created student: ' . $username . ' (ID: ' . $user_id . ')</p>';
                    } else {
                        echo '<p class="error">❌ Failed to create ' . $username . ': ' . $user_id->get_error_message() . '</p>';
                    }
                } else {
                    $existing_user = get_user_by('login', $username);
                    if ($existing_user) {
                        $demo_students[] = $existing_user->ID;
                        echo '<p class="info">ℹ️ Student already exists: ' . $username . ' (ID: ' . $existing_user->ID . ')</p>';
                    }
                }
            }
            
            // Store created students in session for next step
            set_transient('demo_students_created', $demo_students, 300); // 5 minutes
            ?>
            <p><strong>Created/Found <?php echo count($demo_students); ?> demo students.</strong></p>
        </div>
        <?php endif; ?>
        
        <?php if ($action === 'assign_to_groups'): ?>
        <div class="card">
            <h2>Assigning Students to Groups...</h2>
            <?php
            $demo_students = get_transient('demo_students_created');
            if (!$demo_students) {
                // Fallback: get all demo students
                $demo_students = array();
                for ($i = 1; $i <= 15; $i++) {
                    $user = get_user_by('login', 'demo_student_' . $i);
                    if ($user) {
                        $demo_students[] = $user->ID;
                    }
                }
            }
            
            if (empty($demo_students)) {
                echo '<p class="error">❌ No demo students found. Please create them first.</p>';
            } else {
                $students_per_group = ceil(count($demo_students) / count($groups));
                $student_index = 0;
                
                foreach ($groups as $group_id) {
                    $group_post = get_post($group_id);
                    if (!$group_post) {
                        echo '<p class="error">❌ Group ' . $group_id . ' does not exist</p>';
                        continue;
                    }
                    
                    echo '<h3>Group ' . $group_id . ': ' . $group_post->post_title . '</h3>';
                    
                    // Get students for this group
                    $group_students = array_slice($demo_students, $student_index, $students_per_group);
                    $student_index += $students_per_group;
                    
                    foreach ($group_students as $student_id) {
                        $student = get_user_by('id', $student_id);
                        if ($student) {
                            // Method 1: Use LearnDash function if available
                            if (function_exists('ld_update_group_access')) {
                                $result = ld_update_group_access($student_id, $group_id, false);
                                echo '<p class="success">✅ Added ' . $student->display_name . ' to group (LearnDash method)</p>';
                            } else {
                                // Method 2: Direct meta update
                                $current_users = get_post_meta($group_id, 'learndash_group_users', true);
                                if (!is_array($current_users)) {
                                    $current_users = array();
                                }
                                
                                if (!in_array($student_id, $current_users)) {
                                    $current_users[] = $student_id;
                                    update_post_meta($group_id, 'learndash_group_users', $current_users);
                                    echo '<p class="success">✅ Added ' . $student->display_name . ' to group (meta method)</p>';
                                } else {
                                    echo '<p class="info">ℹ️ ' . $student->display_name . ' already in group</p>';
                                }
                            }
                        }
                    }
                }
                
                echo '<div class="success"><p><strong>✅ Student assignment completed!</strong></p></div>';
            }
            ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>🎯 Quick Actions</h2>
            <p>This will create demo students and assign them to your LearnDash groups:</p>
            
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="create_demo_students">
                <button type="submit" class="success">1. Create Demo Students</button>
            </form>
            
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="assign_to_groups">
                <button type="submit" class="success">2. Assign to Groups</button>
            </form>
            
            <button onclick="testDashboard()">3. Test Dashboard</button>
        </div>
        
        <div class="card">
            <h2>📊 Current Group Status</h2>
            <?php
            echo '<table>';
            echo '<tr><th>Group ID</th><th>Group Name</th><th>Current Students</th></tr>';
            
            foreach ($groups as $group_id) {
                $group_post = get_post($group_id);
                $group_name = $group_post ? $group_post->post_title : 'Unknown';
                
                $student_count = 0;
                if (function_exists('learndash_get_groups_users')) {
                    $users = learndash_get_groups_users($group_id);
                    $student_count = count($users);
                } else {
                    $users = get_post_meta($group_id, 'learndash_group_users', true);
                    $student_count = is_array($users) ? count($users) : 0;
                }
                
                echo '<tr>';
                echo '<td>' . $group_id . '</td>';
                echo '<td>' . $group_name . '</td>';
                echo '<td class="' . ($student_count > 0 ? 'success' : 'warning') . '">' . $student_count . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            ?>
        </div>
    </div>

    <script>
    function testDashboard() {
        window.location.href = 'test-click-debug.html';
    }
    </script>
</body>
</html>
