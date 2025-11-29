function validateCourseSelection() {
    // Select all checkboxes with the name 'course_ids[]'
    const checkboxes = document.querySelectorAll('input[name="course_ids[]"]');
    let isChecked = false;

    // Loop through checkboxes to see if at least one is checked
    checkboxes.forEach((cb) => {
        if(cb.checked) isChecked = true;
    });

    // Get the error message element
    const errorMsg = document.getElementById('course_error');
    
    // Check validation status
    if (!isChecked) {
        // Show error and prevent submission
        if(errorMsg) errorMsg.style.display = 'block';
        return false; 
    } else {
        // Hide error and allow submission
        if(errorMsg) errorMsg.style.display = 'none';
        return true; 
    }
}