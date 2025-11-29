document.addEventListener('DOMContentLoaded', function() {
    var bellIcon = document.getElementById('notificationDropdown');
    var badge = document.querySelector('.notification-badge');

    if (bellIcon && badge) {
        bellIcon.addEventListener('click', function() {
            badge.style.display = 'none';
        });
    }
});