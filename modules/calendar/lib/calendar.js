jQuery(document).ready(function ($) {

    $('#pp-content-filters select#filter_author').pp_select2({
        allowClear: true,
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 0,
            data: function (params) {
                return {
                    action: 'publishpress_calendar_search_authors',
                    nonce: PPContentCalendar.nonce,
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

    $('#pp-content-filters select.filter_taxonomy').pp_select2({
        allowClear: true,
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 0,
        data: function (params) {
                return {
                    action: 'publishpress_calendar_search_terms',
                    taxonomy: $(this).attr('data-taxonomy'),
                    nonce: PPContentCalendar.nonce,
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

    $('#pp-content-filters select.calendar-weeks-filter').pp_select2();
    $('#pp-content-filters select#post_status').pp_select2({width: "190px"});
    $('#pp-content-filters select#revision_status').pp_select2({width: "190px"});
    $('#pp-content-filters select#post_type').pp_select2();
    $('#pp-content-filters select.pp-custom-select2').pp_select2();
    
    $(document).on('click', 'div.pp-show-revision-btn', function(e) {
        $('div.pp-content-calendar-filters button.revision_status').toggle(!$(this).hasClass('active-filter'));
    });

    $(document).on('click', '.pp-content-calendar-filters .co-filter, .pp-content-calendar-manage .co-filter, .board-title-content .co-filter', function (e) {
        e.preventDefault();
        var modalID = $(this).attr("data-target");
        var modalDisplay = $(modalID).css('display');
        var isCustomModal = $(modalID).hasClass('customize-customize-item-modal');
        var isPostModal = $(modalID).hasClass('new-post-modal');
        
        $('.content-calendar-modal').hide();

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
                var filterContainer = $(this).closest('.pp-content-calendar-filters');
                var modalTop = filterContainer.length ? filterContainer.position().top + filterContainer.outerHeight() : $(this).position().top + 28;

                $(modalID).css({
                    top: modalTop,
                    left: $(this).position().left
                }).show();
            }
        }
    });

    $(document).on('click', '.pp-content-calendar-filters .content-calendar-modal-content .close, .pp-content-calendar-manage .content-calendar-modal-content .close, .board-title-content .content-calendar-modal-content .close', function (e) {
        e.preventDefault();
        $('.content-calendar-modal').hide();
    });

    $(document).on('click', '#pp-content-filters .clear-filter', function (e) {
        e.preventDefault();
        $('#pp-content-filters-hidden').submit();
    });

    $(document).on('click', '.co-customize-tabs .customize-tab', function (e) {
      e.preventDefault();
      var currentTab = $(this).attr('data-tab');
      var customizeForm = $(this).closest('.pp-content-calendar-customize-form').attr('data-form');
      var formClass     = '.pp-content-calendar-customize-form.' + customizeForm;

      $(formClass + ' .co-customize-tabs .customize-tab').removeClass('cc-active-tab');
      $(formClass + ' .co-cc-content .customize-content').hide();

      $(this).addClass('cc-active-tab');
      $(formClass + ' .co-cc-content .' + currentTab).show();
    });

    $(document).on('click', '.co-cc-content .enable-item.entry-item', function (e) {
      e.preventDefault();
      var entryName    = $(this).attr('data-name');
      var activeStatus  = $(this).hasClass('active-item');
      var customizeForm = $(this).closest('.pp-content-calendar-customize-form').attr('data-form');
      var formClass     = '.pp-content-calendar-customize-form.' + customizeForm;

      if (activeStatus) {
        $(formClass + ' .co-cc-content .entry-item.customize-item-' + entryName).removeClass('active-item');
        $(this).find('.customize-item-input').attr('name', '');
      } else {
        $(formClass + ' .co-cc-content .entry-item.customize-item-' + entryName).addClass('active-item');
        $(this).find('.customize-item-input').attr('name', 'content_calendar_' + customizeForm + '[' + entryName + ']');
      }
    });

    $(document).on('click', '.co-cc-content .customize-group-title .title-action.new-item', function (e) {
      e.preventDefault();
      var customizeForm = $(this).closest('.pp-content-calendar-customize-form').attr('data-form');
      var formClass     = '.pp-content-calendar-customize-form.' + customizeForm;

      $(formClass + ' .co-cc-content .entry-item.form-item').slideToggle('slow');
    });

    if ($(".co-cc-content .customize-content.reorder-content .scrollable-content").length > 0) {
        $(".co-cc-content .customize-content.reorder-content .scrollable-content").sortable({
            axis: "y"
        });
    }

    $(document).on('click', '.co-cc-content .entry-item.form-item .new-submit', function (e) {
      e.preventDefault();
      var entryTitleField    = $(this).closest('.entry-item').find('.new-item-title');
      var entryMetaKeyField  = $(this).closest('.entry-item').find('.new-item-metakey');
      var customizeForm = $(this).closest('.pp-content-calendar-customize-form').attr('data-form');
      var formClass     = '.pp-content-calendar-customize-form.' + customizeForm;

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
        new_entry += '<input class="customize-item-input" type="hidden" name="content_calendar_' + customizeForm + '[' + entryMetaKey + ']" value="' + entryTitle + '" />';
        new_entry += '<input type="hidden" name="content_calendar_custom_' + customizeForm + '[' + entryMetaKey + ']" value="' + entryTitle + '" />';
        new_entry += '<div class="items-list-item-check checked"><svg><use xlink:href="' + PPContentCalendar.publishpressUrl + 'common/icons/content-icon.svg#svg-sprite-cu2-check-2-fill"></use></svg></div>';
        new_entry += '<div class="items-list-item-check unchecked"><svg><use xlink:href="' + PPContentCalendar.publishpressUrl + 'common/icons/content-icon.svg#svg-sprite-x"></use></svg></div>';
        new_entry += '<div class="items-list-item-name"><div class="items-list-item-name-text">' + entryTitle + ' <span class="customize-item-info">(' + entryMetaKey + ')</span></div></div>';
        new_entry += '<div class="delete-content-calendar-item" data-meta="' + entryMetaKey + '"><svg><use xlink:href="' + PPContentCalendar.publishpressUrl + 'common/icons/content-icon.svg#svg-sprite-cu2-menu-trash"></use></svg></div>';
        new_entry += '</div>';
        $(formClass + ' .co-cc-content .entry-item.form-item').after(new_entry);

        // add reorder entry
        var reorder_entry = '';
        reorder_entry += '<div class="entry-item reorder-item active-item customize-item-' + entryMetaKey + ' custom" data-name="' + entryMetaKey + '">';
        reorder_entry += '<input class="customize-item-input" type="hidden" name="content_calendar_' + customizeForm + '_order[' + entryMetaKey + ']" value="' + entryTitle + '" />';
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

    $(document).on('click', '.co-cc-content .entry-item .delete-content-calendar-item', function (e) {
        e.preventDefault();
        var entryMetaKey = $(this).attr('data-meta');
        var customizeForm = $(this).closest('.pp-content-calendar-customize-form').attr('data-form');
        var formClass     = '.pp-content-calendar-customize-form.' + customizeForm;

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
    

    function isEmptyOrSpaces(str) {
        return str == '' || str === null || str.match(/^ *$/) !== null;
    }

    function updateParam(url, paramToUpdate, newValue) {
        var parts = url.split('?'),
            query,
            param,
            paramFound = false,
            newQuery = [],
            newUrl = parts[0];

        if (parts.length === 1) {
            parts[1] = '';
        }

        query = parts[1].split('&');

        // Update the param in the query, building a new query
        if (query.length > 0) {

            for (var i = 0; i < query.length; i++) {
                param = query[i].split('=');

                if (param[0] === paramToUpdate) {
                    param[1] = newValue;
                    paramFound = true;
                }

                newQuery.push(param);
            }

            if (!paramFound) {
                newQuery.push([paramToUpdate, newValue]);
            }
        }

        // Convert the new query into a string
        if (newQuery.length > 0) {
            newUrl += '?';

            for (var i = 0; i < newQuery.length; i++) {
                param = newQuery[i];

                if (i > 0) {
                    newUrl += '&';
                }

                newUrl += param[0] + '=' + param[1];
            }
        }

        return newUrl;
    }

    $('#publishpress-calendar-ics-subs #publishpress-start-date').on('change', function () {
        var buttonDownload = document.getElementById('publishpress-ics-download'),
            buttonCopy = document.getElementById('publishpress-ics-copy');

        // Get the URL
        var url = buttonDownload.href;

        url = updateParam(url, 'start', $(this).val());

        buttonDownload.href = url;
        buttonCopy.dataset.clipboardText = url;
    });

    $('#publishpress-calendar-ics-subs #publishpress-end-date').on('change', function () {
        var buttonDownload = document.getElementById('publishpress-ics-download'),
            buttonCopy = document.getElementById('publishpress-ics-copy');

        // Get the URL
        var url = buttonDownload.href;

        url = updateParam(url, 'end', $(this).val());

        buttonDownload.href = url;
        buttonCopy.dataset.clipboardText = url;
    });

    new Clipboard('#publishpress-ics-copy');
});
