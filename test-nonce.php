<?php
/**
 * Test Nonce Generation and Verification
 * 
 * Quick test to verify nonce is working
 * Access: https://207lilac.local/wp-content/plugins/simple-teacher-dashboard/test-nonce.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!is_user_logged_in()) {
    wp_die('Please log in first.');
}

$current_user = wp_get_current_user();

// Generate nonce
$nonce = wp_create_nonce('teacher_dashboard_nonce');

// Test verification
$verify_result = wp_verify_nonce($nonce, 'teacher_dashboard_nonce');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Nonce</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        button { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; background: #007cba; color: white; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        #results { margin-top: 20px; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>🔐 Test Nonce</h1>
        
        <div class="card info">
            <h3>User: <?php echo esc_html($current_user->display_name); ?> (ID: <?php echo $current_user->ID; ?>)</h3>
        </div>
        
        <div class="card">
            <h2>🧪 Nonce Test Results</h2>
            <p><strong>Generated Nonce:</strong> <code><?php echo $nonce; ?></code></p>
            <p><strong>Verification Result:</strong> 
                <span class="<?php echo $verify_result ? 'success' : 'error'; ?>">
                    <?php echo $verify_result ? '✅ Valid' : '❌ Invalid'; ?>
                </span>
            </p>
            <p><strong>AJAX URL:</strong> <code><?php echo admin_url('admin-ajax.php'); ?></code></p>
        </div>
        
        <div class="card">
            <h2>🎯 Live AJAX Test</h2>
            <p>Test the actual AJAX call with this nonce:</p>
            <button id="test-ajax">Test AJAX with Nonce</button>
            <button id="test-ajax-bad">Test AJAX with Bad Nonce</button>
            <div id="results"></div>
        </div>
        
        <div class="card">
            <h2>📊 Debug Info</h2>
            <pre><?php
                echo "Current Time: " . current_time('Y-m-d H:i:s') . "\n";
                echo "WordPress Nonce Life: " . (defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400) . " seconds\n";
                echo "User ID: " . $current_user->ID . "\n";
                echo "User Roles: " . implode(', ', $current_user->roles) . "\n";
                echo "Is Teacher: " . (class_exists('Simple_Teacher_Dashboard') ? 'Testing...' : 'Dashboard class not found') . "\n";
                
                if (class_exists('Simple_Teacher_Dashboard')) {
                    $dashboard = new Simple_Teacher_Dashboard();
                    $reflection = new ReflectionClass($dashboard);
                    $is_teacher_method = $reflection->getMethod('is_teacher');
                    $is_teacher_method->setAccessible(true);
                    $is_teacher = $is_teacher_method->invoke($dashboard, $current_user);
                    echo "Is Teacher Result: " . ($is_teacher ? 'Yes' : 'No') . "\n";
                }
            ?></pre>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#test-ajax').click(function() {
            $('#results').html('<div class="info"><p>Testing AJAX with valid nonce...</p></div>');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_group_students',
                    group_id: 10025, // Test with a known group ID
                    nonce: '<?php echo $nonce; ?>'
                },
                success: function(response) {
                    console.log('AJAX Success:', response);
                    var html = '<div class="success">';
                    html += '<h4>✅ AJAX Success</h4>';
                    html += '<p>Response received successfully!</p>';
                    html += '<pre>' + JSON.stringify(response, null, 2) + '</pre>';
                    html += '</div>';
                    $('#results').html(html);
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
                    $('#results').html(html);
                }
            });
        });
        
        $('#test-ajax-bad').click(function() {
            $('#results').html('<div class="info"><p>Testing AJAX with bad nonce...</p></div>');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_group_students',
                    group_id: 10025,
                    nonce: 'bad_nonce_test'
                },
                success: function(response) {
                    $('#results').html('<div class="error"><h4>❌ This should not succeed</h4><pre>' + JSON.stringify(response, null, 2) + '</pre></div>');
                },
                error: function(xhr, status, error) {
                    var html = '<div class="success">';
                    html += '<h4>✅ Security Working</h4>';
                    html += '<p>Bad nonce was correctly rejected!</p>';
                    html += '<p><strong>Status:</strong> ' + xhr.status + '</p>';
                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        html += '<p><strong>Message:</strong> ' + xhr.responseJSON.data + '</p>';
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
