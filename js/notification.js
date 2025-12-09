document.addEventListener('DOMContentLoaded', function() {
    const bellBtn = document.querySelector('#notificationDropdown');
    const badge = document.querySelector('.notification-badge');

    if (bellBtn) {
        bellBtn.addEventListener('click', function() {
            if (badge && badge.style.display !== 'none') {
                badge.style.display = 'none';

                const formData = new FormData();
                formData.append('action', 'clear_badge_only');

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log(data);
                })
                .catch(error => {
                    console.error(error);
                });
            }
        });
    }
});
