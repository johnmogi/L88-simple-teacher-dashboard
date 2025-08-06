<?php
/**
 * Sync School Manager Students to LearnDash Groups
 * 
 * Synchronize existing School Manager student-class assignments to LearnDash groups
 * Access: https://207lilac.local/wp-content/plugins/simple-teacher-dashboard/sync-students-to-learndash.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Access denied. Please log in as administrator.');
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Sync Students to LearnDash</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; font-size: 12px; }
        th { background: #f8f9fa; font-weight: bold; }
        button { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; background: #007cba; color: white; }
        .sync-btn { background: #28a745; }
        .test-btn { background: #17a2b8; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 11px; max-height: 200px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Sync Students to LearnDash</h1>
        
        <?php if ($action === 'sync_students'): ?>
        <div class="card">
            <h2>🔄 Synchronizing Students...</h2>
            <?php
            global $wpdb;
            
            // Get all student-class assignments from School Manager
            $assignments = $wpdb->get_results("
                SELECT 
                    sc.student_id,
                    sc.class_id,
                    c.class_name,
                    c.teacher_id,
                    c.group_id,
                    u.user_login,
                    u.display_name,
                    u.user_email
                FROM {$wpdb->prefix}school_student_classes sc
                JOIN {$wpdb->prefix}school_classes c ON sc.class_id = c.id
                JOIN {$wpdb->users} u ON sc.student_id = u.ID
                ORDER BY c.class_name, u.display_name
            ");
            
            if (empty($assignments)) {
                echo '<p class="error">❌ No student-class assignments found in School Manager tables.</p>';
            } else {
                echo '<p class="info">Found ' . count($assignments) . ' student-class assignments to sync.</p>';
                
                $synced_count = 0;
                $error_count = 0;
                $groups_updated = array();
                
                foreach ($assignments as $assignment) {
                    echo '<div style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 3px;">';
                    echo '<strong>Student:</strong> ' . $assignment->display_name . ' (' . $assignment->user_login . ')<br>';
                    echo '<strong>Class:</strong> ' . $assignment->class_name . ' (ID: ' . $assignment->class_id . ')<br>';
                    echo '<strong>Group ID:</strong> ' . $assignment->group_id . '<br>';
                    
                    if (!$assignment->group_id) {
                        echo '<span class="error">❌ No LearnDash group ID assigned to this class</span>';
                        $error_count++;
                    } else {
                        // Check if LearnDash group exists
                        $group_post = get_post($assignment->group_id);
                        if (!$group_post || $group_post->post_type !== 'groups') {
                            echo '<span class="error">❌ LearnDash group ' . $assignment->group_id . ' does not exist</span>';
                            $error_count++;
                        } else {
                            // Add student to LearnDash group
                            $current_users = get_post_meta($assignment->group_id, 'learndash_group_users', true);
                            if (!is_array($current_users)) {
                                $current_users = array();
                            }
                            
                            if (!in_array($assignment->student_id, $current_users)) {
                                $current_users[] = $assignment->student_id;
                                update_post_meta($assignment->group_id, 'learndash_group_users', $current_users);
                                
                                // Also update user meta (LearnDash pattern)
                                $user_groups = get_user_meta($assignment->student_id, 'learndash_group_users_' . $assignment->group_id, true);
                                if (!$user_groups) {
                                    update_user_meta($assignment->student_id, 'learndash_group_users_' . $assignment->group_id, $assignment->group_id);
                                }
                                
                                echo '<span class="success">✅ Added to LearnDash group "' . $group_post->post_title . '"</span>';
                                $synced_count++;
                                
                                if (!isset($groups_updated[$assignment->group_id])) {
                                    $groups_updated[$assignment->group_id] = array(
                                        'name' => $group_post->post_title,
                                        'students' => 0
                                    );
                                }
                                $groups_updated[$assignment->group_id]['students']++;
                            } else {
                                echo '<span class="info">ℹ️ Already in LearnDash group "' . $group_post->post_title . '"</span>';
                            }
                        }
                    }
                    
                    echo '</div>';
                }
                
                echo '<div class="success">';
                echo '<h3>📊 Sync Summary</h3>';
                echo '<p><strong>Successfully synced:</strong> ' . $synced_count . ' students</p>';
                echo '<p><strong>Errors:</strong> ' . $error_count . '</p>';
                echo '<p><strong>Groups updated:</strong> ' . count($groups_updated) . '</p>';
                
                if (!empty($groups_updated)) {
                    echo '<h4>Updated Groups:</h4>';
                    foreach ($groups_updated as $group_id => $info) {
                        echo '<p>• Group ' . $group_id . ' (' . $info['name'] . '): ' . $info['students'] . ' students</p>';
                    }
                }
                echo '</div>';
            }
            ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>📊 Current Status</h2>
            <?php
            global $wpdb;
            
            // Check School Manager tables
            $classes_table = $wpdb->prefix . 'school_classes';
            $student_classes_table = $wpdb->prefix . 'school_student_classes';
            
            echo '<h3>School Manager Data</h3>';
            if ($wpdb->get_var("SHOW TABLES LIKE '$classes_table'") == $classes_table) {
                $classes_with_students = $wpdb->get_results("
                    SELECT 
                        c.id,
                        c.name,
                        c.teacher_id,
                        c.group_id,
                        COUNT(sc.student_id) as student_count
                    FROM $classes_table c
                    LEFT JOIN $student_classes_table sc ON c.id = sc.class_id
                    GROUP BY c.id
                    HAVING student_count > 0
                    ORDER BY c.name
                ");
                
                if (!empty($classes_with_students)) {
                    echo '<table>';
                    echo '<tr><th>Class ID</th><th>Class Name</th><th>Teacher ID</th><th>Group ID</th><th>Students</th><th>Status</th></tr>';
                    
                    foreach ($classes_with_students as $class) {
                        $status = '';
                        if (!$class->group_id) {
                            $status = '<span class="error">❌ No Group ID</span>';
                        } else {
                            $group_post = get_post($class->group_id);
                            if (!$group_post) {
                                $status = '<span class="error">❌ Group Missing</span>';
                            } else {
                                $ld_users = get_post_meta($class->group_id, 'learndash_group_users', true);
                                $ld_count = is_array($ld_users) ? count($ld_users) : 0;
                                
                                if ($ld_count == $class->student_count) {
                                    $status = '<span class="success">✅ Synced</span>';
                                } else {
                                    $status = '<span class="warning">⚠️ ' . $ld_count . '/' . $class->student_count . ' synced</span>';
                                }
                            }
                        }
                        
                        echo '<tr>';
                        echo '<td>' . $class->id . '</td>';
                        echo '<td>' . $class->name . '</td>';
                        echo '<td>' . $class->teacher_id . '</td>';
                        echo '<td>' . $class->group_id . '</td>';
                        echo '<td>' . $class->student_count . '</td>';
                        echo '<td>' . $status . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</table>';
                } else {
                    echo '<p class="warning">No classes with students found</p>';
                }
            } else {
                echo '<p class="error">School Manager classes table not found</p>';
            }
            ?>
        </div>
        
        <div class="card">
            <h2>🚀 Actions</h2>
            <p>This will sync all School Manager student-class assignments to their corresponding LearnDash groups:</p>
            
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="sync_students">
                <button type="submit" class="sync-btn">🔄 Sync All Students to LearnDash</button>
            </form>
            
            <button class="test-btn" onclick="testDashboard()">🧪 Test Dashboard</button>
            
            <button onclick="viewDiagnostics()">🔍 View Diagnostics</button>
        </div>
        
        <div class="card">
            <h2>📋 Sample Student Data</h2>
            <?php
            // Show first 10 student assignments
            $sample_assignments = $wpdb->get_results("
                SELECT 
                    sc.student_id,
                    sc.class_id,
                    c.class_name,
                    c.group_id,
                    u.user_login,
                    u.display_name
                FROM {$wpdb->prefix}school_student_classes sc
                JOIN {$wpdb->prefix}school_classes c ON sc.class_id = c.id
                JOIN {$wpdb->users} u ON sc.student_id = u.ID
                ORDER BY c.class_name, u.display_name
                LIMIT 10
            ");
            
            if (!empty($sample_assignments)) {
                echo '<table>';
                echo '<tr><th>Student</th><th>Username</th><th>Class</th><th>Group ID</th></tr>';
                foreach ($sample_assignments as $assignment) {
                    echo '<tr>';
                    echo '<td>' . $assignment->display_name . '</td>';
                    echo '<td>' . $assignment->user_login . '</td>';
                    echo '<td>' . $assignment->class_name . '</td>';
                    echo '<td>' . ($assignment->group_id ?: 'None') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
            ?>
        </div>
    </div>

    <script>
    function testDashboard() {
        window.location.href = 'test-click-debug.html';
    }
    
    function viewDiagnostics() {
        window.location.href = 'diagnose-student-connections.php';
    }
    </script>
</body>
</html>
