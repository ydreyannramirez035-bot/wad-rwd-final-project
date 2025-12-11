document.addEventListener("DOMContentLoaded", () => {
    if (document.getElementById('table_data')) {
        loadTable(1);
    }
});

function loadTable(page = 1) {
    const query = document.getElementById("search").value;
    const filterCourse = document.getElementById("filter_course").value;
    const sortBy = document.getElementById("sort_by").value;
    const tableBody = document.getElementById("table_data");
    const paginationContainer = document.getElementById("pagination_container");

    // Show loading state
    tableBody.style.opacity = '0.5';

    // Construct URL
    let url = `?ajax=1&q=${encodeURIComponent(query)}&filter_course=${filterCourse}&sort_by=${sortBy}&page=${page}`;

    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            // Update Table Rows
            tableBody.innerHTML = data.table_html;
            
            // Update Pagination Buttons
            if (paginationContainer) {
                paginationContainer.innerHTML = data.pagination_html;
            }
            
            // Restore Opacity
            tableBody.style.opacity = '1';
        })
        .catch(error => {
            console.error("Error loading students:", error);
            tableBody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error loading data.</td></tr>`;
            tableBody.style.opacity = '1';
        });
}