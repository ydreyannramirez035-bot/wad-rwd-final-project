document.addEventListener('DOMContentLoaded', function() {
    const bellBtn = document.querySelector('#notificationDropdown');
    
    if (bellBtn) {
        bellBtn.addEventListener('click', function(e) {
            // Find the badge container relative to the button
            const container = bellBtn.closest('.notification-container');
            const badge = container.querySelector('.notification-badge');

            if (badge) {
                // 1. Visually hide it immediately for instant feedback
                badge.style.display = 'none';
                badge.remove(); 

                // 2. Prepare data
                const formData = new FormData();
                formData.append('action', 'clear_badge_only');

                // 3. Send to current page (which includes student_nav.php)
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log("Badge cleared in DB:", data);
                })
                .catch(error => {
                    console.error("Error clearing badge:", error);
                });
            }
        });
    }
});