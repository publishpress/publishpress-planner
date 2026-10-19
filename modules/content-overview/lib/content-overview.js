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

    var coL10n = (typeof PPContentOverview !== 'undefined') ? PPContentOverview : {};

    function coSpeak(message) {
        if (message && window.wp && wp.a11y && typeof wp.a11y.speak === 'function') {
            wp.a11y.speak(message);
        }
    }

    function coSprintf(format) {
        var args = Array.prototype.slice.call(arguments, 1);
        var index = 0;
        return String(format || '').replace(/%(?:(\d+)\$)?[sd]/g, function (match, position) {
            var value = position ? args[parseInt(position, 10) - 1] : args[index++];
            return (typeof value === 'undefined') ? '' : value;
        });
    }

    function coEscape(value) {
        return $('<div/>').text(value === null || typeof value === 'undefined' ? '' : String(value)).html();
    }

    function coFocusableIn(container) {
        return $(container).find('a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')
            .filter(':visible');
    }

    // Close any open popover, update its trigger state and optionally return focus to the trigger.
    function closeContentOverviewModals(returnFocus) {
        var openModal = $('.content-overview-modal:visible');
        var trigger = openModal.length ? $(openModal.data('ppTrigger')) : $();

        $('.content-overview-modal').hide();
        $('.pp-content-overview-filters .co-filter, .pp-content-overview-manage .co-filter').attr('aria-expanded', 'false');

        if (returnFocus && trigger.length) {
            trigger.trigger('focus');
        }
    }

    $(document).on('click', '.pp-content-overview-filters .co-filter, .pp-content-overview-manage .co-filter', function (e) {
        e.preventDefault();
        var modalID = $(this).attr("data-target");
        var modalDisplay = $(modalID).css('display');
        var isCustomModal = $(modalID).hasClass('customize-customize-item-modal');
        var isPostModal = $(modalID).hasClass('new-post-modal');

        $('.content-overview-modal').hide();
        $('.pp-content-overview-filters .co-filter, .pp-content-overview-manage .co-filter').attr('aria-expanded', 'false');

        if (modalDisplay !== 'block') {
            $(modalID).data('ppTrigger', this);
            $(this).attr('aria-expanded', 'true');
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

            // Move focus into the popover: first focusable control after the close button.
            var focusable = coFocusableIn(modalID);
            // Skip date fields: their datepicker opens on focus and would cover the popover.
            var firstControl = focusable.not('.close, .hasDatepicker, .date-pick').first();
            (firstControl.length ? firstControl : focusable.first()).trigger('focus');
        }
    });

    $(document).on('click', '.pp-content-overview-filters .content-overview-modal-content .close, .pp-content-overview-manage .content-overview-modal-content .close', function (e) {
        e.preventDefault();
        closeContentOverviewModals(true);
    });

    // Esc closes an open popover and returns focus to its trigger.
    $(document).on('keydown', function (e) {
        if (e.key !== 'Escape' && e.keyCode !== 27) {
            return;
        }

        if (!$('.content-overview-modal:visible').length) {
            return;
        }

        // Let open select2 dropdowns and datepickers handle Esc themselves.
        // select2 closes its own dropdown on Esc and prevents the default first.
        if (e.isDefaultPrevented()) {
            return;
        }

        if ($('.select2-container--open').length || $('#ui-datepicker-div').is(':visible')) {
            return;
        }

        e.preventDefault();
        closeContentOverviewModals(true);
    });

    $(document).on('click', '#pp-content-filters .clear-filter', function (e) {
        e.preventDefault();
        $('#pp-content-filters-hidden').submit();
    });

    $(document).on('click', '.pp-content-overview-manage .me-mode-action', function (e) {
        e.preventDefault();
        var new_value = '';
        if ($(this).hasClass('active-filter')) {
            new_value = 0;
        } else {
            new_value = 1;
        }

        $(this).attr('aria-pressed', new_value ? 'true' : 'false');

        $('#filter_author').val('');

        $('#pp-content-filters #content_overview_me_mode').val(new_value);
        $('#pp-content-filters').trigger('submit');
    });

    function activateCustomizeTab(tab, setFocus) {
      tab = $(tab);
      var currentTab = tab.attr('data-tab');
      var customizeForm = tab.closest('.pp-content-overview-customize-form').attr('data-form');
      var formClass     = '.pp-content-overview-customize-form.' + customizeForm;

      $(formClass + ' .co-customize-tabs .customize-tab')
        .removeClass('cc-active-tab')
        .attr('aria-selected', 'false')
        .attr('tabindex', '-1');
      $(formClass + ' .co-cc-content .customize-content').hide();

      tab.addClass('cc-active-tab')
        .attr('aria-selected', 'true')
        .attr('tabindex', '0');
      $(formClass + ' .co-cc-content .' + currentTab).show();

      if (setFocus) {
        tab.trigger('focus');
      }
    }

    $(document).on('click', '.co-customize-tabs .customize-tab', function (e) {
      e.preventDefault();
      activateCustomizeTab(this, false);
    });

    // Arrow key / Home / End navigation between tabs.
    $(document).on('keydown', '.co-customize-tabs .customize-tab', function (e) {
      var tabs = $(this).closest('.co-customize-tabs').find('.customize-tab');
      var index = tabs.index(this);
      var newIndex = null;

      switch (e.key) {
        case 'ArrowRight':
        case 'Right':
          newIndex = (index + 1) % tabs.length;
          break;
        case 'ArrowLeft':
        case 'Left':
          newIndex = (index - 1 + tabs.length) % tabs.length;
          break;
        case 'Home':
          newIndex = 0;
          break;
        case 'End':
          newIndex = tabs.length - 1;
          break;
      }

      if (newIndex !== null) {
        e.preventDefault();
        activateCustomizeTab(tabs.eq(newIndex), true);
      }
    });

    $(document).on('click', '.co-cc-content .enable-item.entry-item', function (e) {
      // Clicks on the delete button or on the "add new" form fields must not toggle the item.
      if ($(this).hasClass('form-item') || $(e.target).closest('.delete-content-overview-item').length) {
        return;
      }
      e.preventDefault();
      var entryName    = $(this).attr('data-name');
      var activeStatus  = $(this).hasClass('active-item');
      var customizeForm = $(this).closest('.pp-content-overview-customize-form').attr('data-form');
      var formClass     = '.pp-content-overview-customize-form.' + customizeForm;

      if (activeStatus) {
        $(formClass + ' .co-cc-content .entry-item.customize-item-' + entryName).removeClass('active-item');
        $(this).find('.customize-item-input').attr('name', '');
        $(this).attr('aria-checked', 'false');
      } else {
        $(formClass + ' .co-cc-content .entry-item.customize-item-' + entryName).addClass('active-item');
        $(this).find('.customize-item-input').attr('name', 'content_overview_' + customizeForm + '[' + entryName + ']');
        $(this).attr('aria-checked', 'true');
      }
    });

    // Space / Enter toggle the custom checkbox items.
    $(document).on('keydown', '.co-cc-content .enable-item.entry-item[role="checkbox"]', function (e) {
      if (e.target !== this) {
        return;
      }
      if (e.key === ' ' || e.key === 'Spacebar' || e.key === 'Enter') {
        e.preventDefault();
        $(this).trigger('click');
      }
    });

    $(document).on('click', '.co-cc-content .customize-group-title .title-action.new-item', function (e) {
      e.preventDefault();
      var button        = $(this);
      var customizeForm = button.closest('.pp-content-overview-customize-form').attr('data-form');
      var formClass     = '.pp-content-overview-customize-form.' + customizeForm;
      var formItem      = $(formClass + ' .co-cc-content .entry-item.form-item');
      var willOpen      = !formItem.is(':visible');

      button.attr('aria-expanded', willOpen ? 'true' : 'false');
      formItem.slideToggle('slow', function () {
        if (willOpen) {
          formItem.find('.new-item-title').trigger('focus');
        }
      });
    });

    if ($(".co-cc-content .customize-content.reorder-content .scrollable-content").length > 0) {
        $(".co-cc-content .customize-content.reorder-content .scrollable-content").sortable({
            axis: "y",
            items: "> .reorder-item"
        });
    }

    // Keyboard alternative to drag-and-drop: Move up / Move down buttons on each reorder item.
    function getReorderItemLabel(item) {
        var label = $(item).find('.customize-item-input').val();
        return label ? label : $(item).attr('data-name');
    }

    function addReorderButtons(item) {
        item = $(item);
        if (item.find('.pp-co-reorder-buttons').length) {
            return;
        }
        var label = getReorderItemLabel(item);
        var buttons = '<span class="pp-co-reorder-buttons">'
            + '<button type="button" class="pp-co-move-item pp-co-move-up" data-direction="up" aria-label="' + coEscape(coSprintf(coL10n.moveUpLabel, label)) + '">'
            + '<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>'
            + '<button type="button" class="pp-co-move-item pp-co-move-down" data-direction="down" aria-label="' + coEscape(coSprintf(coL10n.moveDownLabel, label)) + '">'
            + '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>'
            + '</span>';
        item.append(buttons);
    }

    $('.co-cc-content .customize-content.reorder-content .reorder-item').each(function () {
        addReorderButtons(this);
    });

    $(document).on('click', '.co-cc-content .reorder-item .pp-co-move-item', function (e) {
        e.preventDefault();
        var button    = $(this);
        var item      = button.closest('.reorder-item');
        var container = item.parent();
        var direction = button.attr('data-direction');
        var target;

        // Only active (visible) items take part in ordering.
        if (direction === 'up') {
            target = item.prevAll('.reorder-item.active-item').first();
            if (target.length) {
                item.insertBefore(target);
            }
        } else {
            target = item.nextAll('.reorder-item.active-item').first();
            if (target.length) {
                item.insertAfter(target);
            }
        }

        if (container.hasClass('ui-sortable')) {
            container.sortable('refresh');
        }

        button.trigger('focus');

        var activeItems = container.children('.reorder-item.active-item');
        coSpeak(coSprintf(coL10n.movedToPosition, getReorderItemLabel(item), activeItems.index(item) + 1, activeItems.length));
    });

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

      clearFieldError(entryTitleField);
      clearFieldError(entryMetaKeyField);

      if (isEmptyOrSpaces(entryTitle)) {
        formError = true;
        setFieldError(entryTitleField, coL10n.titleRequired);
      }

      if (isEmptyOrSpaces(entryMetaKey)) {
        formError = true;
        setFieldError(entryMetaKeyField, coL10n.metakeyRequired);
      }

      if (formError) {
        // Move focus to the first invalid field.
        var firstInvalid = entryTitleField.attr('aria-invalid') === 'true' ? entryTitleField : entryMetaKeyField;
        var select2Selection = firstInvalid.next('.select2').find('.select2-selection');
        (select2Selection.length ? select2Selection : firstInvalid).trigger('focus');
      }

      if (!formError) {
        var safeTitle   = coEscape(entryTitle);
        var safeMetaKey = coEscape(entryMetaKey);

        // remove old/duplicate one if exist
        $(formClass + ' .entry-item.custom.customize-item-' + entryMetaKey).remove();

        // add new entry
        var new_entry = '';
        new_entry += '<div class="entry-item enable-item active-item customize-item-' + safeMetaKey + ' custom" data-name="' + safeMetaKey + '" role="checkbox" tabindex="0" aria-checked="true" aria-label="' + safeTitle + '">';
        new_entry += '<input class="customize-item-input" type="hidden" name="content_overview_' + customizeForm + '[' + safeMetaKey + ']" value="' + safeTitle + '" />';
        new_entry += '<input type="hidden" name="content_overview_custom_' + customizeForm + '[' + safeMetaKey + ']" value="' + safeTitle + '" />';
        new_entry += '<div class="items-list-item-check checked" aria-hidden="true"><svg><use xlink:href="' + PPContentOverview.publishpressUrl + 'common/icons/content-icon.svg#svg-sprite-cu2-check-2-fill"></use></svg></div>';
        new_entry += '<div class="items-list-item-check unchecked" aria-hidden="true"><svg><use xlink:href="' + PPContentOverview.publishpressUrl + 'common/icons/content-icon.svg#svg-sprite-x"></use></svg></div>';
        new_entry += '<div class="items-list-item-name"><div class="items-list-item-name-text">' + safeTitle + ' <span class="customize-item-info">(' + safeMetaKey + ')</span></div></div>';
        new_entry += '<button type="button" class="delete-content-overview-item" data-meta="' + safeMetaKey + '" aria-label="' + coEscape(coSprintf(coL10n.deleteItemLabel, entryTitle)) + '"><svg aria-hidden="true" focusable="false"><use xlink:href="' + PPContentOverview.publishpressUrl + 'common/icons/content-icon.svg#svg-sprite-cu2-menu-trash"></use></svg></button>';
        new_entry += '</div>';
        $(formClass + ' .co-cc-content .entry-item.form-item').after(new_entry);

        // add reorder entry
        var reorder_entry = '';
        reorder_entry += '<div class="entry-item reorder-item active-item customize-item-' + safeMetaKey + ' custom" data-name="' + safeMetaKey + '">';
        reorder_entry += '<input class="customize-item-input" type="hidden" name="content_overview_' + customizeForm + '_order[' + safeMetaKey + ']" value="' + safeTitle + '" />';
        reorder_entry += '' + safeTitle + '';
        reorder_entry += '</div>';

        var reorderContainer = $(formClass + ' .co-cc-content .customize-content.reorder-content .scrollable-content');
        reorderContainer.prepend(reorder_entry);
        addReorderButtons(reorderContainer.children('.reorder-item').first());

        // Hide empty message
        $(formClass + ' .co-cc-content .item-group-empty.custom').hide();

        // Reset fields
        entryTitleField.val('');
        entryMetaKeyField.val('').trigger('change');

        coSpeak(coSprintf(coL10n.itemAdded, entryTitle));
      }
    });

    function setFieldError(field, message) {
      var errorId = field.attr('aria-describedby');
      field.addClass('co-border-red').attr('aria-invalid', 'true');
      field.next('.select2').find('.select2-selection').addClass('co-border-red').attr('aria-invalid', 'true');
      if (errorId) {
        $('#' + errorId).text(message).prop('hidden', false);
      }
    }

    function clearFieldError(field) {
      var errorId = field.attr('aria-describedby');
      field.removeClass('co-border-red').removeAttr('aria-invalid');
      field.next('.select2').find('.select2-selection').removeClass('co-border-red').removeAttr('aria-invalid');
      if (errorId) {
        $('#' + errorId).text('').prop('hidden', true);
      }
    }

    $(document).on('click', '.co-cc-content .entry-item .delete-content-overview-item', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var entryMetaKey = $(this).attr('data-meta');
        var customizeForm = $(this).closest('.pp-content-overview-customize-form').attr('data-form');
        var formClass     = '.pp-content-overview-customize-form.' + customizeForm;
        var itemLabel     = $(this).closest('.entry-item').find('.customize-item-input').val();

        $(formClass + ' .entry-item.custom.customize-item-' + entryMetaKey).remove();

        // Return focus to a sensible control after the item disappears.
        $(formClass + ' .co-cc-content .title-action.new-item').first().trigger('focus');
        coSpeak(coSprintf(coL10n.itemDeleted, itemLabel));
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

    $(document).on('input change', '.co-cc-content .entry-item.form-item .new-item-title, .co-cc-content .entry-item.form-item .new-item-metakey', function (e) {
        if (!isEmptyOrSpaces($(this).val())) {
            clearFieldError($(this));
        }
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
