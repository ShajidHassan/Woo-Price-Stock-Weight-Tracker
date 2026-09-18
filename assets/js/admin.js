(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Print button on the daily report.
        var printButtons = document.querySelectorAll('.pswt-print');
        Array.prototype.forEach.call(printButtons, function (button) {
            button.addEventListener('click', function () {
                window.print();
            });
        });

        // Keep "from" and "to" dates in order as the user picks them.
        var from = document.getElementById('pswt-from');
        var to = document.getElementById('pswt-to');
        if (from && to) {
            from.addEventListener('change', function () {
                if (to.value && from.value > to.value) {
                    to.value = from.value;
                }
            });
            to.addEventListener('change', function () {
                if (from.value && to.value < from.value) {
                    from.value = to.value;
                }
            });
        }
    });
})();
