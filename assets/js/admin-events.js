
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector(
        '#post'
    );

    const tbody = document.getElementById(
        'pavilion-exceptions'
    );

    const addButton = document.getElementById(
        'pavilion-add-exception'
    );

    const template = document.getElementById(
        'pavilion-exception-template'
    );

    function validateTime(input) {
        if (!input.value || input.disabled) {
            input.setCustomValidity('');
            return;
        }

        const time = input.value.split('T')[1];

        if (!time || time < '09:00' || time > '21:00') {
            input.setCustomValidity(
                'Please choose a time between 09:00 and 21:00.'
            );
        } else {
            input.setCustomValidity('');
        }
    }

    function initialiseTimeInput(input) {
        input.addEventListener('input', function () {
            validateTime(input);
        });

        input.addEventListener('change', function () {
            validateTime(input);
        });
    }

    function initialiseRow(row) {
        const action = row.querySelector(
            '.pavilion-exception-action'
        );

        const start = row.querySelector(
            '.pavilion-exception-start'
        );

        const end = row.querySelector(
            '.pavilion-exception-end'
        );

        function updateAction() {
            const moving = action.value === 'move';

            [start, end].forEach(function (input) {
                input.disabled = !moving;
                input.required = moving;

                if (!moving) {
                    input.setCustomValidity('');
                } else {
                    validateTime(input);
                }
            });
        }

        action.addEventListener('change', updateAction);

        initialiseTimeInput(start);
        initialiseTimeInput(end);

        updateAction();
    }

    document.querySelectorAll(
        '#pavilion_start, #pavilion_end, ' +
        '.pavilion-exception-start, .pavilion-exception-end'
    ).forEach(initialiseTimeInput);

    if (tbody && addButton && template) {
        let nextIndex = tbody.querySelectorAll('tr').length;

        tbody.querySelectorAll('tr').forEach(initialiseRow);

        addButton.addEventListener('click', function () {
            const html = template.innerHTML.replace(
                /__INDEX__/g,
                String(nextIndex++)
            );

            tbody.insertAdjacentHTML('beforeend', html);

            initialiseRow(tbody.lastElementChild);
        });

        tbody.addEventListener('click', function (event) {
            const button = event.target.closest(
                '.pavilion-remove-exception'
            );

            if (button) {
                button.closest('tr').remove();
            }
        });
    }

    if (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll(
                '#pavilion_start, #pavilion_end, ' +
                '.pavilion-exception-start, .pavilion-exception-end'
            ).forEach(function (input) {
                validateTime(input);
            });
        });
    }
});
