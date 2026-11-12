document.querySelectorAll('[data-order-status]').forEach((select) => {
    const form = select.closest('form');
    const reasonField = form.querySelector('.cancellation-reason');
    const reasonInput = reasonField.querySelector('textarea');

    const updateReasonVisibility = () => {
        const cancelling = select.value === 'Cancelled';
        reasonField.hidden = !cancelling;
        reasonInput.required = cancelling && select.dataset.currentStatus !== 'Cancelled';
        if (!cancelling) {
            reasonInput.value = '';
        }
    };

    select.addEventListener('change', updateReasonVisibility);
    updateReasonVisibility();
});

const orderItems = document.querySelector('.staff-order-items');
const orderItemTemplate = document.querySelector('#staff-order-item-template');
const addOrderItem = document.querySelector('[data-add-order-item]');
if (orderItems && orderItemTemplate && addOrderItem) {
    addOrderItem.addEventListener('click', () => {
        orderItems.append(orderItemTemplate.content.cloneNode(true));
    });

    orderItems.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-order-item]');
        if (removeButton) {
            removeButton.closest('.staff-order-item-row').remove();
        }
    });
}

const customerSelect = document.querySelector('.staff-order-create-form select[name="customer_id"]');
const addressInput = document.querySelector('.staff-order-create-form [name="delivery_address"]');
if (customerSelect && addressInput) {
    customerSelect.addEventListener('change', () => {
        const selectedOption = customerSelect.selectedOptions[0];
        addressInput.value = selectedOption?.dataset.address || '';
    });
}
