document.addEventListener("DOMContentLoaded", function () {

    /*
    |--------------------------------------------------------------------------
    | Confirm dangerous actions
    |--------------------------------------------------------------------------
    */

    const confirmButtons =
        document.querySelectorAll("[data-confirm]");

    confirmButtons.forEach(function (button) {

        button.addEventListener("click", function (event) {

            const message =
                button.getAttribute("data-confirm");

            if (!confirm(message)) {
                event.preventDefault();
            }

        });

    });

});