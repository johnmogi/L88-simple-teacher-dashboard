<?php
/**
 * School Manager Bridge
 * 
 * Provides integration between Simple Teacher Dashboard and School Manager Lite
 * for instructor-LearnDash group connections using the new unified connector
 * 
 * @package Simple_Teacher_Dashboard
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_Teacher_Dashboard_School_Manager_Bridge {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize the bridge
     */
    public function init() {
        // Add AJAX handlers for teacher dashboard
        add_action('wp_ajax_teacher_dashboard_assign_to_group', array($this, 'ajax_assign_teacher_to_group'));
        add_action('wp_ajax_teacher_dashboard_get_group_students', array($this, 'ajax_get_group_students'));
    }
    
    /**
     * Check if School Manager Lite is active and has LearnDash integration
     */
    public function is_school_manager_available() {
        return class_exists('School_Manager_Lite_LearnDash_Integration');
    }
    
    /**
     * Get LearnDash integration instance
     */
    public function get_learndash_integration() {
        if ($this->is_school_manager_available()) {
            return School_Manager_Lite_LearnDash_Integration::instance();
        }
        return false;
    }
    
    /**
     * Get teacher's classes from School Manager
     * 
     * @param int $teacher_id Teacher user ID
     * @return array Classes assigned to teacher
     */
    public function get_teacher_classes($teacher_id) {
        if (!$this->is_school_manager_available()) {
            return array();
        }
        
        if (class_exists('School_Manager_Lite_Class_Manager')) {
            $class_manager = School_Manager_Lite_Class_Manager::instance();
            return $class_manager->get_classes(array('teacher_id' => $teacher_id));
        }
        
        return array();
    }
    
    /**
     * Get students in teacher's classes
     * 
     * @param int $teacher_id Teacher user ID
     * @return array Students in teacher's classes
     */
    public function get_teacher_students($teacher_id) {
        if (!$this->is_school_manager_available()) {
            return array();
        }
        
        $students = array();
        $classes = $this->get_teacher_classes($teacher_id);
        
        if (class_exists('School_Manager_Lite_Student_Manager')) {
            $student_manager = School_Manager_Lite_Student_Manager::instance();
            
            foreach ($classes as $class) {
                $class_students = $student_manager->get_students(array('class_id' => $class->id));
                $students = array_merge($students, $class_students);
            }
        }
        
        return $students;
    }
    
    /**
     * Create or connect teacher to LearnDash group for a class
     * 
     * @param int $teacher_id Teacher user ID
     * @param int $class_id Class ID
     * @return array Result with success/error status
     */
    public function connect_teacher_to_class_group($teacher_id, $class_id) {
        $integration = $this->get_learndash_integration();
        
        if (!$integration) {
            return array(
                'success' => false,
                'message' => __('School Manager LearnDash integration not available', 'simple-teacher-dashboard')
            );
        }
        
        if (!$integration->is_learndash_active()) {
            return array(
                'success' => false,
                'message' => __('LearnDash is not active', 'simple-teacher-dashboard')
            );
        }
        
        // Create or get group for class
        $group_id = $integration->create_or_get_group_for_class($class_id);
        
        if (is_wp_error($group_id)) {
            return array(
                'success' => false,
                'message' => $group_id->get_error_message()
            );
        }
        
        // Assign teacher to group
        $teacher_assigned = $integration->assign_teacher_to_group($teacher_id, $group_id);
        
        if ($teacher_assigned) {
            return array(
                'success' => true,
                'message' => __('Teacher successfully connected to LearnDash group', 'simple-teacher-dashboard'),
                'group_id' => $group_id
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Failed to assign teacher to LearnDash group', 'simple-teacher-dashboard')
            );
        }
    }
    
    /**
     * Get LearnDash groups for a teacher
     * 
     * @param int $teacher_id Teacher user ID
     * @return array LearnDash groups where teacher is leader
     */
    public function get_teacher_groups($teacher_id) {
        if (!function_exists('learndash_get_administrators_group_ids')) {
            return array();
        }
        
        $group_ids = learndash_get_administrators_group_ids($teacher_id);
        $groups = array();
        
        foreach ($group_ids as $group_id) {
            $group = get_post($group_id);
            if ($group && $group->post_type === 'groups') {
                $groups[] = array(
                    'id' => $group_id,
                    'name' => $group->post_title,
                    'students_count' => count($this->get_group_students($group_id))
                );
            }
        }
        
        return $groups;
    }
    
    /**
     * Get students in a LearnDash group
     * 
     * @param int $group_id Group ID
     * @return array Student user objects
     */
    public function get_group_students($group_id) {
        $integration = $this->get_learndash_integration();
        
        if (!$integration) {
            return array();
        }
        
        $student_ids = $integration->get_group_students($group_id);
        $students = array();
        
        foreach ($student_ids as $student_id) {
            $user = get_user_by('id', $student_id);
            if ($user) {
                $students[] = array(
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email
                );
            }
        }
        
        return $students;
    }
    
    /**
     * AJAX handler for assigning teacher to group
     */
    public function ajax_assign_teacher_to_group() {
        // Check permissions
        if (!current_user_can('access_school_content')) {
            wp_send_json_error(array('message' => __('Permission denied', 'simple-teacher-dashboard')));
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'teacher_dashboard_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'simple-teacher-dashboard')));
        }
        
        $teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : get_current_user_id();
        $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
        
        if (!$class_id) {
            wp_send_json_error(array('message' => __('Invalid class ID', 'simple-teacher-dashboard')));
        }
        
        $result = $this->connect_teacher_to_class_group($teacher_id, $class_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX handler for getting group students
     */
    public function ajax_get_group_students() {
        // Check permissions
        if (!current_user_can('access_school_content')) {
            wp_send_json_error(array('message' => __('Permission denied', 'simple-teacher-dashboard')));
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'teacher_dashboard_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'simple-teacher-dashboard')));
        }
        
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        
        if (!$group_id) {
            wp_send_json_error(array('message' => __('Invalid group ID', 'simple-teacher-dashboard')));
        }
        
        $students = $this->get_group_students($group_id);
        
        wp_send_json_success(array(
            'students' => $students,
            'count' => count($students)
        ));
    }
    
    /**
     * Get comprehensive teacher dashboard data
     * 
     * @param int $teacher_id Teacher user ID
     * @return array Dashboard data including classes, groups, and students
     */
    public function get_teacher_dashboard_data($teacher_id) {
        $data = array(
            'teacher_id' => $teacher_id,
            'classes' => $this->get_teacher_classes($teacher_id),
            'students' => $this->get_teacher_students($teacher_id),
            'groups' => $this->get_teacher_groups($teacher_id),
            'learndash_active' => function_exists('learndash_get_groups'),
            'school_manager_active' => $this->is_school_manager_available()
        );
        
        return $data;
    }
}

// Initialize the bridge
Simple_Teacher_Dashboard_School_Manager_Bridge::instance();
