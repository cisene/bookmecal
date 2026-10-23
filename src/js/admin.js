/**
 * src/js/admin.js
 * Small behaviours for the admin pages, wired through data attributes:
 *   data-confirm="text"    on a form: ask before submitting
 *   data-autosubmit        on a select: submit its form on change
 *   data-edit-form="id"    on a button: copy data-values (JSON) into that form's fields; a list
 *                          ticks the checkboxes named "<key>[]" whose values it contains
 *   data-select-all        on a read-only input: select the whole value on focus (easy copying)
 *   data-toggle-group      on a select: in its form, show only the elements whose data-show-for
 *                          matches the selected value (without script everything stays visible)
 */
(function () {
    'use strict';

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-select-all]').forEach((input) => {
        input.addEventListener('focus', () => input.select());
    });

    document.querySelectorAll('[data-toggle-group]').forEach((select) => {
        const update = () => {
            select.form.querySelectorAll('[data-show-for]').forEach((el) => {
                el.hidden = el.dataset.showFor !== select.value;
            });
        };
        select.addEventListener('change', update);
        update();
    });

    document.querySelectorAll('[data-autosubmit]').forEach((el) => {
        el.addEventListener('change', () => el.form.submit());
    });

    document.querySelectorAll('[data-edit-form]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.getElementById(button.dataset.editForm);
            const values = JSON.parse(button.dataset.values || '{}');
            Object.entries(values).forEach(([name, value]) => {
                // A list fills a group of checkboxes named "name[]"
                if (Array.isArray(value)) {
                    form.querySelectorAll('input[type="checkbox"][name="' + name + '[]"]').forEach((box) => {
                        box.checked = value.includes(box.value);
                    });
                    return;
                }
                const field = form.elements.namedItem(name);
                if (!field) {
                    return;
                }
                if (field.type === 'checkbox') {
                    field.checked = Boolean(value);
                } else {
                    field.value = value === null ? '' : value;
                }
            });
            form.scrollIntoView({ behavior: 'smooth' });
        });
    });
})();
