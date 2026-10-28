jQuery(function ($) {
    var __ = wp.i18n.__;

    $('.filter-posts').pp_select2({
        placeholder: ppNotifLog.text.allPosts,
        allowClear: true,
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                var query = {
                    search: params.term,
                    page: params.page || 1,
                    action: 'publishpress_search_post',
                    nonce: ppNotifLog.nonce
                };

                return query;
            }
        }
    });

    $('.filter-workflows').pp_select2({
        placeholder: ppNotifLog.text.allWorkflows,
        allowClear: true,
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                var query = {
                    search: params.term,
                    page: params.page || 1,
                    action: 'publishpress_search_workflow',
                    nonce: ppNotifLog.nonce
                };

                return query;
            }
        }
    });

    $('.filter-actions').pp_select2({
        placeholder: ppNotifLog.text.allActions,
        allowClear: true
    });

    $('.filter-channels').pp_select2({
        placeholder: ppNotifLog.text.allChannels,
        allowClear: true
    });

    $('.filter-statuses').pp_select2({
        placeholder: ppNotifLog.text.allStatuses,
        allowClear: true
    });

    // Label the select2 replacement controls with the existing screen-reader labels.
    [
        ['.filter-posts', 'publishpress-notifications-filter-posts-label'],
        ['.filter-workflows', 'publishpress-notifications-filter-workflows-label'],
        ['.filter-actions', 'publishpress-notifications-filter-events-label'],
        ['.filter-channels', 'publishpress-notifications-filter-channels-label']
    ].forEach(function (pair) {
        if (!document.getElementById(pair[1])) {
            return;
        }

        $(pair[0]).each(function () {
            var $selection = $(this).next('.select2-container').find('.select2-selection');
            var renderedId = $selection.find('.select2-selection__rendered').attr('id');
            var labelledBy = renderedId ? pair[1] + ' ' + renderedId : pair[1];

            $selection.attr('aria-labelledby', labelledBy);
        });
    });

    $('.view-log').on('click', function (event) {
        event.preventDefault();
        var $button = $(event.currentTarget).closest('.view-log');
        var $dialog = $('<div class="notif-log-modal">Test!</div>');
        var notificationId = $button.data('id');
        var receiver = $button.data('receiver');
        var receiverText = $button.data('receiver-text');
        var channel = $button.data('channel');


        $('body').append($dialog);

        $dialog.text(ppNotifLog.text.loading);

        $dialog.dialog({
            title: ppNotifLog.text.dialogTitle + ': ' + notificationId + ' ' + __('for', 'publishpress') + ' ' + receiverText + ' ' + __('by', 'publishpress') + ' ' + channel,
            dialogClass: 'wp-dialog',
            autoOpen: false,
            draggable: true,
            width: '70%',
            modal: true,
            resizable: false,
            closeOnEscape: true,
            position: {
                my: 'center',
                at: 'center',
                of: window
            },
            open: function () {
                // close dialog by clicking the overlay behind it
                $('.ui-widget-overlay').bind('click', function () {
                    $dialog.dialog('close');
                });
            },
            create: function () {
                // style fix for WordPress admin
                $('.ui-dialog-titlebar-close').addClass('ui-button');
            }
        });

        $dialog.dialog('open');

        $dialog.load(ajaxurl, {
            nonce: ppNotifLog.nonce,
            action: 'publishpress_view_notification',
            id: notificationId,
            receiver: receiver,
            channel: channel
        }, function () {
            $dialog.dialog("option", "position", {my: "center", at: "center", of: window});
        });
    });

    $('.filter-date-begin').datepicker();
    $('.filter-date-end').datepicker();

    $('.admin_page_pp-notif-log .slide-closed-text').on('click', function (event) {
        event.preventDefault();

        var $toggle = $(this);
        var $slide = $('#' + $toggle.attr('aria-controls'));
        var isExpanded = $toggle.attr('aria-expanded') === 'true';

        if (isExpanded) {
            $slide.slideUp();
        } else {
            $slide.slideDown();
        }

        $toggle.attr('aria-expanded', isExpanded ? 'false' : 'true');
        $toggle.find('.slide-closed-label').text(
            $toggle.data(isExpanded ? 'text-collapsed' : 'text-expanded')
        );
        $toggle.find('.dashicons')
            .toggleClass('dashicons-arrow-down-alt2', isExpanded)
            .toggleClass('dashicons-arrow-up-alt2', !isExpanded);
    });

    if ($('body').hasClass('admin_page_pp-notif-log')) {
        $("#toplevel_page_pp-calendar")
            .removeClass("wp-not-current-submenu")
            .addClass(
                "wp-has-current-submenu wp-menu-open toplevel_page_pp-calendar"
        );
        $('#toplevel_page_pp-calendar > a')
            .removeClass("wp-not-current-submenu")
            .addClass(
                "wp-has-current-submenu wp-menu-open open-if-no-js menu-top-first"
        );
        $('#toplevel_page_pp-calendar li a[href="edit.php?post_type=psppnotif_workflow"]')
            .closest('li')
            .addClass('current'
        );
    }
});