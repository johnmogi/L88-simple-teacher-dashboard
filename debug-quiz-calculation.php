<?php
/**
 * Debug Quiz Calculation Issues
 * 
 * This script will help identify why quiz averages are showing incorrectly
 * Run this by accessing: /wp-content/plugins/simple-teacher-dashboard/debug-quiz-calculation.php?student_id=X
 */

// Include WordPress
$wp_config_path = dirname(__FILE__) . '/../../../../wp-config.php';
if (file_exists($wp_config_path)) {
    require_once($wp_config_path);
} else {
    die('WordPress not found. Please check the path.');
}

// Get student ID from URL parameter
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if ($student_id === 0) {
    echo "<h1>Quiz Calculation Debug Tool</h1>";
    echo "<p>Usage: Add ?student_id=X to the URL where X is the student's user ID</p>";
    
    // Show available students
    global $wpdb;
    $students = $wpdb->get_results("
        SELECT DISTINCT u.ID, u.display_name, u.user_email 
        FROM {$wpdb->users} u
        JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
        WHERE um.meta_key LIKE 'learndash_group_users_%'
        ORDER BY u.display_name
        LIMIT 20
    ");
    
    echo "<h2>Available Students (first 20):</h2>";
    echo "<ul>";
    foreach ($students as $student) {
        echo "<li><a href='?student_id={$student->ID}'>{$student->display_name} (ID: {$student->ID}) - {$student->user_email}</a></li>";
    }
    echo "</ul>";
    exit;
}

echo "<h1>Quiz Calculation Debug for Student ID: $student_id</h1>";

global $wpdb;

// Get student info
$student = get_user_by('ID', $student_id);
if (!$student) {
    die("Student not found with ID: $student_id");
}

echo "<h2>Student Information</h2>";
echo "<p><strong>Name:</strong> {$student->display_name}</p>";
echo "<p><strong>Email:</strong> {$student->user_email}</p>";
echo "<p><strong>Login:</strong> {$student->user_login}</p>";

echo "<h2>Method 1: Pro Quiz Statistic Tables</h2>";

// Check if pro quiz tables exist
$tables_exist = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}learndash_pro_quiz_statistic'");
if (!$tables_exist) {
    echo "<p style='color: red;'>❌ learndash_pro_quiz_statistic table does not exist!</p>";
} else {
    echo "<p style='color: green;'>✅ learndash_pro_quiz_statistic table exists</p>";
    
    // Get raw quiz data
    $raw_quiz_data = $wpdb->get_results($wpdb->prepare("
        SELECT 
            ref.statistic_ref_id,
            ref.quiz_post_id,
            ref.create_time,
            quiz_scores.earned_points,
            quiz_scores.total_questions,
            CASE 
                WHEN quiz_scores.total_questions > 0 
                THEN ROUND((quiz_scores.earned_points / quiz_scores.total_questions) * 100, 1)
                ELSE 0 
            END as percentage
        FROM {$wpdb->prefix}learndash_pro_quiz_statistic_ref ref
        INNER JOIN (
            SELECT 
                statistic_ref_id,
                SUM(points) as earned_points,
                COUNT(*) as total_questions
            FROM {$wpdb->prefix}learndash_pro_quiz_statistic
            GROUP BY statistic_ref_id
            HAVING COUNT(*) > 0
        ) quiz_scores ON ref.statistic_ref_id = quiz_scores.statistic_ref_id
        WHERE ref.user_id = %d
        ORDER BY ref.create_time DESC
    ", $student_id));
    
    if (empty($raw_quiz_data)) {
        echo "<p style='color: orange;'>⚠️ No quiz data found in pro_quiz_statistic tables</p>";
    } else {
        echo "<h3>Raw Quiz Attempts:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Ref ID</th><th>Quiz Post ID</th><th>Date</th><th>Earned Points</th><th>Total Questions</th><th>Percentage</th></tr>";
        
        $total_percentage = 0;
        $completed_percentage = 0;
        $total_attempts = 0;
        $completed_attempts = 0;
        
        foreach ($raw_quiz_data as $attempt) {
            $total_attempts++;
            $total_percentage += $attempt->percentage;
            
            if ($attempt->earned_points > 0) {
                $completed_attempts++;
                $completed_percentage += $attempt->percentage;
            }
            
            echo "<tr>";
            echo "<td>{$attempt->statistic_ref_id}</td>";
            echo "<td>{$attempt->quiz_post_id}</td>";
            echo "<td>{$attempt->create_time}</td>";
            echo "<td>{$attempt->earned_points}</td>";
            echo "<td>{$attempt->total_questions}</td>";
            echo "<td style='font-weight: bold;'>{$attempt->percentage}%</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        $overall_avg = $total_attempts > 0 ? round($total_percentage / $total_attempts, 1) : 0;
        $completed_avg = $completed_attempts > 0 ? round($completed_percentage / $completed_attempts, 1) : 0;
        
        echo "<h3>Calculated Averages:</h3>";
        echo "<p><strong>Total Attempts:</strong> $total_attempts</p>";
        echo "<p><strong>Completed Attempts (with points > 0):</strong> $completed_attempts</p>";
        echo "<p><strong>Overall Average:</strong> {$overall_avg}%</p>";
        echo "<p><strong>Completed Only Average:</strong> {$completed_avg}%</p>";
    }
}

echo "<h2>Method 2: LearnDash User Activity Table</h2>";

$activity_data = $wpdb->get_results($wpdb->prepare("
    SELECT 
        activity_id,
        post_id,
        activity_type,
        activity_status,
        activity_started,
        activity_completed,
        activity_updated,
        activity_meta
    FROM {$wpdb->prefix}learndash_user_activity
    WHERE user_id = %d AND activity_type = 'quiz'
    ORDER BY activity_updated DESC
", $student_id));

if (empty($activity_data)) {
    echo "<p style='color: orange;'>⚠️ No quiz activity data found</p>";
} else {
    echo "<h3>LearnDash Activity Records:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Activity ID</th><th>Post ID</th><th>Status</th><th>Started</th><th>Completed</th><th>Meta Data</th></tr>";
    
    $activity_total = 0;
    $activity_completed = 0;
    $activity_count = 0;
    $activity_completed_count = 0;
    
    foreach ($activity_data as $activity) {
        $meta = maybe_unserialize($activity->activity_meta);
        $percentage = isset($meta['percentage']) ? $meta['percentage'] : 'N/A';
        
        if (is_numeric($percentage)) {
            $activity_count++;
            $activity_total += $percentage;
            
            if ($percentage > 0) {
                $activity_completed_count++;
                $activity_completed += $percentage;
            }
        }
        
        echo "<tr>";
        echo "<td>{$activity->activity_id}</td>";
        echo "<td>{$activity->post_id}</td>";
        echo "<td>{$activity->activity_status}</td>";
        echo "<td>{$activity->activity_started}</td>";
        echo "<td>{$activity->activity_completed}</td>";
        echo "<td><pre>" . print_r($meta, true) . "</pre></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    $activity_overall_avg = $activity_count > 0 ? round($activity_total / $activity_count, 1) : 0;
    $activity_completed_avg = $activity_completed_count > 0 ? round($activity_completed / $activity_completed_count, 1) : 0;
    
    echo "<h3>Activity-Based Averages:</h3>";
    echo "<p><strong>Total Records:</strong> $activity_count</p>";
    echo "<p><strong>Completed Records (percentage > 0):</strong> $activity_completed_count</p>";
    echo "<p><strong>Overall Average:</strong> {$activity_overall_avg}%</p>";
    echo "<p><strong>Completed Only Average:</strong> {$activity_completed_avg}%</p>";
}

echo "<h2>Current Dashboard Method Result</h2>";

// Test the current method
class Debug_Dashboard {
    private function get_student_quiz_stats($student_id) {
        global $wpdb;
        
        // Method 1: Try to get quiz scores from pro_quiz_statistic tables (most accurate)
        $pro_quiz_query = "
            SELECT 
                COUNT(ref.statistic_ref_id) as total_attempts,
                COUNT(DISTINCT ref.quiz_post_id) as unique_quizzes,
                COALESCE(ROUND(AVG(
                    CASE 
                        WHEN quiz_scores.total_questions > 0 THEN (quiz_scores.earned_points / quiz_scores.total_questions) * 100
                        ELSE 0
                    END
                ), 1), 0) as overall_success_rate,
                COALESCE(ROUND(AVG(
                    CASE 
                        WHEN quiz_scores.total_questions > 0 AND quiz_scores.earned_points > 0 
                        THEN (quiz_scores.earned_points / quiz_scores.total_questions) * 100
                        ELSE NULL
                    END
                ), 1), 0) as completed_only_rate
            FROM {$wpdb->prefix}learndash_pro_quiz_statistic_ref ref
            INNER JOIN (
                SELECT 
                    statistic_ref_id,
                    SUM(points) as earned_points,
                    COUNT(*) as total_questions
                FROM {$wpdb->prefix}learndash_pro_quiz_statistic
                GROUP BY statistic_ref_id
                HAVING COUNT(*) > 0
            ) quiz_scores ON ref.statistic_ref_id = quiz_scores.statistic_ref_id
            WHERE ref.user_id = %d
        ";
        
        $pro_quiz_result = $wpdb->get_row($wpdb->prepare($pro_quiz_query, $student_id), ARRAY_A);
        
        // If we have data from pro_quiz_statistic, use it
        if ($pro_quiz_result && $pro_quiz_result['total_attempts'] > 0) {
            return $pro_quiz_result;
        }
        
        // Method 2: Fallback to learndash_user_activity table
        $activity_scores = $wpdb->get_results($wpdb->prepare("
            SELECT activity_meta
            FROM {$wpdb->prefix}learndash_user_activity
            WHERE user_id = %d AND activity_type = 'quiz' AND activity_status = 1
        ", $student_id));
        
        if (count($activity_scores) > 0) {
            $total_percentage = 0;
            $valid_scores = 0;
            
            foreach ($activity_scores as $score) {
                $meta = maybe_unserialize($score->activity_meta);
                if (isset($meta['percentage']) && is_numeric($meta['percentage'])) {
                    $total_percentage += $meta['percentage'];
                    $valid_scores++;
                }
            }
            
            if ($valid_scores > 0) {
                $average = round($total_percentage / $valid_scores, 1);
                return array(
                    'total_attempts' => $valid_scores,
                    'unique_quizzes' => $valid_scores,
                    'overall_success_rate' => $average,
                    'completed_only_rate' => $average
                );
            }
        }
        
        return array(
            'total_attempts' => 0,
            'unique_quizzes' => 0,
            'overall_success_rate' => 0,
            'completed_only_rate' => 0
        );
    }
    
    public function test_student($student_id) {
        return $this->get_student_quiz_stats($student_id);
    }
}

$debug_dashboard = new Debug_Dashboard();
$current_result = $debug_dashboard->test_student($student_id);

echo "<h3>Current Dashboard Method Returns:</h3>";
echo "<pre>" . print_r($current_result, true) . "</pre>";

echo "<h2>Recommendations</h2>";
echo "<div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #2271b1;'>";
echo "<h3>Issues Found:</h3>";
echo "<ul>";

if (empty($raw_quiz_data) && empty($activity_data)) {
    echo "<li>❌ <strong>No quiz data found at all</strong> - Student may not have taken any quizzes</li>";
} else {
    if (!empty($raw_quiz_data)) {
        echo "<li>✅ Pro quiz data exists and should be used</li>";
        if ($completed_avg != $current_result['completed_only_rate']) {
            echo "<li>❌ <strong>Calculation mismatch:</strong> Manual calculation shows {$completed_avg}% but dashboard shows {$current_result['completed_only_rate']}%</li>";
        }
    }
    
    if (!empty($activity_data)) {
        echo "<li>✅ Activity data exists as fallback</li>";
    }
}

echo "</ul>";

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>If you see calculation mismatches, the SQL query in the dashboard needs to be fixed</li>";
echo "<li>Add debug logging to see exactly what data is being processed</li>";
echo "<li>Consider using the improved version with better error handling</li>";
echo "<li>Test with multiple students to identify patterns</li>";
echo "</ol>";
echo "</div>";

echo "<p><small>Debug completed at " . date('Y-m-d H:i:s') . "</small></p>";
?>
