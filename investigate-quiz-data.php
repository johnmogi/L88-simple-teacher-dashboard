<?php
// Quick script to investigate quiz activity data after mock data generation
require_once('../../../wp-config.php');

global $wpdb;

echo "<h2>Quiz Activity Data Investigation</h2>\n";

// Check activity status breakdown
echo "<h3>1. Activity Status Breakdown</h3>\n";
$status_breakdown = $wpdb->get_results("
    SELECT activity_status, COUNT(*) as count 
    FROM {$wpdb->prefix}learndash_user_activity 
    WHERE activity_type = 'quiz' 
    GROUP BY activity_status 
    ORDER BY activity_status
");

echo "<table border='1'>\n";
echo "<tr><th>Activity Status</th><th>Count</th></tr>\n";
foreach ($status_breakdown as $row) {
    echo "<tr><td>{$row->activity_status}</td><td>{$row->count}</td></tr>\n";
}
echo "</table>\n";

// Check sample quiz attempts for a few users
echo "<h3>2. Sample Quiz Attempts (First 10 Records)</h3>\n";
$sample_attempts = $wpdb->get_results("
    SELECT user_id, post_id, activity_status, activity_completed, activity_updated 
    FROM {$wpdb->prefix}learndash_user_activity 
    WHERE activity_type = 'quiz' 
    ORDER BY activity_updated DESC 
    LIMIT 10
");

echo "<table border='1'>\n";
echo "<tr><th>User ID</th><th>Quiz ID</th><th>Status</th><th>Completed</th><th>Updated</th></tr>\n";
foreach ($sample_attempts as $row) {
    echo "<tr><td>{$row->user_id}</td><td>{$row->post_id}</td><td>{$row->activity_status}</td><td>{$row->activity_completed}</td><td>" . date('Y-m-d H:i:s', $row->activity_updated) . "</td></tr>\n";
}
echo "</table>\n";

// Check a specific user's quiz calculation
echo "<h3>3. Sample User Quiz Calculation (User ID 196)</h3>\n";
$user_id = 196; // From the browser data, this user shows 100%

// Get all quiz attempts for this user
$user_attempts = $wpdb->get_results($wpdb->prepare("
    SELECT post_id, activity_status, activity_completed, activity_updated 
    FROM {$wpdb->prefix}learndash_user_activity 
    WHERE activity_type = 'quiz' 
    AND user_id = %d 
    ORDER BY post_id, activity_updated DESC
", $user_id));

echo "<p>User $user_id quiz attempts:</p>\n";
echo "<table border='1'>\n";
echo "<tr><th>Quiz ID</th><th>Status</th><th>Completed</th><th>Updated</th></tr>\n";
foreach ($user_attempts as $row) {
    echo "<tr><td>{$row->post_id}</td><td>{$row->activity_status}</td><td>{$row->activity_completed}</td><td>" . date('Y-m-d H:i:s', $row->activity_updated) . "</td></tr>\n";
}
echo "</table>\n";

// Calculate what our current logic would produce
$total_quizzes = count($user_attempts);
$completed_quizzes = 0;
foreach ($user_attempts as $attempt) {
    if ($attempt->activity_status == 1) {
        $completed_quizzes++;
    }
}

$calculated_percentage = $total_quizzes > 0 ? round(($completed_quizzes / $total_quizzes) * 100, 2) : 0;

echo "<p><strong>Current Logic Result:</strong></p>\n";
echo "<p>Total Attempts: $total_quizzes</p>\n";
echo "<p>Completed Attempts: $completed_quizzes</p>\n";
echo "<p>Calculated Percentage: $calculated_percentage%</p>\n";

// Check if there are any failed attempts (status = 0)
$failed_attempts = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->prefix}learndash_user_activity 
    WHERE activity_type = 'quiz' 
    AND activity_status = 0
");

echo "<h3>4. Failed Attempts Check</h3>\n";
echo "<p>Total failed attempts (status = 0) in database: $failed_attempts</p>\n";

if ($failed_attempts == 0) {
    echo "<p><strong>ISSUE IDENTIFIED:</strong> No failed attempts in database! All quiz attempts have activity_status = 1, which explains why everyone shows 100%.</p>\n";
}
?>
