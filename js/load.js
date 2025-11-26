// This function runs automatically when the page opens
document.addEventListener("DOMContentLoaded", function() {
    loadTable(); 
});

function loadTable() {
    // 1. Get values from the inputs
    var course = document.getElementById("filter_course").value;
    var sort = document.getElementById("sort_by").value;
    var search = document.getElementById("search").value;

    // 2. Send request to PHP
    var url = "admin_student_manage.php?ajax=1&filter_course=" + course + "&sort_by=" + sort + "&q=" + search;

    fetch(url)
    .then(response => response.text())
    .then(data => {
        // 3. Update the table body with the result
        document.getElementById("table_data").innerHTML = data;
    });
}