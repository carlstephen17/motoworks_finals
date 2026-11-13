const activeCancellationTimers = new Map();
let cancellationFormAwaitingConfirmation = null;

function showHistoryToast(message, success) {
    let container = document.getElementById('history-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'history-toast-container';
        container.className = 'history-toast-container';
        container.setAttribute('aria-live', 'polite');
        document.body.append(container);
    }

    const toast = document.createElement('div');
    toast.className = `history-toast ${success ? 'history-toast-success' : 'history-toast-error'}`;
    toast.setAttribute('role', success ? 'status' : 'alert');
    toast.textContent = message;
    container.append(toast);
    window.setTimeout(() => toast.remove(), 3000);
}

function updateTransactionStatus(recordType, recordId, status) {
    const key = `${recordType}:${recordId}`;
    document.querySelectorAll('[data-transaction-key]').forEach((row) => {
        if (row.dataset.transactionKey !== key) {
            return;
        }

        const statusElement = row.querySelector('[data-transaction-status]');
        if (statusElement) {
            statusElement.textContent = status;
            if (recordType === 'service_booking') {
                statusElement.className = `badge-status-${status.toLowerCase().replaceAll(' ', '-')}`;
            } else {
                const badge = status.toLowerCase().includes('cancel')
                    ? 'badge-cancelled'
                    : status === 'Completed'
                        ? 'badge-completed'
                        : 'badge-pending';
                statusElement.className = `badge ${badge}`;
            }
        }

        const cancelForm = row.querySelector('[data-cancel-form]');
        if (cancelForm) {
            cancelForm.hidden = status !== 'Pending';
            const button = cancelForm.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = status !== 'Pending';
            }
        }

        const viewButton = row.querySelector('.js-view-order, .js-view-claim');
        if (viewButton) {
            viewButton.dataset.status = status;
        }
    });

    if (recordType === 'service_booking') {
        document.querySelectorAll('[data-transaction-details-status]').forEach((statusElement) => {
            if (statusElement.dataset.transactionDetailsStatus === String(recordId)) {
                statusElement.textContent = status;
                statusElement.className = `badge-status-${status.toLowerCase().replaceAll(' ', '-')}`;
            }
        });
    }
}

function setCancellationModalOpen(isOpen) {
    const modal = document.getElementById('cancel-confirmation-modal');
    if (!modal) {
        return;
    }
    modal.classList.toggle('active', isOpen);
    modal.setAttribute('aria-hidden', String(!isOpen));
}

function createCancellationNotice(action, message) {
    const container = document.getElementById('history-notifications');
    if (!container) {
        return null;
    }

    const notice = document.createElement('div');
    notice.className = 'history-notification history-notification-undo';
    notice.setAttribute('role', 'status');
    notice.setAttribute('aria-live', 'polite');
    notice.dataset.cancellationAction = '';
    notice.dataset.actionId = action.id;
    notice.dataset.recordType = action.record_type;
    notice.dataset.recordId = String(action.record_id);
    notice.dataset.expiresAt = action.expires_at;
    notice.dataset.remainingMs = String(Math.max(0, Date.parse(action.expires_at) - Date.parse(action.server_now)));
    notice.dataset.undoUrl = action.undo_url;
    notice.dataset.undoToken = action.undo_token;
    notice.dataset.finalizeUrl = action.finalize_url;
    notice.dataset.finalizeToken = action.finalize_token;

    const text = document.createElement('span');
    text.append(document.createTextNode(`${message} Undo within `));
    const countdown = document.createElement('strong');
    countdown.dataset.countdown = '';
    countdown.textContent = '3.0';
    text.append(countdown, document.createTextNode('s.'));

    const undoButton = document.createElement('button');
    undoButton.type = 'button';
    undoButton.className = 'cancellation-undo-button';
    undoButton.dataset.cancelUndo = '';
    undoButton.textContent = 'Undo';

    notice.append(text, undoButton);
    container.append(notice);
    notice.dataset.initialized = 'true';
    startCancellationCountdown(notice);

    return notice;
}

async function postCancellationAction(url, token) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
        },
        body: new URLSearchParams({_token: token})
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
        const error = new Error(result.message || 'The cancellation request could not be completed.');
        error.result = result;
        throw error;
    }

    return result;
}

function startCancellationCountdown(notice) {
    const id = notice.dataset.actionId;
    const existing = activeCancellationTimers.get(id);
    if (existing) {
        window.clearInterval(existing.interval);
        window.clearTimeout(existing.timeout);
    }

    const expiresAt = Date.parse(notice.dataset.expiresAt);
    const initialRemaining = Number(notice.dataset.remainingMs);
    const deadline = Number.isFinite(initialRemaining)
        ? Date.now() + initialRemaining
        : expiresAt;
    const countdown = notice.querySelector('[data-countdown]');
    const updateCountdown = () => {
        const remaining = Math.max(0, deadline - Date.now());
        if (countdown) {
            countdown.textContent = (remaining / 1000).toFixed(1);
        }
    };

    updateCountdown();
    const interval = window.setInterval(updateCountdown, 100);
    const timeout = window.setTimeout(() => finalizeCancellation(notice), Math.max(0, deadline - Date.now()));
    activeCancellationTimers.set(id, {interval, timeout});
}

async function finalizeCancellation(notice) {
    const {actionId, recordType, recordId, finalizeUrl, finalizeToken} = notice.dataset;
    const timers = activeCancellationTimers.get(actionId);
    if (timers) {
        window.clearInterval(timers.interval);
        window.clearTimeout(timers.timeout);
        activeCancellationTimers.delete(actionId);
    }

    try {
        await postCancellationAction(finalizeUrl, finalizeToken);
        updateTransactionStatus(recordType, recordId, 'Cancelled');
        notice.remove();
    } catch (error) {
        if (error.result?.status === 'Cancelled') {
            updateTransactionStatus(recordType, recordId, 'Cancelled');
            notice.remove();
        } else {
            showHistoryToast(error.message, false);
            window.setTimeout(() => window.location.reload(), 1000);
        }
    }
}

async function undoCancellation(notice) {
    const {actionId, recordType, recordId, undoUrl, undoToken} = notice.dataset;
    const button = notice.querySelector('[data-cancel-undo]');
    if (button) {
        button.disabled = true;
    }

    try {
        await postCancellationAction(undoUrl, undoToken);
        const timers = activeCancellationTimers.get(actionId);
        if (timers) {
            window.clearInterval(timers.interval);
            window.clearTimeout(timers.timeout);
            activeCancellationTimers.delete(actionId);
        }
        updateTransactionStatus(recordType, recordId, 'Pending');
        notice.remove();
        showHistoryToast('Cancellation undone.', true);
    } catch (error) {
        if (error.result?.status === 'Cancelled') {
            updateTransactionStatus(recordType, recordId, 'Cancelled');
            notice.remove();
        }
        showHistoryToast(error.message, false);
        if (button) {
            button.disabled = false;
        }
    }
}

async function submitCancellation(form) {
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = true;
    }

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            body: new FormData(form),
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'The cancellation request could not be completed.');
        }

        const action = result.action;
        updateTransactionStatus(action.record_type, action.record_id, 'Cancellation Pending');
        createCancellationNotice(action, result.message);
    } catch (error) {
        showHistoryToast(error instanceof Error ? error.message : 'The cancellation request could not be completed.', false);
        if (submitButton) {
            submitButton.disabled = false;
        }
    }
}

function initializeHistoryNotifications() {
    document.querySelectorAll('[data-cancellation-action]').forEach((notice) => {
        if (!notice.dataset.initialized) {
            notice.dataset.initialized = 'true';
            startCancellationCountdown(notice);
        }
    });
    document.querySelectorAll('[data-auto-dismiss]').forEach((alert) => {
        if (!alert.dataset.dismissScheduled) {
            alert.dataset.dismissScheduled = 'true';
            window.setTimeout(() => alert.remove(), 3000);
        }
    });
}

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-cancel-form]');
    if (!form) {
        return;
    }

    event.preventDefault();
    cancellationFormAwaitingConfirmation = form;
    setCancellationModalOpen(true);
});

document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) {
        return;
    }

    if (target.closest('[data-cancel-confirm-no]')) {
        cancellationFormAwaitingConfirmation = null;
        setCancellationModalOpen(false);
        return;
    }
    if (target.closest('[data-cancel-confirm-yes]')) {
        const form = cancellationFormAwaitingConfirmation;
        cancellationFormAwaitingConfirmation = null;
        setCancellationModalOpen(false);
        if (form) {
            submitCancellation(form);
        }
        return;
    }

    const undoButton = target.closest('[data-cancel-undo]');
    if (undoButton) {
        const notice = undoButton.closest('[data-cancellation-action]');
        if (notice) {
            undoCancellation(notice);
        }
    }
});

document.addEventListener('turbo:load', initializeHistoryNotifications);
document.addEventListener('DOMContentLoaded', initializeHistoryNotifications);
initializeHistoryNotifications();
