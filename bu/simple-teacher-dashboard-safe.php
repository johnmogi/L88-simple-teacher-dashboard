<?php
/**
 * Plugin Name: Simple Teacher Dashboard (Safe Version)
 * Description: A simplified teacher dashboard showing only teacher's groups, students, and grades
 * Version: 2.0.1
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Simple_Teacher_Dashboard_Safe {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_get_group_students', array($this, 'ajax_get_group_students'));
        add_action('wp_ajax_nopriv_get_group_students', array($this, 'ajax_no_permission'));
    }
    
    public function init() {
        // Register shortcode
        add_shortcode('teacher_dashboard', array($this, 'render_dashboard'));
    }
    
    public function enqueue_scripts() {
        // Only register scripts here, don't enqueue them yet
        wp_register_style(
            'simple-teacher-dashboard-css',
            plugins_url('assets/css/teacher-dashboard.css', __FILE__),
            array(),
            '2.0.1'
        );
        
        wp_register_script(
            'simple-teacher-dashboard-js',
            plugins_url('assets/js/teacher-dashboard.js', __FILE__),
            array('jquery'),
            '2.0.1',
            true
        );
    }
    
    public function render_dashboard($atts) {
        return '<div class="simple-teacher-dashboard"><p>Plugin temporarily disabled for safety. Please check with administrator.</p></div>';
    }
    
    public function ajax_get_group_students() {
        wp_send_json_error('Plugin temporarily disabled');
    }
    
    public function ajax_no_permission() {
        wp_send_json_error('Plugin temporarily disabled');
    }
}

// Initialize the safe plugin
new Simple_Teacher_Dashboard_Safe();
