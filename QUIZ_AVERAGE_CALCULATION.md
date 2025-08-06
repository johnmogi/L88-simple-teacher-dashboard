# Quiz Average Calculation Documentation

## 1. Average Calculation Logic

The quiz average is calculated using a multi-layered approach to ensure accuracy and handle different data scenarios. Here's how it works:

### Primary Method: Using pro_quiz_statistic Tables

```sql
SELECT 
    COUNT(ref.statistic_ref_id) as total_attempts,
    COUNT(DISTINCT ref.quiz_post_id) as unique_quizzes,
    COALESCE(ROUND(AVG(
        CASE 
            WHEN quiz_scores.total_questions > 0 
            THEN (quiz_scores.earned_points / quiz_scores.total_questions) * 100
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
GROUP BY ref.user_id
```

### Fallback Method: Using learndash_user_activity Table

If no results are found in the primary method, the system falls back to using the `learndash_user_activity` table:

```sql
SELECT 
    COUNT(ua.activity_id) as total_attempts,
    COUNT(DISTINCT ua.post_id) as unique_quizzes,
    ROUND(AVG(
        CASE 
            WHEN ua.activity_status = 1 THEN 100
            ELSE 0
        END
    ), 1) as success_rate
FROM {$wpdb->prefix}learndash_user_activity ua
WHERE ua.user_id = %d
AND ua.activity_type = 'quiz'
AND ua.activity_completed > 0
```

### Handling Empty Results

If both methods return no results, the system returns zeros to indicate "אין נתונים" (No data).

## 2. Key Functions and Their Locations

### 1. `get_student_quiz_stats($student_id)`
- **Location**: `simple-teacher-dashboard.php`
- **Purpose**: Main function that calculates quiz statistics for a student
- **Parameters**:
  - `$student_id` (int): The ID of the student
- **Returns**: Array containing:
  - `total_attempts`: Total number of quiz attempts
  - `unique_quizzes`: Number of distinct quizzes attempted
  - `overall_success_rate`: Average success rate across all attempts
  - `completed_only_rate`: Average success rate for completed attempts only

### 2. `get_dashboard_javascript($groups)`
- **Location**: `simple-teacher-dashboard.php`
- **Purpose**: Prepares quiz statistics data for JavaScript rendering
- **Key Operations**:
  - Retrieves quiz stats for each student
  - Formats the data for display in the dashboard
  - Includes success rate calculations

### 3. `formatQuizAverage(successRate)` (JavaScript)
- **Location**: Inline JavaScript in `get_dashboard_javascript()`
- **Purpose**: Formats the quiz average for display with appropriate styling
- **Parameters**:
  - `successRate`: The calculated success rate percentage
- **Features**:
  - Applies different CSS classes based on score ranges
  - Handles "No data" cases
  - Formats the number to one decimal place

## Calculation Examples

. Average Calculation Logic
Primary Method: Uses pro_quiz_statistic tables for precise calculation
Fallback Method: Uses learndash_user_activity table if primary method returns no results
Handling Empty Results: Returns zeros to indicate "אין נתונים" (No data)
2. Key Functions Documented
get_student_quiz_stats()
: Main calculation function
get_dashboard_javascript()
: Prepares data for display
formatQuizAverage(): Frontend formatting function
3. Practical Examples
Student with multiple quiz attempts
Student with incomplete attempts
No quiz data scenario
4. Data Flow
Step-by-step explanation of how the data moves through the system
The documentation is saved in your plugin directory as 
QUIZ_AVERAGE_CALCULATION.md
. It includes SQL queries, code snippets, and explanations that make it easy to understand how the quiz averages are calculated and displayed in the dashboard.

### Example 1: Student with Multiple Quiz Attempts
- **Quiz 1**: 8/10 correct (80%)
- **Quiz 2**: 15/20 correct (75%)
- **Calculation**: (80 + 75) / 2 = 77.5%
- **Display**: 77.5% (styled based on the score range)

### Example 2: Student with Incomplete Attempts
- **Quiz 1**: 0/5 (incomplete)
- **Quiz 2**: 9/10 (completed)
- **Calculation (overall)**: (0 + 90) / 2 = 45%
- **Calculation (completed only)**: 90%
- **Display**: Shows both values in the dashboard

### Example 3: No Quiz Data
- **No attempts found**
- **Display**: "אין נתונים" (No data)

## Data Flow
1. Dashboard loads and calls `render_dashboard()`
2. For each student, `get_student_quiz_stats()` is called
3. The function tries the primary method first, then falls back if needed
4. Results are formatted and passed to JavaScript
5. The frontend displays the formatted results with appropriate styling

## Important Notes
- The system handles edge cases like division by zero
- Empty quiz attempts are filtered out
- The calculation respects the RTL layout for Hebrew display
- All database queries use prepared statements for security
