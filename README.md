# lilac-Simple-Teacher-Dashboard-

A WordPress plugin that provides a clean, interactive dashboard for teachers to view their groups and students.

## Features

**User Role Detection**: Automatically detects teacher roles (`school_teacher`, `instructor`, `Instructor`, `wdm_instructor`)
**Group Management**: Shows all groups assigned to the teacher
**Student Lists**: Interactive display of students within each group
**Responsive Design**: Works on desktop and mobile devices
**Clean UI**: Modern, professional interface
**Stable Queries**: Uses proven SQL patterns from QUERIES_SUCCESS.md

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin panel
3. Add the shortcode `[teacher_dashboard]` to any page or post

## Usage

### Shortcode
```
[teacher_dashboard]
```

### User Requirements
The plugin will only display the dashboard for users who have:
- One of the teacher roles: `school_teacher`, `instructor`, `Instructor`, `wdm_instructor`
- OR have LearnDash group leader meta keys in the database

### How It Works

1. **Login Check**: Verifies user is logged in
2. **Role Verification**: Checks if user has teacher permissions
3. **Group Retrieval**: Finds all groups where user is a leader
4. **Interactive Display**: Shows group buttons for selection
5. **Student Lists**: Displays students when a group is selected

## Technical Details

### Database Queries
The plugin uses optimized SQL queries based on the working patterns documented in `QUERIES_SUCCESS.md`:

- **Teacher Detection**: Looks for `%group_leader%` meta keys
- **Group Retrieval**: Uses `CAST(SUBSTRING_INDEX(meta_key, '_', -1) AS UNSIGNED)` pattern
- **Student Lists**: Queries `learndash_group_users_{group_id}` meta keys

### File Structure
```
simple-teacher-dashboard/
├── simple-teacher-dashboard.php    # Main plugin file
├── check_user_group.php           # Utility script
├── QUERIES_SUCCESS.md             # Working SQL queries documentation
├── README.md                      # This file
└── test-shortcode.html           # Usage instructions
```

## Version History

### Version 2.0.0 (Current)
- Complete rewrite with stable implementation
- Improved user role detection
- Interactive group selection interface
- Modern responsive design
- Optimized database queries
- Better error handling

### Version 1.0.0 (Previous)
- Basic functionality (had issues)
- Multiple query methods (inefficient)
- Static display

## Future Enhancements

**Phase 2**: Class and individual grades display
**Phase 3**: Quiz statistics and success rates
**Phase 4**: Export functionality
**Phase 5**: Advanced filtering and search

## Support

For issues or questions, refer to:
1. `QUERIES_SUCCESS.md` for database query patterns
2. `test-shortcode.html` for usage instructions
3. Plugin code comments for technical details

## Requirements

- WordPress 5.0+
- LearnDash LMS plugin
- jQuery (automatically loaded)
- Users with appropriate teacher roles or group leader permissions
