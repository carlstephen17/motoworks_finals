document.querySelectorAll('[data-claim-promo]').forEach((promoSelect) => {
    const form = promoSelect.closest('form');
    const optionSelect = form.querySelector('[data-claim-option]');
    if (!optionSelect) {
        return;
    }

    const currentOption = optionSelect.dataset.currentOption || '';
    const currentPromo = optionSelect.dataset.currentPromo || '';

    const populateOptions = () => {
        const selectedPromo = promoSelect.selectedOptions[0];
        const options = JSON.parse(selectedPromo?.dataset.options || '[]');
        const previousValue = optionSelect.value || currentOption;
        optionSelect.replaceChildren();

        options.forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            optionSelect.append(option);
        });

        if (previousValue && !options.includes(previousValue) && promoSelect.value === currentPromo) {
            const historicalOption = document.createElement('option');
            historicalOption.value = previousValue;
            historicalOption.textContent = `${previousValue} (existing claim)`;
            optionSelect.append(historicalOption);
        }

        optionSelect.disabled = options.length === 0;
        if (previousValue && Array.from(optionSelect.options).some((option) => option.value === previousValue)) {
            optionSelect.value = previousValue;
        } else if (options.length > 0) {
            optionSelect.selectedIndex = 0;
        }
    };

    promoSelect.addEventListener('change', populateOptions);
    populateOptions();
});

document.querySelectorAll('[data-open-claim-dialog]').forEach((button) => {
    button.addEventListener('click', () => {
        document.getElementById(button.dataset.openClaimDialog)?.showModal();
    });
});

document.querySelectorAll('[data-edit-claim-dialog]').forEach((button) => {
    button.addEventListener('click', () => {
        const dialog = document.getElementById(button.dataset.editClaimDialog);
        dialog?.showModal();
        dialog?.querySelector('form')?.scrollIntoView({ block: 'nearest' });
    });
});

document.querySelectorAll('[data-close-claim-dialog]').forEach((button) => {
    button.addEventListener('click', () => {
        button.closest('dialog')?.close();
    });
});
