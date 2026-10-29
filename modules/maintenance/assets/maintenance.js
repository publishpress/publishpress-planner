jQuery(function ($) {
    $('#pp-clear-notification-logs').on('click', function () {
        if (!window.confirm(ppMaintenance.confirm)) {
            return;
        }
        var button = $(this), status = $('#pp-maintenance-status'), deleted = 0;
        button.prop('disabled', true);
        function stop(message) {
            status.text(message);
            button.prop('disabled', false);
        }
        function batch() {
            $.post(ajaxurl, {
                action: 'publishpress_clear_notification_logs', nonce: button.attr('data-nonce')
            }).done(function (response) {
                if (!response.success) {
                    stop(ppMaintenance.error);
                    return;
                }
                deleted += response.data.deleted;
                status.text(ppMaintenance.progress.replace('%d', deleted));
                if (response.data.more) {
                    batch();
                } else {
                    stop(ppMaintenance.complete.replace('%d', deleted));
                }
            }).fail(function () { stop(ppMaintenance.error); });
        }
        batch();
    });
});
