const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const sidebar = document.getElementById('staff-sidebar');
const mobileNavigation = window.matchMedia('(max-width: 768px)');
document.querySelectorAll('[data-auto-filter]').forEach((form) => {
    let searchTimer;
    const search = form.querySelector('input[type="search"]');
    search?.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => form.requestSubmit(), 300);
    });
    form.querySelectorAll('select, input[type="date"]').forEach((control) => {
        control.addEventListener('change', () => form.requestSubmit());
    });
    form.addEventListener('submit', () => window.clearTimeout(searchTimer));
});

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

function showStaffRecordToast(message, type) {
    if (typeof window.showAdminToast === 'function') {
        window.showAdminToast(message, type);
    } else {
        console.error(message);
    }
}

function setStaffStatusBadge(badge, status) {
    if (!badge) return;
    badge.textContent = status;
    badge.className = `badge ${status === 'Completed' ? 'badge-success' : (status === 'Cancelled' ? 'badge-danger' : 'badge-warning')}`;
}

function updateStaffRecordCounts(section, counts = {}) {
    Object.entries(counts).forEach(([key, count]) => {
        const value = document.querySelector(`[data-${section}-count="${key}"] .stat-info h3`);
        if (value) value.textContent = String(count);
    });
}

function requestRecordData(button, dataAttribute) {
    try {
        return JSON.parse(button.dataset[dataAttribute]);
    } catch (error) {
        console.error('Unable to read the selected staff record.', error);
        showStaffRecordToast('Unable to load the selected record details.', 'error');
        return null;
    }
}

document.addEventListener('click', (event) => {
    const appointmentEditButton = event.target.closest('[data-edit-appointment]');
    const promoEditButton = event.target.closest('[data-edit-promo-claim]');
    const appointmentCloseButton = event.target.closest('[data-close-appointment-edit]');
    const promoCloseButton = event.target.closest('[data-close-promo-edit]');

    if (appointmentCloseButton || promoCloseButton) {
        (appointmentCloseButton || promoCloseButton).closest('dialog')?.close();
        return;
    }

    if (appointmentEditButton) {
        const data = requestRecordData(appointmentEditButton, 'appointment');
        const dialog = document.getElementById('staffAppointmentEdit');
        const form = dialog?.querySelector('[data-appointment-edit-form]');
        if (!data || !dialog || !form) return;
        form.action = appointmentEditButton.dataset.editUrl;
        form.dataset.recordId = String(appointmentEditButton.closest('tr')?.dataset.id || '');
        form.querySelector('[data-appointment-csrf]').value = data.csrfToken;
        Object.entries(data.edit).forEach(([name, value]) => {
            const field = form.querySelector(`[data-appointment-field="${name}"]`);
            if (field) field.value = value ?? '';
        });
        dialog.showModal();
        form.querySelector('[data-appointment-field="service"]')?.focus();
        return;
    }

    if (promoEditButton) {
        const data = requestRecordData(promoEditButton, 'claim');
        const dialog = document.getElementById('staffPromoClaimEdit');
        const form = dialog?.querySelector('[data-promo-claim-edit-form]');
        if (!data || !dialog || !form) return;
        form.action = promoEditButton.dataset.editUrl;
        form.dataset.recordId = String(promoEditButton.closest('tr')?.dataset.id || '');
        form.querySelector('[data-promo-csrf]').value = data.csrfToken;
        Object.entries(data.edit).forEach(([name, value]) => {
            const field = form.querySelector(`[data-promo-field="${name}"]`);
            if (field) field.value = value ?? '';
        });
        dialog.showModal();
        form.querySelector('[data-promo-field="bundleSlug"]')?.focus();
    }
});

async function postStaffEditForm(form, expectedId, payloadKey) {
    const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    });
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
        throw new Error(`The update failed (${response.status}).`);
    }
    const result = await response.json();
    if (!response.ok || result.success !== true || !result[payloadKey]) {
        throw new Error(result.message || `The update failed (${response.status}).`);
    }
    const id = Number(String(result[payloadKey].id).replace(/\D/g, ''));
    if (id !== Number(expectedId)) {
        throw new Error('The update response did not match the selected record.');
    }
    return result;
}

document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)
        || (!form.matches('[data-appointment-edit-form]') && !form.matches('[data-promo-claim-edit-form]'))) return;
    event.preventDefault();
    if (form.dataset.submitting === 'true') return;

    const appointmentForm = form.matches('[data-appointment-edit-form]');
    const payloadKey = appointmentForm ? 'appointment' : 'claim';
    const expectedId = form.dataset.recordId;
    const submitButton = form.querySelector('button[type="submit"]');
    form.dataset.submitting = 'true';
    if (submitButton) submitButton.disabled = true;

    try {
        const result = await postStaffEditForm(form, expectedId, payloadKey);
        const record = result[payloadKey];
        const row = document.querySelector(`.${appointmentForm ? 'appointment' : 'promo-claim'}-row[data-id="${CSS.escape(expectedId)}"]`);
        if (!row) throw new Error('The updated record row could not be found.');

        if (appointmentForm) {
            row.querySelector('[data-appointment-vehicle]').textContent = record.vehicle;
            const serviceCell = row.querySelector('[data-appointment-service]');
            serviceCell.replaceChildren(document.createTextNode(record.service));
            const price = document.createElement('small');
            price.className = 'text-muted';
            price.textContent = record.price;
            serviceCell.append(document.createElement('br'), price);
            row.querySelector('[data-appointment-date]').textContent = record.date;
            row.querySelector('[data-appointment-time]').textContent = record.time;
            setStaffStatusBadge(row.querySelector('[data-appointment-status]'), record.status);
            row.querySelectorAll('.view-appointment, .edit-appointment').forEach((button) => {
                const current = JSON.parse(button.dataset.appointment);
                button.dataset.appointment = JSON.stringify({...record, csrfToken: current.csrfToken});
            });
            updateStaffRecordCounts('appointment', result.counts);
            if (record.status === 'Completed') row.querySelector('.edit-appointment')?.remove();
        } else {
            const promotionCell = row.querySelector('[data-claim-promotion]');
            promotionCell.replaceChildren(document.createTextNode(record.promotion));
            const option = document.createElement('small');
            option.className = 'text-muted';
            option.dataset.claimOption = '';
            option.textContent = record.selectedOption;
            promotionCell.append(document.createElement('br'), option);
            row.querySelector('[data-claim-date]').textContent = record.appointmentDate;
            row.querySelector('[data-claim-time]').textContent = record.appointmentTime;
            setStaffStatusBadge(row.querySelector('[data-claim-status]'), record.status);
            row.querySelectorAll('.view-promo-claim, .edit-promo-claim').forEach((button) => {
                const current = JSON.parse(button.dataset.claim);
                button.dataset.claim = JSON.stringify({...record, csrfToken: current.csrfToken});
            });
            updateStaffRecordCounts('promo', result.counts);
            if (record.status === 'Completed') row.querySelector('.edit-promo-claim')?.remove();
        }

        form.closest('dialog')?.close();
        showStaffRecordToast(result.message || 'Record updated.', 'success');
    } catch (error) {
        console.error('Unable to update the selected staff record.', error);
        showStaffRecordToast(error.message || 'Unable to update the selected record. Please try again.', 'error');
    } finally {
        form.dataset.submitting = 'false';
        if (submitButton) submitButton.disabled = false;
    }
});
