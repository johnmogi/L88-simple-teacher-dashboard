<?php
/**
 * Check if a specific user is connected to a group
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get database handler instance
$db = Teacher_Dashboard_Database::get_instance();

// User to check
$user_name = 'davei wdm instructor';

// Query to find user's group connections
$query = "
SELECT 
    u.ID as user_id,
    u.display_name,
    g.post_title as group_name,
    g.ID as group_id,
    g.post_status
FROM {$db->get_prefix()}users u
LEFT JOIN {$db->get_prefix()}usermeta um ON u.ID = um.user_id
LEFT JOIN {$db->get_prefix()}posts g ON g.ID = CAST(SUBSTRING_INDEX(um.meta_key, '_', -1) AS UNSIGNED)
WHERE u.display_name = %s
AND um.meta_key LIKE 'learndash_group_users_%'
ORDER BY g.post_title;
";

// Prepare and execute query
$results = $db->wpdb->get_results($db->wpdb->prepare($query, $user_name));

// Display results
if (!empty($results)) {
    echo "User '$user_name' is connected to the following groups:\n";
    foreach ($results as $result) {
        echo "- Group: {$result->group_name} (ID: {$result->group_id})\n";
        echo "  Status: {$result->post_status}\n";
    }
} else {
    echo "User '$user_name' is not connected to any groups.\n";
}
