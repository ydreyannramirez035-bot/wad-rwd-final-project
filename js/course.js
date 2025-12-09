function fetchDashboardData(courseId) {
    const scheduleBody = document.getElementById('schedule_table_body');
    const studentBody = document.getElementById('student_table_body');
    const headerCourse = document.getElementById('header_course_name');
    const headerStudent = document.getElementById('header_student_title');
    
    scheduleBody.style.opacity = '0.5';
    studentBody.style.opacity = '0.5';

    fetch('admin_dashboard.php?ajax=1&course_id=' + courseId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            document.getElementById('stat_students').textContent = data.stats.students;
            document.getElementById('stat_classes').textContent = data.stats.classes;
            document.getElementById('stat_teachers').textContent = data.stats.teachers;
            document.getElementById('stat_rooms').textContent = data.stats.rooms;

            headerCourse.textContent = data.titles.course;
            headerStudent.textContent = data.titles.student_title;

            document.getElementById('last_update_display').textContent = data.last_update;

            scheduleBody.innerHTML = data.html.schedule;
            studentBody.innerHTML = data.html.students;

            scheduleBody.style.opacity = '1';
            studentBody.style.opacity = '1';
        })
        .catch(error => {
            console.error('Error fetching data:', error);
            scheduleBody.style.opacity = '1';
            studentBody.style.opacity = '1';
        });
}