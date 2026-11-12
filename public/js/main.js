/* ==========================================================================
   TOAST NOTIFICATION HANDLER
   ========================================================================== */
function showToast(message, type = 'error') {
    // Find the currently active modal's toast container
    const activeModal = document.querySelector('.auth-modal.active');
    let toastContainer;

    if (activeModal) {
        toastContainer = activeModal.querySelector('.modal-toast-container');
    }

    // Fallback to a global container if no modal is open
    if (!toastContainer) {
        toastContainer = document.getElementById('global-toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'global-toast-container';
            toastContainer.style.cssText = 'position: fixed; bottom: 20px; right: 20px; z-index: 10005;';
            document.body.appendChild(toastContainer);
        }
    }

    // Create the toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<span>${message}</span>`;

    // Clear previous toasts inside this container and insert the new one
    toastContainer.innerHTML = '';
    toastContainer.appendChild(toast);

    // Auto-remove after 4 seconds
    setTimeout(() => {
        toast.remove();
    }, 4000);
}