(function ($) {
    $(function () {
        $('.delete-status a').on('click', function () {
            if (!confirm(objectL10nMetadata.pp_confirm_delete_term_string)) {
                return false;
            }
        });

        var l10n = window.objectL10nMetadata || {};

        /**
         * Minimal sprintf supporting %s, %d and numbered %1$s / %2$d placeholders.
         */
        function ppSprintf(format) {
            var args = Array.prototype.slice.call(arguments, 1);
            var i = 0;
            return String(format || '').replace(/%(?:(\d+)\$)?[sd]/g, function (match, num) {
                var value = num ? args[parseInt(num, 10) - 1] : args[i++];
                return typeof value === 'undefined' ? '' : value;
            });
        }

        function ppSpeak(message, politeness) {
            if (message && window.wp && wp.a11y && typeof wp.a11y.speak === 'function') {
                wp.a11y.speak(message, politeness || 'polite');
            }
        }

        function buildMoveButtons(extraClass) {
            return $('<span class="pp-move-buttons ' + extraClass + '"></span>')
                .append('<button type="button" class="button pp-move-button pp-move-up"><span aria-hidden="true">&uarr;</span></button>')
                .append('<button type="button" class="button pp-move-button pp-move-down"><span aria-hidden="true">&darr;</span></button>');
        }

        function setMoveButtonLabels($row, name) {
            $row.find('.pp-move-up').attr('aria-label', ppSprintf(l10n.move_up, name));
            $row.find('.pp-move-down').attr('aria-label', ppSprintf(l10n.move_down, name));
        }

        /**
         * Save the editorial field positions (used by drag and drop and keyboard reorder).
         */
        function saveTermPositions() {
            // Reset the position indicies for all terms
            $('.pp-editorial-metadata-wrap #the-list tr').removeClass('alternate');

            var terms = [];

            $('.pp-editorial-metadata-wrap #the-list tr.term-static').each(function (index, value) {
                var term_id = $(this).attr('id').replace('term-', '');
                terms[index] = term_id;

                $('td.position', this).html(index + 1);
                // Update the WP core design for alternating rows
                if (index % 2 == 0)
                    $(this).addClass('alternate');
            });

            // Prepare the POST
            var params = {
                action: 'update_term_positions',
                term_positions: terms,
                editorial_metadata_sortable_nonce: $('#editorial-metadata-sortable').val()
            };

            // Inform WordPress of our updated positions
            $.post(ajaxurl, params, function (retval) {
                $('.notice').remove();

                // If there's a success message, print it. Otherwise we assume we received an error message
                if (retval.status == 'success') {
                    var message = '<div class="is-dismissible notice notice-success"><p>' + retval.message + '</p></div>';
                } else {
                    var message = '<div class="is-dismissible notice notice-error"><p>' + retval.message + '</p></div>';
                }

                $('.publishpress-admin header').after(message);

                ppSpeak($('<div>').html(retval.message).text(), retval.status == 'success' ? 'polite' : 'assertive');
            }).fail(function () {
                ppSpeak(l10n.reorder_failed, 'assertive');
            });
        }

        /**
         * Move a row up or down. Returns true when the row moved.
         */
        function moveRow($row, direction, selector) {
            if (direction === 'up') {
                var $prev = $row.prevAll(selector).first();
                if (!$prev.length) {
                    return false;
                }
                $row.insertBefore($prev);
            } else {
                var $next = $row.nextAll(selector).first();
                if (!$next.length) {
                    return false;
                }
                $row.insertAfter($next);
            }
            return true;
        }

        function announceMove(name, $row, selector, moved, direction) {
            var $rows = $row.parent().children(selector);
            if (!moved) {
                ppSpeak(ppSprintf(direction === 'up' ? l10n.already_first : l10n.already_last, name));
                return;
            }
            ppSpeak(ppSprintf(l10n.moved_to_position, name, $rows.index($row) + 1, $rows.length));
        }

        /**
         * Instantiate the drag and drop sorting functionality
         */
        $('.pp-editorial-metadata-wrap #the-list').sortable({
            items: 'tr.term-static',
            placeholder: 'test',
            update: function (event, ui) {
                saveTermPositions();
            },
            sort: function (e, ui) {
                // Fix the issue with the width of the line while draging, due to the hidden column
                ui.placeholder.find('td').each(function (key, value) {
                    if (ui.helper.find('td').eq(key).is(':visible')) $(this).show();
                    else $(this).hide();
                });
            }
        });

        /**
         * Keyboard alternative to drag and drop for the editorial fields list
         */
        function getTermName($row) {
            return $.trim($row.find('.row-title').first().text());
        }

        $('.pp-editorial-metadata-wrap #the-list tr.term-static').each(function () {
            var $row = $(this);
            var $buttons = buildMoveButtons('pp-term-move-buttons');
            $row.find('td.column-name strong, td.name strong').first().after($buttons);
            setMoveButtonLabels($row, getTermName($row));
        });

        $(document).on('click', '.pp-editorial-metadata-wrap #the-list .pp-move-button', function (e) {
            e.preventDefault();
            var $button = $(this);
            var $row = $button.closest('tr.term-static');
            var direction = $button.hasClass('pp-move-up') ? 'up' : 'down';
            var moved = moveRow($row, direction, 'tr.term-static');
            $button.trigger('focus');
            announceMove(getTermName($row), $row, 'tr.term-static', moved, direction);
            if (moved) {
                saveTermPositions();
            }
        });
      
        /**
         * clone select type drop down field
         */
        $(document).on('click', '.pp-add-new-option', function (e) {
          e.preventDefault();
          var new_option_html = $('.pp-select-options-wrap .pp-select-options-box:first-child').clone();
          new_option_html.find('.entry-field').val('');
          new_option_html.find('.select_dropdown_default').removeAttr('checked').val($('.pp-select-options-wrap .pp-select-options-box').length);
          new_option_html.find('.delete-button').css('visibility', 'visible');
          new_option_html.find('.delete-button').css('display', '');
          $(".pp-select-options-wrap").append(new_option_html);
          setSelectOptionChanges();
          new_option_html.find('.value-field').trigger('focus');
        });
      
        /**
         * delete select type fields
         */
        $(document).on('click', '.pp-select-options-box .delete-button', function (e) {
          e.preventDefault();
          var $box = $(this).closest('.pp-select-options-box');
          var $focus_target = $box.prev('.pp-select-options-box').find('.value-field');
          if (!$focus_target.length) {
            $focus_target = $box.next('.pp-select-options-box').find('.value-field');
          }
          if (!$focus_target.length) {
            $focus_target = $('.pp-add-new-option');
          }
          $box.remove();
          setSelectOptionChanges();
          $focus_target.first().trigger('focus');
          ppSpeak(l10n.option_deleted);
        });
      
        /**
         * show matched fields when meta data type is changed
         */
        $(document).on('change', '#metadata_type', function (e) {
          var selected_type = $(this).val();
          //hide all optional field
          $('.pp-editorial-optional-field').hide();
          //show type applicable field
          $('.pp-editorial-'+ selected_type +'-field').show();
        });
        if ($('#metadata_type').length > 0) {
          var selected_type = $('#metadata_type').val();
          //hide all optional field
          $('.pp-editorial-optional-field').hide();
          //show type applicable field
          $('.pp-editorial-'+ selected_type +'-field').show();
        }

      /**
       * disable table static term selection
       */
        $('.pp-editorial-metadata-wrap #the-list tr.term-static').disableSelection();
        
        /**
         * load select2
         */
        if ($('.pp_editorial_meta_user').length > 0) {
          $('.pp_editorial_meta_user select').select2();
        }
      
        if ($('.pp_editorial_single_select2').length > 0) {
          $('.pp_editorial_single_select2').select2(
            {
              allowClear: true,
              placeholder: function(){
                $(this).data('placeholder');
              }
            }
          );
        }
      
        if ($('.pp_editorial_meta_multi_select2').length > 0) {
          $('.pp_editorial_meta_multi_select2').select2({
            placeholder: function(){
              $(this).data('placeholder');
            },
            multiple: true
          });
        }
      
      /**
       * make dropdown options sortable
       */
      if ($('.pp-select-options-wrap').length > 0) {
        $('.pp-select-options-wrap .pp-select-options-box').each(function () {
          if (!$(this).find('.pp-move-buttons').length) {
            $(this).find('.pp-option-icon.move').after(buildMoveButtons('pp-option-move-buttons'));
          }
        });

        $(".pp-select-options-wrap").sortable({
          axis: "y",
        }).on("sortupdate", function (event, ui) { setSelectOptionChanges(); } );

        setSelectOptionChanges();
      }

      /**
       * Keyboard alternative to drag and drop for dropdown options
       */
      function getOptionName($box) {
        var name = $.trim($box.find('.label-field').val()) || $.trim($box.find('.value-field').val());
        if (!name) {
          name = ppSprintf(l10n.option_name, $box.parent().children('.pp-select-options-box').index($box) + 1);
        }
        return name;
      }

      $(document).on('click', '.pp-select-options-wrap .pp-move-button', function (e) {
        e.preventDefault();
        var $button = $(this);
        var $box = $button.closest('.pp-select-options-box');
        var direction = $button.hasClass('pp-move-up') ? 'up' : 'down';
        var name = getOptionName($box);
        var moved = moveRow($box, direction, '.pp-select-options-box');
        if (moved) {
          setSelectOptionChanges();
        }
        $button.trigger('focus');
        announceMove(name, $box, '.pp-select-options-box', moved, direction);
      });

      $(document).on('change', '.pp-select-options-wrap .entry-field', function () {
        setSelectOptionChanges();
      });

      /**
       * Set options after position change
       */
      function setSelectOptionChanges() {
        $('.pp-select-options-wrap .pp-select-options-box').each(function (index) {
          if (index === 0) {
            $(this).find('.delete-button').css('visibility', 'hidden');
            $(this).find('.delete-button').css('display', '');
          } else {
            $(this).find('.delete-button').css('visibility', 'visible');
            $(this).find('.delete-button').css('display', '');
          }
          $(this).find('.select_dropdown_default').val(index);
          $(this).find('.value-field').attr('aria-label', ppSprintf(l10n.option_value, index + 1));
          $(this).find('.label-field').attr('aria-label', ppSprintf(l10n.option_label, index + 1));
          setMoveButtonLabels($(this), getOptionName($(this)));
        });
      }
    });
})(jQuery);
