<?php
/**
 * Debug Dashboard Issues
 * 
 * Comprehensive debugging for the teacher dashboard
 * Access: https://207lilac.local/wp-content/plugins/simple-teacher-dashboard/debug-dashboard.php
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
    <title>Debug Teacher Dashboard</title>
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
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; white-space: pre-wrap; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; font-size: 12px; }
        th { background: #f8f9fa; font-weight: bold; }
        .two-column { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        #results { margin-top: 20px; }
        .dashboard-test { border: 2px solid #007cba; padding: 20px; margin: 20px 0; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug Teacher Dashboard</h1>
        
        <div class="card info">
            <h3>Current User: <?php echo esc_html($current_user->display_name); ?> (ID: <?php echo $current_user->ID; ?>)</h3>
            <p><strong>Roles:</strong> <?php echo implode(', ', $current_user->roles); ?></p>
            <p><strong>Time:</strong> <?php echo current_time('Y-m-d H:i:s'); ?></p>
        </div>
        
        <div class="two-column">
            <div>
                <div class="card">
                    <h2>🧪 Dashboard Class Test</h2>
                    <?php
                    if (class_exists('Simple_Teacher_Dashboard')) {
                        echo '<p class="success">✅ Simple_Teacher_Dashboard class exists</p>';
                        
                        $dashboard = new Simple_Teacher_Dashboard();
                        $reflection = new ReflectionClass($dashboard);
                        
                        // Test is_teacher
                        $is_teacher_method = $reflection->getMethod('is_teacher');
                        $is_teacher_method->setAccessible(true);
                        $is_teacher = $is_teacher_method->invoke($dashboard, $current_user);
                        
                        echo '<p class="' . ($is_teacher ? 'success' : 'error') . '">';
                        echo ($is_teacher ? '✅' : '❌') . ' Is Teacher: ' . ($is_teacher ? 'Yes' : 'No');
                        echo '</p>';
                        
                        // Test get_teacher_groups
                        $get_groups_method = $reflection->getMethod('get_teacher_groups');
                        $get_groups_method->setAccessible(true);
                        $groups = $get_groups_method->invoke($dashboard, $current_user->ID);
                        
                        echo '<p class="' . (count($groups) > 0 ? 'success' : 'warning') . '">';
                        echo (count($groups) > 0 ? '✅' : '⚠️') . ' Groups Found: ' . count($groups);
                        echo '</p>';
                        
                        if (!empty($groups)) {
                            echo '<table>';
                            echo '<tr><th>Group ID</th><th>Name</th><th>Status</th><th>Students</th></tr>';
                            
                            foreach ($groups as $group) {
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
                        
                    } else {
                        echo '<p class="error">❌ Simple_Teacher_Dashboard class not found</p>';
                    }
                    ?>
                </div>
                
                <div class="card">
                    <h2>🔧 AJAX Actions Test</h2>
                    <?php
                    $ajax_actions = array(
                        'get_group_students' => has_action('wp_ajax_get_group_students'),
                        'get_student_quiz_data' => has_action('wp_ajax_get_student_quiz_data')
                    );
                    
                    foreach ($ajax_actions as $action => $registered) {
                        echo '<p class="' . ($registered ? 'success' : 'error') . '">';
                        echo ($registered ? '✅' : '❌') . ' wp_ajax_' . $action . ': ';
                        echo ($registered ? 'Registered' : 'Not Registered');
                        echo '</p>';
                    }
                    ?>
                </div>
            </div>
            
            <div>
                <div class="card">
                    <h2>📊 System Status</h2>
                    <table>
                        <tr><th>Component</th><th>Status</th></tr>
                        <tr><td>WordPress</td><td>✅ <?php echo get_bloginfo('version'); ?></td></tr>
                        <tr><td>jQuery</td><td>✅ Loaded</td></tr>
                        <tr><td>Simple Teacher Dashboard</td><td><?php echo class_exists('Simple_Teacher_Dashboard') ? '✅ Active' : '❌ Missing'; ?></td></tr>
                        <tr><td>LearnDash</td><td><?php echo function_exists('learndash_get_groups_users') ? '✅ Active' : '❌ Missing'; ?></td></tr>
                        <tr><td>School Manager Lite</td><td><?php 
                            global $wpdb;
                            $classes_table = $wpdb->prefix . 'school_classes';
                            echo ($wpdb->get_var("SHOW TABLES LIKE '$classes_table'") == $classes_table) ? '✅ Active' : '❌ Missing';
                        ?></td></tr>
                        <tr><td>AJAX URL</td><td><?php echo admin_url('admin-ajax.php'); ?></td></tr>
                        <tr><td>Nonce</td><td><?php echo wp_create_nonce('teacher_dashboard_nonce'); ?></td></tr>
                    </table>
                </div>
                
                <div class="card">
                    <h2>🎯 Live AJAX Test</h2>
                    <?php if (class_exists('Simple_Teacher_Dashboard')): ?>
                        <p>Click to test AJAX calls:</p>
                        <button class="btn-primary" id="test-ajax">Test AJAX Call</button>
                        <button class="btn-warning" id="test-shortcode">Test Shortcode</button>
                        <div id="ajax-results"></div>
                    <?php else: ?>
                        <p class="error">Dashboard class not available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2>🎯 Live Dashboard Render</h2>
            <div class="dashboard-test">
                <?php
                if (class_exists('Simple_Teacher_Dashboard')) {
                    echo '<h3>Shortcode Output:</h3>';
                    echo do_shortcode('[simple_teacher_dashboard]');
                } else {
                    echo '<p class="error">Dashboard class not available</p>';
                }
                ?>
            </div>
        </div>
        
        <div id="results"></div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#test-ajax').click(function() {
            $('#ajax-results').html('<div class="info"><p>Testing AJAX...</p></div>');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_group_students',
                    group_id: 10025, // Test with a known group ID
                    nonce: '<?php echo wp_create_nonce('teacher_dashboard_nonce'); ?>'
                },
                success: function(response) {
                    console.log('AJAX Success:', response);
                    var html = '<div class="success">';
                    html += '<h4>✅ AJAX Success</h4>';
                    html += '<pre>' + JSON.stringify(response, null, 2) + '</pre>';
                    html += '</div>';
                    $('#ajax-results').html(html);
                },
                error: function(xhr, status, error) {
                    console.log('AJAX Error:', xhr, status, error);
                    var html = '<div class="error">';
                    html += '<h4>❌ AJAX Error</h4>';
                    html += '<p><strong>Status:</strong> ' + xhr.status + '</p>';
                    html += '<p><strong>Error:</strong> ' + error + '</p>';
                    if (xhr.responseText) {
                        html += '<pre>' + xhr.responseText + '</pre>';
                    }
                    html += '</div>';
                    $('#ajax-results').html(html);
                }
            });
        });
        
        $('#test-shortcode').click(function() {
            $('#ajax-results').html('<div class="info"><p>Testing shortcode render...</p></div>');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'test_shortcode_render',
                    nonce: '<?php echo wp_create_nonce('test_shortcode'); ?>'
                },
                success: function(response) {
                    $('#ajax-results').html('<div class="success"><h4>Shortcode Test</h4><pre>' + JSON.stringify(response, null, 2) + '</pre></div>');
                },
                error: function(xhr, status, error) {
                    $('#ajax-results').html('<div class="error"><h4>Shortcode Test Failed</h4><p>' + error + '</p></div>');
                }
            });
        });
    });
    </script>
</body>
</html>
