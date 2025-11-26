document.addEventListener("DOMContentLoaded", function() {
    loadTable(); 
});

// Helper to safely get value or empty string if element missing
function getVal(id) {
    let el = document.getElementById(id);
    return el ? el.value : "";
}

function loadTable() {
    // 1. Get values from the inputs
    var course  = getVal("filter_course");
    var subject = getVal("filter_subject");
    var teacher = getVal("filter_teacher");
    var sort    = getVal("sort_by");
    var search  = getVal("search");

    // 2. Build URL

    var url = "?ajax=1" + 
              "&filter_course=" + encodeURIComponent(course) + 
              "&filter_subject=" + encodeURIComponent(subject) + 
              "&filter_teacher=" + encodeURIComponent(teacher) + 
              "&sort_by=" + encodeURIComponent(sort) + 
              "&q=" + encodeURIComponent(search);

    // 3. Fetch
    fetch(url)
    .then(response => response.text())
    .then(data => {
        var tbody = document.getElementById("table_data");
        if(tbody) {
            tbody.innerHTML = data;
        }
    })
    .catch(error => console.error('Error:', error));
}