document.querySelectorAll('[data-motorcycle-form]').forEach((form) => {
    const customer = form.querySelector('[data-customer-select]');
    const mode = form.querySelector('[data-motorcycle-mode]');
    const motorcycle = form.querySelector('[data-motorcycle-select]');
    const existingGroup = form.querySelector('[data-existing-motorcycle]');
    const newGroups = form.querySelectorAll('[data-new-motorcycle]');
    const newFields = form.querySelectorAll('[data-new-motorcycle-required]');

    const updateForm = () => {
        const isNew = mode.value === 'new';
        existingGroup.hidden = isNew;
        motorcycle.required = !isNew;
        newGroups.forEach((group) => { group.hidden = !isNew; });
        newFields[0].required = isNew;
        newFields[1].required = false;

        Array.from(motorcycle.options).forEach((option) => {
            if (!option.value) {
                option.hidden = false;
                return;
            }
            option.hidden = option.dataset.customer !== customer.value;
            if (option.hidden && option.selected) {
                motorcycle.value = '';
            }
        });
    };

    customer.addEventListener('change', updateForm);
    mode.addEventListener('change', updateForm);
    updateForm();
});
