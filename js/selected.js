function validateCourseSelection() {
    const checkboxes = document.querySelectorAll('input[name="course_ids[]"]');
    let isChecked = false;

    checkboxes.forEach((cb) => {
        if(cb.checked) isChecked = true;
    });

    const errorMsg = document.getElementById('course_error');

    if (!isChecked) {
        if(errorMsg) errorMsg.style.display = 'block';
        return false;
    } else {
        if(errorMsg) errorMsg.style.display = 'none';
        return true;
    }
}