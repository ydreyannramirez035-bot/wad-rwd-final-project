document.addEventListener('DOMContentLoaded', function() {
    const bellBtn = document.querySelector('#notificationDropdown');
    
    if (bellBtn) {
        bellBtn.addEventListener('click', function(e) {
            const container = bellBtn.closest('.notification-container');
            const badge = container.querySelector('.notification-badge');

            if (badge) {
                badge.style.display = 'none';
                badge.remove(); 
                const formData = new FormData();
                formData.append('action', 'clear_badge_only');
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

