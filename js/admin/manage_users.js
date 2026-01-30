/**
 * Admin Manage Users - Interaction Logic
 * Handles deletion modal and other user actions.
 */

function confirmDelete(userId, fullName) {
    const targetName = document.getElementById('deleteTargetName');
    const targetId = document.getElementById('deleteUserId');
    const modal = document.getElementById('deleteModal');

    if (targetName) targetName.textContent = fullName;
    if (targetId) targetId.value = userId;

    if (modal) {
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('active'), 10);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => modal.style.display = 'none', 300);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    window.onclick = function (event) {
        if (event.target.classList.contains('modal-overlay')) {
            closeModal(event.target.id);
        }
    }
});
