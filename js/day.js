document.addEventListener('DOMContentLoaded', function() {
    const daySelect = document.getElementById('day-select');
    const scheduleBody = document.getElementById('schedule-body');
    const statsLabel = document.getElementById('stats-label');
    const statsNumber = document.getElementById('stats-number');

    daySelect.addEventListener('change', function() {
        const selectedDay = this.value;

        fetch(`?ajax=1&day=${selectedDay}`)
            .then(response => response.json())
            .then(data => {
                scheduleBody.innerHTML = data.html;
                
                if(statsLabel) statsLabel.textContent = data.label;
                if(statsNumber) statsNumber.textContent = data.count;
            })
            .catch(error => {
                console.error('Error fetching schedule:', error);
            });
    });
});