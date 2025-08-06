<?php
// Test the new quiz calculation approach
require_once('wp-config.php');

global $wpdb;

// Test the new quiz calculation for a specific user
$student_id = 1; // Test with user ID 1

echo "=== TESTING NEW QUIZ CALCULATION FOR USER $student_id ===\n";

// Get actual quiz scores from pro_quiz_statistic tables
$query = "
    SELECT 
        COUNT(ref.statistic_ref_id) as total_attempts,
        COUNT(DISTINCT ref.quiz_post_id) as unique_quizzes,
        SUM(CASE WHEN ref.statistic_ref_id IN (
            SELECT statistic_ref_id FROM {$wpdb->prefix}learndash_pro_quiz_statistic 
            WHERE statistic_ref_id = ref.statistic_ref_id AND points > 0
        ) THEN 1 ELSE 0 END) as passed_attempts,
        COALESCE(ROUND(AVG(
            CASE 
                WHEN quiz_scores.total_points > 0 THEN (quiz_scores.earned_points / quiz_scores.total_points) * 100
                ELSE 0
            END
        ), 1), 0) as overall_success_rate,
        COALESCE(ROUND(AVG(
            CASE 
                WHEN quiz_scores.total_points > 0 AND quiz_scores.earned_points > 0 
                THEN (quiz_scores.earned_points / quiz_scores.total_points) * 100
                ELSE NULL
            END
        ), 1), 0) as completed_only_rate
    FROM {$wpdb->prefix}learndash_pro_quiz_statistic_ref ref
    LEFT JOIN (
        SELECT 
            statistic_ref_id,
            SUM(points) as earned_points,
            COUNT(*) as total_questions,
            COUNT(*) as total_points
        FROM {$wpdb->prefix}learndash_pro_quiz_statistic
        GROUP BY statistic_ref_id
    ) quiz_scores ON ref.statistic_ref_id = quiz_scores.statistic_ref_id
    WHERE ref.user_id = %d
";

$result = $wpdb->get_row($wpdb->prepare($query, $student_id), ARRAY_A);

echo "Results:\n";
print_r($result);

// Let's also check individual quiz attempts to see the actual data
echo "\n=== INDIVIDUAL QUIZ ATTEMPTS ===\n";
$individual_query = "
    SELECT 
        ref.quiz_post_id,
        p.post_title as quiz_name,
        quiz_scores.earned_points,
        quiz_scores.total_questions,
        CASE 
            WHEN quiz_scores.total_questions > 0 THEN (quiz_scores.earned_points / quiz_scores.total_questions) * 100
            ELSE 0
        END as percentage
    FROM {$wpdb->prefix}learndash_pro_quiz_statistic_ref ref
    LEFT JOIN (
        SELECT 
            statistic_ref_id,
            SUM(points) as earned_points,
            COUNT(*) as total_questions
        FROM {$wpdb->prefix}learndash_pro_quiz_statistic
        GROUP BY statistic_ref_id
    ) quiz_scores ON ref.statistic_ref_id = quiz_scores.statistic_ref_id
    LEFT JOIN {$wpdb->posts} p ON p.ID = ref.quiz_post_id
    WHERE ref.user_id = %d
    ORDER BY ref.create_time
";

$individual_results = $wpdb->get_results($wpdb->prepare($individual_query, $student_id));

foreach ($individual_results as $quiz) {
    echo "Quiz: {$quiz->quiz_name}\n";
    echo "  Earned Points: {$quiz->earned_points}\n";
    echo "  Total Questions: {$quiz->total_questions}\n";
    echo "  Percentage: {$quiz->percentage}%\n\n";
}

// Let's also check what the old method would have returned
echo "\n=== OLD METHOD COMPARISON ===\n";
$old_query = "
    SELECT 
        COUNT(ua.activity_id) as total_attempts,
        COUNT(DISTINCT ua.post_id) as unique_quizzes,
        SUM(CASE WHEN ua.activity_status = 1 THEN 1 ELSE 0 END) as passed_attempts,
        ROUND(AVG(
            CASE 
                WHEN ua.activity_status = 1 THEN 100
                ELSE 0
            END
        ), 1) as overall_success_rate
    FROM {$wpdb->prefix}learndash_user_activity ua
    WHERE ua.user_id = %d
    AND ua.activity_type = 'quiz'
    AND ua.activity_completed > 0
";

$old_result = $wpdb->get_row($wpdb->prepare($old_query, $student_id), ARRAY_A);
echo "Old method results:\n";
print_r($old_result);
?>
