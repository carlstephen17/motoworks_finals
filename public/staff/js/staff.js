const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const sidebar = document.getElementById('staff-sidebar');
const mobileNavigation = window.matchMedia('(max-width: 768px)');
const toggleSidebar = (isOpen) => {
    document.body.classList.toggle('staff-nav-open', isOpen);
    sidebarToggle?.setAttribute('aria-expanded', String(isOpen));
    sidebarToggle?.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
    if (sidebar) {
        sidebar.inert = !isOpen && mobileNavigation.matches;
    }
    if (isOpen && mobileNavigation.matches) {
        sidebar?.querySelector('.sidebar-menu a')?.focus();
    }
};

toggleSidebar(false);
if (typeof mobileNavigation.addEventListener === 'function') {
    mobileNavigation.addEventListener('change', () => toggleSidebar(false));
} else {
    mobileNavigation.addListener(() => toggleSidebar(false));
}

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && document.body.classList.contains('staff-nav-open')) {
        toggleSidebar(false);
        sidebarToggle?.focus();
    }
});

document.addEventListener('click', (event) => {
    const sidebarToggleButton = event.target.closest('[data-sidebar-toggle]');
    const sidebarBackdrop = event.target.closest('[data-sidebar-backdrop]');
    const sidebarLink = event.target.closest('.admin-sidebar a');

    if (sidebarToggleButton) {
        toggleSidebar(!document.body.classList.contains('staff-nav-open'));
        return;
    }
    if (sidebarBackdrop || sidebarLink) {
        toggleSidebar(false);
    }

    const openDialog = event.target.closest('[data-open-staff-dialog]');
    const editDialog = event.target.closest('[data-edit-staff-dialog]');
    const dialogButton = openDialog || editDialog;
    if (dialogButton) {
        const dialog = document.getElementById(dialogButton.dataset.openStaffDialog || dialogButton.dataset.editStaffDialog);
        dialog?.showModal();
        if (editDialog) {
            dialog?.querySelector('form')?.scrollIntoView({ block: 'nearest' });
        }
        return;
    }

    const closeDialog = event.target.closest('[data-close-staff-dialog]');
    if (closeDialog) {
        closeDialog.closest('dialog')?.close();
        return;
    }

    const toggle = event.target.closest('[data-edit-toggle]');
    if (!toggle) {
        return;
    }

    const form = document.getElementById(toggle.dataset.editToggle);
    if (!form) {
        return;
    }

    form.hidden = !form.hidden;
    toggle.setAttribute('aria-expanded', String(!form.hidden));
});
