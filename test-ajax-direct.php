<?php
/**
 * Direct AJAX Test
 * 
 * Test AJAX endpoints directly
 * Access: https://207lilac.local/wp-content/plugins/simple-teacher-dashboard/test-ajax-direct.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!is_user_logged_in()) {
    wp_die('Please log in first.');
}

$current_user = wp_get_current_user();

// Test if user is teacher
$dashboard = new Simple_Teacher_Dashboard();
$reflection = new ReflectionClass($dashboard);
$is_teacher_method = $reflection->getMethod('is_teacher');
$is_teacher_method->setAccessible(true);
$is_teacher = $is_teacher_method->invoke($dashboard, $current_user);

if (!$is_teacher && !current_user_can('manage_options')) {
    wp_die('You need teacher permissions to test this.');
}

// Get user's groups
$get_groups_method = $reflection->getMethod('get_teacher_groups');
$get_groups_method->setAccessible(true);
$groups = $get_groups_method->invoke($dashboard, $current_user->ID);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Direct AJAX Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        button { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #007cba; color: white; }
        .btn-success { background: #28a745; color: white; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; white-space: pre-wrap; }
        #results { margin-top: 20px; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>🧪 Direct AJAX Test</h1>
        
        <div class="card info">
            <h3>User: <?php echo esc_html($current_user->display_name); ?> (ID: <?php echo $current_user->ID; ?>)</h3>
            <p><strong>Is Teacher:</strong> <?php echo $is_teacher ? '✅ Yes' : '❌ No'; ?></p>
            <p><strong>Groups Found:</strong> <?php echo count($groups); ?></p>
        </div>
        
        <?php if (!empty($groups)): ?>
            <div class="card">
                <h2>🎯 Test AJAX Calls</h2>
                <p>Click buttons to test AJAX endpoints:</p>
                
                <?php foreach ($groups as $group): ?>
                    <button class="btn-primary test-group" data-group-id="<?php echo $group->group_id; ?>">
                        Test Group: <?php echo esc_html($group->group_name); ?> (ID: <?php echo $group->group_id; ?>)
                    </button>
                <?php endforeach; ?>
                
                <div id="results"></div>
            </div>
        <?php else: ?>
            <div class="card error">
                <h3>❌ No Groups Found</h3>
                <p>User has no groups assigned. Please check the simple connections system.</p>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>📊 Debug Info</h2>
            <pre><?php
                echo "AJAX URL: " . admin_url('admin-ajax.php') . "\n";
                echo "Nonce: " . wp_create_nonce('teacher_dashboard_nonce') . "\n";
                echo "User ID: " . $current_user->ID . "\n";
                echo "User Roles: " . implode(', ', $current_user->roles) . "\n";
                echo "\nGroups:\n";
                foreach ($groups as $group) {
                    echo "- {$group->group_name} (ID: {$group->group_id})\n";
                }
            ?></pre>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('.test-group').click(function() {
            var groupId = $(this).data('group-id');
            var groupName = $(this).text();
            
            $('#results').html('<div class="card info"><h3>Testing ' + groupName + '...</h3></div>');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_group_students',
                    group_id: groupId,
                    nonce: '<?php echo wp_create_nonce('teacher_dashboard_nonce'); ?>'
                },
                success: function(response) {
                    console.log('Success:', response);
                    var html = '<div class="card success">';
                    html += '<h3>✅ Success for ' + groupName + '</h3>';
                    
                    if (response.success && response.data && response.data.students) {
                        html += '<p><strong>Students found:</strong> ' + response.data.students.length + '</p>';
                        
                        if (response.data.students.length > 0) {
                            html += '<ul>';
                            response.data.students.forEach(function(student) {
                                html += '<li>' + student.display_name + ' (' + student.user_email + ')</li>';
                            });
                            html += '</ul>';
                        }
                    } else {
                        html += '<p>No students data in response</p>';
                    }
                    
                    html += '<pre>' + JSON.stringify(response, null, 2) + '</pre>';
                    html += '</div>';
                    
                    $('#results').html(html);
                },
                error: function(xhr, status, error) {
                    console.log('Error:', xhr, status, error);
                    var html = '<div class="card error">';
                    html += '<h3>❌ Error for ' + groupName + '</h3>';
                    html += '<p><strong>Status:</strong> ' + xhr.status + '</p>';
                    html += '<p><strong>Error:</strong> ' + error + '</p>';
                    
                    if (xhr.responseText) {
                        html += '<p><strong>Response:</strong></p>';
                        html += '<pre>' + xhr.responseText + '</pre>';
                    }
                    
                    html += '</div>';
                    
                    $('#results').html(html);
                }
            });
        });
    });
    </script>
</body>
</html>
