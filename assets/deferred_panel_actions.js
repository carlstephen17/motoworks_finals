const undoWindowMs = 3000;
let awaitingConfirmation = null;
let pendingAction = null;
let submittingDelete = false;

function setConfirmationModalOpen(isOpen) {
    const modal = document.getElementById('panel-action-confirmation-modal');
    if (!modal) {
        return;
    }

    if (isOpen && !modal.open) {
        modal.showModal();
    } else if (!isOpen && modal.open) {
        modal.close();
    }
    modal.setAttribute('aria-hidden', String(!isOpen));
}

function showPanelActionToast(message, success) {
    if (typeof window.showAdminToast === 'function') {
        window.showAdminToast(message, success ? 'success' : 'error');
        return;
    }

    const container = document.getElementById('panel-action-toast-container');
    if (!container) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = `history-toast ${success ? 'history-toast-success' : 'history-toast-error'}`;
    toast.setAttribute('role', success ? 'status' : 'alert');
    toast.textContent = message;
    container.append(toast);
    if (typeof container.showPopover === 'function' && !container.matches(':popover-open')) {
        container.showPopover();
    }
    container.classList.add('is-visible');
    window.setTimeout(() => {
        toast.remove();
        hidePendingActionNotifications(container);
    }, undoWindowMs);
}

function hidePendingActionNotifications(container) {
    if (container.childElementCount > 0) return;
    if (typeof container.hidePopover === 'function' && container.matches(':popover-open')) {
        container.hidePopover();
    }
    container.classList.remove('is-visible');
}

function setFormDisabled(form, disabled, previousStates = null) {
    const controls = [...form.querySelectorAll('button, input, select, textarea')];
    if (disabled) {
        return controls.map((control) => {
            const previousDisabled = control.disabled;
            control.disabled = true;
            return {control, previousDisabled};
        });
    }

    previousStates?.forEach(({control, previousDisabled}) => {
        control.disabled = previousDisabled;
    });
    return [];
}

function restoreInitialStatus(form) {
    const status = form.querySelector('select[name="status"]');
    const initialStatus = status?.dataset.initialStatus;
    if (!status || !initialStatus || ![...status.options].some((option) => option.value === initialStatus)) {
        return;
    }

    status.value = initialStatus;
    status.dispatchEvent(new Event('change', {bubbles: true}));
}

function closeConfirmation(restoreStatus) {
    if (restoreStatus && awaitingConfirmation) {
        restoreInitialStatus(awaitingConfirmation.form);
    }
    awaitingConfirmation = null;
    setConfirmationModalOpen(false);
}

function updateDeletedRow(form, result) {
    const row = form.closest('tr');
    if (!row) {
        console.error('The deleted record row could not be found.');
        return;
    }

    if (!result.deleted && result.active === false) {
        row.dataset.status = '0';
        const statusBadge = row.querySelector('.badge');
        if (statusBadge) {
            statusBadge.textContent = 'Inactive';
            statusBadge.classList.remove('badge-success');
            statusBadge.classList.add('badge-danger');
        }
        form.remove();

        const activeFilter = document.getElementById('productStatus') || document.getElementById('serviceStatus');
        if (activeFilter?.value === '1') row.hidden = true;
        return;
    }

    const detailsRow = row.nextElementSibling;
    if (detailsRow?.classList.contains('order-details-row')) detailsRow.remove();
    row.remove();
}

function updateDeletedCounts(counts = {}) {
    Object.entries(counts).forEach(([key, count]) => {
        const card = document.querySelector(`[data-order-count="${key}"] h3, [data-appointment-count="${key}"] h3`);
        if (card) card.textContent = String(count);
    });
}

async function submitAdminDelete(form) {
    submittingDelete = true;
    const path = new URL(form.action, window.location.href).pathname;
    const expectedId = path.match(/\/(\d+)\/delete$/)?.[1];
    if (!expectedId) {
        submittingDelete = false;
        showPanelActionToast('The delete URL does not identify a record.', false);
        return;
    }

    const formData = new FormData(form);
    const previousDisabledStates = setFormDisabled(form, true);
    form.dataset.submitting = 'true';
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            throw new Error('The server did not return a valid deletion response.');
        }
        const result = await response.json();
        if (!response.ok || result.success !== true) {
            throw new Error(result.message || `Deletion failed (${response.status}).`);
        }
        if (Number(result.id) !== Number(expectedId) || typeof result.deleted !== 'boolean') {
            throw new Error('The deletion response did not match the selected record.');
        }

        updateDeletedRow(form, result);
        updateDeletedCounts(result.counts);
        showAdminToast(result.message || 'Record deleted.', result.deleted ? 'success' : 'warning');
    } catch (error) {
        console.error('Unable to delete the selected record.', error);
        showPanelActionToast(error.message || 'Unable to delete the selected record. Please try again.', false);
    } finally {
        submittingDelete = false;
        form.dataset.submitting = 'false';
        setFormDisabled(form, false, previousDisabledStates);
    }
}

function beginPendingAction(form, submitter) {
    if (pendingAction) {
        return;
    }

    const container = document.getElementById('panel-action-notifications');
    if (!container) {
        console.error('The deferred action notification region is missing.');
        window.alert('Unable to start the Undo period. Refresh the page and try again.');
        return;
    }

    const record = {
        form,
        submitter,
        previousDisabledStates: setFormDisabled(form, true),
        notice: document.createElement('div'),
        deadline: Date.now() + undoWindowMs,
        timer: null,
        interval: null
    };

    record.notice.className = 'history-notification history-notification-undo';
    record.notice.setAttribute('role', 'status');
    record.notice.setAttribute('aria-live', 'polite');
    const text = document.createElement('span');
    text.append(document.createTextNode('The action will be applied in '));
    const countdown = document.createElement('strong');
    countdown.textContent = '3.0';
    text.append(countdown, document.createTextNode('s. Undo is available during this time.'));

    const undoButton = document.createElement('button');
    undoButton.type = 'button';
    undoButton.className = 'cancellation-undo-button';
    undoButton.textContent = 'Undo';
    undoButton.dataset.panelActionUndo = '';
    record.notice.append(text, undoButton);
    container.append(record.notice);
    if (typeof container.showPopover === 'function' && !container.matches(':popover-open')) {
        container.showPopover();
    }
    container.classList.add('is-visible');
    pendingAction = record;

    const updateCountdown = () => {
        const remaining = Math.max(0, record.deadline - Date.now());
        countdown.textContent = (remaining / 1000).toFixed(1);
    };
    updateCountdown();
    record.interval = window.setInterval(updateCountdown, 100);
    record.timer = window.setTimeout(() => {
        window.clearInterval(record.interval);
        pendingAction = null;
        record.notice.remove();
        hidePendingActionNotifications(container);
        setFormDisabled(form, false, record.previousDisabledStates);
        if (document.body.classList.contains('admin-page') && form.dataset.deferredAction === 'delete') {
            void submitAdminDelete(form);
            return;
        }
        form.dataset.deferredSubmit = 'true';
        if (submitter) {
            form.requestSubmit(submitter);
        } else {
            form.requestSubmit();
        }
        delete form.dataset.deferredSubmit;
    }, undoWindowMs);
}

function undoPendingAction() {
    if (!pendingAction) {
        return;
    }

    const {form, previousDisabledStates, notice, timer, interval} = pendingAction;
    window.clearTimeout(timer);
    window.clearInterval(interval);
    pendingAction = null;
    notice.remove();
    hidePendingActionNotifications(document.getElementById('panel-action-notifications'));
    setFormDisabled(form, false, previousDisabledStates);
    restoreInitialStatus(form);
    showPanelActionToast('Action cancelled.', true);
}

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-deferred-action], form[data-deferred-cancel-status]');
    if (!form) {
        return;
    }
    if (form.dataset.deferredSubmit === 'true') {
        return;
    }

    const cancelStatus = form.dataset.deferredCancelStatus;
    const status = form.querySelector('select[name="status"]');
    if (cancelStatus && (!status || status.value !== cancelStatus || status.value === status.dataset.initialStatus)) {
        return;
    }
    if (pendingAction || awaitingConfirmation || submittingDelete) {
        event.preventDefault();
        showPanelActionToast('Finish or undo the pending action before starting another.', false);
        return;
    }

    const isDelete = form.dataset.deferredAction === 'delete';
    const message = form.dataset.confirmMessage || (isDelete ? 'Delete this record?' : 'Cancel this transaction?');
    const modal = document.getElementById('panel-action-confirmation-modal');
    if (!modal) {
        event.preventDefault();
        console.error('The deferred action confirmation modal is missing.');
        window.alert('Unable to confirm this action. Refresh the page and try again.');
        return;
    }

    event.preventDefault();
    awaitingConfirmation = {form, submitter: event.submitter};
    modal.querySelector('[data-panel-action-title]').textContent = isDelete ? 'Confirm permanent deletion' : 'Confirm cancellation';
    modal.querySelector('[data-panel-action-message]').textContent = isDelete
        ? `${message} This action becomes permanent after the 3-second Undo period.`
        : message;
    modal.querySelector('[data-panel-action-no]').textContent = isDelete ? 'Cancel' : 'No';
    modal.querySelector('[data-panel-action-yes]').textContent = isDelete ? 'Delete' : 'Yes, cancel';
    setConfirmationModalOpen(true);
    modal.querySelector('[data-panel-action-no]').focus();
});

document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) {
        return;
    }

    if (target.closest('[data-panel-action-no]')) {
        closeConfirmation(true);
        return;
    }
    if (target.closest('[data-panel-action-yes]')) {
        const action = awaitingConfirmation;
        awaitingConfirmation = null;
        setConfirmationModalOpen(false);
        if (action) {
            beginPendingAction(action.form, action.submitter);
        }
        return;
    }
    if (target.closest('[data-panel-action-undo]')) {
        undoPendingAction();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && awaitingConfirmation) {
        event.preventDefault();
        closeConfirmation(true);
    }
});

window.addEventListener('beforeunload', (event) => {
    if (!pendingAction && !submittingDelete) {
        return;
    }

    event.preventDefault();
    event.returnValue = '';
});
