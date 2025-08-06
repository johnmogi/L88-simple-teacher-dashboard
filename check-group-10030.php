<?php
/**
 * Check Group 10030 Specifically
 * 
 * Debug why group 10030 shows empty when it has 3 students in School Manager
 * Access: https://207lilac.local/wp-content/plugins/simple-teacher-dashboard/check-group-10030.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!is_user_logged_in()) {
    wp_die('Please log in first.');
}

$group_id = 10030;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Group 10030</title>
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
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        button { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; background: #007cba; color: white; }
        .fix-btn { background: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug Group <?php echo $group_id; ?></h1>
        
        <div class="card">
            <h2>📊 School Manager Data</h2>
            <?php
            global $wpdb;
            
            // Get students from School Manager for this group
            $school_students = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    u.ID AS student_id,
                    u.user_login,
                    u.display_name,
                    u.user_email,
                    c.id AS class_id,
                    c.name AS class_name
                FROM {$wpdb->users} u
                JOIN {$wpdb->prefix}school_student_classes sc ON u.ID = sc.student_id
                JOIN {$wpdb->prefix}school_classes c ON sc.class_id = c.id
                WHERE c.group_id = %d
                ORDER BY u.display_name
            ", $group_id));
            
            echo '<p><strong>Students in School Manager for group ' . $group_id . ':</strong> ' . count($school_students) . '</p>';
            
            if (!empty($school_students)) {
                echo '<table>';
                echo '<tr><th>Student ID</th><th>Username</th><th>Display Name</th><th>Email</th><th>Class</th></tr>';
                foreach ($school_students as $student) {
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
                echo '<p class="warning">No students found in School Manager for this group</p>';
            }
            ?>
        </div>
        
        <div class="card">
            <h2>🎯 LearnDash Group Data</h2>
            <?php
            // Check if group exists
            $group_post = get_post($group_id);
            if (!$group_post || $group_post->post_type !== 'groups') {
                echo '<p class="error">❌ Group ' . $group_id . ' does not exist or is not a LearnDash group</p>';
            } else {
                echo '<p class="success">✅ Group exists: ' . $group_post->post_title . '</p>';
                
                // Method 1: learndash_get_groups_users
                if (function_exists('learndash_get_groups_users')) {
                    $ld_users = learndash_get_groups_users($group_id);
                    echo '<p><strong>learndash_get_groups_users() result:</strong> ' . count($ld_users) . ' users</p>';
                    if (!empty($ld_users)) {
                        echo '<p>User IDs: ' . implode(', ', $ld_users) . '</p>';
                        
                        echo '<table>';
                        echo '<tr><th>User ID</th><th>Username</th><th>Display Name</th><th>Email</th></tr>';
                        foreach ($ld_users as $user_id) {
                            $user = get_user_by('id', $user_id);
                            if ($user) {
                                echo '<tr>';
                                echo '<td>' . $user->ID . '</td>';
                                echo '<td>' . $user->user_login . '</td>';
                                echo '<td>' . $user->display_name . '</td>';
                                echo '<td>' . $user->user_email . '</td>';
                                echo '</tr>';
                            }
                        }
                        echo '</table>';
                    }
                } else {
                    echo '<p class="error">learndash_get_groups_users() function not available</p>';
                }
                
                // Method 2: Direct meta query
                $meta_users = get_post_meta($group_id, 'learndash_group_users', true);
                echo '<p><strong>learndash_group_users meta:</strong> ';
                if (is_array($meta_users)) {
                    echo count($meta_users) . ' users';
                    if (!empty($meta_users)) {
                        echo ' (IDs: ' . implode(', ', $meta_users) . ')';
                    }
                } else {
                    echo 'Not an array - Type: ' . gettype($meta_users);
                    if ($meta_users) {
                        echo ', Value: ' . print_r($meta_users, true);
                    } else {
                        echo ', Value: EMPTY/NULL';
                    }
                }
                echo '</p>';
                
                // Show all group meta
                echo '<h3>All Group Meta</h3>';
                $all_meta = get_post_meta($group_id);
                echo '<pre>';
                foreach ($all_meta as $key => $values) {
                    echo $key . ': ' . print_r($values, true) . "\n";
                }
                echo '</pre>';
            }
            ?>
        </div>
        
        <div class="card">
            <h2>🧪 Test AJAX Call</h2>
            <p>This simulates the exact AJAX call your dashboard makes:</p>
            <button onclick="testAjaxCall()">Test AJAX Call</button>
            <div id="ajax-result"></div>
        </div>
        
        <div class="card">
            <h2>🔧 Fix Actions</h2>
            <button class="fix-btn" onclick="syncThisGroup()">Sync This Group Now</button>
            <button onclick="manualAdd()">Manually Add Students</button>
            
            <div id="fix-result"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function testAjaxCall() {
        $('#ajax-result').html('<div class="info"><p>Testing AJAX call...</p></div>');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'get_group_students',
                group_id: <?php echo $group_id; ?>,
                nonce: '<?php echo wp_create_nonce('teacher_dashboard_nonce'); ?>'
            },
            success: function(response) {
                $('#ajax-result').html(
                    '<div class="success">' +
                    '<h4>AJAX Success</h4>' +
                    '<p><strong>Response:</strong></p>' +
                    '<pre>' + JSON.stringify(response, null, 2) + '</pre>' +
                    '</div>'
                );
            },
            error: function(xhr, status, error) {
                $('#ajax-result').html(
                    '<div class="error">' +
                    '<h4>AJAX Error</h4>' +
                    '<p><strong>Status:</strong> ' + status + '</p>' +
                    '<p><strong>Error:</strong> ' + error + '</p>' +
                    '<p><strong>Response:</strong> ' + xhr.responseText + '</p>' +
                    '</div>'
                );
            }
        });
    }
    
    function syncThisGroup() {
        $('#fix-result').html('<div class="info"><p>This would sync just this group...</p></div>');
        
        // Make AJAX call to sync this specific group
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                action: 'sync_group',
                group_id: <?php echo $group_id; ?>
            },
            success: function(response) {
                $('#fix-result').html('<div class="success"><p>Group synced! Refresh page to see results.</p></div>');
                setTimeout(function() {
                    location.reload();
                }, 2000);
            },
            error: function() {
                $('#fix-result').html('<div class="error"><p>Sync failed. Try the full sync page.</p></div>');
            }
        });
    }
    
    function manualAdd() {
        window.location.href = 'sync-students-to-learndash.php';
    }
    </script>
</body>
</html>

<?php
// Handle sync action
if (isset($_POST['action']) && $_POST['action'] === 'sync_group') {
    global $wpdb;
    
    // Get students for this group from School Manager
    $students = $wpdb->get_results($wpdb->prepare("
        SELECT DISTINCT u.ID
        FROM {$wpdb->users} u
        JOIN {$wpdb->prefix}school_student_classes sc ON u.ID = sc.student_id
        JOIN {$wpdb->prefix}school_classes c ON sc.class_id = c.id
        WHERE c.group_id = %d
    ", $group_id));
    
    if (!empty($students)) {
        $student_ids = array_map(function($s) { return $s->ID; }, $students);
        
        // Update LearnDash group meta
        update_post_meta($group_id, 'learndash_group_users', $student_ids);
        
        // Also update user meta for each student
        foreach ($student_ids as $student_id) {
            update_user_meta($student_id, 'learndash_group_users_' . $group_id, $group_id);
        }
        
        wp_send_json_success(array('synced' => count($student_ids)));
    } else {
        wp_send_json_error('No students found');
    }
}
?>
