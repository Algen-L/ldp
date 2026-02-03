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

// Certificate Filtering Logic
function initCertificateFilters() {
    const searchInput = document.getElementById('certSearchInput');
    const statusSelect = document.getElementById('certStatusSelect');
    const grid = document.querySelector('.certificate-grid');

    if (!searchInput || !statusSelect || !grid) return;

    const cards = Array.from(grid.querySelectorAll('.activity-card'));

    // Create empty state message for filters if it doesn't exist
    let noResultsMsg = document.getElementById('certNoResults');
    if (!noResultsMsg) {
        noResultsMsg = document.createElement('div');
        noResultsMsg.id = 'certNoResults';
        noResultsMsg.className = 'empty-state';
        noResultsMsg.style.display = 'none';
        noResultsMsg.innerHTML = `
            <div style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 12px;"><i class="bi bi-search"></i></div>
            <h3 style="color: #64748b; font-size: 1rem; margin-bottom: 4px;">No matching certificates</h3>
            <p style="color: #94a3b8; font-size: 0.85rem;">Try adjusting your search or filter criteria.</p>
        `;
        grid.parentNode.appendChild(noResultsMsg); // Append to scroll container parent or logic needs to be careful
        // Actually, let's append it as the last child of grid, but grid is display:grid. 
        // Better to toggle the grid's display or handle it inside. 
        // Let's hide the grid content and show this message sibling.
        grid.after(noResultsMsg);
    }

    function filterCards() {
        const query = searchInput.value.toLowerCase().trim();
        const status = statusSelect.value;
        let pVisibleCount = 0;

        cards.forEach(card => {
            const getSafeText = (selector) => {
                const el = card.querySelector(selector);
                return el ? el.textContent.toLowerCase() : '';
            };

            const title = getSafeText('.activity-title');
            const type = getSafeText('.activity-type');
            const hasCert = card.querySelector('.has-cert') !== null;

            let matchesSearch = title.includes(query) || type.includes(query);
            let matchesStatus = true;

            if (status === 'ready') {
                matchesStatus = hasCert;
            } else if (status === 'upload') {
                matchesStatus = !hasCert;
            }

            if (matchesSearch && matchesStatus) {
                card.style.display = 'flex'; // Restore display
                pVisibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        // Toggle Empty State
        if (pVisibleCount === 0 && cards.length > 0) {
            noResultsMsg.style.display = 'block';
            grid.style.display = 'none';
        } else {
            noResultsMsg.style.display = 'none';
            grid.style.display = 'grid';
        }
    }

    searchInput.addEventListener('input', filterCards);
    statusSelect.addEventListener('change', filterCards);
}

// Global listeners for profile pages
document.addEventListener('DOMContentLoaded', () => {
    initSettingsToggle();
    initCertificateFilters();

    // Close modal when clicking outside
    window.addEventListener('click', (event) => {
        const overlay = document.getElementById('deleteModalOverlay');
        if (event.target === overlay) {
            closeDeleteModal();
        }
    });
});
