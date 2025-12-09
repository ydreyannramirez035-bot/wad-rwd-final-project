document.addEventListener("DOMContentLoaded", function() {
    loadTable(); 
});

function getVal(id) {
    let el = document.getElementById(id);
    return el ? el.value : "";
}

function loadTable() {
    // 1. Get values from the inputs
    const course  = getVal("filter_course");
    const subject = getVal("filter_subject");
    const teacher = getVal("filter_teacher");
    const sort    = getVal("sort_by");
    const search  = getVal("search");

    // 2. Build URL
    const url = "?ajax=1" + 
              "&filter_course=" + encodeURIComponent(course) + 
              "&filter_subject=" + encodeURIComponent(subject) + 
              "&filter_teacher=" + encodeURIComponent(teacher) + 
              "&sort_by=" + encodeURIComponent(sort) + 
              "&q=" + encodeURIComponent(search);

    // 3. Fetch
    fetch(url)
    .then(response => response.text())
    .then(data => {
        const tbody = document.getElementById("table_data");
        if(tbody) {
            tbody.innerHTML = data;
        }
    })
    .catch(error => console.error('Error:', error));
}