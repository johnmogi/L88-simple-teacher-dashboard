<?php
/**
 * Live AJAX Debug
 * 
 * Real-time debugging of AJAX calls
 * Access: https://207lilac.local/wp-content/plugins/simple-teacher-dashboard/debug-ajax-live.php
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
    <title>Live AJAX Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        button { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #007cba; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; white-space: pre-wrap; font-size: 11px; max-height: 400px; overflow-y: auto; }
        .two-column { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        #console-log { background: #000; color: #0f0; font-family: monospace; padding: 15px; border-radius: 5px; max-height: 300px; overflow-y: auto; }
        .log-entry { margin: 2px 0; }
        .log-error { color: #f00; }
        .log-warn { color: #ff0; }
        .log-info { color: #0ff; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; font-size: 12px; }
        th { background: #f8f9fa; font-weight: bold; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>🔍 Live AJAX Debug</h1>
        
        <div class="card info">
            <h3>Current Status</h3>
            <p><strong>User:</strong> <?php echo esc_html($current_user->display_name); ?> (ID: <?php echo $current_user->ID; ?>)</p>
            <p><strong>Time:</strong> <?php echo current_time('Y-m-d H:i:s'); ?></p>
            <p><strong>AJAX URL:</strong> <?php echo admin_url('admin-ajax.php'); ?></p>
            <p><strong>Test Nonce:</strong> <?php echo wp_create_nonce('teacher_dashboard_nonce'); ?></p>
        </div>
        
        <div class="two-column">
            <div>
                <div class="card">
                    <h2>🎯 Quick Tests</h2>
                    <button class="btn-primary" id="test-basic">Test Basic AJAX</button>
                    <button class="btn-success" id="test-with-nonce">Test With Nonce</button>
                    <button class="btn-danger" id="test-no-nonce">Test Without Nonce</button>
                    <button class="btn-primary" id="test-group-10025">Test Group 10025</button>
                    <button class="btn-primary" id="clear-log">Clear Log</button>
                </div>
                
                <div class="card">
                    <h2>📊 User Groups</h2>
                    <?php
                    if (class_exists('Simple_Teacher_Dashboard')) {
                        $dashboard = new Simple_Teacher_Dashboard();
                        $reflection = new ReflectionClass($dashboard);
                        
                        $get_groups_method = $reflection->getMethod('get_teacher_groups');
                        $get_groups_method->setAccessible(true);
                        $groups = $get_groups_method->invoke($dashboard, $current_user->ID);
                        
                        if (!empty($groups)) {
                            echo '<table>';
                            echo '<tr><th>Group ID</th><th>Name</th><th>Test</th></tr>';
                            
                            foreach ($groups as $group) {
                                echo '<tr>';
                                echo '<td>' . $group->group_id . '</td>';
                                echo '<td>' . esc_html($group->group_name) . '</td>';
                                echo '<td><button class="btn-primary test-group" data-group-id="' . $group->group_id . '">Test</button></td>';
                                echo '</tr>';
                            }
                            echo '</table>';
                        } else {
                            echo '<p class="error">No groups found</p>';
                        }
                    } else {
                        echo '<p class="error">Dashboard class not found</p>';
                    }
                    ?>
                </div>
            </div>
            
            <div>
                <div class="card">
                    <h2>📟 Console Log</h2>
                    <div id="console-log"></div>
                </div>
                
                <div class="card">
                    <h2>📋 AJAX Response</h2>
                    <div id="ajax-response"></div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2>🔧 System Check</h2>
            <div id="system-check">
                <p>Checking system status...</p>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var logCount = 0;
        
        // Override console.log to capture logs
        var originalLog = console.log;
        var originalError = console.error;
        var originalWarn = console.warn;
        
        function addToLog(message, type) {
            type = type || 'info';
            logCount++;
            var timestamp = new Date().toLocaleTimeString();
            var logEntry = '<div class="log-entry log-' + type + '">[' + timestamp + '] ' + message + '</div>';
            $('#console-log').append(logEntry);
            $('#console-log').scrollTop($('#console-log')[0].scrollHeight);
        }
        
        console.log = function() {
            originalLog.apply(console, arguments);
            addToLog(Array.prototype.slice.call(arguments).join(' '), 'info');
        };
        
        console.error = function() {
            originalError.apply(console, arguments);
            addToLog(Array.prototype.slice.call(arguments).join(' '), 'error');
        };
        
        console.warn = function() {
            originalWarn.apply(console, arguments);
            addToLog(Array.prototype.slice.call(arguments).join(' '), 'warn');
        };
        
        // System check
        function runSystemCheck() {
            var checks = [];
            checks.push('jQuery Version: ' + $.fn.jquery);
            checks.push('AJAX URL: <?php echo admin_url('admin-ajax.php'); ?>');
            checks.push('User ID: <?php echo $current_user->ID; ?>');
            checks.push('Current Time: ' + new Date().toLocaleString());
            
            $('#system-check').html('<pre>' + checks.join('\n') + '</pre>');
        }
        
        // Test functions
        function testAjax(data, description) {
            console.log('Testing: ' + description);
            $('#ajax-response').html('<div class="info"><h4>Testing: ' + description + '</h4><p>Sending request...</p></div>');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: data,
                success: function(response) {
                    console.log('SUCCESS: ' + description, response);
                    var html = '<div class="success">';
                    html += '<h4>✅ SUCCESS: ' + description + '</h4>';
                    
                    if (response.success && response.data && response.data.students) {
                        html += '<p><strong>Students found:</strong> ' + response.data.students.length + '</p>';
                        if (response.data.students.length > 0) {
                            html += '<ul>';
                            response.data.students.slice(0, 3).forEach(function(student) {
                                html += '<li>' + student.display_name + ' (' + student.user_email + ')</li>';
                            });
                            if (response.data.students.length > 3) {
                                html += '<li>... and ' + (response.data.students.length - 3) + ' more</li>';
                            }
                            html += '</ul>';
                        }
                    }
                    
                    html += '<pre>' + JSON.stringify(response, null, 2) + '</pre>';
                    html += '</div>';
                    $('#ajax-response').html(html);
                },
                error: function(xhr, status, error) {
                    console.error('ERROR: ' + description, xhr, status, error);
                    var html = '<div class="error">';
                    html += '<h4>❌ ERROR: ' + description + '</h4>';
                    html += '<p><strong>Status:</strong> ' + xhr.status + '</p>';
                    html += '<p><strong>Error:</strong> ' + error + '</p>';
                    html += '<p><strong>Response Text:</strong></p>';
                    html += '<pre>' + xhr.responseText + '</pre>';
                    html += '</div>';
                    $('#ajax-response').html(html);
                }
            });
        }
        
        // Button handlers
        $('#test-basic').click(function() {
            testAjax({
                action: 'get_group_students',
                group_id: 10025
            }, 'Basic AJAX (no nonce)');
        });
        
        $('#test-with-nonce').click(function() {
            testAjax({
                action: 'get_group_students',
                group_id: 10025,
                nonce: '<?php echo wp_create_nonce('teacher_dashboard_nonce'); ?>'
            }, 'AJAX with nonce');
        });
        
        $('#test-no-nonce').click(function() {
            testAjax({
                action: 'get_group_students',
                group_id: 10025,
                nonce: ''
            }, 'AJAX with empty nonce');
        });
        
        $('#test-group-10025').click(function() {
            testAjax({
                action: 'get_group_students',
                group_id: 10025,
                nonce: '<?php echo wp_create_nonce('teacher_dashboard_nonce'); ?>'
            }, 'Group 10025 with nonce');
        });
        
        $('.test-group').click(function() {
            var groupId = $(this).data('group-id');
            testAjax({
                action: 'get_group_students',
                group_id: groupId,
                nonce: '<?php echo wp_create_nonce('teacher_dashboard_nonce'); ?>'
            }, 'Group ' + groupId);
        });
        
        $('#clear-log').click(function() {
            $('#console-log').empty();
            logCount = 0;
        });
        
        // Run initial system check
        runSystemCheck();
        
        console.log('Live AJAX Debug initialized');
        console.log('Ready to test AJAX calls');
    });
    </script>
</body>
</html>
