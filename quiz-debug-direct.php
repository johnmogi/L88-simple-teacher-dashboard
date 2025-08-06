<?php
/**
 * Direct Quiz Debug Script
 * Run this by visiting: /wp-content/plugins/simple-teacher-dashboard/quiz-debug-direct.php
 */

// Include WordPress
require_once(dirname(__FILE__) . '/../../../../wp-config.php');

echo "<h1>Quiz Average Debug Tool</h1>";
echo "<style>
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
.error { color: red; }
.success { color: green; }
.info { color: blue; }
</style>";

global $wpdb;

// Check if required tables exist
echo "<h2>Database Table Check</h2>";
$tables_to_check = [
    $wpdb->prefix . 'learndash_pro_quiz_statistic',
    $wpdb->prefix . 'learndash_pro_quiz_statistic_ref',
    $wpdb->prefix . 'learndash_user_activity',
    $wpdb->prefix . 'school_students',
    $wpdb->prefix . 'school_classes'
];

foreach ($tables_to_check as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        echo "<p class='success'>✅ $table exists with $count records</p>";
    } else {
        echo "<p class='error'>❌ $table does not exist</p>";
    }
}

// Get students with quiz activity
echo "<h2>Students with Quiz Activity</h2>";
$students_with_quizzes = $wpdb->get_results("
    SELECT DISTINCT u.ID, u.display_name, u.user_email,
           COUNT(DISTINCT ref.statistic_ref_id) as quiz_attempts,
           COUNT(DISTINCT ref.quiz_post_id) as unique_quizzes
    FROM {$wpdb->users} u
    LEFT JOIN {$wpdb->prefix}learndash_pro_quiz_statistic_ref ref ON u.ID = ref.user_id
    WHERE ref.user_id IS NOT NULL
    GROUP BY u.ID
    ORDER BY quiz_attempts DESC
    LIMIT 10
");

if (empty($students_with_quizzes)) {
    echo "<p class='error'>❌ No students found with quiz activity in pro_quiz_statistic tables</p>";
    
    // Try alternative method
    echo "<h3>Checking learndash_user_activity table...</h3>";
    $students_activity = $wpdb->get_results("
        SELECT DISTINCT u.ID, u.display_name, u.user_email,
               COUNT(ua.activity_id) as activity_count
        FROM {$wpdb->users} u
        LEFT JOIN {$wpdb->prefix}learndash_user_activity ua ON u.ID = ua.user_id
        WHERE ua.activity_type = 'quiz' AND ua.activity_status = 1
        GROUP BY u.ID
        ORDER BY activity_count DESC
        LIMIT 10
    ");
    
    if (empty($students_activity)) {
        echo "<p class='error'>❌ No students found with quiz activity in learndash_user_activity table either</p>";
    } else {
        echo "<table>";
        echo "<tr><th>User ID</th><th>Name</th><th>Email</th><th>Quiz Activities</th><th>Test</th></tr>";
        foreach ($students_activity as $student) {
            echo "<tr>";
            echo "<td>{$student->ID}</td>";
            echo "<td>{$student->display_name}</td>";
            echo "<td>{$student->user_email}</td>";
            echo "<td>{$student->activity_count}</td>";
            echo "<td><a href='?test_student={$student->ID}'>Test Quiz Calculation</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<table>";
    echo "<tr><th>User ID</th><th>Name</th><th>Email</th><th>Quiz Attempts</th><th>Unique Quizzes</th><th>Test</th></tr>";
    foreach ($students_with_quizzes as $student) {
        echo "<tr>";
        echo "<td>{$student->ID}</td>";
        echo "<td>{$student->display_name}</td>";
        echo "<td>{$student->user_email}</td>";
        echo "<td>{$student->quiz_attempts}</td>";
        echo "<td>{$student->unique_quizzes}</td>";
        echo "<td><a href='?test_student={$student->ID}'>Test Quiz Calculation</a></td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test specific student if requested
if (isset($_GET['test_student']) && intval($_GET['test_student']) > 0) {
    $student_id = intval($_GET['test_student']);
    $student = get_user_by('ID', $student_id);
    
    echo "<h2>Testing Quiz Calculation for Student: {$student->display_name} (ID: $student_id)</h2>";
    
    // Include the Simple Teacher Dashboard class
    if (file_exists(dirname(__FILE__) . '/simple-teacher-dashboard.php')) {
        require_once(dirname(__FILE__) . '/simple-teacher-dashboard.php');
        
        // Create instance and test the method
        $dashboard = new Simple_Teacher_Dashboard();
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($dashboard);
        $method = $reflection->getMethod('get_student_quiz_stats');
        $method->setAccessible(true);
        
        $result = $method->invoke($dashboard, $student_id);
        
        echo "<h3>Quiz Calculation Result:</h3>";
        echo "<table>";
        echo "<tr><th>Metric</th><th>Value</th></tr>";
        echo "<tr><td>Total Attempts</td><td>{$result['total_attempts']}</td></tr>";
        echo "<tr><td>Unique Quizzes</td><td>{$result['unique_quizzes']}</td></tr>";
        echo "<tr><td>Overall Success Rate</td><td>{$result['overall_success_rate']}%</td></tr>";
        echo "<tr><td>Completed Only Rate</td><td>{$result['completed_only_rate']}%</td></tr>";
        echo "</table>";
        
        // Show raw data
        echo "<h3>Raw Quiz Data (Pro Quiz Statistic):</h3>";
        $raw_data = $wpdb->get_results($wpdb->prepare("
            SELECT ref.quiz_post_id, ref.create_time,
                   SUM(stat.points) as earned_points,
                   COUNT(stat.statistic_id) as total_questions
            FROM {$wpdb->prefix}learndash_pro_quiz_statistic_ref ref
            LEFT JOIN {$wpdb->prefix}learndash_pro_quiz_statistic stat ON ref.statistic_ref_id = stat.statistic_ref_id
            WHERE ref.user_id = %d
            GROUP BY ref.statistic_ref_id
            ORDER BY ref.create_time DESC
        ", $student_id));
        
        if (empty($raw_data)) {
            echo "<p class='error'>No raw quiz data found in pro_quiz_statistic tables</p>";
            
            // Try learndash_user_activity
            echo "<h3>Raw Activity Data:</h3>";
            $activity_data = $wpdb->get_results($wpdb->prepare("
                SELECT post_id, activity_meta, activity_updated, activity_status
                FROM {$wpdb->prefix}learndash_user_activity
                WHERE user_id = %d AND activity_type = 'quiz'
                ORDER BY activity_updated DESC
            ", $student_id));
            
            if (empty($activity_data)) {
                echo "<p class='error'>No activity data found either</p>";
            } else {
                echo "<table>";
                echo "<tr><th>Quiz ID</th><th>Status</th><th>Date</th><th>Meta Data</th></tr>";
                foreach ($activity_data as $activity) {
                    $meta = maybe_unserialize($activity->activity_meta);
                    $percentage = isset($meta['percentage']) ? $meta['percentage'] : 'N/A';
                    echo "<tr>";
                    echo "<td>{$activity->post_id}</td>";
                    echo "<td>{$activity->activity_status}</td>";
                    echo "<td>{$activity->activity_updated}</td>";
                    echo "<td>Percentage: $percentage</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        } else {
            echo "<table>";
            echo "<tr><th>Quiz ID</th><th>Date</th><th>Earned Points</th><th>Total Questions</th><th>Percentage</th></tr>";
            foreach ($raw_data as $quiz) {
                $percentage = $quiz->total_questions > 0 ? round(($quiz->earned_points / $quiz->total_questions) * 100, 1) : 0;
                echo "<tr>";
                echo "<td>{$quiz->quiz_post_id}</td>";
                echo "<td>{$quiz->create_time}</td>";
                echo "<td>{$quiz->earned_points}</td>";
                echo "<td>{$quiz->total_questions}</td>";
                echo "<td>{$percentage}%</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p class='error'>Could not load Simple Teacher Dashboard class</p>";
    }
}

// Show recent error logs related to quiz calculation
echo "<h2>Recent Error Logs (Quiz Related)</h2>";
$log_file = WP_CONTENT_DIR . '/debug.log';
if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $lines = explode("\n", $log_content);
    $quiz_lines = array_filter($lines, function($line) {
        return strpos($line, '[QUIZ DEBUG]') !== false;
    });
    
    if (empty($quiz_lines)) {
        echo "<p class='info'>No recent quiz debug logs found</p>";
    } else {
        echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow-y: scroll;'>";
        echo implode("\n", array_slice($quiz_lines, -20)); // Show last 20 lines
        echo "</pre>";
    }
} else {
    echo "<p class='error'>Debug log file not found</p>";
}

echo "<p><a href='?'>← Back to Student List</a></p>";
?>
