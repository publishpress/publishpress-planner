// Content Overview specific JS, assumes that pp_date.js has already been included

jQuery(document).ready(function ($) {

    // Hide all post details when directed
    $('#toggle_details').on('click', function () {
        $('.post-title > p').toggle('hidden');
    });

    // Make print link open up print dialog
    $('#print_link').on('click', function () {
        window.print();
        return false;
    });

    // Hide a single section when directed
    $('h3.hndle,div.handlediv').on('click', function () {
        $(this).parent().children('div.inside').toggle();
    });

    // Change number of columns when choosing a new number from Screen Options
    $('input[name=pp_story_budget_screen_columns]').on('click', function () {
        var numColumns = $(this).val();

        $('.postbox-container').css('width', (100 / numColumns) + '%');
    });

    $('h2 a.change-date').on('click', function () {
        $(this).hide();
        $('h2 form .form-value').hide();
        $('h2 form input').show();
        $('h2 form a.change-date-cancel').show();

        var use_today_as_start_date_input = $('#pp-content-overview-range-use-today');
        if (use_today_as_start_date_input.length > 0) {
            use_today_as_start_date_input.val(0);
        }

        return false;
    });

    $('h2 form a.change-date-cancel').on('click', function () {
        $(this).hide();
        $('h2 form .form-value').show();
        $('h2 form input').hide();
        $('h2 form a.change-date').show();

        return false;
    });

    $('#pp-content-filters select').on('change', function () {
        if (!$(this).hasClass('non-trigger-select')) {
            $(this).closest('form').trigger('submit');
        }
    });

    $('#pp-content-overview-range-today-btn').on('click', function (event) {
        var start_date_input = $('#pp-content-overview-start-date');
        var use_today_as_start_date_input = $('#pp-content-overview-range-use-today');

        if (
            start_date_input.length === 0
            || !start_date_input.is(':visible')
            || use_today_as_start_date_input.length === 0
        ) {
            event.preventDefault();
            event.stopPropagation();
            return false;
        }

        use_today_as_start_date_input.val(1);
    });

    $('.co-cc-content .entry-item.form-item .new-fields .field .new-item-metakey').pp_select2({
        allowClear: true,
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 0,
            data: function (params) {
                return {
                    action: 'publishpress_content_search_meta_keys',
                    nonce: $(this).attr('data-nonce'),
                    q: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: false
        }
    });

    $('#pp-content-filters select#filter_author').pp_select2({
        allowClear: true,
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 0,
            data: function (params) {
                return {
                    action: 'publishpress_content_overview_search_authors',
                    nonce: PPContentOverview.nonce,
                    q: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: false
        }
    });

    $('#pp-content-filters select.filter_taxonomy').pp_select2({
        allowClear: true,
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 0,
        data: function (params) {
                return {
                    action: 'publishpress_content_overview_search_categories',
                    taxonomy: $(this).attr('data-taxonomy'),
                    nonce: PPContentOverview.nonce,
                    q: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: false
        }
    });

    $('#pp-content-filters select#post_status').pp_select2({width: '190px'});
    $('#pp-content-filters select#revision_status').pp_select2({width: "190px"});
    $('#pp-content-filters select#filter_post_type').pp_select2();
    $('#pp-content-filters select.pp-custom-select2').pp_select2();

    // Rest button submit
    $('#post-query-clear').on('click', function (e) {
      e.preventDefault();
      $('#pp-content-filters-hidden').submit();
    });
  
    //populate hidden dearch input and trigger filter to search
    $('#co-searchbox-search-submit').on('click', function (e) {
      e.preventDefault();
      $('#search_box-search-input').val($('#co-searchbox-search-input').val())
      $('#filter-submit').trigger('click');
    });
    $('#co-searchbox-search-input').on('keyup', function (e) {
      e.preventDefault();
      if (e.keyCode === 13) {
        $('#search_box-search-input').val($('#co-searchbox-search-input').val())
        $('#filter-submit').trigger('click');
      }
    });

    $(document).on('click', '.pp-content-overview-filters .co-filter, .pp-content-overview-manage .co-filter', function (e) {
        e.preventDefault();
        var modalID = $(this).attr("data-target");
        var modalDisplay = $(modalID).css('display');
        var isCustomModal = $(modalID).hasClass('customize-customize-item-modal');
        var isPostModal = $(modalID).hasClass('new-post-modal');
        
        $('.content-overview-modal').hide();
    
        if (modalDisplay !== 'block') {
            if (isCustomModal) {
                var adminBarHeight = $('#wpadminbar').outerHeight() || 0;
                var buttonHeight = $(this).outerHeight();
                var windowHeight = $(window).height();
                var maxModalHeight = windowHeight - $(this).position().top - buttonHeight - adminBarHeight + 50;
    
                $(modalID).css({
                    top: $('.pp-version-notice-bold-purple').length > 0 ? -32 : 0,
                    left: isPostModal ? $(this).position().left - $(modalID).outerWidth() - 5 : $(this).position().left + $(this).outerWidth() + 5,
                    //'max-height': maxModalHeight
                }).show();
    
            } else {
                var filterContainer = $(this).closest('.pp-content-overview-filters');
                var modalTop = filterContainer.length ? filterContainer.position().top + filterContainer.outerHeight() : $(this).position().top + 28;

                $(modalID).css({
                    top: modalTop,
                    left: $(this).position().left
                }).show();
            }
        }
    });

    $(document).on('click', '.pp-content-overview-filters .content-overview-modal-content .close, .pp-content-overview-manage .content-overview-modal-content .close', function (e) {
        e.preventDefault();
        $('.content-overview-modal').hide();
    });

    $(document).on('click', '#pp-content-filters .clear-filter', function (e) {
        e.preventDefault();
        $('#pp-content-filters-hidden').submit();
    });

    $(document).on('click', '.pp-content-overview-manage .me-mode-action', function (e) {
        var new_value = '';
        if ($(this).hasClass('active-filter')) {
            new_value = 0;
        } else {
            new_value = 1;
        }

        $('#filter_author').val('');

        $('#pp-content-filters #content_overview_me_mode').val(new_value);
        $('#pp-content-filters').trigger('submit');
    });

    $(document).on('click', '.co-customize-tabs .customize-tab', function (e) {
      e.preventDefault();
      var currentTab = $(this).attr('data-tab');
      var customizeForm = $(this).closest('.pp-content-overview-customize-form').attr('data-form');
      var formClass     = '.pp-content-overview-customize-form.' + customizeForm;

      $(formClass + ' .co-customize-tabs .customize-tab').removeClass('cc-active-tab');
      $(formClass + ' .co-cc-content .customize-content').hide();

      $(this).addClass('cc-active-tab');
      $(formClass + ' .co-cc-content .' + currentTab).show();
    });

    $(document).on('click', '.co-cc-content .enable-item.entry-item', function (e) {
      e.preventDefault();
      var entryName    = $(this).attr('data-name');
      var activeStatus  = $(this).hasClass('active-item');
      var customizeForm = $(this).closest('.pp-content-overview-customize-form').attr('data-form');
      var formClass     = '.pp-content-overview-customize-form.' + customizeForm;

      if (activeStatus) {
        $(formClass + ' .co-cc-content .entry-item.customize-item-' + entryName).removeClass('active-item');
        $(this).find('.customize-item-input').attr('name', '');
      } else {
        $(formClass + ' .co-cc-content .entry-item.customize-item-' + entryName).addClass('active-item');
        $(this).find('.customize-item-input').attr('name', 'content_overview_' + customizeForm + '[' + entryName + ']');
      }
    });

    $(document).on('click', '.co-cc-content .customize-group-title .title-action.new-item', function (e) {
      e.preventDefault();
      var customizeForm = $(this).closest('.pp-content-overview-customize-form').attr('data-form');
      var formClass     = '.pp-content-overview-customize-form.' + customizeForm;

      $(formClass + ' .co-cc-content .entry-item.form-item').slideToggle('slow');
    });

    if ($(".co-cc-content .customize-content.reorder-content .scrollable-content").length > 0) {
        $(".co-cc-content .customize-content.reorder-content .scrollable-content").sortable({
            axis: "y"
        });
    }

    if ($("#pp-content-overview-post-form").length > 0) {
        initFormSelect2();
    }

    $(document).on('click', '.co-cc-content .entry-item.form-item .new-submit', function (e) {
      e.preventDefault();
      var entryTitleField    = $(this).closest('.entry-item').find('.new-item-title');
      var entryMetaKeyField  = $(this).closest('.entry-item').find('.new-item-metakey');
      var customizeForm = $(this).closest('.pp-content-overview-customize-form').attr('data-form');
      var formClass     = '.pp-content-overview-customize-form.' + customizeForm;

      var entryTitle = entryTitleField.val();
      var entryMetaKey = entryMetaKeyField.val();

      var formError = false;

      if (isEmptyOrSpaces(entryTitle)) {
        formError = true;
        entryTitleField.addClass('co-border-red');
      }

      if (isEmptyOrSpaces(entryMetaKey)) {
        formError = true;
        entryMetaKeyField.addClass('co-border-red');
      }

      if (!formError) {
        // remove old/duplicate one if exist
        $(formClass + ' .entry-item.custom.customize-item-' + entryMetaKey).remove();

        // add new entry
        var new_entry = '';
        new_entry += '<div class="entry-item enable-item active-item customize-item-' + entryMetaKey + ' custom" data-name="' + entryMetaKey + '">';
        new_entry += '<input class="customize-item-input" type="hidden" name="content_overview_' + customizeForm + '[' + entryMetaKey + ']" value="' + entryTitle + '" />';
        new_entry += '<input type="hidden" name="content_overview_custom_' + customizeForm + '[' + entryMetaKey + ']" value="' + entryTitle + '" />';
        new_entry += '<div class="items-list-item-check checked"><svg><use xlink:href="' + PPContentOverview.publishpressUrl + 'common/icons/content-icon.svg#svg-sprite-cu2-check-2-fill"></use></svg></div>';
        new_entry += '<div class="items-list-item-check unchecked"><svg><use xlink:href="' + PPContentOverview.publishpressUrl + 'common/icons/content-icon.svg#svg-sprite-x"></use></svg></div>';
        new_entry += '<div class="items-list-item-name"><div class="items-list-item-name-text">' + entryTitle + ' <span class="customize-item-info">(' + entryMetaKey + ')</span></div></div>';
        new_entry += '<div class="delete-content-overview-item" data-meta="' + entryMetaKey + '"><svg><use xlink:href="' + PPContentOverview.publishpressUrl + 'common/icons/content-icon.svg#svg-sprite-cu2-menu-trash"></use></svg></div>';
        new_entry += '</div>';
        $(formClass + ' .co-cc-content .entry-item.form-item').after(new_entry);

        // add reorder entry
        var reorder_entry = '';
        reorder_entry += '<div class="entry-item reorder-item active-item customize-item-' + entryMetaKey + ' custom" data-name="' + entryMetaKey + '">';
        reorder_entry += '<input class="customize-item-input" type="hidden" name="content_overview_' + customizeForm + '_order[' + entryMetaKey + ']" value="' + entryTitle + '" />';
        reorder_entry += '' + entryTitle + '';
        reorder_entry += '</div>';

        $(formClass + ' .co-cc-content .customize-content.reorder-content .scrollable-content').prepend(reorder_entry);

        // Hide empty message
        $(formClass + ' .co-cc-content .item-group-empty.custom').hide();

        // Reset fields
        entryTitleField.val('');
        entryMetaKeyField.val('');
      }
    });

    $(document).on('click', '.co-cc-content .entry-item .delete-content-overview-item', function (e) {
        e.preventDefault();
        var entryMetaKey = $(this).attr('data-meta');
        var customizeForm = $(this).closest('.pp-content-overview-customize-form').attr('data-form');
        var formClass     = '.pp-content-overview-customize-form.' + customizeForm;

        $(formClass + ' .entry-item.custom.customize-item-' + entryMetaKey).remove();
    });
    

    $(document).on('click', '.co-cc-content .save-cc-changes', function (e) {
        e.preventDefault();
        var button = $(this);
        if (button.hasClass('save-new-post-form')) {
            button.closest('form').find('.form-submit-button').trigger('click');
        } else {
            $(button).closest('form').trigger('submit');
        }
    });

    $(document).on('keypress', '.co-cc-content .entry-item input', function (e) {
        if (e.which === 13) {
            e.preventDefault();
        }
    });

    $(document).on('input', '.co-cc-content .entry-item.form-item .new-item-title, .co-cc-content .entry-item.form-item .new-item-metakey', function (e) {
        $(this).removeClass('co-border-red');
    });

    $(document).on('change', '#pp-content-overview-post-form select#post_form_post_type', function (e) {
        var select = $(this);
        select.closest('form').css("visibility", "hidden");
        select.closest('.content-overview-modal-form').find('.content-overview-form-loader').show();

        var data = {
            action: "publishpress_content_overview_get_form_fields",
            nonce: PPContentOverview.nonce,
            post_type: select.val()
        };

        $.post(ajaxurl, data, function (response) {
            select.closest('.content-overview-modal-form').html(response.content);
            initFormSelect2();
        });

    });

    function isEmptyOrSpaces(str) {
        return str == '' || str === null || str.match(/^ *$/) !== null;
    }

    function initFormSelect2() {
        $('#pp-content-overview-post-form select.post_form_author').pp_select2({
            allowClear: true,
            ajax: {
                url: ajaxurl,
                dataType: 'json',
                delay: 0,
                data: function (params) {
                    return {
                        action: 'publishpress_content_overview_search_authors',
                        nonce: PPContentOverview.nonce,
                        q: params.term
                    };
                },
                processResults: function (data) {
                    return {
                        results: data
                    };
                },
                cache: false
            }
        });

        $('#pp-content-overview-post-form select.post_form_taxonomy').pp_select2({
            allowClear: true,
            ajax: {
                url: ajaxurl,
                dataType: 'json',
                delay: 0,
            data: function (params) {
                    return {
                        action: 'publishpress_content_overview_search_categories',
                        taxonomy: $(this).attr('data-taxonomy'),
                        nonce: PPContentOverview.nonce,
                        q: params.term
                    };
                },
                processResults: function (data) {
                    return {
                        results: data
                    };
                },
                cache: false
            }
        });

        $('#pp-content-overview-post-form select#post_form_post_type').pp_select2();
        $('#pp-content-overview-post-form select#form_post_status').pp_select2();
    }
    
});
