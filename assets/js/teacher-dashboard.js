jQuery(document).ready(function($) {
    // Try multiple ways to get the localized data
    var groupsData = window.teacherDashboardData || window.teacherDashboard || {};
    
    // Fallback if localized data is not available
    if (!groupsData.ajaxurl) {
        groupsData.ajaxurl = '/wp-admin/admin-ajax.php';
    }
    if (!groupsData.nonce) {
        console.warn('No nonce found in localized data');
    }
    
    console.log('Dashboard data:', groupsData);
    
    var currentStudents = [];
    var currentSort = { column: 'completed', direction: 'asc' };

    // Handle group button clicks
    $(document).on('click', '.group-btn', function() {
        var groupId = $(this).data('group-id');
        
        // Remove active class from all buttons and add to clicked one
        $('.group-btn').removeClass('active');
        $(this).addClass('active');
        
        if (!groupId) {
            $('#students-display').html('<p class="select-group-message">אנא בחר קבוצה כדי לראות את התלמידים.</p>');
            return;
        }

        // Show loading
        $('#students-display').html('<div class="loading">טוען נתונים...</div>');
        
        var ajaxUrl = groupsData.ajaxurl || '/wp-admin/admin-ajax.php';
        var nonce = groupsData.nonce || '';

        // Get students for the selected group
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_group_students',
                group_id: groupId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    currentStudents = response.data.students;
                    renderStudentsTable(currentStudents);
                } else {
                    var errorMsg = 'אירעה שגיאה';
                    if (response.data) {
                        if (typeof response.data === 'string') {
                            errorMsg += ': ' + response.data;
                        } else if (response.data.message) {
                            errorMsg += ': ' + response.data.message;
                        } else {
                            errorMsg += ': ' + JSON.stringify(response.data);
                        }
                    }
                    $('#students-display').html('<p>' + errorMsg + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', xhr, status, error);
                var errorMsg = 'אירעה שגיאה בטעינת הנתונים.';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMsg += ' שגיאה: ' + xhr.responseJSON.data;
                } else if (xhr.responseText) {
                    errorMsg += ' פרטים: ' + xhr.responseText;
                }
                $('#students-display').html('<p>' + errorMsg + '</p>');
            }
        });
    });

    // Render students table with grades and statistics
    function renderStudentsTable(students) {
        if (!students || students.length === 0) {
            $('#students-display').html('<p>אין תלמידים בקבוצה זו.</p>');
            return;
        }

        // Calculate group statistics using completed_only_rate for better average
        var studentsWithScores = students.filter(function(student) {
            return student.quiz_stats && 
                   student.quiz_stats.completed_only_rate && 
                   parseFloat(student.quiz_stats.completed_only_rate) > 0;
        });
        
        var groupAverage = 0;
        if (studentsWithScores.length > 0) {
            var totalScore = studentsWithScores.reduce(function(sum, student) {
                return sum + parseFloat(student.quiz_stats.completed_only_rate || 0);
            }, 0);
            groupAverage = totalScore / studentsWithScores.length;
        }

        var html = '<div class="group-stats">';
        html += '<h4>סטטיסטיקת הקבוצה</h4>';
        html += '<p><strong>תלמידים עם ציוני בחינות:</strong> ' + studentsWithScores.length + ' מתוך ' + students.length + '</p>';
        if (groupAverage > 0) {
            html += '<p><strong>ממוצע הקבוצה:</strong> ' + formatQuizRate(groupAverage) + '</p>';
        }
        html += '</div>';
        
        // Add export buttons
        html += '<div class="table-controls">';
        html += '<div class="export-buttons">';
        html += '<button class="export-btn" onclick="exportToCSV()">📄 ייצא ל-CSV</button> ';
        html += '<button class="print-btn" onclick="printTable()">🖨 הדפס</button>';
        html += '</div>';
        html += '</div>';

        // Sortable table with proper grade columns (no icons)
        html += '<table id="students-table" class="students-table sortable-table">';
        html += '<thead><tr>';
        html += '<th class="sortable" data-column="name">שם התלמיד</th>';
        html += '<th class="sortable" data-column="email">אימייל</th>';
        html += '<th class="sortable" data-column="course">השלמת קורס</th>';
        html += '<th class="sortable" data-column="overall">ממוצע כל הבחינות</th>';
        html += '<th class="sortable" data-column="completed">ממוצע בחינות שהושלמו</th>';
        html += '</tr></thead>';
        html += '<tbody>';

        students.forEach(function(student) {
            html += '<tr>';
            html += '<td>' + student.display_name + '</td>';
            html += '<td>' + student.user_email + '</td>';
            html += '<td>' + formatCourseCompletion(student.course_completion) + '</td>';
            html += '<td>' + formatQuizRate(student.quiz_stats ? student.quiz_stats.overall_success_rate : null) + '</td>';
            html += '<td>' + formatQuizRate(student.quiz_stats ? student.quiz_stats.completed_only_rate : null) + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';

        $('#students-display').html(html);
        
        // Sort by default column on first load
        if (currentSort.column === 'completed') {
            sortStudents('completed');
        }
        
        // Add click handlers for sorting
        $('#students-display').off('click', '.sortable').on('click', '.sortable', function(e) {
            e.preventDefault();
            var column = $(this).data('column');
            if (column) {
                sortStudents(column);
            }
        });
    }
    
    // Sorting functionality
    function sortStudents(column) {
        if (currentSort.column === column) {
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort.column = column;
            currentSort.direction = 'asc';
        }
        
        currentStudents.sort(function(a, b) {
            var aVal, bVal;
            
            switch(column) {
                case 'name':
                    aVal = a.display_name.toLowerCase();
                    bVal = b.display_name.toLowerCase();
                    break;
                case 'email':
                    aVal = a.user_email.toLowerCase();
                    bVal = b.user_email.toLowerCase();
                    break;
                case 'course':
                    aVal = a.course_completion ? (a.course_completion.completed ? 2 : (a.course_completion.started ? 1 : 0)) : 0;
                    bVal = b.course_completion ? (b.course_completion.completed ? 2 : (b.course_completion.started ? 1 : 0)) : 0;
                    break;
                case 'overall':
                    aVal = a.quiz_stats ? parseFloat(a.quiz_stats.overall_success_rate || 0) : 0;
                    bVal = b.quiz_stats ? parseFloat(b.quiz_stats.overall_success_rate || 0) : 0;
                    break;
                case 'completed':
                    aVal = a.quiz_stats ? parseFloat(a.quiz_stats.completed_only_rate || 0) : 0;
                    bVal = b.quiz_stats ? parseFloat(b.quiz_stats.completed_only_rate || 0) : 0;
                    break;
                default:
                    return 0;
            }
            
            if (aVal < bVal) return currentSort.direction === 'asc' ? -1 : 1;
            if (aVal > bVal) return currentSort.direction === 'asc' ? 1 : -1;
            return 0;
        });
        
        renderStudentsTable(currentStudents);
        
        // Update table headers to show current sort (without icons)
        $('.sortable').removeClass('sorted-asc sorted-desc');
        $('.sortable[data-column="' + currentSort.column + '"]').addClass('sorted-' + currentSort.direction);
    }
    
    // Removed getSortIcon function - no more icons

    // Format quiz rate with color coding
    function formatQuizRate(rate) {
        if (rate === null || rate === undefined || rate === '' || rate === 0) {
            return '<span class="no-data">אין נתונים</span>';
        }

        // Convert to number if it's a string
        var numRate = parseFloat(rate);
        if (isNaN(numRate)) {
            return '<span class="no-data">אין נתונים</span>';
        }

        var className = '';
        if (numRate >= 80) {
            className = 'excellent';
        } else if (numRate >= 70) {
            className = 'good';
        } else if (numRate >= 60) {
            className = 'average';
        } else {
            className = 'needs-improvement';
        }

        return '<span class="quiz-rate ' + className + '">' + numRate.toFixed(1) + '%</span>';
    }

    // Format course completion status
    function formatCourseCompletion(courseData) {
        if (!courseData || !courseData.course_name) {
            return '<span class="no-data">אין נתוני קורס</span>';
        }

        var statusClass = '';
        var statusText = '';
        
        switch(courseData.completion_status) {
            case 'Completed':
                statusClass = 'completed';
                statusText = 'הושלם';
                break;
            case 'In Progress':
                statusClass = 'in-progress';
                statusText = 'בתהליך';
                break;
            default:
                statusClass = 'not-started';
                statusText = 'לא התחיל';
        }

        return '<div class="course-completion">' +
               '<div class="course-name">' + courseData.course_name + '</div>' +
               '<span class="completion-status ' + statusClass + '">' + statusText + '</span>' +
               '</div>';
    }

    // Update sort indicator
    function updateSortIndicator() {
        $('.sort-indicator').text('↕');
        var indicator = $('th[data-sort="' + currentSort.column + '"] .sort-indicator');
        indicator.text(currentSort.direction === 'asc' ? '↑' : '↓');
    }

    // Handle table sorting
    $('body').on('click', 'th[data-sort]', function() {
        var column = $(this).data('sort');
        
        // Toggle direction if clicking the same column
        if (currentSort.column === column) {
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            // Default to ascending for new column
            currentSort.column = column;
            currentSort.direction = 'asc';
        }

        // Sort students
        sortStudents(currentSort.column, currentSort.direction);
        renderStudentsTable(currentStudents);
    });

    // Sort students array
    function sortStudents(column, direction) {
        currentStudents.sort(function(a, b) {
            var valueA = getSortValue(a, column);
            var valueB = getSortValue(b, column);

            // Handle null/undefined values
            if (valueA === null || valueA === undefined) return 1;
            if (valueB === null || valueB === undefined) return -1;

            // Compare values
            if (valueA < valueB) return direction === 'asc' ? -1 : 1;
            if (valueA > valueB) return direction === 'asc' ? 1 : -1;
            return 0;
        });
    }

    // Get sort value based on column
    function getSortValue(student, column) {
        switch(column) {
            case 'name':
                return student.display_name;
            case 'email':
                return student.user_email;
            case 'quiz_score':
                return student.quiz_score || 0;
            case 'completion':
                if (!student.course_data) return '';
                switch(student.course_data.completion_status) {
                    case 'Completed': return 3;
                    case 'In Progress': return 2;
                    default: return 1;
                }
            default:
                return '';
        }
    }

    // Global functions for print and export
    window.printTable = function() {
        var table = document.getElementById("students-table");
        if (!table) {
            alert("אין נתונים להדפסה");
            return;
        }
        
        var printWindow = window.open("", "_blank");
        var groupStats = document.querySelector(".group-stats");
        var groupStatsHTML = groupStats ? groupStats.outerHTML : "";
        
        var printHTML = [
            '<html dir="rtl">',
            '<head>',
            '<title>לוח בקרה מורה - נתוני תלמידים</title>',
            '<style>',
            'body { font-family: Arial, sans-serif; direction: rtl; text-align: right; margin: 20px; }',
            'table { width: 100%; border-collapse: collapse; margin-top: 20px; }',
            'th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }',
            'th { background-color: #f2f2f2; font-weight: bold; }',
            '.group-stats { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }',
            '.quiz-rate.excellent { color: #00a32a; font-weight: bold; }',
            '.quiz-rate.good { color: #72aee6; font-weight: bold; }',
            '.quiz-rate.average { color: #ffb900; font-weight: bold; }',
            '.quiz-rate.needs-improvement { color: #d63638; font-weight: bold; }',
            '.no-data { color: #666; font-style: italic; }',
            '.completion-status.completed { color: #00a32a; }',
            '.completion-status.in-progress { color: #ffb900; }',
            '.completion-status.not-started { color: #d63638; }',
            '@media print { body { margin: 0; } .group-stats { break-inside: avoid; } }',
            '</style>',
            '</head>',
            '<body>',
            '<h1>לוח בקרה מורה - נתוני תלמידים</h1>',
            '<p><strong>תאריך הדפסה:</strong> ' + new Date().toLocaleDateString('he-IL') + '</p>',
            groupStatsHTML,
            table.outerHTML,
            '</body>',
            '</html>'
        ].join('');
        
        printWindow.document.write(printHTML);
        printWindow.document.close();
        printWindow.focus();
        
        setTimeout(function() {
            printWindow.print();
            printWindow.close();
        }, 250);
    };

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
                row.push('"' + cellText + '"');
            }
            csv.push(row.join(","));
        }
        
        var csvContent = csv.join("\n");
        var blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement("a");
        
        if (link.download !== undefined) {
            var url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", "students-data-" + new Date().toISOString().slice(0,10) + ".csv");
            link.style.visibility = "hidden";
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    };
});
