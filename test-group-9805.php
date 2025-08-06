<?php
/**
 * Test Group 9805 - David's Missing Group
 * 
 * Test the group with many students in user meta
 * Access: https://207lilac.local/wp-content/plugins/simple-teacher-dashboard/test-group-9805.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!is_user_logged_in()) {
    wp_die('Please log in first.');
}

$group_id = 9805; // The group with many students in user meta

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Group 9805 - David's Group</title>
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
        button { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; background: #007cba; color: white; }
        .test-btn { background: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Test Group <?php echo $group_id; ?> - David's Missing Group</h1>
        
        <div class="card info">
            <h2>📊 Group Information</h2>
            <?php
            global $wpdb;
            
            $group_post = get_post($group_id);
            if ($group_post) {
                echo '<p><strong>Group Name:</strong> ' . $group_post->post_title . '</p>';
                echo '<p><strong>Group Status:</strong> ' . $group_post->post_status . '</p>';
            } else {
                echo '<p class="error">Group post does not exist</p>';
            }
            
            // Check user meta for this group
            $user_meta_count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) 
                FROM {$wpdb->usermeta} 
                WHERE meta_key = %s
            ", 'learndash_group_users_' . $group_id));
            
            echo '<p><strong>Users with group meta:</strong> ' . $user_meta_count . '</p>';
            ?>
        </div>
        
        <div class="card">
            <h2>👥 Students in User Meta</h2>
            <?php
            // Get all users with this group meta
            $students_meta = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    u.ID,
                    u.user_login,
                    u.display_name,
                    u.user_email,
                    um.meta_value
                FROM {$wpdb->users} u
                JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                WHERE um.meta_key = %s
                ORDER BY u.display_name
            ", 'learndash_group_users_' . $group_id));
            
            echo '<p><strong>Students found in user meta:</strong> ' . count($students_meta) . '</p>';
            
            if (!empty($students_meta)) {
                echo '<table>';
                echo '<tr><th>ID</th><th>Username</th><th>Display Name</th><th>Email</th><th>Meta Value</th></tr>';
                foreach ($students_meta as $student) {
                    $highlight = (strpos(strtolower($student->display_name), 'david') !== false) ? 'style="background: #fff3cd;"' : '';
                    echo '<tr ' . $highlight . '>';
                    echo '<td>' . $student->ID . '</td>';
                    echo '<td>' . $student->user_login . '</td>';
                    echo '<td>' . $student->display_name . '</td>';
                    echo '<td>' . $student->user_email . '</td>';
                    echo '<td>' . $student->meta_value . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
            ?>
        </div>
        
        <div class="card">
            <h2>🧪 Test New AJAX Method</h2>
            <p>Test the updated method that checks multiple data sources:</p>
            <button class="test-btn" onclick="testNewMethod()">Test New Multi-Source Method</button>
            <div id="test-result"></div>
        </div>
        
        <div class="card">
            <h2>🔍 Compare Data Sources</h2>
            <?php
            // Method 1: School Manager
            $school_count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) 
                FROM {$wpdb->prefix}school_student_classes sc
                JOIN {$wpdb->prefix}school_classes c ON sc.class_id = c.id
                WHERE c.group_id = %d
            ", $group_id));
            
            // Method 2: LearnDash group meta
            $ld_meta = get_post_meta($group_id, 'learndash_group_users', true);
            $ld_count = is_array($ld_meta) ? count($ld_meta) : 0;
            
            // Method 3: User meta
            $user_meta_count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) 
                FROM {$wpdb->usermeta} 
                WHERE meta_key = %s
            ", 'learndash_group_users_' . $group_id));
            
            echo '<table>';
            echo '<tr><th>Data Source</th><th>Student Count</th><th>Status</th></tr>';
            echo '<tr><td>School Manager Tables</td><td>' . $school_count . '</td><td>' . ($school_count > 0 ? '✅' : '❌') . '</td></tr>';
            echo '<tr><td>LearnDash Group Meta</td><td>' . $ld_count . '</td><td>' . ($ld_count > 0 ? '✅' : '❌') . '</td></tr>';
            echo '<tr><td>User Meta</td><td>' . $user_meta_count . '</td><td>' . ($user_meta_count > 0 ? '✅' : '❌') . '</td></tr>';
            echo '</table>';
            
            $total_sources = ($school_count > 0 ? 1 : 0) + ($ld_count > 0 ? 1 : 0) + ($user_meta_count > 0 ? 1 : 0);
            echo '<p><strong>Total active data sources:</strong> ' . $total_sources . '</p>';
            
            if ($user_meta_count > 10) {
                echo '<div class="success"><p>🎯 <strong>This looks like David\'s missing group with many students!</strong></p></div>';
            }
            ?>
        </div>
        
        <div class="card">
            <h2>🚀 Actions</h2>
            <button onclick="openDashboard()">Test in Dashboard</button>
            <button onclick="testAllGroups()">Test All Groups</button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function testNewMethod() {
        $('#test-result').html('<div class="info"><p>Testing new multi-source method...</p></div>');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'get_group_students',
                group_id: <?php echo $group_id; ?>,
                nonce: '<?php echo wp_create_nonce('teacher_dashboard_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data && response.data.students) {
                    const studentCount = response.data.students.length;
                    let html = '<div class="' + (studentCount > 10 ? 'success' : 'warning') + '">';
                    html += '<h4>🎉 Found ' + studentCount + ' students!</h4>';
                    
                    if (studentCount > 0) {
                        html += '<table>';
                        html += '<tr><th>ID</th><th>Name</th><th>Email</th><th>Source</th></tr>';
                        response.data.students.forEach(function(student) {
                            const highlight = student.student_name.toLowerCase().includes('david') ? 'style="background: #fff3cd;"' : '';
                            html += '<tr ' + highlight + '>';
                            html += '<td>' + student.student_id + '</td>';
                            html += '<td>' + student.student_name + '</td>';
                            html += '<td>' + student.student_email + '</td>';
                            html += '<td>' + (student.source || 'unknown') + '</td>';
                            html += '</tr>';
                        });
                        html += '</table>';
                        
                        if (studentCount > 10) {
                            html += '<p><strong>🎯 This looks like the missing group with many students!</strong></p>';
                        }
                    }
                    
                    html += '</div>';
                    $('#test-result').html(html);
                } else {
                    $('#test-result').html('<div class="error"><p>❌ No students found or error occurred</p><pre>' + JSON.stringify(response) + '</pre></div>');
                }
            },
            error: function(xhr, status, error) {
                $('#test-result').html('<div class="error"><p>❌ AJAX Error: ' + status + ' - ' + error + '</p></div>');
            }
        });
    }
    
    function openDashboard() {
        window.location.href = 'test-click-debug.html';
    }
    
    function testAllGroups() {
        window.location.href = 'test-all-groups.php';
    }
    </script>
</body>
</html>
