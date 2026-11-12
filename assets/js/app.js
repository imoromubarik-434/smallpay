document.addEventListener('DOMContentLoaded', () => {
    // Success and info messages disappear by themselves (errors stay)
    document.querySelectorAll('.alert-success, .alert-info').forEach((box) => {
        setTimeout(() => box.remove(), 6000);
    });

    // Client-side validation for any form marked with data-validate
    document.querySelectorAll('form[data-validate]').forEach((form) => {
        form.addEventListener('submit', (event) => {
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
});
