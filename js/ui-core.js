/**
 * UI Core Functionality
 * Global listeners and helper functions for the LDP system
 */

// Real-time Clock Functionality
function updateClock() {
    const clockElement = document.getElementById('real-time-clock');
    if (!clockElement) return;

    const now = new Date();
    const options = {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    };
    clockElement.textContent = now.toLocaleTimeString('en-US', options);
}

// Global Initialization
document.addEventListener('DOMContentLoaded', () => {
    // Initial clock update
    updateClock();
    // Update every second
    setInterval(updateClock, 1000);
});
