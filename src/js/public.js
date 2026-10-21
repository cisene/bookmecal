/**
 * src/js/public.js
 * Behaviour shared by all public pages: the language switcher submits on change.
 */
(function () {
    'use strict';

    document.querySelectorAll('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form.submit()));
})();
