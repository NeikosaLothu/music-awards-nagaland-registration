/* =========================================================
   MUSIC AWARDS OF NAGALAND — shared front-end behaviour
   Used by index.php (form), preview.php and success.php
   ========================================================= */

document.addEventListener('DOMContentLoaded', () => {

    /* Reveal-on-load elements (hero copy, cards, etc.) */
    document.querySelectorAll('.reveal').forEach((el, index) => {
        window.setTimeout(() => el.classList.add('is-visible'), Math.min(index * 70, 350));
    });

    /* -----------------------------------------------------
       Main submission form -> preview.php
    ----------------------------------------------------- */
    const form = document.getElementById('submissionForm');

    if (form) {
        form.addEventListener('submit', (event) => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                form.classList.add('was-validated');
                const firstInvalid = form.querySelector(':invalid');
                firstInvalid?.focus();
                return;
            }

            const button = document.getElementById('previewButton');
            if (button) {
                button.disabled = true;
                const label = button.querySelector('span:first-child');
                if (label) label.textContent = 'STARTING PAYMENT...';
            }
        });
    }

    /* -----------------------------------------------------
       Preview confirmation form -> submit.php
    ----------------------------------------------------- */
    const confirmForm = document.getElementById('confirmForm');

    if (confirmForm) {
        confirmForm.addEventListener('submit', () => {
            const button = document.getElementById('confirmButton');
            if (button) {
                button.disabled = true;
                const label = button.querySelector('span:first-child');
                if (label) label.textContent = 'Submitting...';
            }
        });
    }

});