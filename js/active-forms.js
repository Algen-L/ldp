/**
 * Active Forms & Signature Pads
 * Logic for complex forms, signatures, and dynamic fields
 */

// Direct Signature Pad Configuration
let orgCanvas, orgCtx, orgIsDrawing = false;

function initOrgSignature() {
    orgCanvas = document.getElementById('org-sig-canvas');
    if (!orgCanvas) return;

    orgCtx = orgCanvas.getContext('2d');
    resizeOrgCanvas(); // Initial try

    orgCtx.lineWidth = 2.5;
    orgCtx.lineCap = 'round';
    orgCtx.strokeStyle = '#000';

    const getPos = (e) => {
        const r = orgCanvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;

        // Handle coordinate scaling
        const scaleX = orgCanvas.width / r.width;
        const scaleY = orgCanvas.height / r.height;

        return {
            x: (clientX - r.left) * (scaleX || 1),
            y: (clientY - r.top) * (scaleY || 1)
        };
    };

    const startDraw = (e) => {
        e.preventDefault();

        // Final fallback to ensure non-zero dimensions
        if (orgCanvas.width === 0) resizeOrgCanvas();

        orgIsDrawing = true;

        // Re-set context properties (they reset when canvas is resized)
        orgCtx.lineWidth = 2.5;
        orgCtx.lineCap = 'round';
        orgCtx.strokeStyle = '#000';

        const pos = getPos(e);
        orgCtx.beginPath();
        orgCtx.moveTo(pos.x, pos.y);
        document.getElementById('org-sig-hint').style.display = 'none';
    };

    const draw = (e) => {
        if (!orgIsDrawing) return;
        e.preventDefault();
        const pos = getPos(e);
        orgCtx.lineTo(pos.x, pos.y);
        orgCtx.stroke();
    };

    const stopDraw = () => {
        if (orgIsDrawing) {
            orgIsDrawing = false;
            syncOrgSignature();
        }
    };

    orgCanvas.addEventListener('mousedown', startDraw);
    orgCanvas.addEventListener('mousemove', draw);
    window.addEventListener('mouseup', stopDraw);
    orgCanvas.addEventListener('touchstart', startDraw, { passive: false });
    orgCanvas.addEventListener('touchmove', draw, { passive: false });
    orgCanvas.addEventListener('touchend', stopDraw);

    // Resize on window changes
    window.addEventListener('resize', resizeOrgCanvas);
}

function resizeOrgCanvas() {
    if (!orgCanvas || !orgCtx) return;
    const rect = orgCanvas.getBoundingClientRect();

    // Only resize if we have actual dimensions (container is visible)
    if (rect.width > 0 && rect.height > 0) {
        // Save current content if any and valid
        let tempImage = null;
        if (orgCanvas.width > 0 && orgCanvas.height > 0) {
            tempImage = orgCanvas.toDataURL();
        }

        orgCanvas.width = rect.width;
        orgCanvas.height = rect.height;

        // Restore context properties
        orgCtx.lineWidth = 2.5;
        orgCtx.lineCap = 'round';
        orgCtx.strokeStyle = '#000';

        // Restore content if it was saved
        if (tempImage && document.getElementById('organizer_signature_data').value) {
            const img = new Image();
            img.onload = () => orgCtx.drawImage(img, 0, 0);
            img.src = tempImage;
        }
    }
}

function clearOrgSignature() {
    if (orgCtx && orgCanvas) {
        orgCtx.clearRect(0, 0, orgCanvas.width, orgCanvas.height);
        document.getElementById('org-sig-hint').style.display = 'block';
        document.getElementById('org-sig-preview-container').style.display = 'none';
        document.getElementById('organizer_signature_data').value = "";
    }
}

function syncOrgSignature() {
    if (!orgCanvas) return;
    const dataUrl = orgCanvas.toDataURL("image/png");
    document.getElementById('organizer_signature_data').value = dataUrl;
}

function triggerSignatureUpload() {
    const input = document.getElementById('org-sig-file');
    if (!input) return;

    input.click();
    input.onchange = function () {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = (e) => {
                document.getElementById('org-sig-preview').src = e.target.result;
                document.getElementById('org-sig-preview-container').style.display = 'block';
                document.getElementById('organizer_signature_data').value = "";
                // Clear canvas if file uploaded
                if (orgCtx) orgCtx.clearRect(0, 0, orgCanvas.width, orgCanvas.height);
            };
            reader.readAsDataURL(this.files[0]);
        }
    };
}

// Logic for Dynamic Sections
function initDynamicFormFields() {
    const conductedByInput = document.getElementById('conducted_by');
    if (conductedByInput) {
        conductedByInput.addEventListener('input', function () {
            const section = document.getElementById('organizer-signature-section');
            if (section) {
                const isVisible = this.value.trim() !== "";
                section.style.display = isVisible ? 'block' : 'none';
                if (isVisible) {
                    // Small timeout to ensure DOM has rendered the 'block' display
                    setTimeout(resizeOrgCanvas, 10);
                }
            }
        });
    }

    const typeOthersCheckbox = document.getElementById('type-others-checkbox');
    if (typeOthersCheckbox) {
        typeOthersCheckbox.addEventListener('change', function () {
            const container = document.getElementById('type-others-input-container');
            if (container) container.style.display = this.checked ? 'block' : 'none';
        });
    }

    const workplaceInput = document.getElementById('workplace_image');
    const dropZone = document.getElementById('drop-zone');

    if (dropZone && workplaceInput) {
        // Drag and Drop Events
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.add('drag-over');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.remove('drag-over');
            }, false);
        });

        dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            workplaceInput.files = files;
            // Trigger change event manually
            const event = new Event('change', { bubbles: true });
            workplaceInput.dispatchEvent(event);
        }, false);

        workplaceInput.addEventListener('change', function () {
            const list = document.getElementById('file-list');
            if (!list) return;
            list.innerHTML = '';

            if (this.files.length > 0) {
                dropZone.classList.add('has-content');
                Array.from(this.files).forEach(file => {
                    const isImage = file.type.startsWith('image/');
                    const icon = isImage ? 'bi-image' : 'bi-file-earmark-text';

                    const badge = document.createElement('div');
                    badge.className = 'file-badge';
                    badge.innerHTML = `<i class="bi ${icon}"></i> <span>${file.name.substring(0, 20)}${file.name.length > 20 ? '...' : ''}</span>`;
                    list.appendChild(badge);
                });
            } else {
                dropZone.classList.remove('has-content');
            }
        });
    }
}

// Form Submission & Validation
function validateAndSubmitForm() {
    const form = document.getElementById('activity-form');
    if (!form) return;

    if (!form.checkValidity()) {
        const invalidElem = form.querySelector(':invalid');
        if (invalidElem) {
            let label = invalidElem.closest('.form-group')?.querySelector('.form-label')?.innerText.replace('*', '').trim() || "Required field";
            if (invalidElem.id === 'workplace_image') label = "Evidence / Attachments";
            showToast("Form Incomplete", `Please provide: ${label}`, "error");
            invalidElem.focus();
        } else {
            form.reportValidity();
        }
        return;
    }

    // Modality Check
    const modalityChecked = form.querySelectorAll('input[name="modality[]"]:checked').length > 0;
    if (!modalityChecked) {
        showToast("Missing Information", "Please select at least one Modality.", "error");
        document.querySelector('input[name="modality[]"]').focus();
        return;
    }

    // Type Check
    const typeChecked = form.querySelectorAll('input[name="type_ld[]"]:checked').length > 0;
    if (!typeChecked) {
        showToast("Missing Information", "Please select at least one Type of L&D.", "error");
        document.querySelector('input[name="type_ld[]"]').focus();
        return;
    }

    // Evidence Check
    const evidenceFiles = document.getElementById('workplace_image').files.length;
    if (evidenceFiles === 0) {
        showToast("Missing Evidence", "Please upload at least one supporting document or image.", "error");
        document.getElementById('workplace_image').scrollIntoView({ behavior: 'smooth' });
        return;
    }

    // Mandatory Signature Check
    const orgVal = document.getElementById('organizer_signature_data').value || document.getElementById('org-sig-file').files.length;
    if (!orgVal) {
        showToast("Missing Signature", "Please provide the Organizer's signature.", "error");
        document.getElementById('org-sig-box').scrollIntoView({ behavior: 'smooth' });
        return;
    }

    // Privacy Check
    const privacyChecked = document.getElementById('privacy-agree')?.checked;
    if (!privacyChecked) {
        showToast("Agreement Required", "Please review and agree to the Privacy Notice before submitting.", "error");
        document.getElementById('privacy-agree').focus();
        return;
    }

    form.submit();
}

// Initialization
document.addEventListener('DOMContentLoaded', () => {
    initOrgSignature();
    initDynamicFormFields();
});
