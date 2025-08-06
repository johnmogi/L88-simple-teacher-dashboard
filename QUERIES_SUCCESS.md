# LearnDash Quiz Success Rate Queries

## 1. Individual Quiz Attempts Query
```sql
SELECT 
    s.ID as student_id,
    s.display_name as student_name,
    q.post_title as quiz_name,
    c.post_title as course_name,
    MAX(ua.activity_status) as latest_status,
    MAX(ua.activity_completed) as latest_completion,
    COUNT(ua.activity_id) as attempts,
    SUM(CASE WHEN ua.activity_status = 1 THEN 1 ELSE 0 END) as successful_attempts,
    ROUND((SUM(CASE WHEN ua.activity_status = 1 THEN 1 ELSE 0 END) / COUNT(ua.activity_id) * 100), 2) as success_rate,
    DATE_FORMAT(FROM_UNIXTIME(MAX(ua.activity_completed)), '%Y-%m-%d %H:%i:%s') as last_attempt_date
FROM edc_learndash_user_activity ua
JOIN edc_users s ON ua.user_id = s.ID
JOIN edc_posts q ON ua.post_id = q.ID
JOIN edc_posts c ON ua.course_id = c.ID
WHERE ua.activity_type = 'quiz'
GROUP BY s.ID, q.ID, c.ID
ORDER BY s.display_name, c.post_title, q.post_title;
```

### Fields:
- student_id: Unique student ID
- student_name: Student's display name
- quiz_name: Name of the quiz (note: may show as question marks due to encoding)
- course_name: Name of the course
- latest_status: 0 for incomplete, 1 for completed
- latest_completion: Unix timestamp of last attempt
- attempts: Number of attempts for this quiz
- successful_attempts: Number of successful attempts
- success_rate: Percentage of successful attempts
- last_attempt_date: Formatted date/time of last attempt

## 2. Student Summary Query
```sql
SELECT 
    s.ID as student_id,
    s.display_name as student_name,
    COUNT(DISTINCT ua.post_id) as total_quizzes,
    SUM(CASE WHEN ua.activity_status = 1 THEN 1 ELSE 0 END) as completed_quizzes,
    ROUND((SUM(CASE WHEN ua.activity_status = 1 THEN 1 ELSE 0 END) / 
           COUNT(DISTINCT ua.post_id) * 100), 2) as overall_success_rate,
    GROUP_CONCAT(DISTINCT c.post_title ORDER BY c.post_title SEPARATOR ', ') as courses,
    MIN(FROM_UNIXTIME(ua.activity_completed)) as first_quiz_date,
    MAX(FROM_UNIXTIME(ua.activity_completed)) as last_quiz_date
FROM edc_learndash_user_activity ua
JOIN edc_users s ON ua.user_id = s.ID
JOIN edc_posts c ON ua.course_id = c.ID
WHERE ua.activity_type = 'quiz'
GROUP BY s.ID
ORDER BY overall_success_rate DESC;
```

### Fields:
- student_id: Unique student ID
- student_name: Student's display name
- total_quizzes: Total number of unique quizzes attempted
- completed_quizzes: Number of quizzes completed successfully
- overall_success_rate: Percentage of quizzes completed successfully
- courses: List of courses the student has quizzes in
- first_quiz_date: Date of first quiz attempt
- last_quiz_date: Date of last quiz attempt

## 3. Group Success Rate Query
```sql
SELECT 
    g.post_title as group_name,
    t.display_name as teacher_name,
    COUNT(DISTINCT s.ID) as student_count,
    COUNT(DISTINCT CASE 
        WHEN ua.activity_type = 'quiz' AND ua.activity_status = 1 THEN ua.post_id 
        ELSE NULL 
    END) as total_successful_attempts,
    COUNT(DISTINCT CASE 
        WHEN ua.activity_type = 'quiz' THEN ua.post_id 
        ELSE NULL 
    END) as total_attempts,
    ROUND((COUNT(DISTINCT CASE 
        WHEN ua.activity_type = 'quiz' AND ua.activity_status = 1 THEN ua.post_id 
        ELSE NULL 
    END) / COUNT(DISTINCT CASE 
        WHEN ua.activity_type = 'quiz' THEN ua.post_id 
        ELSE NULL 
    END) * 100), 2) as group_success_rate,
    GROUP_CONCAT(DISTINCT CONCAT(
        s.display_name, ' (', 
        ROUND((COUNT(CASE WHEN ua.activity_type = 'quiz' AND ua.activity_status = 1 THEN ua.post_id END) / 
               COUNT(CASE WHEN ua.activity_type = 'quiz' THEN ua.post_id END) * 100), 2), 
        '%)')
        ORDER BY s.display_name SEPARATOR ', ') as student_success_rates
FROM edc_learndash_user_activity ua
JOIN edc_users s ON ua.user_id = s.ID
JOIN edc_usermeta sm ON s.ID = sm.user_id
JOIN edc_usermeta tm ON tm.meta_key LIKE 'learndash_group_leaders_%'
JOIN edc_users t ON tm.user_id = t.ID
JOIN edc_posts g ON g.ID = SUBSTRING_INDEX(tm.meta_key, '_', -1)
JOIN edc_posts c ON ua.course_id = c.ID
WHERE ua.activity_type = 'quiz'
AND sm.meta_key LIKE 'learndash_group_users_%'
AND g.ID = SUBSTRING_INDEX(sm.meta_key, '_', -1)
AND g.post_type = 'groups'
AND g.post_status = 'publish'
GROUP BY g.ID, g.post_title, t.display_name
ORDER BY group_success_rate DESC;
```

### Fields:
- group_name: Name of the group
- teacher_name: Name of the group teacher
- student_count: Number of students in the group
- total_successful_attempts: Number of successful quiz attempts across all students
- total_attempts: Total number of quiz attempts across all students
- group_success_rate: Percentage success rate for the entire group
- student_success_rates: List of individual student success rates

## Notes on Implementation
1. All queries use proper GROUP BY clauses to comply with MySQL's ONLY_FULL_GROUP_BY mode
2. Success rates are calculated as (successful_attempts / total_attempts) * 100
3. Date formatting uses FROM_UNIXTIME to convert timestamps
4. GROUP_CONCAT is used to aggregate multiple values into single strings
5. All queries include proper JOIN conditions to maintain data integrity

## Common Issues and Solutions
1. **Encoding Issues**: 
   - Quiz and course names may appear as question marks due to character encoding
   - This is a display issue and doesn't affect the query results

2. **Time Zone**: 
   - All timestamps are stored in Unix time and converted to local time zone
   - Adjust the date format if needed using MySQL's CONVERT_TZ function

3. **Performance Considerations**: 
   - Large datasets may require indexing on:
     - edc_learndash_user_activity(activity_type, activity_status, user_id)
     - edc_users(ID)
     - edc_posts(ID, post_type)
     - edc_usermeta(user_id, meta_key)

## Additional Metrics to Consider
1. Time-based analysis:
   - Average time taken per quiz
   - Time between quiz attempts
   - Success rate over time

2. Course-specific metrics:
   - Success rate per course
   - Difficulty level analysis
   - Common failure points

3. Teacher-specific metrics:
   - Teacher success rate across all groups
   - Teacher improvement over time
   - Teacher-student success rate correlation


//  student GROUPS 

SELECT 
    -- Teacher Information
    t.ID as teacher_id,
    t.display_name as teacher_name,
    t.user_login as teacher_login,
    t.user_email as teacher_email,
    
    -- Group Information
    g.ID as group_id,
    g.post_title as group_name,
    g.post_status as group_status,
    
    -- Student Information
    s.ID as student_id,
    s.display_name as student_name,
    s.user_login as student_login,
    s.user_email as student_email
FROM edc_users t
-- Get all group leaders
JOIN edc_usermeta glm ON t.ID = glm.user_id 
    AND glm.meta_key LIKE '%group_leader%'
JOIN edc_posts g ON g.ID = CAST(SUBSTRING_INDEX(glm.meta_key, '_', -1) AS UNSIGNED)
-- Get students in those groups
JOIN edc_usermeta sm ON sm.meta_key = CONCAT('learndash_group_users_', g.ID)
JOIN edc_users s ON s.ID = sm.user_id
WHERE g.post_status = 'publish'
ORDER BY t.display_name, g.post_title, s.display_name;



mysql> SELECT 
    ->     c.id AS class_id,
    ->     c.name AS class_name,
    ->     c.teacher_id,
    ->     u.user_login AS teacher_username,
    ->     u.user_email AS teacher_email
    -> FROM
    ->     edc_school_classes c
    -> LEFT JOIN
    ->     edc_users u ON c.teacher_id = u.ID
    -> WHERE
    ->     c.teacher_id > 0;
+----------+------------+------------+------------------+-------------------------+
| class_id | class_name | teacher_id | teacher_username | teacher_email           |
+----------+------------+------------+------------------+-------------------------+
|        1 | 2          |         62 | test.teacher     | test.teacher@school.edu |
|        3 | TEST       |         61 | testteacher3     | teacher3@test.com       |
|        4 | TEST       |         24 | 97250938246      | dev@johnmogi.com        |
|        7 | TA2        |         14 | 0501234567       | teacher1@school.edu     |
|    10017 | TA         |         38 | 0512345678       | teacher32@school.edu    |
+----------+------------+------------+------------------+-------------------------+
5 rows in set (0.00 sec)

mysql> -- Check all LearnDash groups
mysql> SELECT 
    ->     p.ID AS group_id,
    ->     p.post_title AS group_name,
    ->     p.post_status AS status,
    ->     p.post_author AS creator_id,
    ->     u.user_login AS creator_username
    -> FROM
    ->     edc_posts p
    -> LEFT JOIN
    ->     edc_users u ON p.post_author = u.ID
    -> WHERE
    ->     p.post_type = 'groups'
    ->     AND p.post_status = 'publish'
    -> ORDER BY
    ->     p.post_title;
+----------+------------------+---------+------------+------------------+
| group_id | group_name       | status  | creator_id | creator_username |
+----------+------------------+---------+------------+------------------+
|    10013 | 2                | publish |         18 | 44.44            |
|    10020 | bin              | publish |         38 | 0512345678       |
|    10030 | Class: 2         | publish |          1 | testihrt_admin   |
|    10028 | Class: TA        | publish |          1 | testihrt_admin   |
|    10025 | Class: TA2       | publish |          1 | testihrt_admin   |
|    10027 | Class: TEST      | publish |          1 | testihrt_admin   |
|    10029 | Class: TEST      | publish |          1 | testihrt_admin   |
|     9762 | Default Class    | publish |          1 | testihrt_admin   |
|     9876 | e32              | publish |          1 | testihrt_admin   |
|    10017 | TA               | publish |         24 | 97250938246      |
|     9805 | test             | publish |          1 | testihrt_admin   |
|    10014 | TEST             | publish |         18 | 44.44            |
|    10015 | TEST             | publish |         14 | 0501234567       |
|    10016 | TEST             | publish |         24 | 97250938246      |
|     9774 | ??????? ?????? ? | publish |          1 | testihrt_admin   |
+----------+------------------+---------+------------+------------------+
15 rows in set (0.00 sec)


ERROR 1146 (42S02): Table 'local.edc_learndash_user_groups' doesn't exist
mysql> -- Check if we can find the group users table
mysql> SHOW TABLES LIKE 'edc_learndash%';
+--------------------------------------+
| Tables_in_local (edc_learndash%)     |
+--------------------------------------+
| edc_learndash_pro_quiz_category      |
| edc_learndash_pro_quiz_form          |
| edc_learndash_pro_quiz_lock          |
| edc_learndash_pro_quiz_master        |
| edc_learndash_pro_quiz_prerequisite  |
| edc_learndash_pro_quiz_question      |
| edc_learndash_pro_quiz_statistic     |
| edc_learndash_pro_quiz_statistic_ref |
| edc_learndash_pro_quiz_template      |
| edc_learndash_pro_quiz_toplist       |
| edc_learndash_user_activity          |
| edc_learndash_user_activity_meta     |
+--------------------------------------+
12 rows in set (0.00 sec)

mysql> -- Check student-class relationships
mysql> SELECT 
    ->     sc.class_id,
    ->     c.name AS class_name,
    ->     c.teacher_id,
    ->     u.user_login AS teacher_username,
    ->     COUNT(DISTINCT sc.student_id) AS student_count
    -> FROM
    ->     edc_school_student_classes sc
    -> JOIN
    ->     edc_school_classes c ON sc.class_id = c.id
    -> LEFT JOIN
    ->     edc_users u ON c.teacher_id = u.ID
    -> GROUP BY
    ->     sc.class_id, c.name, c.teacher_id, u.user_login
    -> ORDER BY 
    ->     student_count DESC;
+----------+------------+------------+------------------+---------------+
| class_id | class_name | teacher_id | teacher_username | student_count |
+----------+------------+------------+------------------+---------------+
|        1 | 2          |         62 | test.teacher     |             3 |
|        3 | TEST       |         61 | testteacher3     |             3 |
|        2 | TEST       |          0 | NULL             |             2 |
|        4 | TEST       |         24 | 97250938246      |             2 |
+----------+------------+------------+------------------+---------------+
4 rows in set (0.01 sec)

mysql> -- Check teacher-student direct relationships
mysql> SELECT 
    ->     ts.teacher_id,
    ->     u.user_login AS teacher_username,
    ->     COUNT(DISTINCT ts.student_id) AS student_count
    -> FROM 
    ->     edc_school_teacher_students ts
    -> JOIN
    ->     edc_users u ON ts.teacher_id = u.ID
    -> GROUP BY 
    ->     ts.teacher_id, u.user_login
    -> ORDER BY
    ->     student_count DESC;
Empty set (0.01 sec)

mysql> -- Check if any users are assigned to groups
mysql> SELECT 
    ->     g.ID AS group_id,
    ->     g.post_title AS group_name,
    ->     um.meta_value AS group_users
    -> FROM
    ->     edc_posts g
    -> LEFT JOIN
    ->     edc_postmeta um ON g.ID = um.post_id AND um.meta_key = 'ld_group_users'
    -> WHERE 
    ->     g.post_type = 'groups'
    ->     AND g.post_status = 'publish'
    -> ORDER BY
    ->     g.post_title;
+----------+------------------+-------------+
| group_id | group_name       | group_users |
+----------+------------------+-------------+
|    10013 | 2                | NULL        |
|    10020 | bin              | NULL        |
|    10030 | Class: 2         | NULL        |
|    10028 | Class: TA        | NULL        |
|    10025 | Class: TA2       | NULL        |
|    10027 | Class: TEST      | NULL        |
|    10029 | Class: TEST      | NULL        |
|     9762 | Default Class    | NULL        |
|     9876 | e32              | NULL        |
|    10017 | TA               | NULL        |
|     9805 | test             | NULL        |
|    10014 | TEST             | NULL        |
|    10015 | TEST             | NULL        |
|    10016 | TEST             | NULL        |
|     9774 | ??????? ?????? ? | NULL        |
+----------+------------------+-------------+
15 rows in set (0.01 sec)


+----------------+
| total_students |
+----------------+
|             41 |
+----------------+
1 row in set (0.00 sec)

mysql> -- Check if students are assigned to any classes
mysql> SELECT 
    ->     sc.class_id,
    ->     c.name AS class_name,
    ->     c.teacher_id,
    ->     u.user_login AS teacher_username,
    ->     COUNT(DISTINCT sc.student_id) AS student_count
    -> FROM
    ->     edc_school_student_classes sc
    -> JOIN 
    ->     edc_school_classes c ON sc.class_id = c.id
    -> LEFT JOIN 
    ->     edc_users u ON c.teacher_id = u.ID
    -> GROUP BY 
    ->     sc.class_id, c.name, c.teacher_id, u.user_login
    -> ORDER BY
    ->     student_count DESC;
+----------+------------+------------+------------------+---------------+
| class_id | class_name | teacher_id | teacher_username | student_count |
+----------+------------+------------+------------------+---------------+
|        1 | 2          |         62 | test.teacher     |             3 |
|        3 | TEST       |         61 | testteacher3     |             3 |
|        2 | TEST       |          0 | NULL             |             2 |
|        4 | TEST       |         24 | 97250938246      |             2 |
+----------+------------+------------+------------------+---------------+
4 rows in set (0.00 sec)

mysql> -- Check if groups have any members through postmeta
mysql> SELECT
    ->     g.ID AS group_id,
    ->     g.post_title AS group_name,
    ->     um.meta_key,
    ->     LENGTH(um.meta_value) AS meta_value_length
    -> FROM 
    ->     edc_posts g
    -> LEFT JOIN
    ->     edc_postmeta um ON g.ID = um.post_id 
    ->     AND um.meta_key IN ('ld_group_leaders', 'ld_group_users')
    -> WHERE
    ->     g.post_type = 'groups'
    ->     AND g.post_status = 'publish'
    -> ORDER BY
    ->     g.post_title;
+----------+------------------+----------+-------------------+
| group_id | group_name       | meta_key | meta_value_length |
+----------+------------------+----------+-------------------+
|    10013 | 2                | NULL     |              NULL |
|    10020 | bin              | NULL     |              NULL |
|    10030 | Class: 2         | NULL     |              NULL |
|    10028 | Class: TA        | NULL     |              NULL |
|    10025 | Class: TA2       | NULL     |              NULL |
|    10027 | Class: TEST      | NULL     |              NULL |
|    10029 | Class: TEST      | NULL     |              NULL |
|     9762 | Default Class    | NULL     |              NULL |
|     9876 | e32              | NULL     |              NULL |
|    10017 | TA               | NULL     |              NULL |
|     9805 | test             | NULL     |              NULL |
|    10014 | TEST             | NULL     |              NULL |
|    10015 | TEST             | NULL     |              NULL |
|    10016 | TEST             | NULL     |              NULL |
|     9774 | ??????? ?????? ? | NULL     |              NULL |
+----------+------------------+----------+-------------------+
15 rows in set (0.00 sec)

mysql> -- Verify teacher capabilities
mysql> SELECT 
    ->     u.ID,
    ->     u.user_login,
    ->     u.user_email,
    ->     um.meta_value AS capabilities
    -> FROM
    ->     edc_users u
    -> JOIN
    ->     edc_usermeta um ON u.ID = um.user_id
    -> WHERE
    ->     um.meta_key = 'wp_capabilities'
    ->     AND um.meta_value LIKE '%stm_lms_instructor%'
    -> ORDER BY
    ->     u.user_login;
+----+---------------------+-------------------------+--------------------------------------+
| ID | user_login          | user_email              | capabilities                         |
+----+---------------------+-------------------------+--------------------------------------+
| 14 | 0501234567          | teacher1@school.edu     | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 38 | 0512345678          | teacher32@school.edu    | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 39 | 0523456789          | teacher4@school.edu     | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 15 | 0529876543          | teacher2@school.edu     | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 40 | 0534567890          | teacher5@school.edu     | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 16 | 0545556677          | 0545556677@school.edu   | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 41 | 0545678901          | teacher6@school.edu     | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 42 | 0556789012          | teacher7@school.edu     | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 43 | 0567890123          | teacher8@school.edu     | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 55 | 1212                |                         | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 55 | 1212                |                         | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 12 | 2.2                 | anguru@gmail.com        | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 64 | 2222222222_22222222 | yo2ni@aviv-digital.com  | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 18 | 44.44               | inf44o@aviv-digital.com | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 23 | 5656456546456       | ter@fdse.vp             | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 24 | 97250938246         | dev@johnmogi.com        | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 62 | test.teacher        | test.teacher@school.edu | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 59 | testteacher1        | teacher1@test.com       | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 60 | testteacher2        | teacher2@test.com       | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 61 | testteacher3        | teacher3@test.com       | a:1:{s:18:"stm_lms_instructor";b:1;} |
+----+---------------------+-------------------------+--------------------------------------+
20 rows in set (0.00 sec)





USUARIO@DESKTOP-GSCHD4K MINGW64 ~/Documents/SITES/LILAC/207lilac
$ mys -P 10089
mysql: [Warning] Using a password on the command line interface can be insecure.
Welcome to the MySQL monitor.  Commands end with ; or \g.
Your MySQL connection id is 56
Server version: 8.0.35 MySQL Community Server - GPL

Copyright (c) 2000, 2023, Oracle and/or its affiliates.

Oracle is a registered trademark of Oracle Corporation and/or its
affiliates. Other names may be trademarks of their respective
owners.

Type 'help;' or '\h' for help. Type '\c' to clear the current input statement.

mysql> show tables;
+--------------------------------------------------+
| Tables_in_local                                  |
+--------------------------------------------------+
| edc_actionscheduler_actions                      |
| edc_actionscheduler_claims                       |
| edc_actionscheduler_groups                       |
| edc_actionscheduler_logs                         |
| edc_addonlibrary_addons                          |
| edc_addonlibrary_categories                      |
| edc_chaty_contact_form_leads                     |
| edc_commentmeta                                  |
| edc_comments                                     |
| edc_e_events                                     |
| edc_e_notes                                      |
| edc_e_notes_users_relations                      |
| edc_e_submissions                                |
| edc_e_submissions_actions_log                    |
| edc_e_submissions_values                         |
| edc_edc_registration_codes                       |
| edc_edc_school_promo_code_usage                  |
| edc_edc_school_promo_codes                       |
| edc_gf_draft_submissions                         |
| edc_gf_entry                                     |
| edc_gf_entry_meta                                |
| edc_gf_entry_notes                               |
| edc_gf_form                                      |
| edc_gf_form_meta                                 |
| edc_gf_form_revisions                            |
| edc_gf_form_view                                 |
| edc_ir_commission_logs                           |
| edc_ir_paypal_payouts_transactions               |
| edc_jet_post_types                               |
| edc_jet_taxonomies                               |
| edc_ld_course_time_spent                         |
| edc_ld_quiz_entries                              |
| edc_ld_time_entries                              |
| edc_learndash_pro_quiz_category                  |
| edc_learndash_pro_quiz_form                      |
| edc_learndash_pro_quiz_lock                      |
| edc_learndash_pro_quiz_master                    |
| edc_learndash_pro_quiz_prerequisite              |
| edc_learndash_pro_quiz_question                  |
| edc_learndash_pro_quiz_statistic                 |
| edc_learndash_pro_quiz_statistic_ref             |
| edc_learndash_pro_quiz_template                  |
| edc_learndash_pro_quiz_toplist                   |
| edc_learndash_user_activity                      |
| edc_learndash_user_activity_meta                 |
| edc_lilac_user_subscriptions                     |
| edc_links                                        |
| edc_options                                      |
| edc_postmeta                                     |
| edc_posts                                        |
| edc_registration_code_usage                      |
| edc_registration_codes                           |
| edc_school_classes                               |
| edc_school_promo_codes                           |
| edc_school_student_classes                       |
| edc_school_students                              |
| edc_school_teacher_students                      |
| edc_site_mail_logs                               |
| edc_site_mail_statuses                           |
| edc_site_mail_suppressions                       |
| edc_stm_lms_curriculum_materials                 |
| edc_stm_lms_curriculum_sections                  |
| edc_stm_lms_order_items                          |
| edc_stm_lms_user_answers                         |
| edc_stm_lms_user_cart                            |
| edc_stm_lms_user_chat                            |
| edc_stm_lms_user_conversation                    |
| edc_stm_lms_user_courses                         |
| edc_stm_lms_user_lessons                         |
| edc_stm_lms_user_quizzes                         |
| edc_stm_lms_user_quizzes_times                   |
| edc_stm_lms_user_searches                        |
| edc_stm_lms_user_searches_stats                  |
| edc_teacher_import_logs                          |
| edc_term_relationships                           |
| edc_term_taxonomy                                |
| edc_termmeta                                     |
| edc_terms                                        |
| edc_um_metadata                                  |
| edc_user_registration_sessions                   |
| edc_usermeta                                     |
| edc_users                                        |
| edc_wc_admin_note_actions                        |
| edc_wc_admin_notes                               |
| edc_wc_category_lookup                           |
| edc_wc_customer_lookup                           |
| edc_wc_download_log                              |
| edc_wc_order_addresses                           |
| edc_wc_order_coupon_lookup                       |
| edc_wc_order_operational_data                    |
| edc_wc_order_product_lookup                      |
| edc_wc_order_stats                               |
| edc_wc_order_tax_lookup                          |
| edc_wc_orders                                    |
| edc_wc_orders_meta                               |
| edc_wc_product_attributes_lookup                 |
| edc_wc_product_download_directories              |
| edc_wc_product_meta_lookup                       |
| edc_wc_rate_limits                               |
| edc_wc_reserved_stock                            |
| edc_wc_tax_rate_classes                          |
| edc_wc_webhooks                                  |
| edc_wdm_instructor_commission                    |
| edc_woocommerce_api_keys                         |
| edc_woocommerce_attribute_taxonomies             |
| edc_woocommerce_downloadable_product_permissions |
| edc_woocommerce_log                              |
| edc_woocommerce_order_itemmeta                   |
| edc_woocommerce_order_items                      |
| edc_woocommerce_payment_tokenmeta                |
| edc_woocommerce_payment_tokens                   |
| edc_woocommerce_sessions                         |
| edc_woocommerce_shipping_zone_locations          |
| edc_woocommerce_shipping_zone_methods            |
| edc_woocommerce_shipping_zones                   |
| edc_woocommerce_tax_rate_locations               |
| edc_woocommerce_tax_rates                        |
| edc_wrld_cached_entries                          |
| edc_yith_ywsbs_activities_log                    |
| edc_yith_ywsbs_order_lookup                      |
| edc_yith_ywsbs_revenue_lookup                    |
| edc_yith_ywsbs_stats                             |
| edc_yoast_indexable                              |
| edc_yoast_indexable_hierarchy                    |
| edc_yoast_migrations                             |
| edc_yoast_primary_term                           |
| edc_yoast_seo_links                              |
+--------------------------------------------------+
127 rows in set (0.00 sec)

mysql> -- Teachers are assigned to classes in edc_school_classes.teacher_id
mysql> SELECT * FROM edc_school_classes WHERE teacher_id IS NOT NULL;
+-------+------+-------------+------------+----------+-----------+---------------------+---------------------+
| id    | name | description | teacher_id | group_id | course_id | created_at          | updated_at          |
+-------+------+-------------+------------+----------+-----------+---------------------+---------------------+
|     1 | 2    | 2           |         62 |    10030 |      NULL | 2025-07-01 13:33:07 | 2025-07-23 10:28:58 |
|     2 | TEST |             |          0 |     NULL |      NULL | 2025-07-01 20:08:08 | 2025-07-23 10:08:00 |
|     3 | TEST |             |         61 |    10029 |      NULL | 2025-07-09 09:38:06 | 2025-07-23 10:28:58 |
|     4 | TEST |             |         24 |    10027 |      NULL | 2025-07-09 11:35:13 | 2025-07-23 10:28:58 |
|     5 | TA   |             |          0 |     NULL |      NULL | 2025-07-09 11:42:49 | 2025-07-23 10:08:00 |
|     6 | bin  |             |          0 |     NULL |      NULL | 2025-07-22 06:20:24 | 2025-07-23 10:08:00 |
|     7 | TA2  | 2           |         14 |    10025 |      NULL | 2025-07-23 08:17:54 | 2025-07-23 10:00:09 |
| 10017 | TA   | NULL        |         38 |    10028 |      NULL | 2025-07-23 10:08:00 | 2025-07-23 10:28:58 |
+-------+------+-------------+------------+----------+-----------+---------------------+---------------------+
8 rows in set (0.01 sec)

mysql> -- Get teacher for a specific class
mysql> SELECT 
    ->     c.id AS class_id,
    ->     c.name AS class_name,
    ->     t.ID AS teacher_id,
    ->     t.user_login,
    ->     t.display_name,
    ->     t.user_email
    -> FROM
    ->     edc_school_classes c
    -> JOIN
    ->     edc_users t ON c.teacher_id = t.ID
    -> WHERE 
    ->     c.teacher_id IS NOT NULL;
+----------+------------+------------+--------------+-----------------------+-------------------------+
| class_id | class_name | teacher_id | user_login   | display_name          | user_email              |
+----------+------------+------------+--------------+-----------------------+-------------------------+
|        1 | 2          |         62 | test.teacher | Test Teacher          | test.teacher@school.edu |
|        3 | TEST       |         61 | testteacher3 | Bob Johnson           | teacher3@test.com       |
|        4 | TEST       |         24 | 97250938246  | Jonathan Moguillansky | dev@johnmogi.com        |
|        7 | TA2        |         14 | 0501234567   | David Cohen           | teacher1@school.edu     |
|    10017 | TA         |         38 | 0512345678   | Eitan2 Goldman2       | teacher32@school.edu    |
+----------+------------+------------+--------------+-----------------------+-------------------------+
5 rows in set (0.00 sec)

mysql> -- Check for group leaders
mysql> SELECT 
    ->     g.ID AS group_id,
    ->     g.post_title AS group_name,
    ->     u.ID AS teacher_id,
    ->     u.user_login,
    ->     u.display_name
    -> FROM
    ->     edc_posts g
    -> JOIN
    ->     edc_postmeta pm ON g.ID = pm.post_id
    -> JOIN
    ->     edc_users u ON pm.meta_value = u.ID
    -> WHERE
    ->     g.post_type = 'groups'
    ->     AND pm.meta_key = 'ld_group_leaders';
Empty set (0.01 sec)

mysql> -- Check for teacher role in user meta
mysql> SELECT 
    ->     u.ID,
    ->     u.user_login,
    ->     u.display_name,
    ->     um.meta_value AS capabilities
    -> FROM
    ->     edc_users u
    -> JOIN
    ->     edc_usermeta um ON u.ID = um.user_id
    -> WHERE 
    ->     um.meta_key = 'wp_capabilities'
    ->     AND (um.meta_value LIKE '%stm_lms_instructor%' 
    ->          OR um.meta_value LIKE '%wdm_instructor%');
+----+---------------------+-----------------------+--------------------------------------+
| ID | user_login          | display_name          | capabilities                         |
+----+---------------------+-----------------------+--------------------------------------+
| 14 | 0501234567          | David Cohen           | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 55 | 1212                | 1212                  | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 55 | 1212                | 1212                  | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 38 | 0512345678          | Eitan2 Goldman2       | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 39 | 0523456789          | Lior Katz             | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 15 | 0529876543          | Sarah Levi            | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 40 | 0534567890          | Yasmin Peretz         | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 16 | 0545556677          | Rachel Meir           | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 41 | 0545678901          | Yasmin Peretz         | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 42 | 0556789012          | Lior Katz             | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 43 | 0567890123          | Yael Dahan            | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 18 | 44.44               | 44 44                 | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 23 | 5656456546456       | rgr rgrg              | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 24 | 97250938246         | Jonathan Moguillansky | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 59 | testteacher1        | John Doe              | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 60 | testteacher2        | Jane Smith            | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 61 | testteacher3        | Bob Johnson           | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 12 | 2.2                 | 0501234567            | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 62 | test.teacher        | Test Teacher          | a:1:{s:18:"stm_lms_instructor";b:1;} |
| 64 | 2222222222_22222222 | 2222222222 22222222   | a:1:{s:18:"stm_lms_instructor";b:1;} |
+----+---------------------+-----------------------+--------------------------------------+
20 rows in set (0.01 sec)

mysql> -- Get all possible teacher relationships
mysql> SELECT DISTINCT
    ->     u.ID AS teacher_id,
    ->     u.user_login,
    ->     u.display_name,
    ->     u.user_email,
    ->     CASE
    ->         WHEN um.meta_value LIKE '%stm_lms_instructor%' THEN 'stm_lms_instructor'
    ->         WHEN um.meta_value LIKE '%wdm_instructor%' THEN 'wdm_instructor'
    ->         WHEN pm.meta_key = 'ld_group_leaders' THEN 'group_leader'
    ->         ELSE 'unknown'
    ->     END AS teacher_type,
    ->     GROUP_CONCAT(DISTINCT c.name) AS classes_taught,
    ->     GROUP_CONCAT(DISTINCT g.post_title) AS groups_led
    -> FROM
    ->     edc_users u
    -> -- Check user capabilities
    -> LEFT JOIN
    ->     edc_usermeta um ON u.ID = um.user_id 
    ->     AND um.meta_key = 'wp_capabilities'
    -> -- Check class assignments
    -> LEFT JOIN 
    ->     edc_school_classes c ON u.ID = c.teacher_id
    -> -- Check group leadership
    -> LEFT JOIN
    ->     edc_postmeta pm ON pm.meta_value = u.ID 
    ->     AND pm.meta_key = 'ld_group_leaders'
    -> LEFT JOIN
    ->     edc_posts g ON pm.post_id = g.ID
    -> WHERE
    ->     um.meta_value LIKE '%stm_lms_instructor%'
    ->     OR um.meta_value LIKE '%wdm_instructor%'
    ->     OR pm.meta_key = 'ld_group_leaders'
    -> GROUP BY
    ->     u.ID, u.user_login, u.display_name, u.user_email, teacher_type;
+------------+---------------------+-----------------------+-------------------------+--------------------+----------------+------------+
| teacher_id | user_login          | display_name          | user_email              | teacher_type       | classes_taught | groups_led |
+------------+---------------------+-----------------------+-------------------------+--------------------+----------------+------------+
|         12 | 2.2                 | 0501234567            | anguru@gmail.com        | stm_lms_instructor | NULL           | NULL       |
|         14 | 0501234567          | David Cohen           | teacher1@school.edu     | stm_lms_instructor | TA2            | NULL       |
|         15 | 0529876543          | Sarah Levi            | teacher2@school.edu     | stm_lms_instructor | NULL           | NULL       |
|         16 | 0545556677          | Rachel Meir           | 0545556677@school.edu   | stm_lms_instructor | NULL           | NULL       |
|         18 | 44.44               | 44 44                 | inf44o@aviv-digital.com | stm_lms_instructor | NULL           | NULL       |
|         23 | 5656456546456       | rgr rgrg              | ter@fdse.vp             | stm_lms_instructor | NULL           | NULL       |
|         24 | 97250938246         | Jonathan Moguillansky | dev@johnmogi.com        | stm_lms_instructor | TEST           | NULL       |
|         38 | 0512345678          | Eitan2 Goldman2       | teacher32@school.edu    | stm_lms_instructor | TA             | NULL       |
|         39 | 0523456789          | Lior Katz             | teacher4@school.edu     | stm_lms_instructor | NULL           | NULL       |
|         40 | 0534567890          | Yasmin Peretz         | teacher5@school.edu     | stm_lms_instructor | NULL           | NULL       |
|         41 | 0545678901          | Yasmin Peretz         | teacher6@school.edu     | stm_lms_instructor | NULL           | NULL       |
|         42 | 0556789012          | Lior Katz             | teacher7@school.edu     | stm_lms_instructor | NULL           | NULL       |
|         43 | 0567890123          | Yael Dahan            | teacher8@school.edu     | stm_lms_instructor | NULL           | NULL       |
|         55 | 1212                | 1212                  |                         | stm_lms_instructor | NULL           | NULL       |
|         59 | testteacher1        | John Doe              | teacher1@test.com       | stm_lms_instructor | NULL           | NULL       |
|         60 | testteacher2        | Jane Smith            | teacher2@test.com       | stm_lms_instructor | NULL           | NULL       |
|         61 | testteacher3        | Bob Johnson           | teacher3@test.com       | stm_lms_instructor | TEST           | NULL       |
|         62 | test.teacher        | Test Teacher          | test.teacher@school.edu | stm_lms_instructor | 2              | NULL       |
|         64 | 2222222222_22222222 | 2222222222 22222222   | yo2ni@aviv-digital.com  | stm_lms_instructor | NULL           | NULL       |
+------------+---------------------+-----------------------+-------------------------+--------------------+----------------+------------+
19 rows in set (0.01 sec)

mysql> -- Teachers assigned to classes in edc_school_classes
mysql> SELECT * FROM edc_school_classes WHERE teacher_id IS NOT NULL;]
+-------+------+-------------+------------+----------+-----------+---------------------+---------------------+
| id    | name | description | teacher_id | group_id | course_id | created_at          | updated_at          |
+-------+------+-------------+------------+----------+-----------+---------------------+---------------------+
|     1 | 2    | 2           |         62 |    10030 |      NULL | 2025-07-01 13:33:07 | 2025-07-23 10:28:58 |
|     2 | TEST |             |          0 |     NULL |      NULL | 2025-07-01 20:08:08 | 2025-07-23 10:08:00 |
|     3 | TEST |             |         61 |    10029 |      NULL | 2025-07-09 09:38:06 | 2025-07-23 10:28:58 |
|     4 | TEST |             |         24 |    10027 |      NULL | 2025-07-09 11:35:13 | 2025-07-23 10:28:58 |
|     5 | TA   |             |          0 |     NULL |      NULL | 2025-07-09 11:42:49 | 2025-07-23 10:08:00 |
|     6 | bin  |             |          0 |     NULL |      NULL | 2025-07-22 06:20:24 | 2025-07-23 10:08:00 |
|     7 | TA2  | 2           |         14 |    10025 |      NULL | 2025-07-23 08:17:54 | 2025-07-23 10:00:09 |
| 10017 | TA   | NULL        |         38 |    10028 |      NULL | 2025-07-23 10:08:00 | 2025-07-23 10:28:58 |
+-------+------+-------------+------------+----------+-----------+---------------------+---------------------+
8 rows in set (0.01 sec)

    -> ;
ERROR 1064 (42000): You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near ']' at line 1
mysql> SELECT 
    ->     u.ID, 
    ->     u.user_login,
    ->     u.display_name,
    ->     u.user_email,
    ->     GROUP_CONCAT(DISTINCT c.name) AS classes_taught
    -> FROM
    ->     edc_users u
    -> JOIN 
    ->     edc_usermeta um ON u.ID = um.user_id
    ->     AND um.meta_key = 'wp_capabilities'
    -> LEFT JOIN
    ->     edc_school_classes c ON u.ID = c.teacher_id
    -> WHERE 
    ->     um.meta_value LIKE '%stm_lms_instructor%'
    -> GROUP BY 
    ->     u.ID, u.user_login, u.display_name, u.user_email;
+----+---------------------+-----------------------+-------------------------+----------------+
| ID | user_login          | display_name          | user_email              | classes_taught |
+----+---------------------+-----------------------+-------------------------+----------------+
| 12 | 2.2                 | 0501234567            | anguru@gmail.com        | NULL           |
| 14 | 0501234567          | David Cohen           | teacher1@school.edu     | TA2            |
| 15 | 0529876543          | Sarah Levi            | teacher2@school.edu     | NULL           |
| 16 | 0545556677          | Rachel Meir           | 0545556677@school.edu   | NULL           |
| 18 | 44.44               | 44 44                 | inf44o@aviv-digital.com | NULL           |
| 23 | 5656456546456       | rgr rgrg              | ter@fdse.vp             | NULL           |
| 24 | 97250938246         | Jonathan Moguillansky | dev@johnmogi.com        | TEST           |
| 38 | 0512345678          | Eitan2 Goldman2       | teacher32@school.edu    | TA             |
| 39 | 0523456789          | Lior Katz             | teacher4@school.edu     | NULL           |
| 40 | 0534567890          | Yasmin Peretz         | teacher5@school.edu     | NULL           |
| 41 | 0545678901          | Yasmin Peretz         | teacher6@school.edu     | NULL           |
| 42 | 0556789012          | Lior Katz             | teacher7@school.edu     | NULL           |
| 43 | 0567890123          | Yael Dahan            | teacher8@school.edu     | NULL           |
| 55 | 1212                | 1212                  |                         | NULL           |
| 59 | testteacher1        | John Doe              | teacher1@test.com       | NULL           |
| 60 | testteacher2        | Jane Smith            | teacher2@test.com       | NULL           |
| 61 | testteacher3        | Bob Johnson           | teacher3@test.com       | TEST           |
| 62 | test.teacher        | Test Teacher          | test.teacher@school.edu | 2              |
| 64 | 2222222222_22222222 | 2222222222 22222222   | yo2ni@aviv-digital.com  | NULL           |
+----+---------------------+-----------------------+-------------------------+----------------+
19 rows in set (0.01 sec)

mysql> -- Replace TEACHER_ID with actual teacher ID
mysql> SELECT 
    ->     s.ID AS student_id,
    ->     s.user_login,
    ->     s.display_name,
    ->     s.user_email,
    ->     c.name AS class_name
    -> FROM
    ->     edc_users t
    -> JOIN
    ->     edc_school_classes c ON t.ID = c.teacher_id
    -> JOIN
    ->     edc_school_student_classes sc ON c.id = sc.class_id
    -> JOIN 
    ->     edc_users s ON sc.student_id = s.ID
    -> WHERE
    ->     t.ID = TEACHER_ID;
+------------+---------------+---------------+---------------------------+------------+
| student_id | user_login    | display_name  | user_email                | class_name |
+------------+---------------+---------------+---------------------------+------------+
|         66 | alice.johnson | Alice Johnson | alice.johnson@example.com | 2          |
|         67 | bob.smith     | Bob Smith     | bob.smith@example.com     | 2          |
|         68 | charlie.brown | Charlie Brown | charlie.brown@example.com | 2          |
|         71 | fiona.green   | Fiona Green   | fiona.green@example.com   | TEST       |
|         72 | george.wilson | George Wilson | george.wilson@example.com | TEST       |
|         73 | hannah.davis  | Hannah Davis  | hannah.davis@example.com  | TEST       |
|         74 | ian.thompson  | Ian Thompson  | ian.thompson@example.com  | TEST       |
|         75 | julia.roberts | Julia Roberts | julia.roberts@example.com | TEST       |
+------------+---------------+---------------+---------------------------+------------+
8 rows in set (0.02 sec)

mysql> -- Replace STUDENT_ID with actual student ID
mysql> SELECT 
    ->     t.ID AS teacher_id,
    ->     t.user_login,
    ->     t.display_name,
    ->     t.user_email,
    ->     c.name AS class_name
    -> FROM
    ->     edc_users s
    -> JOIN
    ->     edc_school_student_classes sc ON s.ID = sc.student_id
    -> JOIN
    ->     edc_school_classes c ON sc.class_id = c.id
    -> JOIN 
    ->     edc_users t ON c.teacher_id = t.ID
    -> WHERE
    ->     s.ID = STUDENT_ID;
+------------+--------------+-----------------------+-------------------------+------------+
| teacher_id | user_login   | display_name          | user_email              | class_name |
+------------+--------------+-----------------------+-------------------------+------------+
|         62 | test.teacher | Test Teacher          | test.teacher@school.edu | 2          |
|         62 | test.teacher | Test Teacher          | test.teacher@school.edu | 2          |
|         62 | test.teacher | Test Teacher          | test.teacher@school.edu | 2          |
|         61 | testteacher3 | Bob Johnson           | teacher3@test.com       | TEST       |
|         61 | testteacher3 | Bob Johnson           | teacher3@test.com       | TEST       |
|         61 | testteacher3 | Bob Johnson           | teacher3@test.com       | TEST       |
|         24 | 97250938246  | Jonathan Moguillansky | dev@johnmogi.com        | TEST       |
|         24 | 97250938246  | Jonathan Moguillansky | dev@johnmogi.com        | TEST       |
+------------+--------------+-----------------------+-------------------------+------------+
8 rows in set (0.00 sec)