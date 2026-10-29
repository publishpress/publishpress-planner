jQuery(function ($) {
    var select = $('#to_notify');
    var localRecipients = [];
    select.find('option').each(function () {
        if (!/^\d+$/.test(this.value)) {
            localRecipients.push({id: this.value, text: $(this).text()});
        }
    });
    $('select', '#pp_post_notify_users_box').pp_select2(
        {
            placeholder: '',
            width: '95%',
            tags: true,
            allowClear: true,
            ajax: {
                url: ajaxurl,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        action: 'pp_notifications_search_users',
                        nonce: select.attr('data-nonce'),
                        post_id: select.attr('data-post-id'),
                        q: params.term || '',
                        page: params.page || 1
                    };
                },
                processResults: function (data, params) {
                    if (!params.page || params.page === 1) {
                        var term = (params.term || '').toLowerCase();
                        data.results = localRecipients.filter(function (recipient) {
                            return recipient.text.toLowerCase().indexOf(term) !== -1;
                        }).concat(data.results);
                    }
                    return data;
                }
            }
        }
    );
});
