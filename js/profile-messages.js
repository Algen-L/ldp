function openMessagesModal() {
    const modal = document.getElementById('messagesModalOverlay');
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.querySelector('.messages-modal').classList.add('show');
    }, 10);
    document.body.style.overflow = 'hidden';
}

function closeMessagesModal() {
    const modal = document.getElementById('messagesModalOverlay');
    const modalContent = modal.querySelector('.messages-modal');

    modalContent.classList.remove('show');

    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }, 300);
}

// Single Message Delete
function confirmDeleteMessage(id) {
    document.getElementById('delete_msg_id').value = id;
    const modal = document.getElementById('deleteMessageModalOverlay');
    modal.style.display = 'flex';
    setTimeout(() => modal.querySelector('.custom-modal').classList.add('show'), 10);
}

function closeDeleteMessageModal() {
    const modal = document.getElementById('deleteMessageModalOverlay');
    modal.querySelector('.custom-modal').classList.remove('show');
    setTimeout(() => modal.style.display = 'none', 300);
}

// Delete All Messages
function confirmDeleteAllMessages() {
    const modal = document.getElementById('deleteAllMessagesModalOverlay');
    modal.style.display = 'flex';
    setTimeout(() => modal.querySelector('.custom-modal').classList.add('show'), 10);
}

function closeDeleteAllMessagesModal() {
    const modal = document.getElementById('deleteAllMessagesModalOverlay');
    modal.querySelector('.custom-modal').classList.remove('show');
    setTimeout(() => modal.style.display = 'none', 300);
}

// Close modals when clicking outside
document.addEventListener('click', function (event) {
    const msgModal = document.getElementById('messagesModalOverlay');
    const delMsgModal = document.getElementById('deleteMessageModalOverlay');
    const delAllModal = document.getElementById('deleteAllMessagesModalOverlay');

    if (event.target === msgModal) closeMessagesModal();
    if (event.target === delMsgModal) closeDeleteMessageModal();
    if (event.target === delAllModal) closeDeleteAllMessagesModal();
});
