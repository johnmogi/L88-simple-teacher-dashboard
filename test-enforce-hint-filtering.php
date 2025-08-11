<?php
/**
 * Test script to verify enforce hint filtering
 * Access via: /wp-content/plugins/simple-teacher-dashboard/test-enforce-hint-filtering.php
 */

// Load WordPress
require_once('../../../wp-config.php');

// Include the dashboard class
require_once('simple-teacher-dashboard.php');

echo "<h1>Enforce Hint Filtering Test - FORCED WITH REAL DATA</h1>";
echo "<style>body{font-family:Arial;margin:20px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#f2f2f2;} .highlight{background:#ffffcc;}</style>";

echo "<div class='highlight'>";
echo "<h2>🔍 Testing with users who ACTUALLY have quiz attempts!</h2>";
echo "<p>The teacher dashboard was showing zeros because it's testing students with no quiz data.</p>";
echo "<p>This test forces the calculation with users who have real quiz attempts.</p>";
echo "</div>";

// Test users with quiz data (from your MySQL results)
$test_users = [
    6 => '20 attempts, 4 with enforce hint → Should show DIFFERENT averages',
    1 => '10 attempts, 4 with enforce hint → Should show DIFFERENT averages', 
    0 => '4 attempts, ALL with enforce hint → REAL should be 0%',
    10 => '2 attempts, 0 with enforce hint → Should show SAME averages',
    13 => '2 attempts, 0 with enforce hint → Should show SAME averages',
    25 => '1 attempt, 0 with enforce hint → Should show SAME averages',
    309 => '1 attempt, 1 with enforce hint → REAL should be 0%'
];

echo "<table>";
echo "<tr><th>User ID</th><th>Description</th><th>ALL Quizzes</th><th>REAL Quizzes Only</th><th>Difference</th></tr>";

// Create dashboard instance to access the method
$dashboard = new Simple_Teacher_Dashboard();

// Use reflection to access private method
$reflection = new ReflectionClass($dashboard);
$method = $reflection->getMethod('get_student_quiz_stats');
$method->setAccessible(true);

foreach ($test_users as $user_id => $description) {
    echo "<tr>";
    echo "<td><strong>$user_id</strong></td>";
    echo "<td>$description</td>";
    
    // Get quiz stats (this will log both ALL and REAL results)
    $stats = $method->invoke($dashboard, $user_id);
    
    echo "<td>";
    echo "Overall: {$stats['overall_success_rate']}%<br>";
    echo "Completed: {$stats['completed_only_rate']}%<br>";
    echo "Attempts: {$stats['total_attempts']}";
    echo "</td>";
    
    echo "<td colspan='2'>";
    echo "<em>Check debug.log for REAL QUIZZES ONLY results</em>";
    echo "</td>";
    
    echo "</tr>";
}

echo "</table>";

echo "<h2>Instructions:</h2>";
echo "<ol>";
echo "<li>Check <code>wp-content/debug.log</code> for detailed breakdown</li>";
echo "<li>Look for lines containing 'REAL QUIZZES ONLY' vs 'ALL QUIZZES'</li>";
echo "<li>Compare the success rates between filtered and unfiltered results</li>";
echo "</ol>";

echo "<h2>Expected Results:</h2>";
echo "<ul>";
echo "<li><strong>User 6:</strong> Should show lower averages for REAL QUIZZES (excludes 4 enforce hint attempts)</li>";
echo "<li><strong>User 0:</strong> Should show 0% for REAL QUIZZES (all attempts have enforce hint)</li>";
echo "<li><strong>Users 10,13,25:</strong> Should show identical results (no enforce hint attempts)</li>";
echo "</ul>";
?>
