jQuery(function ($) {
    if (ppNotif.log_url !== '' && $('body').hasClass('post-type-psppnotif_workflow')) {
        var target = $('.wrap .page-title-action').first();
        if (!target.length) {
            target = $('.wrap h1').first();
        }
        if (target.length && !$('#publishpress-notifications-log-link').length) {
            $('<a>').attr('id', 'publishpress-notifications-log-link')
                .attr('href', ppNotif.log_url).addClass('page-title-action')
                .text(ppNotif.log_text + ' (' + ppNotif.log_total + ')').insertAfter(target);
        }
    }
});
