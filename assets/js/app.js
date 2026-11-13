document.addEventListener('DOMContentLoaded', () => {
    // Success and info messages disappear by themselves (errors stay)
    document.querySelectorAll('.alert-success, .alert-info').forEach((box) => {
        setTimeout(() => box.remove(), 6000);
            // Show a block only when another field has a given value:
    // <div data-show-if="method=percentage">
    document.querySelectorAll('[data-show-if]').forEach((box) => {
        const [name, value] = box.dataset.showIf.split('=');
        const inputs = document.querySelectorAll('[name="' + name + '"]');

        const update = () => {
            let current = '';
            inputs.forEach((input) => {
                if (input.type !== 'radio' || input.checked) {
                    current = input.value;
                }
            });
            box.hidden = current !== value;
        };

        inputs.forEach((input) => input.addEventListener('change', update));
        update();
    });
 });

    // Client-side validation for any form marked with data-validate
    document.querySelectorAll('form[data-validate]').forEach((form) => {
        form.addEventListener('submit', (event) => {
                        // Fields marked data-match must equal the field they name
            form.querySelectorAll('input[data-match]').forEach((field) => {
                const other = form.querySelector('#' + field.dataset.match);
                field.setCustomValidity(
                    other && field.value !== other.value ? 'The passwords do not match.' : ''
                );
            });
            let firstInvalid = null;

            form.querySelectorAll('input, select, textarea').forEach((field) => {
                const message = field.closest('.form-group')?.querySelector('.field-error');

                if (field.checkValidity()) {
                    field.removeAttribute('aria-invalid');
                    if (message) message.textContent = '';
                } else {
                    field.setAttribute('aria-invalid', 'true');
                    if (message) message.textContent = field.validationMessage;
                    firstInvalid = firstInvalid || field;
                }
            });

            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.focus();
            }
        });
    });
        // Ask before a risky action: <form data-confirm="Are you sure?">
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
});
