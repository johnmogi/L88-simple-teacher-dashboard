<?php
/**
 * Test All Groups with Students
 * 
 * Verify that the fixed query logic works for all groups
 * Access: https://207lilac.local/wp-content/plugins/simple-teacher-dashboard/test-all-groups.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!is_user_logged_in()) {
    wp_die('Please log in first.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test All Groups</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; font-size: 12px; }
        th { background: #f8f9fa; font-weight: bold; }
        button { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; background: #007cba; color: white; }
        .test-btn { background: #28a745; }
        .group-card { border-left: 4px solid #007cba; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 11px; max-height: 200px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test All Groups with Students</h1>
        
        <div class="card info">
            <h2>📊 Overview Query Results</h2>
            <?php
            global $wpdb;
            
            // Use your comprehensive query to see all groups with students
            $all_groups_data = $wpdb->get_results("
                SELECT 
                    c.group_id,
                    g.post_title AS group_name,
                    c.id AS class_id,
                    c.name AS class_name,
                    COUNT(u.ID) as student_count
                FROM edc_school_classes c
                JOIN edc_school_student_classes sc ON c.id = sc.class_id
                JOIN edc_users u ON sc.student_id = u.ID
                LEFT JOIN edc_posts g ON c.group_id = g.ID
                WHERE c.group_id IS NOT NULL
                GROUP BY c.group_id, c.id
                ORDER BY g.post_title, c.name
            ");
            
            echo '<p><strong>Groups with students found:</strong> ' . count($all_groups_data) . '</p>';
            
            if (!empty($all_groups_data)) {
                echo '<table>';
                echo '<tr><th>Group ID</th><th>Group Name</th><th>Class ID</th><th>Class Name</th><th>Students</th><th>Test</th></tr>';
                
                foreach ($all_groups_data as $group) {
                    echo '<tr>';
                    echo '<td>' . $group->group_id . '</td>';
                    echo '<td>' . ($group->group_name ?: 'No Title') . '</td>';
                    echo '<td>' . $group->class_id . '</td>';
                    echo '<td>' . $group->class_name . '</td>';
                    echo '<td>' . $group->student_count . '</td>';
                    echo '<td><button class="test-btn" onclick="testGroup(' . $group->group_id . ')">Test AJAX</button></td>';
                    echo '</tr>';
                }
                
                echo '</table>';
            }
            ?>
        </div>
        
        <div class="card">
            <h2>🔍 Individual Group Tests</h2>
            <?php
            // Get detailed data for each group
            $detailed_data = $wpdb->get_results("
                SELECT 
                    c.group_id,
                    g.post_title AS group_name,
                    c.id AS class_id,
                    c.name AS class_name,
                    u.ID AS student_id,
                    u.user_login,
                    u.display_name,
                    u.user_email
                FROM edc_school_classes c
                JOIN edc_school_student_classes sc ON c.id = sc.class_id
                JOIN edc_users u ON sc.student_id = u.ID
                LEFT JOIN edc_posts g ON c.group_id = g.ID
                WHERE c.group_id IS NOT NULL
                ORDER BY g.post_title, c.name, u.display_name
            ");
            
            // Group by group_id
            $groups = array();
            foreach ($detailed_data as $row) {
                if (!isset($groups[$row->group_id])) {
                    $groups[$row->group_id] = array(
                        'group_name' => $row->group_name,
                        'class_name' => $row->class_name,
                        'students' => array()
                    );
                }
                $groups[$row->group_id]['students'][] = $row;
            }
            
            foreach ($groups as $group_id => $group_data) {
                echo '<div class="card group-card">';
                echo '<h3>Group ' . $group_id . ': ' . ($group_data['group_name'] ?: 'No Title') . '</h3>';
                echo '<p><strong>Class:</strong> ' . $group_data['class_name'] . '</p>';
                echo '<p><strong>Students:</strong> ' . count($group_data['students']) . '</p>';
                
                echo '<table>';
                echo '<tr><th>Student ID</th><th>Username</th><th>Display Name</th><th>Email</th></tr>';
                foreach ($group_data['students'] as $student) {
                    echo '<tr>';
                    echo '<td>' . $student->student_id . '</td>';
                    echo '<td>' . $student->user_login . '</td>';
                    echo '<td>' . $student->display_name . '</td>';
                    echo '<td>' . $student->user_email . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                
                echo '<button class="test-btn" onclick="testGroup(' . $group_id . ')">Test AJAX for Group ' . $group_id . '</button>';
                echo '<div id="result-' . $group_id . '"></div>';
                echo '</div>';
            }
            ?>
        </div>
        
        <div class="card">
            <h2>🚀 Bulk Test All Groups</h2>
            <button class="test-btn" onclick="testAllGroups()">Test All Groups via AJAX</button>
            <button onclick="openDashboard()">Open Teacher Dashboard</button>
            <div id="bulk-results"></div>
        </div>
        
        <div class="card">
            <h2>📋 Raw Query Test</h2>
            <p>Test the exact query used in the fixed code:</p>
            <button onclick="testRawQuery()">Test Raw Query</button>
            <div id="raw-results"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function testGroup(groupId) {
        const resultDiv = $('#result-' + groupId);
        resultDiv.html('<div class="info"><p>Testing group ' + groupId + '...</p></div>');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'get_group_students',
                group_id: groupId,
                nonce: '<?php echo wp_create_nonce('teacher_dashboard_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data && response.data.students) {
                    const studentCount = response.data.students.length;
                    let html = '<div class="success">';
                    html += '<h4>✅ AJAX Success - ' + studentCount + ' students found</h4>';
                    
                    if (studentCount > 0) {
                        html += '<table>';
                        html += '<tr><th>ID</th><th>Name</th><th>Email</th></tr>';
                        response.data.students.forEach(function(student) {
                            html += '<tr>';
                            html += '<td>' + student.student_id + '</td>';
                            html += '<td>' + student.student_name + '</td>';
                            html += '<td>' + student.student_email + '</td>';
                            html += '</tr>';
                        });
                        html += '</table>';
                    }
                    
                    html += '</div>';
                    resultDiv.html(html);
                } else {
                    resultDiv.html('<div class="warning"><p>⚠️ Success but no students: ' + JSON.stringify(response) + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                resultDiv.html('<div class="error"><p>❌ AJAX Error: ' + status + ' - ' + error + '</p></div>');
            }
        });
    }
    
    function testAllGroups() {
        const groups = <?php echo json_encode(array_keys($groups)); ?>;
        let results = '<h3>Testing ' + groups.length + ' groups...</h3>';
        $('#bulk-results').html('<div class="info">' + results + '</div>');
        
        let completed = 0;
        let successful = 0;
        
        groups.forEach(function(groupId) {
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_group_students',
                    group_id: groupId,
                    nonce: '<?php echo wp_create_nonce('teacher_dashboard_nonce'); ?>'
                },
                success: function(response) {
                    completed++;
                    if (response.success && response.data && response.data.students) {
                        successful++;
                        results += '<p>✅ Group ' + groupId + ': ' + response.data.students.length + ' students</p>';
                    } else {
                        results += '<p>⚠️ Group ' + groupId + ': No students</p>';
                    }
                    
                    if (completed === groups.length) {
                        results += '<h4>Summary: ' + successful + '/' + groups.length + ' groups working</h4>';
                        $('#bulk-results').html('<div class="' + (successful === groups.length ? 'success' : 'warning') + '">' + results + '</div>');
                    }
                },
                error: function() {
                    completed++;
                    results += '<p>❌ Group ' + groupId + ': AJAX Error</p>';
                    
                    if (completed === groups.length) {
                        results += '<h4>Summary: ' + successful + '/' + groups.length + ' groups working</h4>';
                        $('#bulk-results').html('<div class="error">' + results + '</div>');
                    }
                }
            });
        });
    }
    
    function testRawQuery() {
        $('#raw-results').html('<div class="info"><p>Testing raw query...</p></div>');
        
        // This would need to be implemented as an AJAX endpoint
        alert('Raw query test would need a separate endpoint. The main tests above use the actual dashboard AJAX.');
    }
    
    function openDashboard() {
        window.location.href = 'test-click-debug.html';
    }
    </script>
</body>
</html>
