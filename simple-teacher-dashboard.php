<?php
/**
 * Plugin Name: Simple Teacher Dashboard
 * Description: A simplified teacher dashboard showing only teacher's groups, students, and grades
 * Version: 2.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Simple_Teacher_Dashboard {
    
    public function __construct() {
        // Include School Manager bridge for LearnDash integration
        $this->include_bridge();
        
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_get_group_students', array($this, 'ajax_get_group_students'));
        add_action('wp_ajax_nopriv_get_group_students', array($this, 'ajax_no_permission'));
    }
    
    /**
     * Include School Manager bridge if available
     */
    private function include_bridge() {
        $bridge_file = plugin_dir_path(__FILE__) . 'includes/school-manager-bridge.php';
        if (file_exists($bridge_file)) {
            require_once $bridge_file;
        }
    }
    
    public function init() {
        // Register shortcode
        add_shortcode('teacher_dashboard', array($this, 'render_dashboard'));
        add_shortcode('simple_teacher_dashboard', array($this, 'render_dashboard'));
        
        // Register AJAX actions
        add_action('wp_ajax_get_group_students', array($this, 'ajax_get_group_students'));
        add_action('wp_ajax_nopriv_get_group_students', array($this, 'ajax_no_permission'));
        add_action('wp_ajax_get_student_quiz_data', array($this, 'ajax_get_student_quiz_data'));
        add_action('wp_ajax_nopriv_get_student_quiz_data', array($this, 'ajax_no_permission'));
    }
    
    public function enqueue_scripts() {
        // Only register scripts here, don't enqueue them yet
        wp_register_style(
            'simple-teacher-dashboard-css',
            plugins_url('assets/css/teacher-dashboard.css', __FILE__),
            array(),
            '2.0.0'
        );
        
        wp_register_script(
            'simple-teacher-dashboard-js',
            plugins_url('assets/js/teacher-dashboard.js', __FILE__),
            array('jquery'),
            '2.0.0',
            true
        );
    }
    
    public function render_dashboard($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return $this->render_login_message();
        }
        
        $current_user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        
        // Check if user has teacher role or is admin
        if (!$this->is_teacher($current_user) && !$is_admin) {
            return $this->render_no_permission_message();
        }
        
        // Determine which teacher to show dashboard for
        $selected_teacher_id = $current_user->ID;
        $selected_teacher = $current_user;
        
        // If admin and teacher_id parameter is provided, use that teacher
        if ($is_admin && isset($_GET['teacher_id']) && intval($_GET['teacher_id']) > 0) {
            $selected_teacher_id = intval($_GET['teacher_id']);
            $selected_teacher = get_user_by('ID', $selected_teacher_id);
            
            // Verify the selected user is actually a teacher
            if (!$selected_teacher || !$this->is_teacher($selected_teacher)) {
                $selected_teacher_id = $current_user->ID;
                $selected_teacher = $current_user;
            }
        }
        
        // Get teacher's groups
        $groups = $this->get_teacher_groups($selected_teacher_id);
        
        if (empty($groups) && !$is_admin) {
            return $this->render_no_groups_message($selected_teacher);
        }
        
        // Build dashboard HTML
        $html = '<div class="simple-teacher-dashboard">';
        
        // Add admin teacher selector if user is admin
        if ($is_admin) {
            $html .= $this->render_teacher_selector($current_user->ID, $selected_teacher_id);
        }
        
        $html .= '<h2>לוח בקרה למורה - ' . esc_html($selected_teacher->display_name) . '</h2>';
        
        // Add group selection interface or no groups message
        if (empty($groups)) {
            $html .= '<div class="no-groups-message">';
            $html .= '<p>למורה הזה אין קבוצות עם תלמידים.</p>';
            $html .= '</div>';
        } else {
            $html .= $this->render_group_selector($groups);
            
            // Add students display area
            $html .= '<div id="students-display" class="students-display-area">';
            $html .= '<p class="select-group-message">אנא בחר קבוצה כדי לראות את התלמידים.</p>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        // Add CSS and JavaScript
        $html .= $this->get_dashboard_css();
        $html .= $this->get_dashboard_javascript($groups);
        
        return $html;
    }
    
    /**
     * Check if user has teacher role
     */
    private function is_teacher($user) {
        // Check WordPress user roles first
        $teacher_roles = array(
            'school_teacher', 
            'instructor', 
            'Instructor', 
            'wdm_instructor',
            'stm_lms_instructor',
            'group_leader'
        );
        
        foreach ($teacher_roles as $role) {
            if (in_array($role, $user->roles)) {
                return true;
            }
        }
        
        // Check for group leader meta keys (LearnDash pattern)
        global $wpdb;
        $has_group_leader_meta = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$wpdb->usermeta}
            WHERE user_id = %d
            AND meta_key LIKE '%%group_leader%%'",
            $user->ID
        ));
        
        return $has_group_leader_meta > 0;
    }
    
    /**
     * Get all teachers for admin selection
     */
    private function get_all_teachers() {
        global $wpdb;
        
        // Enhanced query to find all potential teachers
        $potential_teachers = $wpdb->get_results("
            SELECT DISTINCT
                u.ID as teacher_id,
                u.display_name as teacher_name,
                u.user_email as teacher_email,
                u.user_login as teacher_login
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = 'wp_capabilities' 
            AND (
                um.meta_value LIKE '%group_leader%' 
                OR um.meta_value LIKE '%school_teacher%'
                OR um.meta_value LIKE '%instructor%'
                OR um.meta_value LIKE '%Instructor%'
                OR um.meta_value LIKE '%wdm_instructor%'
                OR um.meta_value LIKE '%stm_lms_instructor%'
                OR um.meta_value LIKE '%teacher%'
                OR um.meta_value LIKE '%Teacher%'
                OR um.meta_value LIKE '%educator%'
                OR um.meta_value LIKE '%Educator%'
            )
            UNION
            SELECT DISTINCT
                u.ID as teacher_id,
                u.display_name as teacher_name,
                u.user_email as teacher_email,
                u.user_login as teacher_login
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key LIKE '%group_leader%'
            OR um.meta_key LIKE '%instructor%'
            OR um.meta_key LIKE '%teacher%'
            ORDER BY teacher_name
        ");
        
        // Return ALL potential teachers (not just those with current groups)
        // This ensures missing teachers like David are included
        return $potential_teachers;
    }
    
    /**
     * Render teacher selector for admins
     */
    private function render_teacher_selector($current_teacher_id, $selected_teacher_id) {
        $teachers = $this->get_all_teachers();
        
        if (empty($teachers)) {
            return '';
        }
        
        $html = '<div class="admin-teacher-selector">';
        $html .= '<h3>בחירת מורה לצפייה (מנהל)</h3>';
        $html .= '<form method="get" class="teacher-selector-form">';
        
        // Preserve other GET parameters
        foreach ($_GET as $key => $value) {
            if ($key !== 'teacher_id') {
                $html .= '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
            }
        }
        
        $html .= '<select name="teacher_id" id="teacher-selector" onchange="this.form.submit()">';
        
        foreach ($teachers as $teacher) {
            $selected = ($teacher->teacher_id == $selected_teacher_id) ? 'selected' : '';
            $html .= '<option value="' . esc_attr($teacher->teacher_id) . '" ' . $selected . '>';
            $html .= esc_html($teacher->teacher_name) . ' (' . esc_html($teacher->teacher_email) . ')';
            $html .= '</option>';
        }
        
        $html .= '</select>';
        $html .= '</form>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get teacher's groups - compatible with School Manager Lite and LearnDash
     */
    private function get_teacher_groups($teacher_id) {
        $groups = array();
        
        // Method 1: Check School Manager Lite classes table
        global $wpdb;
        $classes_table = $wpdb->prefix . 'school_classes';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$classes_table'") == $classes_table) {
            $classes = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name, group_id FROM $classes_table WHERE teacher_id = %d AND group_id IS NOT NULL",
                $teacher_id
            ));
            
            foreach ($classes as $class) {
                if ($class->group_id) {
                    $group_post = get_post($class->group_id);
                    if ($group_post && $group_post->post_type === 'groups') {
                        $groups[] = (object) array(
                            'group_id' => $class->group_id,
                            'group_name' => 'Class: ' . $class->name,
                            'post_status' => $group_post->post_status
                        );
                    }
                }
            }
        }
        
        // Method 2: Check LearnDash group leaders meta
        if (function_exists('learndash_get_administrators_group_ids')) {
            $leader_groups = learndash_get_administrators_group_ids($teacher_id);
            if (!empty($leader_groups)) {
                foreach ($leader_groups as $group_id) {
                    $group_post = get_post($group_id);
                    if ($group_post && $group_post->post_type === 'groups') {
                        // Check if already added from classes
                        $already_added = false;
                        foreach ($groups as $existing_group) {
                            if ($existing_group->group_id == $group_id) {
                                $already_added = true;
                                break;
                            }
                        }
                        
                        if (!$already_added) {
                            $groups[] = (object) array(
                                'group_id' => $group_id,
                                'group_name' => $group_post->post_title,
                                'post_status' => $group_post->post_status
                            );
                        }
                    }
                }
            }
        }
        
        // Method 3: Fallback - check group leaders meta directly
        if (empty($groups)) {
            $leader_meta = get_user_meta($teacher_id, 'learndash_group_leaders', true);
            if (!empty($leader_meta) && is_array($leader_meta)) {
                foreach ($leader_meta as $group_id) {
                    $group_post = get_post($group_id);
                    if ($group_post && $group_post->post_type === 'groups') {
                        $groups[] = (object) array(
                            'group_id' => $group_id,
                            'group_name' => $group_post->post_title,
                            'post_status' => $group_post->post_status
                        );
                    }
                }
            }
        }
        
        // Sort by group name
        usort($groups, function($a, $b) {
            return strcmp($a->group_name, $b->group_name);
        });
        
        return $groups;
    }
    
    /**
     * Render login message
     */
    private function render_login_message() {
        return '<div class="simple-teacher-dashboard login-message">
            <h3>לוח בקרה למורה</h3>
            <p>אנא התחבר כדי לראות את לוח הבקרה שלך.</p>
        </div>';
    }
    
    /**
     * Render no permission message
     */
    private function render_no_permission_message() {
        return '<div class="simple-teacher-dashboard no-permission">
            <h3>גישה נדחתה</h3>
            <p>אין לך הרשאה לראות את לוח הבקרה הזה. לוח בקרה זה זמין רק למורים ומדריכים.</p>
        </div>';
    }
    
    /**
     * Render no groups message
     */
    private function render_no_groups_message($user) {
        return '<div class="simple-teacher-dashboard no-groups">
            <h3>לוח בקרה למורה - ' . esc_html($user->display_name) . '</h3>
            <p>אתה עדיין לא מוקצה לאף קבוצה. אנא פנה למנהל המערכת.</p>
        </div>';
    }
    
    /**
     * Render group selector interface
     */
    private function render_group_selector($groups) {
        $html = '<div class="group-selector">';
        $html .= '<h3>בחר קבוצה</h3>';
        $html .= '<div class="group-buttons">';
        
        foreach ($groups as $group) {
            $html .= '<button class="group-btn" data-group-id="' . esc_attr($group->group_id) . '">';
            $html .= esc_html($group->group_name);
            $html .= '</button>';
        }
        
        $html .= '</div></div>';
        
        return $html;
    }
    
    /**
     * Get students in a specific group using both School Manager and LearnDash data sources
     */
    private function get_group_students($group_id) {
        global $wpdb;
        
        $students = array();
        
        // Method 1: Check School Manager tables first
        $school_students = $wpdb->get_results($wpdb->prepare("
            SELECT 
                u.ID as student_id,
                u.user_login as student_login,
                u.display_name as student_name,
                u.user_email as student_email,
                c.id as class_id,
                c.name as class_name,
                'school_manager' as source
            FROM {$wpdb->users} u
            JOIN {$wpdb->prefix}school_student_classes sc ON u.ID = sc.student_id
            JOIN {$wpdb->prefix}school_classes c ON sc.class_id = c.id
            WHERE c.group_id = %d
            ORDER BY u.display_name
        ", $group_id));
        
        // Method 2: Check LearnDash group meta (this is what your dashboard was actually showing)
        $learndash_users = get_post_meta($group_id, 'learndash_group_users', true);
        if (is_array($learndash_users) && !empty($learndash_users)) {
            foreach ($learndash_users as $user_id) {
                $user = get_user_by('ID', $user_id);
                if ($user) {
                    $school_students[] = (object) array(
                        'student_id' => $user->ID,
                        'student_login' => $user->user_login,
                        'student_name' => $user->display_name,
                        'student_email' => $user->user_email,
                        'class_id' => null,
                        'class_name' => 'LearnDash Group',
                        'source' => 'learndash_meta'
                    );
                }
            }
        }
        
        // Method 3: Check user meta for group membership (alternative LearnDash pattern)
        $user_meta_students = $wpdb->get_results($wpdb->prepare("
            SELECT 
                u.ID as student_id,
                u.user_login as student_login,
                u.display_name as student_name,
                u.user_email as student_email,
                'user_meta' as source
            FROM {$wpdb->users} u
            JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = %s
            ORDER BY u.display_name
        ", 'learndash_group_users_' . $group_id));
        
        // Merge all sources and remove duplicates
        $all_students = array_merge($school_students, $user_meta_students);
        $unique_students = array();
        $seen_ids = array();
        
        foreach ($all_students as $student) {
            if (!in_array($student->student_id, $seen_ids)) {
                $unique_students[] = (object) array(
                    'student_id' => $student->student_id,
                    'student_name' => $student->student_name,
                    'student_login' => $student->student_login,
                    'student_email' => $student->student_email,
                    'class_id' => isset($student->class_id) ? $student->class_id : null,
                    'class_name' => isset($student->class_name) ? $student->class_name : 'Unknown',
                    'source' => $student->source
                );
                $seen_ids[] = $student->student_id;
            }
        }
        
        // Sort by name
        usort($unique_students, function($a, $b) {
            return strcmp($a->student_name, $b->student_name);
        });
        
        // Debug logging
        error_log('get_group_students for group ' . $group_id . ': Found ' . count($unique_students) . ' students from multiple sources');
        error_log('Sources: School Manager: ' . count($school_students) . ', User Meta: ' . count($user_meta_students));
        
        return $unique_students;
    }
    
    private function get_student_quiz_stats($student_id) {
        global $wpdb;
        
        // Debug logging
        error_log("[QUIZ DEBUG] Getting quiz stats for student ID: $student_id");
        
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
                ), 1), 0) as completed_only_rate,
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
            // Fix for completed_only_rate being 0 when it shouldn't be
            if ($pro_quiz_result['completed_only_rate'] == 0 && $pro_quiz_result['overall_success_rate'] > 0) {
                error_log("[QUIZ DEBUG] Fixing completed_only_rate for student $student_id");
                
                // Recalculate completed only rate
                $fix_query = "
                    SELECT 
                        ROUND(AVG(
                            CASE 
                                WHEN quiz_scores.total_questions > 0 AND quiz_scores.earned_points > 0
                                THEN (quiz_scores.earned_points / quiz_scores.total_questions) * 100
                                ELSE NULL
                            END
                        ), 1) as fixed_completed_rate
                    FROM {$wpdb->prefix}learndash_pro_quiz_statistic_ref ref
                    INNER JOIN (
                        SELECT 
                            statistic_ref_id,
                            SUM(points) as earned_points,
                            COUNT(*) as total_questions
                        FROM {$wpdb->prefix}learndash_pro_quiz_statistic
                        GROUP BY statistic_ref_id
                        HAVING COUNT(*) > 0 AND SUM(points) > 0
                    ) quiz_scores ON ref.statistic_ref_id = quiz_scores.statistic_ref_id
                    WHERE ref.user_id = %d
                ";
                
                $fixed_rate = $wpdb->get_var($wpdb->prepare($fix_query, $student_id));
                if ($fixed_rate > 0) {
                    $pro_quiz_result['completed_only_rate'] = $fixed_rate;
                    error_log("[QUIZ DEBUG] Fixed completed_only_rate to $fixed_rate for student $student_id");
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
        // This covers both students with no attempts and students with only empty attempts
        error_log("[QUIZ DEBUG] No quiz data found for student $student_id");
        return array(
            'total_attempts' => 0,
            'unique_quizzes' => 0,
            'overall_success_rate' => 0,
            'completed_only_rate' => 0
        );
    }
    
    /**
     * Get student course completion status
     */
    private function get_student_course_completion($student_id) {
        global $wpdb;
        
        // Get course completion data from LearnDash
        $query = "
            SELECT 
                c.ID as course_id,
                c.post_title as course_name,
                CASE 
                    WHEN ua.activity_status = 1 THEN 'Completed'
                    WHEN ua.activity_status = 0 THEN 'In Progress'
                    ELSE 'Not Started'
                END as completion_status,
                ua.activity_completed as completion_date
            FROM {$wpdb->prefix}learndash_user_activity ua
            JOIN {$wpdb->posts} c ON c.ID = ua.post_id
            WHERE ua.user_id = %d
            AND ua.activity_type = 'course'
            AND c.post_type = 'sfwd-courses'
            ORDER BY ua.activity_updated DESC
            LIMIT 1
        ";
        
        $result = $wpdb->get_row($wpdb->prepare($query, $student_id), ARRAY_A);
        
        if (!$result) {
            return array(
                'course_name' => 'No Course Data',
                'completion_status' => 'Not Started',
                'completion_date' => null
            );
        }
        
        return array(
            'course_name' => $result['course_name'],
            'completion_status' => $result['completion_status'],
            'completion_date' => $result['completion_date']
        );
    }
    
    /**
     * Get dashboard CSS styles
     */
    private function get_dashboard_css() {
        return '<style>
            .simple-teacher-dashboard { 
                padding: 20px; 
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                max-width: 1200px;
                margin: 20px auto;
                direction: rtl;
                text-align: right;
            }
            .admin-teacher-selector {
                background: #f8f9fa;
                border: 2px solid #2271b1;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 25px;
                text-align: center;
            }
            .admin-teacher-selector h3 {
                color: #2271b1;
                margin: 0 0 15px 0;
                font-size: 18px;
            }
            .teacher-selector-form {
                display: flex;
                justify-content: center;
                align-items: center;
            }
            #teacher-selector {
                padding: 10px 15px;
                border: 2px solid #ddd;
                border-radius: 5px;
                font-size: 16px;
                min-width: 300px;
                background: white;
                cursor: pointer;
                direction: ltr;
                text-align: left;
            }
            #teacher-selector:focus {
                outline: none;
                border-color: #2271b1;
                box-shadow: 0 0 5px rgba(34, 113, 177, 0.3);
            }
            .simple-teacher-dashboard h2 {
                color: #2271b1;
                margin-top: 0;
                padding-bottom: 15px;
                border-bottom: 2px solid #e0e0e0;
            }
            .group-selector {
                margin-bottom: 30px;
            }
            .group-selector h3 {
                color: #333;
                margin-bottom: 15px;
            }
            .group-buttons {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }
            .group-btn {
                padding: 12px 20px;
                background: #0073aa;
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                transition: all 0.3s ease;
            }
            .group-btn:hover {
                background: #005a87;
                transform: translateY(-2px);
            }
            .group-btn.active {
                background: #00a32a;
            }
            .students-display-area {
                min-height: 200px;
                padding: 20px;
                background: #f9f9f9;
                border-radius: 5px;
                border: 1px solid #ddd;
            }
            .select-group-message {
                text-align: center;
                color: #666;
                font-style: italic;
                padding: 40px;
                background: #f9f9f9;
                border-radius: 5px;
            }
            .no-groups-message {
                text-align: center;
                padding: 40px;
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 5px;
                margin: 20px 0;
            }
            .no-groups-message p {
                color: #856404;
                font-size: 16px;
                margin: 0;
            }
            .students-table {
                margin-top: 20px;
            }
            .students-table h4 {
                color: #333;
                margin-bottom: 15px;
            }
            .students-table table { 
                width: 100%; 
                border-collapse: collapse;
                background: white;
                border-radius: 5px;
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .students-table th, .students-table td { 
                padding: 12px 15px;
                text-align: right;
                border-bottom: 1px solid #eee;
            }
            .students-table th {
                background-color: #f8f9fa;
                font-weight: 600;
                color: #333;
            }
            .students-table tr:hover {
                background-color: #f5f5f5;
            }
                padding: 30px;
                text-align: center;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 8px;
                margin: 20px auto;
                max-width: 600px;
            }
            .login-message h3, .no-permission h3, .no-groups h3 {
                color: #333;
                margin-bottom: 15px;
            }
            .loading {
                text-align: center;
                padding: 20px;
                color: #666;
            }
            .group-stats {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 6px;
                margin-bottom: 20px;
                border-left: 4px solid #2271b1;
            }
            .group-stats h4 {
                margin: 0 0 10px 0;
                color: #2271b1;
            }
            .group-stats p {
                margin: 5px 0;
                color: #333;
            }
            .course-completion {
                text-align: center;
            }
            .course-name {
                font-weight: bold;
                margin-bottom: 4px;
                color: #333;
            }
            .completion-status {
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: bold;
                text-transform: uppercase;
            }
            .completion-status.completed {
                background: #d4edda;
                color: #155724;
            }
            .completion-status.in-progress {
                background: #fff3cd;
                color: #856404;
            }
            .completion-status.not-started {
                background: #f8d7da;
                color: #721c24;
            }
            .table-controls {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
                padding: 10px 0;
            }
            .export-buttons {
                display: flex;
                gap: 10px;
            }
            .export-btn, .print-btn {
                background: #2271b1;
                color: white;
                border: none;
                padding: 8px 15px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                font-weight: bold;
                transition: background-color 0.3s ease;
                display: flex;
                align-items: center;
                gap: 5px;
            }
            .print-btn {
                background: #00a32a;
            }
            .export-btn:hover {
                background: #135e96;
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            .print-btn:hover {
                background: #007a1f;
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            /* Sorting CSS */
            .sortable {
                cursor: pointer;
                user-select: none;
                position: relative;
                transition: background-color 0.2s ease;
            }
            .sortable:hover {
                background-color: #f0f0f0;
            }
            .sort-icon {
                font-size: 12px;
                margin-left: 5px;
                color: #999;
                font-weight: normal;
            }
            .sort-icon.active {
                color: #2271b1;
                font-weight: bold;
            }
            .sortable-table th {
                white-space: nowrap;
            }
            @media (max-width: 768px) {
                .group-buttons {
                    flex-direction: column;
                }
                .group-btn {
                    width: 100%;
                }
                .students-table {
                    overflow-x: auto;
                }
            }
        </style>';
    }
    
    /**
     * Get dashboard JavaScript functionality
     */
    private function get_dashboard_javascript($groups) {
        // Enqueue the styles and scripts
        wp_enqueue_style('simple-teacher-dashboard-css');
        wp_enqueue_script('jquery');
        
        // Prepare groups data for JavaScript
        $groups_json = array();
        foreach ($groups as $group) {
            $groups_json[$group->group_id] = array(
                'name' => $group->group_name,
                'id' => $group->group_id
            );
        }
        
        // Localize script with data
        wp_localize_script(
            'simple-teacher-dashboard-js',
            'teacherDashboardData',
            array(
                'groups' => $groups_json,
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('teacher_dashboard_nonce')
            )
        );
        
        // Enqueue the script after localizing
        wp_enqueue_script('simple-teacher-dashboard-js');
        
        // Return empty string since scripts are enqueued
        return '';
    }
    
    /**
     * AJAX handler for getting group students
     */
    public function ajax_get_group_students() {
        // Log the request for debugging
        error_log('AJAX get_group_students called with data: ' . print_r($_POST, true));
        
        // TEMPORARY: Disable nonce check for debugging
        // TODO: Re-enable after fixing localization issue
        /*
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : (isset($_GET['nonce']) ? $_GET['nonce'] : '');
        if (!$nonce || !wp_verify_nonce($nonce, 'teacher_dashboard_nonce')) {
            error_log('Nonce verification failed. Nonce: ' . $nonce);
            wp_send_json_error('Security check failed', 403);
            return;
        }
        */
        
        // Basic security check - user must be logged in
        if (!is_user_logged_in()) {
            error_log('User not logged in');
            wp_send_json_error('Not logged in', 401);
            return;
        }
        
        $current_user = wp_get_current_user();
        
        // Check teacher permissions (stm_lms_instructor role is supported)
        if (!$this->is_teacher($current_user) && !current_user_can('manage_options')) {
            error_log('User does not have teacher permissions: ' . $current_user->ID . ', Roles: ' . implode(', ', $current_user->roles));
            wp_send_json_error('Unauthorized', 403);
            return;
        }
        
        // Debug: Log user info for troubleshooting
        error_log('Teacher dashboard access - User ID: ' . $current_user->ID . ', Roles: ' . implode(', ', $current_user->roles));
        
        // Get group ID from request
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        
        if (!$group_id) {
            error_log('Invalid group ID: ' . $group_id);
            wp_send_json_error('Invalid group ID', 400);
            return;
        }
        
        // Get students for the group
        $students = $this->get_group_students($group_id);
        error_log('Found ' . count($students) . ' students for group ' . $group_id);
        
        // Format student data for response
        $formatted_students = array();
        foreach ($students as $student) {
            $quiz_stats = $this->get_student_quiz_stats($student->student_id);
            $course_completion = $this->get_student_course_completion($student->student_id);
            
            $formatted_students[] = array(
                'ID' => $student->student_id,
                'display_name' => $student->student_name,
                'user_email' => $student->student_email,
                'user_login' => $student->student_login,
                'quiz_stats' => $quiz_stats,
                'course_completion' => $course_completion
            );
        }
        
        error_log('Sending response with ' . count($formatted_students) . ' formatted students');
        wp_send_json_success(array('students' => $formatted_students));
    }
    
    /**
     * AJAX handler for getting student quiz data
     */
    public function ajax_get_student_quiz_data() {
        // TEMPORARY: Disable nonce check for debugging
        // TODO: Re-enable after fixing localization issue
        /*
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'teacher_dashboard_nonce')) {
            wp_send_json_error('Invalid nonce', 403);
            return;
        }
        */
        
        // Basic security check - user must be logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in', 401);
            return;
        }
        
        $current_user = wp_get_current_user();
        if (!$this->is_teacher($current_user) && !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
            return;
        }
        
        // Get student ID from request
        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
        
        if (!$student_id) {
            wp_send_json_error('Invalid student ID', 400);
            return;
        }
        
        // Get quiz stats and course completion
        $quiz_stats = $this->get_student_quiz_stats($student_id);
        $course_completion = $this->get_student_course_completion($student_id);
        
        wp_send_json_success(array(
            'quiz_stats' => $quiz_stats,
            'course_completion' => $course_completion
        ));
    }
    
    /**
     * Handle unauthorized AJAX requests
     */
    public function ajax_no_permission() {
        wp_send_json_error('You do not have permission to perform this action.', 403);
    }
}

// Initialize the plugin
new Simple_Teacher_Dashboard();
