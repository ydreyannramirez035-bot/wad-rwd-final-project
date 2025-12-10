document.addEventListener('DOMContentLoaded', function() {
    const markAllBtn = document.getElementById('markAllBtn');
    
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function() {
            // Show loading state
            const originalContent = markAllBtn.innerHTML;
            markAllBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Processing...';
            markAllBtn.disabled = true;

            const formData = new FormData();
            formData.append('mark_all_read', '1');
            formData.append('ajax', '1');

            fetch('notif_view.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 1. Visually change all unread cards to read
                    const unreadCards = document.querySelectorAll('.notif-card.unread');
                    unreadCards.forEach(card => {
                        card.classList.remove('unread');
                        card.classList.add('read');
                    });

                    // 2. Remove all "NEW" badges inside cards
                    const newBadges = document.querySelectorAll('.new-badge');
                    newBadges.forEach(badge => badge.remove());

                    // 3. Remove the notification badge on the navbar
                    const navBadge = document.getElementById('nav-badge');
                    if (navBadge) navBadge.remove();

                    // 4. Hide the "Mark all as read" button
                    markAllBtn.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                markAllBtn.innerHTML = originalContent;
                markAllBtn.disabled = false;
                alert('Something went wrong. Please try again.');
            });
        });
    }
});