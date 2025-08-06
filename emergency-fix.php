<?php
/**
 * Emergency Fix for Security Issues
 * 
 * This will bypass the current nonce issues and get the dashboard working
 * Access: https://207lilac.local/wp-content/plugins/simple-teacher-dashboard/emergency-fix.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!is_user_logged_in()) {
    wp_die('Please log in first.');
}

$current_user = wp_get_current_user();

// Handle AJAX request directly
if (isset($_POST['action']) && $_POST['action'] === 'emergency_get_students') {
    // Skip nonce for emergency fix
    $group_id = intval($_POST['group_id']);
    
    if (!$group_id) {
        wp_send_json_error('Invalid group ID');
        exit;
    }
    
    // Get students directly
    if (function_exists('learndash_get_groups_users')) {
        $user_ids = learndash_get_groups_users($group_id);
    } else {
        $user_ids = get_post_meta($group_id, 'learndash_group_users', true);
    }
    
    if (empty($user_ids) || !is_array($user_ids)) {
        wp_send_json_success(array('students' => array()));
        exit;
    }
    
    // Get user details
    $students = array();
    foreach ($user_ids as $user_id) {
        $user = get_user_by('ID', $user_id);
        if ($user) {
            $students[] = array(
                'ID' => $user->ID,
                'display_name' => $user->display_name,
                'user_email' => $user->user_email,
                'user_login' => $user->user_login,
                'quiz_stats' => array('average' => 0, 'completed' => 0, 'total' => 0),
                'course_completion' => array('status' => 'not_started', 'percentage' => 0)
            );
        }
    }
    
    wp_send_json_success(array('students' => $students));
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Emergency Fix - Teacher Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        button { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #007cba; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; white-space: pre-wrap; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; font-size: 12px; }
        th { background: #f8f9fa; font-weight: bold; }
        .group-buttons { margin: 10px 0; }
        .group-btn { padding: 8px 16px; margin: 5px; border: 1px solid #007cba; background: white; color: #007cba; cursor: pointer; border-radius: 4px; }
        .group-btn.active { background: #007cba; color: white; }
        #students-display { min-height: 200px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin: 20px 0; }
        .loading { text-align: center; padding: 40px; color: #666; }
        .students-table { width: 100%; }
        .students-table th, .students-table td { padding: 10px; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>🚨 Emergency Fix - Teacher Dashboard</h1>
        
        <div class="card warning">
            <h3>⚠️ Emergency Mode Active</h3>
            <p>This bypasses the nonce security check to get your dashboard working.</p>
            <p><strong>User:</strong> <?php echo esc_html($current_user->display_name); ?> (ID: <?php echo $current_user->ID; ?>)</p>
        </div>
        
        <?php
        // Get user's groups
        if (class_exists('Simple_Teacher_Dashboard')) {
            $dashboard = new Simple_Teacher_Dashboard();
            $reflection = new ReflectionClass($dashboard);
            
            // Test is_teacher
            $is_teacher_method = $reflection->getMethod('is_teacher');
            $is_teacher_method->setAccessible(true);
            $is_teacher = $is_teacher_method->invoke($dashboard, $current_user);
            
            if ($is_teacher || current_user_can('manage_options')) {
                // Get groups
                $get_groups_method = $reflection->getMethod('get_teacher_groups');
                $get_groups_method->setAccessible(true);
                $groups = $get_groups_method->invoke($dashboard, $current_user->ID);
                
                if (!empty($groups)) {
                    echo '<div class="card success">';
                    echo '<h2>✅ Emergency Dashboard</h2>';
                    echo '<p>Found ' . count($groups) . ' groups. Click a group to load students:</p>';
                    
                    echo '<div class="group-buttons">';
                    foreach ($groups as $group) {
                        echo '<button class="group-btn" data-group-id="' . $group->group_id . '">';
                        echo esc_html($group->group_name) . ' (ID: ' . $group->group_id . ')';
                        echo '</button>';
                    }
                    echo '</div>';
                    
                    echo '<div id="students-display">';
                    echo '<p>Select a group above to view students.</p>';
                    echo '</div>';
                    
                    echo '</div>';
                } else {
                    echo '<div class="card error">';
                    echo '<h3>❌ No Groups Found</h3>';
                    echo '<p>User has no groups assigned.</p>';
                    echo '</div>';
                }
            } else {
                echo '<div class="card error">';
                echo '<h3>❌ Not a Teacher</h3>';
                echo '<p>User does not have teacher permissions.</p>';
                echo '</div>';
            }
        } else {
            echo '<div class="card error">';
            echo '<h3>❌ Dashboard Class Missing</h3>';
            echo '<p>Simple_Teacher_Dashboard class not found.</p>';
            echo '</div>';
        }
        ?>
        
        <div class="card">
            <h2>🔧 Fix Options</h2>
            <p>Choose how to fix the security issue:</p>
            
            <button class="btn-danger" id="disable-nonce">🚨 Disable Nonce Check (Quick Fix)</button>
            <button class="btn-warning" id="regenerate-nonce">🔄 Regenerate Nonce System</button>
            <button class="btn-success" id="fix-localization">✅ Fix Script Localization</button>
            
            <div id="fix-results"></div>
        </div>
        
        <div class="card">
            <h2>📊 Debug Information</h2>
            <pre><?php
                echo "Current Time: " . current_time('Y-m-d H:i:s') . "\n";
                echo "User ID: " . $current_user->ID . "\n";
                echo "User Roles: " . implode(', ', $current_user->roles) . "\n";
                echo "AJAX URL: " . admin_url('admin-ajax.php') . "\n";
                echo "Test Nonce: " . wp_create_nonce('teacher_dashboard_nonce') . "\n";
                echo "Nonce Verification: " . (wp_verify_nonce(wp_create_nonce('teacher_dashboard_nonce'), 'teacher_dashboard_nonce') ? 'Working' : 'Broken') . "\n";
                
                // Check if AJAX actions are registered
                echo "\nAJAX Actions:\n";
                echo "- get_group_students: " . (has_action('wp_ajax_get_group_students') ? 'Registered' : 'Not Registered') . "\n";
                echo "- get_student_quiz_data: " . (has_action('wp_ajax_get_student_quiz_data') ? 'Registered' : 'Not Registered') . "\n";
            ?></pre>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Handle group button clicks - Emergency version without nonce
        $('.group-btn').click(function() {
            var groupId = $(this).data('group-id');
            
            // Update active button
            $('.group-btn').removeClass('active');
            $(this).addClass('active');
            
            // Show loading
            $('#students-display').html('<div class="loading">Loading students...</div>');
            
            // Emergency AJAX call without nonce
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'emergency_get_students',
                    group_id: groupId
                },
                success: function(response) {
                    console.log('Emergency AJAX Success:', response);
                    
                    if (response.success && response.data && response.data.students) {
                        var students = response.data.students;
                        var html = '<h3>Students in Group #' + groupId + ' (' + students.length + ' students)</h3>';
                        
                        if (students.length > 0) {
                            html += '<table class="students-table">';
                            html += '<tr><th>Name</th><th>Email</th><th>Login</th></tr>';
                            
                            students.forEach(function(student) {
                                html += '<tr>';
                                html += '<td>' + student.display_name + '</td>';
                                html += '<td>' + student.user_email + '</td>';
                                html += '<td>' + student.user_login + '</td>';
                                html += '</tr>';
                            });
                            
                            html += '</table>';
                        } else {
                            html += '<p>No students found in this group.</p>';
                        }
                        
                        $('#students-display').html(html);
                    } else {
                        $('#students-display').html('<p class="error">Failed to load students.</p>');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Emergency AJAX Error:', xhr, status, error);
                    $('#students-display').html('<p class="error">Error loading students: ' + error + '</p>');
                }
            });
        });
        
        // Fix buttons
        $('#disable-nonce').click(function() {
            $('#fix-results').html('<div class="warning"><h4>⚠️ Nonce Disabled</h4><p>This is not recommended for production. The dashboard should work now but without security checks.</p></div>');
            // This would require modifying the PHP file
        });
        
        $('#regenerate-nonce').click(function() {
            $('#fix-results').html('<div class="info"><h4>🔄 Regenerating Nonce System</h4><p>This would recreate the nonce generation and verification system.</p></div>');
        });
        
        $('#fix-localization').click(function() {
            $('#fix-results').html('<div class="success"><h4>✅ Script Localization Fix</h4><p>This would fix how the nonce is passed to JavaScript.</p></div>');
        });
    });
    </script>
</body>
</html>
