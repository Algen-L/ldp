/**
 * Profile and Administrative Actions
 * Logic for settings, ILDN management, and profile-specific modals
 */

// Toggle Account Settings Panel
function initSettingsToggle() {
    const toggleBtn = document.getElementById('toggleSettings');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            const settings = document.getElementById('accountSettings');
            if (!settings) return;

            if (settings.style.display === 'block') {
                settings.style.display = 'none';
                this.classList.remove('active');
            } else {
                settings.style.display = 'block';
                this.classList.add('active');
                settings.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }
}

// ILDN Delete Confirmation Modal Logic
function confirmDeleteILDN(id) {
    const modalIdInput = document.getElementById('modal_ildn_id');
    const overlay = document.getElementById('deleteModalOverlay');

    if (modalIdInput && overlay) {
        modalIdInput.value = id;
        const modal = overlay.querySelector('.custom-modal');
        overlay.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
    }
}

function closeDeleteModal() {
    const overlay = document.getElementById('deleteModalOverlay');
    if (overlay) {
        const modal = overlay.querySelector('.custom-modal');
        modal.classList.remove('show');
        setTimeout(() => overlay.style.display = 'none', 300);
    }
}

// Global listeners for profile pages
document.addEventListener('DOMContentLoaded', () => {
    initSettingsToggle();

    // Close modal when clicking outside
    window.addEventListener('click', (event) => {
        const overlay = document.getElementById('deleteModalOverlay');
        if (event.target === overlay) {
            closeDeleteModal();
        }
    });
});
