document.addEventListener('DOMContentLoaded', function() {
    var bellIcon = document.getElementById('notificationDropdown');
    var badge = document.querySelector('.notification-badge');

    if (bellIcon) {
        bellIcon.addEventListener('click', function() {
            if (badge) {
                badge.style.display = 'none';
            }

            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=clear_badge_only'
            })
            .then(response => response.json())
            .then(data => {
                console.log('Badge cleared in session (text remains bold).');
            })
            .catch(error => {
                console.error('Error clearing badge:', error);
            });
        });
    }
});

window.addEventListener( "pageshow", function ( event ) {
    var historyTraversal = event.persisted || 
                           ( typeof window.performance != "undefined" && 
                                window.performance.navigation.type === 2 );
    if ( historyTraversal ) {
        window.location.reload();
    }
});