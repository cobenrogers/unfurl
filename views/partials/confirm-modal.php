<!-- Confirm Modal -->
<div id="confirm-modal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="closeConfirmModal()"></div>
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="confirm-modal-title" style="font-size: 1.25rem; font-weight: 600; margin: 0;">Confirm Action</h3>
            <button type="button" class="modal-close" onclick="closeConfirmModal()" aria-label="Close">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div style="display: flex; gap: 1rem; align-items: flex-start;">
                <div style="flex-shrink: 0; color: var(--color-warning);">
                    <svg width="24" height="24" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div style="flex: 1;">
                    <p id="confirm-modal-message" style="margin: 0; color: var(--color-text);">
                        Are you sure you want to proceed with this action?
                    </p>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; gap: 0.75rem; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" onclick="closeConfirmModal()">
                Cancel
            </button>
            <button type="button" id="confirm-modal-action" class="btn btn-danger" onclick="confirmModalAction()">
                Delete
            </button>
        </div>
    </div>
</div>

<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(4px);
}

.modal-content {
    position: relative;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
    width: 100%;
    max-height: 90vh;
    overflow: auto;
    animation: modalSlideIn 0.2s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem;
    border-bottom: 1px solid var(--color-border);
}

.modal-close {
    background: none;
    border: none;
    padding: 0.25rem;
    cursor: pointer;
    color: var(--color-text-muted);
    border-radius: var(--radius-md);
    transition: all 0.2s;
}

.modal-close:hover {
    background: var(--color-border-light);
    color: var(--color-text);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--color-border);
}
</style>

<script>
let confirmModalCallback = null;

function showConfirmModal(options) {
    const modal = document.getElementById('confirm-modal');
    const title = document.getElementById('confirm-modal-title');
    const message = document.getElementById('confirm-modal-message');
    const actionBtn = document.getElementById('confirm-modal-action');

    title.textContent = options.title || 'Confirm Action';
    message.textContent = options.message || 'Are you sure you want to proceed?';
    actionBtn.textContent = options.confirmText || 'Confirm';
    actionBtn.className = 'btn ' + (options.dangerButton ? 'btn-danger' : 'btn-primary');

    confirmModalCallback = options.onConfirm;

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // Focus the cancel button
    modal.querySelector('.btn-secondary').focus();
}

function closeConfirmModal() {
    const modal = document.getElementById('confirm-modal');
    modal.style.display = 'none';
    document.body.style.overflow = '';
    confirmModalCallback = null;
}

function confirmModalAction() {
    if (confirmModalCallback) {
        confirmModalCallback();
    }
    closeConfirmModal();
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeConfirmModal();
    }
});

// Helper function to replace form confirm dialogs
function confirmDelete(form, message) {
    showConfirmModal({
        title: 'Confirm Delete',
        message: message || 'Are you sure you want to delete this? This action cannot be undone.',
        confirmText: 'Delete',
        dangerButton: true,
        onConfirm: () => form.submit()
    });
    return false; // Prevent default form submission
}
</script>
