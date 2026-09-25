// Content Board specific JS, assumes that pp_date.js has already been included

jQuery(document).ready(function ($) {

    if ($('.content-board-table-wrap .statuses-contents .content-wrap.header-content').length > 0) {
        var adminBarHeight  = $('#wpadminbar').outerHeight();
        var stickyHeader    = $('.content-board-table-wrap .statuses-contents .content-wrap.header-content');
        var contentDiv      = $('.content-board-table-wrap .statuses-contents .content-wrap.main-content');
        var stickyHeaderOffset = stickyHeader.offset().top;

        // synch fixed header scroll to main scroll
        $('.content-board-inside').on('scroll', function() {
            stickyHeader.scrollLeft($(this).scrollLeft());
        });

        // stick status header to the top when scroll reach admin bar
        $(window).scroll(function() {
            var scroll = $(window).scrollTop();
            if (scroll >= stickyHeaderOffset - 25) {
                $('.content-board-modal').hide();
                stickyHeader.addClass('sticky');
                stickyHeader.css('top', adminBarHeight + 'px');
                stickyHeader.css('width', contentDiv.width() + 'px');
                contentDiv.addClass('header-sticky');
                stickyHeader.scrollLeft($('.content-board-inside').scrollLeft());
            } else {
                stickyHeader.removeClass('sticky');
                stickyHeader.css('top', 'auto');
                stickyHeader.css('width', '100%');
                contentDiv.removeClass('header-sticky');
            }
        });
    }

    if ($('.content-board-table-wrap .board-content.can_move_to .content-item'.length > 0)) {
        // make content dragable
        sortedPostCardsList($(".content-board-table-wrap .board-content.can_move_to"));
        // update empty card height
        var card_selector = $('.content-board-table-wrap .board-content .content-item:not(.empty-card)');
        var card_height = card_selector.height();
        var card_padding_top = parseInt(card_selector.css("padding-top"));
        var card_padding_bottom = parseInt(card_selector.css("padding-bottom"));
        var card_margin_top = parseInt(card_selector.css("margin-top"));
        var card_margin_bottom = parseInt(card_selector.css("margin-bottom"));
    
        var empty_card_height = card_height - (card_padding_top + card_padding_bottom + card_margin_top + card_margin_bottom);
        $('.content-board-table-wrap .board-content .content-item.empty-card').height(empty_card_height);

        // update status cards height for drag and drop
        var parent_card_selector = $('.content-board-table-wrap .content-wrap.main-content');
        var parent_card_height = parent_card_selector.height();
        var parent_card_padding_top = parseInt(parent_card_selector.css("padding-top"));
        var parent_card_padding_bottom = parseInt(parent_card_selector.css("padding-bottom"));
        var parent_card_margin_top = parseInt(parent_card_selector.css("margin-top"));
        var parent_card_margin_bottom = parseInt(parent_card_selector.css("margin-bottom"));
    
        var all_parent_card_height = parent_card_height - (parent_card_padding_top + parent_card_padding_bottom + parent_card_margin_top + parent_card_margin_bottom);

        $('.content-board-table-wrap .status-content.board-main-content .board-content').css('min-height', all_parent_card_height);
    }

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

        var use_today_as_start_date_input = $('#pp-content-board-range-use-today');
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

    $('#pp-content-board-range-today-btn').on('click', function (event) {
        var start_date_input = $('#pp-content-board-start-date');
        var use_today_as_start_date_input = $('#pp-content-board-range-use-today');

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
                    action: 'publishpress_content_board_search_authors',
                    nonce: PPContentBoard.nonce,
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
                    action: 'publishpress_content_board_search_categories',
                    taxonomy: $(this).attr('data-taxonomy'),
                    nonce: PPContentBoard.nonce,
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

    $('#pp-content-filters select#post_status').pp_select2({width: "190px"});
    $('#pp-content-filters select#filter_post_type').pp_select2();
    $('#pp-content-filters select.pp-custom-select2').pp_select2();

    // Rest button submit
    $('#post-query-clear').on('click', function (e) {
      e.preventDefault();
      $('#pp-content-filters-hidden').submit();
    });
  
    //populate hidden search input and trigger filter to search
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

    $(document).on('click', '.pp-content-board-filters .co-filter, .pp-content-board-manage .co-filter, .board-title-content .co-filter', function (e) {
        e.preventDefault();
        var modalID = $(this).attr("data-target");
        var modalDisplay = $(modalID).css('display');
        var isCustomModal = $(modalID).hasClass('customize-customize-item-modal');
        var isPostModal = $(modalID).hasClass('new-post-modal');

        $('.content-board-modal').hide();
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
            $(modalID).css({
                top: $(this).position().top + 28, 
                left: $(this).position().left
            }).show();
        }
    });

    $(document).on('click', '.pp-content-board-filters .content-board-modal-content .close, .pp-content-board-manage .content-board-modal-content .close, .board-title-content .content-board-modal-content .close', function (e) {
        e.preventDefault();
        $('.content-board-modal').hide();
    });

    $(document).on('click', '#pp-content-filters .clear-filter', function (e) {
        e.preventDefault();
        $('#pp-content-filters-hidden').submit();
    });

    $(document).on('click', '.pp-content-board-manage .me-mode-action', function (e) {
        var new_value = '';
        if ($(this).hasClass('active-filter')) {
            new_value = 0;
        } else {
            new_value = 1;
        }

        $('#filter_author').val('');

        $('#pp-content-filters #content_board_me_mode').val(new_value);
        $('#pp-content-filters').trigger('submit');
    });

    $(document).on('click', '.co-customize-tabs .customize-tab', function (e) {
      e.preventDefault();
      var currentTab = $(this).attr('data-tab');
      var customizeForm = $(this).closest('.pp-content-board-customize-form').attr('data-form');
      var formClass     = '.pp-content-board-customize-form.' + customizeForm;

      $(formClass + ' .co-customize-tabs .customize-tab').removeClass('cc-active-tab');
      $(formClass + ' .co-cc-content .customize-content').hide();

      $(this).addClass('cc-active-tab');
      $(formClass + ' .co-cc-content .' + currentTab).show();
    });

    $(document).on('click', '.co-cc-content .enable-item.entry-item', function (e) {
      e.preventDefault();
      var entryName    = $(this).attr('data-name');
      var activeStatus  = $(this).hasClass('active-item');
      var customizeForm = $(this).closest('.pp-content-board-customize-form').attr('data-form');
      var formClass     = '.pp-content-board-customize-form.' + customizeForm;

      if (activeStatus) {
        $(formClass + ' .co-cc-content .entry-item.customize-item-' + entryName).removeClass('active-item');
        $(this).find('.customize-item-input').attr('name', '');
      } else {
        $(formClass + ' .co-cc-content .entry-item.customize-item-' + entryName).addClass('active-item');
        $(this).find('.customize-item-input').attr('name', 'content_board_' + customizeForm + '[' + entryName + ']');
      }
    });

    $(document).on('click', '.co-cc-content .customize-group-title .title-action.new-item', function (e) {
      e.preventDefault();
      var customizeForm = $(this).closest('.pp-content-board-customize-form').attr('data-form');
      var formClass     = '.pp-content-board-customize-form.' + customizeForm;

      $(formClass + ' .co-cc-content .entry-item.form-item').slideToggle('slow');
    });

    if ($(".co-cc-content .customize-content.reorder-content .scrollable-content").length > 0) {
        $(".co-cc-content .customize-content.reorder-content .scrollable-content").sortable({
            axis: "y"
        });
    }

    if ($("#pp-content-board-post-form").length > 0) {
        initFormSelect2();
    }

    $(document).on('click', '.co-cc-content .entry-item.form-item .new-submit', function (e) {
      e.preventDefault();
      var entryTitleField    = $(this).closest('.entry-item').find('.new-item-title');
      var entryMetaKeyField  = $(this).closest('.entry-item').find('.new-item-metakey');
      var customizeForm = $(this).closest('.pp-content-board-customize-form').attr('data-form');
      var formClass     = '.pp-content-board-customize-form.' + customizeForm;

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
        var escapedEntryTitle = escapeHtml(entryTitle);
        var escapedEntryMetaKey = escapeHtml(entryMetaKey);

        // remove old/duplicate one if exist
        $(formClass + ' .entry-item.custom.customize-item-' + escapedEntryMetaKey).remove();

        // add new entry
        var new_entry = '';
        new_entry += '<div class="entry-item enable-item active-item customize-item-' + escapedEntryMetaKey + ' custom" data-name="' + escapedEntryMetaKey + '">';
        new_entry += '<input class="customize-item-input" type="hidden" name="content_board_' + customizeForm + '[' + escapedEntryMetaKey + ']" value="' + escapedEntryTitle + '" />';
        new_entry += '<input type="hidden" name="content_board_custom_' + customizeForm + '[' + escapedEntryMetaKey + ']" value="' + escapedEntryTitle + '" />';
        new_entry += '<div class="items-list-item-check checked"><svg><use xlink:href="' + PPContentBoard.publishpressUrl + 'common/icons/content-icon.svg#svg-sprite-cu2-check-2-fill"></use></svg></div>';
        new_entry += '<div class="items-list-item-check unchecked"><svg><use xlink:href="' + PPContentBoard.publishpressUrl + 'common/icons/content-icon.svg#svg-sprite-x"></use></svg></div>';
        new_entry += '<div class="items-list-item-name"><div class="items-list-item-name-text">' + escapedEntryTitle + ' <span class="customize-item-info">(' + escapedEntryMetaKey + ')</span></div></div>';
        new_entry += '<div class="delete-content-board-item" data-meta="' + escapedEntryMetaKey + '"><svg><use xlink:href="' + PPContentBoard.publishpressUrl + 'common/icons/content-icon.svg#svg-sprite-cu2-menu-trash"></use></svg></div>';
        new_entry += '</div>';
        $(formClass + ' .co-cc-content .entry-item.form-item').after(new_entry);

        // add reorder entry
        var reorder_entry = '';
        reorder_entry += '<div class="entry-item reorder-item active-item customize-item-' + escapedEntryMetaKey + ' custom" data-name="' + escapedEntryMetaKey + '">';
        reorder_entry += '<input class="customize-item-input" type="hidden" name="content_board_' + customizeForm + '_order[' + escapedEntryMetaKey + ']" value="' + escapedEntryTitle + '" />';
        reorder_entry += '' + escapedEntryTitle + '';
        reorder_entry += '</div>';

        $(formClass + ' .co-cc-content .customize-content.reorder-content .scrollable-content').prepend(reorder_entry);

        // Hide empty message
        $(formClass + ' .co-cc-content .item-group-empty.custom').hide();

        // Reset fields
        entryTitleField.val('');
        entryMetaKeyField.val('');
      }
    });

    $(document).on('click', '.co-cc-content .entry-item .delete-content-board-item', function (e) {
        e.preventDefault();
        var entryMetaKey = $(this).attr('data-meta');
        var customizeForm = $(this).closest('.pp-content-board-customize-form').attr('data-form');
        var formClass     = '.pp-content-board-customize-form.' + customizeForm;

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

    $(document).on('change', '#pp-content-board-post-form select#post_form_post_type', function (e) {
        var select = $(this);
        select.closest('form').css("visibility", "hidden");
        select.closest('.content-board-modal-form').find('.content-board-form-loader').show();

        var data = {
            action: "publishpress_content_board_get_form_fields",
            nonce: PPContentBoard.nonce,
            post_type: select.val()
        };

        $.post(ajaxurl, data, function (response) {
            select.closest('.content-board-modal-form').html(response.content);
            initFormSelect2();
        });

    });

    $(document).on('click', '.content-board-modal.schedule-date-modal #filter-submit', function (e) {
        e.preventDefault();
        
        $('.content-board-modal').hide();
        
        var schedule_number = $('.schedule-content-number').val();
        var schedule_period = $('.schedule-content-period').val();
        var data = {
            action: "publishpress_content_board_update_schedule_period",
            schedule_number: schedule_number,
            schedule_period: schedule_period,
            nonce: PPContentBoard.nonce
        };

        $.post(ajaxurl, data);
    });

 
    function sortedPostCardsList(selector) {
    
        selector.sortable({
            connectWith: ".content-board-table-wrap .board-content.can_move_to",
            items: "> .content-item:not(.no-drag)",
            placeholder: "sortable-placeholder",
            receive: function (event, ui) {
               
                // Get the previous parent before sorting
                var receivedItem = ui.item || $(this);
                var senderUi = ui.sender;

                if (receivedItem.parent().children().length === 1) {
                    receivedItem.parent().find('.sortable-placeholder').show();
                } else {
                    receivedItem.parent().find('.sortable-placeholder').hide();
                }

                if (senderUi.children().length === 1) {
                    senderUi.find('.sortable-placeholder').show();
                } else {
                    senderUi.find('.sortable-placeholder').hide();
                }

                // update count
                var old_status = senderUi.closest('.status-content').attr('data-slug');
                var new_status = receivedItem.closest('.status-content').attr('data-slug');
                var old_status_count = senderUi.closest('.status-content').attr('data-counts');
                var new_status_count = receivedItem.closest('.status-content').attr('data-counts');

                var updated_old_count = Number(old_status_count) - 1;
                var updated_new_count = Number(new_status_count) + 1;
 
                // update old counts
                $('.content-board-table-wrap .statuses-contents .status-content.status-' + old_status).attr('data-counts', updated_old_count);
                $('.content-board-table-wrap .statuses-contents .status-content.status-' + old_status + ' .status-post-total').html(updated_old_count + ' &nbsp;');
                // update new counts
                $('.content-board-table-wrap .statuses-contents .status-content.status-' + new_status).attr('data-counts', updated_new_count);
                $('.content-board-table-wrap .statuses-contents .status-content.status-' + new_status + ' .status-post-total').html(updated_new_count + ' &nbsp;');

                // update post status
                var post_id = receivedItem.attr('data-post_id');
                var post_status = receivedItem.closest('.status-content.board-main-content').attr('data-slug');
                var schedule_number = $('.schedule-content-number').val();
                var schedule_period = $('.schedule-content-period').val();
                var data = {
                    action: "publishpress_content_board_update_post_status",
                    post_id: post_id,
                    post_status: post_status,
                    schedule_number: schedule_number,
                    schedule_period: schedule_period,
                    nonce: PPContentBoard.nonce
                };
        
                $.post(ajaxurl, data, function (response) {
                    ppcTimerStatus(response.status, response.content);
                });
            },
        });
    }
  
    function ppcTimerStatus(type = "success", message = '') {
        setTimeout(function () {
            var uniqueClass = "pp-floating-msg-" + Math.round(new Date().getTime() + Math.random() * 100);
            var instances = $(".pp-floating-status").length;
            $("#wpbody-content").after('<span class="pp-floating-status pp-floating-status--' + type + " " + uniqueClass + '">' + message + "</span>");
            $("." + uniqueClass)
                .css("bottom", instances * 45)
                .fadeIn(1e3)
                .delay(1e4)
                .fadeOut(1e3, function () {
                    $(this).remove();
                });
        }, 500);
    }

    function isEmptyOrSpaces(str) {
        return str == '' || str === null || str.match(/^ *$/) !== null;
    }

    function escapeHtml(value) {
        return $('<div>').text(value).html()
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
    }

    function initFormSelect2() {
        $('#pp-content-board-post-form select.post_form_author').pp_select2({
            allowClear: true,
            ajax: {
                url: ajaxurl,
                dataType: 'json',
                delay: 0,
                data: function (params) {
                    return {
                        action: 'publishpress_content_board_search_authors',
                        nonce: PPContentBoard.nonce,
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

        $('#pp-content-board-post-form select.post_form_taxonomy').pp_select2({
            allowClear: true,
            ajax: {
                url: ajaxurl,
                dataType: 'json',
                delay: 0,
            data: function (params) {
                    return {
                        action: 'publishpress_content_board_search_categories',
                        taxonomy: $(this).attr('data-taxonomy'),
                        nonce: PPContentBoard.nonce,
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

        $('#pp-content-board-post-form select#post_form_post_type').pp_select2();
        $('#pp-content-board-post-form select#form_post_status').pp_select2({width: "190px"});
    }
    
});
