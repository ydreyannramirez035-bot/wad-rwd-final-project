document.addEventListener('DOMContentLoaded', function() {
    const bellBtn = document.querySelector('#notificationDropdown');
    const badge = document.querySelector('.notification-container .badge');

    if (bellBtn) {
        bellBtn.addEventListener('click', function() {
            if (badge) {
                const formData = new FormData();
                formData.append('action', 'clear_badge_only');

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        badge.remove();
                    }
                })
                .catch(error => {
                    console.error('Error clearing badge:', error);
                });
            }
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