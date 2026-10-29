(function () {
    'use strict';

    // The end field cannot be earlier than the start; the browser enforces min= on datetime-local.
    var start = document.getElementById('talktoyourblog_event_start');
    var end = document.getElementById('talktoyourblog_event_end');
    if (!start || !end) {
        return;
    }

    var sync = function () {
        if (start.value) {
            end.min = start.value;
            if (end.value && end.value < start.value) {
                end.value = '';
            }
        } else {
            end.removeAttribute('min');
        }
    };

    start.addEventListener('change', sync);
    sync();
}());
