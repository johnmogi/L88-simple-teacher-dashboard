<?php
/**
 * SQL Debug Script for Quiz Average Issues
 * Run this by visiting: /wp-content/plugins/simple-teacher-dashboard/sql-debug.php
 */

// Include WordPress
require_once(dirname(__FILE__) . '/../../../../wp-config.php');

echo "<h1>SQL Debug for Quiz Averages</h1>";
echo "<style>
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
.success { color: green; }
.error { color: red; }
.info { color: blue; }
pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
</style>";

global $wpdb;

// 1. Check if required tables exist
echo "<h2>1. Database Tables Check</h2>";
$tables = [
    'learndash_pro_quiz_statistic' => 'Quiz statistics (detailed answers)',
    'learndash_pro_quiz_statistic_ref' => 'Quiz references (attempts)',
    'learndash_user_activity' => 'User activity (general)',
    'school_students' => 'School students',
    'school_classes' => 'School classes'
];

foreach ($tables as $table => $description) {
    $full_table = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'");
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table");
        echo "<p class='success'>✅ $full_table ($description): $count records</p>";
    } else {
        echo "<p class='error'>❌ $full_table ($description): Table does not exist</p>";
    }
}

// 2. Check for students with quiz data
echo "<h2>2. Students with Quiz Data</h2>";

// Check pro_quiz_statistic_ref table
$quiz_students = $wpdb->get_results("
    SELECT u.ID, u.display_name, u.user_email,
           COUNT(DISTINCT ref.statistic_ref_id) as attempts,
           COUNT(DISTINCT ref.quiz_post_id) as unique_quizzes,
           MAX(ref.create_time) as last_attempt
    FROM {$wpdb->users} u
    INNER JOIN {$wpdb->prefix}learndash_pro_quiz_statistic_ref ref ON u.ID = ref.user_id
    GROUP BY u.ID
    ORDER BY attempts DESC
    LIMIT 10
");

if (empty($quiz_students)) {
    echo "<p class='error'>❌ No students found with quiz data in pro_quiz_statistic_ref table</p>";
} else {
    echo "<p class='success'>✅ Found " . count($quiz_students) . " students with quiz data:</p>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Attempts</th><th>Unique Quizzes</th><th>Last Attempt</th></tr>";
    foreach ($quiz_students as $student) {
        echo "<tr>";
        echo "<td>{$student->ID}</td>";
        echo "<td>{$student->display_name}</td>";
        echo "<td>{$student->user_email}</td>";
        echo "<td>{$student->attempts}</td>";
        echo "<td>{$student->unique_quizzes}</td>";
        echo "<td>{$student->last_attempt}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. Check learndash_user_activity as fallback
echo "<h2>3. Alternative: User Activity Data</h2>";
$activity_students = $wpdb->get_results("
    SELECT u.ID, u.display_name, u.user_email,
           COUNT(ua.activity_id) as quiz_activities,
           MAX(ua.activity_updated) as last_activity
    FROM {$wpdb->users} u
    INNER JOIN {$wpdb->prefix}learndash_user_activity ua ON u.ID = ua.user_id
    WHERE ua.activity_type = 'quiz' AND ua.activity_status = 1
    GROUP BY u.ID
    ORDER BY quiz_activities DESC
    LIMIT 10
");

if (empty($activity_students)) {
    echo "<p class='error'>❌ No students found with quiz activity in learndash_user_activity table</p>";
} else {
    echo "<p class='info'>ℹ️ Found " . count($activity_students) . " students with quiz activity:</p>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Quiz Activities</th><th>Last Activity</th></tr>";
    foreach ($activity_students as $student) {
        echo "<tr>";
        echo "<td>{$student->ID}</td>";
        echo "<td>{$student->display_name}</td>";
        echo "<td>{$student->user_email}</td>";
        echo "<td>{$student->quiz_activities}</td>";
        echo "<td>{$student->last_activity}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 4. Sample quiz data for first student if available
$test_student_id = null;
if (!empty($quiz_students)) {
    $test_student_id = $quiz_students[0]->ID;
} elseif (!empty($activity_students)) {
    $test_student_id = $activity_students[0]->ID;
}

if ($test_student_id) {
    $test_student = get_user_by('ID', $test_student_id);
    echo "<h2>4. Sample Data for Student: {$test_student->display_name} (ID: $test_student_id)</h2>";
    
    // Show detailed quiz data
    echo "<h3>Pro Quiz Statistic Data:</h3>";
    $detailed_quiz_data = $wpdb->get_results($wpdb->prepare("
        SELECT ref.statistic_ref_id, ref.quiz_post_id, ref.create_time,
               SUM(stat.points) as earned_points,
               COUNT(stat.statistic_id) as total_questions,
               ROUND((SUM(stat.points) / COUNT(stat.statistic_id)) * 100, 2) as percentage
        FROM {$wpdb->prefix}learndash_pro_quiz_statistic_ref ref
        LEFT JOIN {$wpdb->prefix}learndash_pro_quiz_statistic stat ON ref.statistic_ref_id = stat.statistic_ref_id
        WHERE ref.user_id = %d
        GROUP BY ref.statistic_ref_id
        ORDER BY ref.create_time DESC
        LIMIT 5
    ", $test_student_id));
    
    if (!empty($detailed_quiz_data)) {
        echo "<table>";
        echo "<tr><th>Ref ID</th><th>Quiz ID</th><th>Date</th><th>Points</th><th>Questions</th><th>Percentage</th></tr>";
        foreach ($detailed_quiz_data as $quiz) {
            echo "<tr>";
            echo "<td>{$quiz->statistic_ref_id}</td>";
            echo "<td>{$quiz->quiz_post_id}</td>";
            echo "<td>{$quiz->create_time}</td>";
            echo "<td>{$quiz->earned_points}</td>";
            echo "<td>{$quiz->total_questions}</td>";
            echo "<td>{$quiz->percentage}%</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>No detailed quiz data found</p>";
    }
    
    // Show activity data
    echo "<h3>User Activity Data:</h3>";
    $activity_data = $wpdb->get_results($wpdb->prepare("
        SELECT post_id, activity_meta, activity_updated, activity_status
        FROM {$wpdb->prefix}learndash_user_activity
        WHERE user_id = %d AND activity_type = 'quiz'
        ORDER BY activity_updated DESC
        LIMIT 5
    ", $test_student_id));
    
    if (!empty($activity_data)) {
        echo "<table>";
        echo "<tr><th>Quiz ID</th><th>Status</th><th>Date</th><th>Meta (percentage)</th></tr>";
        foreach ($activity_data as $activity) {
            $meta = maybe_unserialize($activity->activity_meta);
            $percentage = isset($meta['percentage']) ? $meta['percentage'] : 'N/A';
            echo "<tr>";
            echo "<td>{$activity->post_id}</td>";
            echo "<td>{$activity->activity_status}</td>";
            echo "<td>{$activity->activity_updated}</td>";
            echo "<td>{$percentage}%</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>No activity data found</p>";
    }
    
    // Test the actual calculation method
    echo "<h3>Simple Teacher Dashboard Calculation Test:</h3>";
    if (class_exists('Simple_Teacher_Dashboard')) {
        try {
            $dashboard = new Simple_Teacher_Dashboard();
            $reflection = new ReflectionClass($dashboard);
            $method = $reflection->getMethod('get_student_quiz_stats');
            $method->setAccessible(true);
            
            $result = $method->invoke($dashboard, $test_student_id);
            
            echo "<table>";
            echo "<tr><th>Metric</th><th>Value</th></tr>";
            echo "<tr><td>Total Attempts</td><td>{$result['total_attempts']}</td></tr>";
            echo "<tr><td>Unique Quizzes</td><td>{$result['unique_quizzes']}</td></tr>";
            echo "<tr><td>Overall Success Rate</td><td>{$result['overall_success_rate']}%</td></tr>";
            echo "<tr><td>Completed Only Rate</td><td>{$result['completed_only_rate']}%</td></tr>";
            echo "</table>";
            
            if ($result['total_attempts'] == 0) {
                echo "<p class='error'>❌ Method returns zero attempts - this is the problem!</p>";
            } else {
                echo "<p class='success'>✅ Method returns data successfully</p>";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error testing method: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p class='error'>❌ Simple_Teacher_Dashboard class not found</p>";
    }
}

// 5. Check for any recent quiz attempts
echo "<h2>5. Recent Quiz Activity (Last 10)</h2>";
$recent_attempts = $wpdb->get_results("
    SELECT ref.user_id, u.display_name, ref.quiz_post_id, p.post_title, ref.create_time
    FROM {$wpdb->prefix}learndash_pro_quiz_statistic_ref ref
    LEFT JOIN {$wpdb->users} u ON ref.user_id = u.ID
    LEFT JOIN {$wpdb->posts} p ON ref.quiz_post_id = p.ID
    ORDER BY ref.create_time DESC
    LIMIT 10
");

if (!empty($recent_attempts)) {
    echo "<table>";
    echo "<tr><th>User ID</th><th>User Name</th><th>Quiz ID</th><th>Quiz Title</th><th>Date</th></tr>";
    foreach ($recent_attempts as $attempt) {
        echo "<tr>";
        echo "<td>{$attempt->user_id}</td>";
        echo "<td>{$attempt->display_name}</td>";
        echo "<td>{$attempt->quiz_post_id}</td>";
        echo "<td>{$attempt->post_title}</td>";
        echo "<td>{$attempt->create_time}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>❌ No recent quiz attempts found</p>";
}

// 6. Summary and recommendations
echo "<h2>6. Summary and Recommendations</h2>";

if (empty($quiz_students) && empty($activity_students)) {
    echo "<div style='background: #ffeeee; padding: 15px; border: 1px solid #ff0000; border-radius: 5px;'>";
    echo "<h3 style='color: red;'>🚨 PROBLEM IDENTIFIED</h3>";
    echo "<p><strong>No students have quiz data in the database.</strong></p>";
    echo "<p>This explains why the quiz averages are empty. Possible causes:</p>";
    echo "<ul>";
    echo "<li>Students haven't taken any quizzes yet</li>";
    echo "<li>Quiz data is stored in a different format or table</li>";
    echo "<li>LearnDash quiz tracking is not working properly</li>";
    echo "<li>Database tables are missing or corrupted</li>";
    echo "</ul>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ol>";
    echo "<li>Check if students can actually take quizzes</li>";
    echo "<li>Verify LearnDash quiz settings</li>";
    echo "<li>Test taking a quiz as a student</li>";
    echo "<li>Check LearnDash logs for errors</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #eeffee; padding: 15px; border: 1px solid #00aa00; border-radius: 5px;'>";
    echo "<h3 style='color: green;'>✅ QUIZ DATA FOUND</h3>";
    echo "<p>Students do have quiz data in the database. The issue might be:</p>";
    echo "<ul>";
    echo "<li>The Simple Teacher Dashboard calculation method has a bug</li>";
    echo "<li>The dashboard is looking at the wrong students</li>";
    echo "<li>There's a filtering issue in the teacher dashboard</li>";
    echo "</ul>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ol>";
    echo "<li>Test the teacher dashboard with the students shown above</li>";
    echo "<li>Check if these students are assigned to the right teacher/group</li>";
    echo "<li>Debug the get_student_quiz_stats method further</li>";
    echo "</ol>";
    echo "</div>";
}

echo "<p><strong>Generated at:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
