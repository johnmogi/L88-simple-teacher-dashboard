<?php
/**
 * Test Dashboard Functionality
 * 
 * Quick test to verify the dashboard is working with sorting and print features
 */

// Include WordPress
$wp_config_path = dirname(__FILE__) . '/../../../../wp-config.php';
if (file_exists($wp_config_path)) {
    require_once($wp_config_path);
} else {
    die('WordPress not found. Please check the path.');
}

echo "<h1>Teacher Dashboard Test</h1>";

// Test if the plugin class exists
if (class_exists('Simple_Teacher_Dashboard')) {
    echo "<p style='color: green;'>✅ Simple_Teacher_Dashboard class exists</p>";
} else {
    echo "<p style='color: red;'>❌ Simple_Teacher_Dashboard class not found</p>";
}

// Test if the shortcode is registered
if (shortcode_exists('teacher_dashboard')) {
    echo "<p style='color: green;'>✅ [teacher_dashboard] shortcode is registered</p>";
} else {
    echo "<p style='color: red;'>❌ [teacher_dashboard] shortcode not found</p>";
}

// Test database tables
global $wpdb;

$tables_to_check = [
    $wpdb->prefix . 'learndash_pro_quiz_statistic',
    $wpdb->prefix . 'learndash_pro_quiz_statistic_ref',
    $wpdb->prefix . 'learndash_user_activity'
];

echo "<h2>Database Tables Check</h2>";
foreach ($tables_to_check as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        echo "<p style='color: green;'>✅ $table exists ($count records)</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ $table does not exist</p>";
    }
}

// Test for students in groups
echo "<h2>Students in Groups Check</h2>";
$students_in_groups = $wpdb->get_var("
    SELECT COUNT(DISTINCT u.ID) 
    FROM {$wpdb->users} u
    JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
    WHERE um.meta_key LIKE 'learndash_group_users_%'
");

echo "<p><strong>Students in groups:</strong> $students_in_groups</p>";

if ($students_in_groups > 0) {
    echo "<p style='color: green;'>✅ Students found in groups</p>";
    
    // Show sample students
    $sample_students = $wpdb->get_results("
        SELECT DISTINCT u.ID, u.display_name, u.user_email 
        FROM {$wpdb->users} u
        JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
        WHERE um.meta_key LIKE 'learndash_group_users_%'
        ORDER BY u.display_name
        LIMIT 5
    ");
    
    echo "<h3>Sample Students (first 5):</h3>";
    echo "<ul>";
    foreach ($sample_students as $student) {
        echo "<li>{$student->display_name} (ID: {$student->ID}) - {$student->user_email}</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: orange;'>⚠️ No students found in groups</p>";
}

echo "<h2>Features Added</h2>";
echo "<ul>";
echo "<li>✅ <strong>Sorting functionality:</strong> Click on table headers to sort by name, email, course completion, or quiz scores</li>";
echo "<li>✅ <strong>Print button:</strong> Print-friendly version of the table with group statistics</li>";
echo "<li>✅ <strong>Enhanced debugging:</strong> Detailed logging for quiz calculation issues</li>";
echo "<li>✅ <strong>Improved quiz calculation:</strong> Better handling of completed vs overall averages</li>";
echo "</ul>";

echo "<h2>How to Test</h2>";
echo "<ol>";
echo "<li>Go to a page with the [teacher_dashboard] shortcode</li>";
echo "<li>Select a group to view students</li>";
echo "<li>Click on table headers to test sorting</li>";
echo "<li>Use the print button to test print functionality</li>";
echo "<li>Check wp-content/debug.log for quiz calculation debug messages</li>";
echo "</ol>";

echo "<h2>Debug Tools</h2>";
echo "<ul>";
echo "<li><a href='debug-quiz-calculation.php'>Quiz Calculation Debug Tool</a> - Analyze individual student quiz data</li>";
echo "<li><a href='../../../debug.log' target='_blank'>WordPress Debug Log</a> - View debug messages</li>";
echo "</ul>";
?>
