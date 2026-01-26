/**
 * Form Auto-save Utility
 * Saves and restores form data using localStorage
 */
function initFormAutosave(formId, storageKey) {
    const form = document.getElementById(formId);
    if (!form) return;

    // Load saved data
    const savedData = localStorage.getItem(storageKey);
    if (savedData) {
        try {
            const data = JSON.parse(savedData);
            Object.keys(data).forEach(name => {
                const value = data[name];

                // Try both exact name and array name
                const elements = form.querySelectorAll(`[name="${name}"], [name="${name}[]"]`);

                elements.forEach(el => {
                    if (el.type === 'checkbox' || el.type === 'radio') {
                        if (Array.isArray(value)) {
                            el.checked = value.includes(el.value);
                        } else {
                            el.checked = el.value === value;
                        }
                    } else if (el.type !== 'file') {
                        el.value = value;
                    }

                    // Trigger events for plugins
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
        } catch (e) {
            console.error('Error parsing saved form data', e);
        }
    }

    // Save data on change
    form.addEventListener('input', (e) => {
        if (e.target.type !== 'file') saveForm();
    });
    form.addEventListener('change', (e) => {
        if (e.target.type !== 'file') saveForm();
    });

    function saveForm() {
        const formData = new FormData(form);
        const data = {};

        for (const [key, value] of formData.entries()) {
            // Ignore file objects
            if (value instanceof File) continue;

            if (key.endsWith('[]')) {
                if (!data[key]) {
                    data[key] = [];
                }
                data[key].push(value);
            } else {
                data[key] = value;
            }
        }

        localStorage.setItem(storageKey, JSON.stringify(data));
    }

    // Return clear function
    return {
        clear: () => localStorage.removeItem(storageKey)
    };
}
