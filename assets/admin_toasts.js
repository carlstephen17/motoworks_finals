const toastContainer = document.getElementById('admin-toast-container');

function showAdminToast(message, type = 'success', duration = 4000) {
    if (!toastContainer) {
        console.error('The admin notification region is missing.');
        return;
    }

    const toast = document.createElement('div');
    const toastType = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info';
    toast.className = `admin-toast admin-toast-${toastType}`;
    toast.setAttribute('role', toastType === 'error' ? 'alert' : 'status');
    const icons = {
        success: 'fa-circle-check',
        error: 'fa-circle-exclamation',
        warning: 'fa-triangle-exclamation',
        info: 'fa-circle-info'
    };
    const icon = document.createElement('i');
    icon.className = `fas ${icons[toastType]}`;
    icon.setAttribute('aria-hidden', 'true');
    const text = document.createElement('span');
    text.className = 'admin-toast-message';
    text.textContent = message;
    toast.append(icon, text);
    toastContainer.append(toast);

    if (typeof toastContainer.showPopover === 'function') {
        if (!toastContainer.matches(':popover-open')) toastContainer.showPopover();
    }
    toastContainer.classList.add('is-visible');

    window.setTimeout(() => {
        toast.classList.add('is-dismissing');
        window.setTimeout(() => {
            toast.remove();
            if (toastContainer.childElementCount > 0) return;

            if (typeof toastContainer.hidePopover === 'function' && toastContainer.matches(':popover-open')) {
                toastContainer.hidePopover();
            }
            toastContainer.classList.remove('is-visible');
        }, 180);
    }, duration);
}

window.showAdminToast = showAdminToast;

function displayFlashMessages() {
    if (!document.body.classList.contains('admin-page') && !document.body.classList.contains('staff-page')) return;

    document.querySelectorAll('[data-auto-dismiss][role="status"], [data-auto-dismiss][role="alert"]').forEach((message) => {
        const text = message.textContent.trim();
        if (text) showAdminToast(text, message.getAttribute('role') === 'alert' ? 'error' : 'success', 3000);
        message.remove();
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', displayFlashMessages, {once: true});
} else {
    displayFlashMessages();
}
