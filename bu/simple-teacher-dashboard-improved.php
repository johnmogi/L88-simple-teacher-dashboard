<?php
/**
 * Plugin Name: Simple Teacher Dashboard - Improved
 * Description: A simplified teacher dashboard with improved quiz calculations and sorting
 * Version: 2.1.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Simple_Teacher_Dashboard_Improved {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function init() {
        // Register shortcode
        add_shortcode('teacher_dashboard_improved', array($this, 'render_dashboard'));
    }
    
    public function enqueue_scripts() {
        // Enqueue jQuery for interactive features
        wp_enqueue_script('jquery');
    }
    
    /**
     * IMPROVED: Get student quiz statistics with better calculation and debugging
     */
    private function get_student_quiz_stats($student_id) {
        global $wpdb;
        
        // Debug logging
        error_log("[QUIZ DEBUG] Getting quiz stats for student ID: $student_id");
        
        // Method 1: Try to get quiz scores from pro_quiz_statistic tables (most accurate)
        // FIXED: Better handling of quiz calculations
        $pro_quiz_query = "
            SELECT 
                COUNT(ref.statistic_ref_id) as total_attempts,
                COUNT(DISTINCT ref.quiz_post_id) as unique_quizzes,
                -- Overall success rate: includes all attempts (even 0 scores)
                COALESCE(ROUND(AVG(
                    CASE 
                        WHEN quiz_scores.total_questions > 0 
                        THEN (quiz_scores.earned_points / quiz_scores.total_questions) * 100
                        ELSE 0
                    END
                ), 1), 0) as overall_success_rate,
                -- Completed only rate: only includes attempts with earned points > 0
                COALESCE(ROUND(AVG(
                    CASE 
                        WHEN quiz_scores.total_questions > 0 AND quiz_scores.earned_points > 0 
                        THEN (quiz_scores.earned_points / quiz_scores.total_questions) * 100
                        ELSE NULL
                    END
                ), 1), 0) as completed_only_rate,
                -- Debug info
                GROUP_CONCAT(CONCAT(quiz_scores.earned_points, '/', quiz_scores.total_questions) SEPARATOR ', ') as debug_scores
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
        
        // Debug logging
        if ($pro_quiz_result) {
            error_log("[QUIZ DEBUG] Pro quiz result for student $student_id: " . print_r($pro_quiz_result, true));
        }
        
        // If we have data from pro_quiz_statistic, use it
        if ($pro_quiz_result && $pro_quiz_result['total_attempts'] > 0) {
            // FIXED: Additional validation - if completed_only_rate is 0 but we have attempts, recalculate
            if ($pro_quiz_result['completed_only_rate'] == 0 && $pro_quiz_result['overall_success_rate'] > 0) {
                error_log("[QUIZ DEBUG] Warning: completed_only_rate is 0 but overall_success_rate is {$pro_quiz_result['overall_success_rate']} for student $student_id");
                
                // Try alternative calculation focusing only on successful attempts
                $alt_query = "
                    SELECT 
                        ROUND(AVG(
                            CASE 
                                WHEN quiz_scores.total_questions > 0 AND quiz_scores.earned_points > 0
                                THEN (quiz_scores.earned_points / quiz_scores.total_questions) * 100
                                ELSE NULL
                            END
                        ), 1) as alt_completed_rate,
                        COUNT(CASE WHEN quiz_scores.earned_points > 0 THEN 1 END) as successful_attempts
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
                
                $alt_result = $wpdb->get_row($wpdb->prepare($alt_query, $student_id), ARRAY_A);
                if ($alt_result && $alt_result['alt_completed_rate'] > 0) {
                    $pro_quiz_result['completed_only_rate'] = $alt_result['alt_completed_rate'];
                    error_log("[QUIZ DEBUG] Updated completed_only_rate to {$alt_result['alt_completed_rate']} for student $student_id (based on {$alt_result['successful_attempts']} successful attempts)");
                }
            }
            
            return $pro_quiz_result;
        }
        
        // Method 2: Fallback to learndash_user_activity table
        error_log("[QUIZ DEBUG] No pro quiz data found, trying learndash_user_activity for student $student_id");
        
        $activity_scores = $wpdb->get_results($wpdb->prepare("
            SELECT activity_meta, activity_updated
            FROM {$wpdb->prefix}learndash_user_activity
            WHERE user_id = %d AND activity_type = 'quiz' AND activity_status = 1
        ", $student_id));
        
        if (count($activity_scores) > 0) {
            error_log("[QUIZ DEBUG] Found " . count($activity_scores) . " activity records for student $student_id");
            
            $total_percentage = 0;
            $valid_scores = 0;
            $completed_percentage = 0;
            $completed_scores = 0;
            
            foreach ($activity_scores as $score) {
                $meta = maybe_unserialize($score->activity_meta);
                error_log("[QUIZ DEBUG] Activity meta for student $student_id: " . print_r($meta, true));
                
                if (isset($meta['percentage']) && is_numeric($meta['percentage'])) {
                    $percentage = floatval($meta['percentage']);
                    $total_percentage += $percentage;
                    $valid_scores++;
                    
                    // Only count non-zero scores for completed rate
                    if ($percentage > 0) {
                        $completed_percentage += $percentage;
                        $completed_scores++;
                    }
                }
            }
            
            if ($valid_scores > 0) {
                $overall_average = round($total_percentage / $valid_scores, 1);
                $completed_average = $completed_scores > 0 ? round($completed_percentage / $completed_scores, 1) : 0;
                
                error_log("[QUIZ DEBUG] Calculated averages for student $student_id - Overall: $overall_average%, Completed: $completed_average%");
                
                return array(
                    'total_attempts' => $valid_scores,
                    'unique_quizzes' => $valid_scores,
                    'overall_success_rate' => $overall_average,
                    'completed_only_rate' => $completed_average
                );
            }
        }
        
        // No quiz data at all - return zeros to indicate "אין נתונים"
        error_log("[QUIZ DEBUG] No quiz data found for student $student_id");
        return array(
            'total_attempts' => 0,
            'unique_quizzes' => 0,
            'overall_success_rate' => 0,
            'completed_only_rate' => 0
        );
    }
    
    /**
     * IMPROVED: Get dashboard JavaScript with sorting functionality
     */
    private function get_dashboard_javascript_improved($groups) {
        // Prepare groups data for JavaScript with quiz statistics
        $groups_json = array();
        foreach ($groups as $group) {
            $students = $this->get_group_students($group->group_id);
            
            // Add quiz statistics and course completion for each student
            $students_with_stats = array();
            foreach ($students as $student) {
                $quiz_stats = $this->get_student_quiz_stats($student->student_id);
                $course_completion = $this->get_student_course_completion($student->student_id);
                $students_with_stats[] = array(
                    'student_id' => $student->student_id,
                    'student_name' => $student->student_name,
                    'student_login' => $student->student_login,
                    'student_email' => $student->student_email,
                    'quiz_stats' => $quiz_stats,
                    'course_completion' => $course_completion
                );
            }
            
            $groups_json[$group->group_id] = array(
                'name' => $group->group_name,
                'students' => $students_with_stats
            );
        }
        
        // Generate JavaScript with groups data and IMPROVED sorting
        $js_code = '<script>
        jQuery(document).ready(function($) {
            var groupsData = ' . wp_json_encode($groups_json) . ';
            var currentStudents = [];
            var sortColumn = "";
            var sortDirection = "asc";
            
            $(".group-btn").click(function() {
                var groupId = $(this).data("group-id");
                var groupData = groupsData[groupId];
                
                $(".group-btn").removeClass("active");
                $(this).addClass("active");
                
                $("#students-display").html("<div class=\"loading\">Loading students...</div>");
                
                setTimeout(function() {
                    currentStudents = groupData.students;
                    displayStudents(currentStudents);
                }, 300);
            });
            
            // IMPROVED: Add sorting functionality
            function sortStudents(column) {
                if (sortColumn === column) {
                    sortDirection = sortDirection === "asc" ? "desc" : "asc";
                } else {
                    sortColumn = column;
                    sortDirection = "asc";
                }
                
                currentStudents.sort(function(a, b) {
                    var aVal, bVal;
                    
                    switch(column) {
                        case "name":
                            aVal = a.student_name.toLowerCase();
                            bVal = b.student_name.toLowerCase();
                            break;
                        case "email":
                            aVal = a.student_email.toLowerCase();
                            bVal = b.student_email.toLowerCase();
                            break;
                        case "course":
                            aVal = a.course_completion.completion_status;
                            bVal = b.course_completion.completion_status;
                            break;
                        case "overall":
                            aVal = parseFloat(a.quiz_stats.overall_success_rate) || 0;
                            bVal = parseFloat(b.quiz_stats.overall_success_rate) || 0;
                            break;
                        case "completed":
                            aVal = parseFloat(a.quiz_stats.completed_only_rate) || 0;
                            bVal = parseFloat(b.quiz_stats.completed_only_rate) || 0;
                            break;
                        default:
                            return 0;
                    }
                    
                    if (aVal < bVal) return sortDirection === "asc" ? -1 : 1;
                    if (aVal > bVal) return sortDirection === "asc" ? 1 : -1;
                    return 0;
                });
                
                displayStudents(currentStudents);
            }
            
            function displayStudents(students) {
                if (!students || students.length === 0) {
                    $("#students-display").html("<p>No students found in this group.</p>");
                    return;
                }
                
                // Calculate group average for students with quiz scores
                var studentsWithScores = students.filter(function(student) {
                    return student.quiz_stats.overall_success_rate > 0;
                });
                
                var groupAverage = 0;
                if (studentsWithScores.length > 0) {
                    var totalScore = studentsWithScores.reduce(function(sum, student) {
                        return sum + parseFloat(student.quiz_stats.overall_success_rate);
                    }, 0);
                    groupAverage = (totalScore / studentsWithScores.length).toFixed(1);
                }
                
                var html = "<div class=\"group-stats\">";
                html += "<h4>סטטיסטיקת הקבוצה</h4>";
                html += "<p><strong>תלמידים עם ציוני בחינות:</strong> " + studentsWithScores.length + " מתוך " + students.length + "</p>";
                if (groupAverage > 0) {
                    html += "<p><strong>ממוצע הקבוצה:</strong> " + formatQuizAverage(groupAverage) + "</p>";
                }
                html += "</div>";
                
                // Add export controls
                html += "<div class=\"table-controls\">";
                html += "<div class=\"export-buttons\">";
                html += "<button class=\"export-btn\" onclick=\"exportToCSV()\">📊 ייצא לCSV</button>";
                html += "</div>";
                html += "</div>";
                
                // IMPROVED: Add sortable table headers
                html += "<table class=\"students-table sortable-table\" id=\"students-table\">";
                html += "<thead><tr>";
                html += "<th class=\"sortable\" data-column=\"name\">שם התלמיד " + getSortIcon("name") + "</th>";
                html += "<th class=\"sortable\" data-column=\"email\">אימייל " + getSortIcon("email") + "</th>";
                html += "<th class=\"sortable\" data-column=\"course\">השלמת קורס " + getSortIcon("course") + "</th>";
                html += "<th class=\"sortable\" data-column=\"overall\">ממוצע כל הבחינות " + getSortIcon("overall") + "</th>";
                html += "<th class=\"sortable\" data-column=\"completed\">ממוצע בחינות שהושלמו " + getSortIcon("completed") + "</th>";
                html += "</tr></thead>";
                html += "<tbody>";
                
                students.forEach(function(student) {
                    html += "<tr>";
                    html += "<td>" + student.student_name + "</td>";
                    html += "<td>" + student.student_email + "</td>";
                    html += "<td>" + formatCourseCompletion(student.course_completion) + "</td>";
                    html += "<td>" + formatQuizAverage(student.quiz_stats.overall_success_rate) + "</td>";
                    html += "<td>" + formatQuizAverage(student.quiz_stats.completed_only_rate) + "</td>";
                    html += "</tr>";
                });
                
                html += "</tbody></table>";
                $("#students-display").html(html);
                
                // Add click handlers for sorting
                $(".sortable").click(function() {
                    var column = $(this).data("column");
                    sortStudents(column);
                });
            }
            
            function getSortIcon(column) {
                if (sortColumn !== column) return "<span class=\"sort-icon\">↕</span>";
                return sortDirection === "asc" ? "<span class=\"sort-icon active\">↑</span>" : "<span class=\"sort-icon active\">↓</span>";
            }
            
            // IMPROVED: Better quiz average formatting with debugging
            function formatQuizAverage(successRate) {
                console.log("Formatting quiz average:", successRate);
                
                if (!successRate || successRate === 0) {
                    return "<span class=\"no-data\">אין נתונים</span>";
                }
                
                var rate = parseFloat(successRate);
                var className;
                
                if (rate >= 80) {
                    className = "excellent";
                } else if (rate >= 70) {
                    className = "good";
                } else if (rate >= 60) {
                    className = "average";
                } else {
                    className = "needs-improvement";
                }
                
                return "<span class=\"quiz-rate " + className + "\">" + rate.toFixed(1) + "%</span>";
            }
            
            function formatCourseCompletion(courseData) {
                if (!courseData || !courseData.course_name) {
                    return "<span class=\"no-data\">אין נתוני קורס</span>";
                }
                
                var statusClass = "";
                var statusText = "";
                switch(courseData.completion_status) {
                    case "Completed":
                        statusClass = "completed";
                        statusText = "הושלם";
                        break;
                    case "In Progress":
                        statusClass = "in-progress";
                        statusText = "בתהליך";
                        break;
                    default:
                        statusClass = "not-started";
                        statusText = "לא התחיל";
                }
                
                return "<div class=\"course-completion\">" +
                       "<div class=\"course-name\">" + courseData.course_name + "</div>" +
                       "<span class=\"completion-status " + statusClass + "\">" + statusText + "</span>" +
                       "</div>";
            }
            
            // CSV Export Function
            window.exportToCSV = function() {
                var table = document.getElementById("students-table");
                if (!table) {
                    alert("אין נתונים לייצא");
                    return;
                }
                
                var csv = [];
                var rows = table.querySelectorAll("tr");
                
                for (var i = 0; i < rows.length; i++) {
                    var row = [];
                    var cols = rows[i].querySelectorAll("td, th");
                    
                    for (var j = 0; j < cols.length; j++) {
                        var cellText = cols[j].innerText.replace(/,/g, ";");
                        row.push("\"" + cellText + "\"");
                    }
                    csv.push(row.join(","));
                }
                
                var csvContent = csv.join("\\n");
                var blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
                var link = document.createElement("a");
                
                if (link.download !== undefined) {
                    var url = URL.createObjectURL(blob);
                    link.setAttribute("href", url);
                    link.setAttribute("download", "students_data.csv");
                    link.style.visibility = "hidden";
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            };
        });
        </script>';
        
        return $js_code;
    }
    
    // Add CSS for sorting
    private function get_improved_css() {
        return '<style>
            .sortable {
                cursor: pointer;
                user-select: none;
                position: relative;
            }
            .sortable:hover {
                background-color: #f0f0f0;
            }
            .sort-icon {
                font-size: 12px;
                margin-left: 5px;
                color: #999;
            }
            .sort-icon.active {
                color: #2271b1;
                font-weight: bold;
            }
            .quiz-rate.excellent { color: #00a32a; font-weight: bold; }
            .quiz-rate.good { color: #72aee6; font-weight: bold; }
            .quiz-rate.average { color: #ffb900; font-weight: bold; }
            .quiz-rate.needs-improvement { color: #d63638; font-weight: bold; }
            .no-data { color: #666; font-style: italic; }
        </style>';
    }
}

// Initialize the improved plugin
new Simple_Teacher_Dashboard_Improved();
