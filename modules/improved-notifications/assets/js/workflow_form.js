/**
 * @package PublishPress
 * @author PublishPress
 *
 * Copyright (c) 2022 PublishPress
 *
 * This file is part of PublishPress
 *
 * PublishPress is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PublishPress is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PublishPress.  If not, see <http://www.gnu.org/licenses/>.
 */
(function ($) {
    $(function () {
        function setupFieldFilters(name) {
            // "When the content is moved to a new status"'s filters
            if ($('#publishpress_notif_' + name).length > 0) {
                var $chkb = $('#publishpress_notif_' + name),
                    $filters = $('.publishpress_notif_' + name + '_filters');

                // Add event to the checkbox
                $chkb.on('change', function () {
                    if ($chkb.is(':checked')) {
                        $filters.show();
                        $filters.find('select').multipleSelect('refresh');
                        $filters.find('select').each(function () {
                            applyMultipleSelectA11y($(this));
                        });
                    } else {
                        $filters.hide();
                    }
                });
            }
        }

        // Make this method public for add-ons.
        window.publishpressSetupFieldFilters = setupFieldFilters;

        setupFieldFilters('event_post_save');
        setupFieldFilters('event_content_post_type');
        setupFieldFilters('event_content_category');
        setupFieldFilters('event_content_taxonomy');
        setupFieldFilters('event_taxonomy_update');
        setupFieldFilters('user');
        setupFieldFilters('role');
        setupFieldFilters('group');

        function getEditor() {
            var editor = tinymce.activeEditor;

            for (var editorIndex = 0; editorIndex < tinymce.editors.length; editorIndex++) {
                if (tinymce.editors[editorIndex].id === 'input_id') {
                    return tinymce.editors[editorIndex];
                }
            }

            return editor;
        }

        function getEditorContent() {
            var editor = getEditor();
            var content = '';

            if (editor !== null) {
                content = editor.getContent();
            } else {
                content = $('#input_id').val();
            }

            return content.trim();
        }

        var i18n = (typeof workflowFormData !== 'undefined' && workflowFormData.text) ? workflowFormData.text : {};

        /**
         * Add accessible names and state to the multiple-select replacement controls.
         * The native select stays hidden (the library sets the "hidden" attribute on it).
         *
         * @param $select
         */
        function applyMultipleSelectA11y($select) {
            var $parent = $select.next('.ms-parent');

            if ($parent.length === 0) {
                return;
            }

            var selectId = $select.attr('id');
            var $choice = $parent.find('.ms-choice');
            var $text = $choice.children('span').first();
            var labelledBy = [];

            if (selectId) {
                var $label = $('label[for="' + selectId.replace(/([:\\\[\]])/g, '\\$1') + '"]').first();

                if ($label.length > 0) {
                    if (!$label.attr('id')) {
                        $label.attr('id', selectId + '-label');
                    }

                    labelledBy.push($label.attr('id'));
                }

                if (!$text.attr('id')) {
                    $text.attr('id', selectId + '-ms-text');
                }

                labelledBy.push($text.attr('id'));
            }

            $choice.attr({
                'aria-haspopup': 'dialog',
                'aria-expanded': $parent.hasClass('open') ? 'true' : 'false'
            });

            if (labelledBy.length > 0) {
                $choice.attr('aria-labelledby', labelledBy.join(' '));
            }

            $parent.find('.ms-search input').attr('aria-label', i18n.search || 'Search');
        }

        // List search
        $('.publishpress-filter-checkbox-list select').each(function () {
            var $select = $(this);

            $select.multipleSelect({
                filter: true,
                onOpen: function () {
                    $select.next('.ms-parent').find('.ms-choice').attr('aria-expanded', 'true');
                },
                onClose: function () {
                    $select.next('.ms-parent').find('.ms-choice').attr('aria-expanded', 'false');
                }
            });

            applyMultipleSelectA11y($select);
        });

        /**
         * Toggle aria-invalid on a field (and on its multiple-select replacement button, if any).
         *
         * @param $field
         * @param invalid
         */
        function set_field_invalid($field, invalid) {
            var $targets = $field.add($field.next('.ms-parent').find('.ms-choice'));

            if (invalid) {
                $targets.attr('aria-invalid', 'true');
            } else {
                $targets.removeAttr('aria-invalid');
            }
        }

        // Clear the invalid state as soon as the user fixes a field.
        $('#publishpress_notification_content_main_subject').on('input', function () {
            if ($(this).val().trim() !== '') {
                set_field_invalid($(this), false);
            }
        });

        $('.publishpress-filter-checkbox-list select').on('change', function () {
            if ($(this).val() !== null && $(this).val().length !== 0) {
                set_field_invalid($(this), false);
            }
        });

        // Form validation
        $('form#post').on('submit', function (event) {
            var selected,
                sections = ['event', 'event_content'],
                messages = [],
                invalidSections = {};

            /**
             * Set the validation status to the given section.
             * A section flagged invalid stays invalid for the rest of this validation pass.
             *
             * @param section
             * @param status
             */
            function set_validation_status(section, status) {
                var $section = $('#psppno-workflow-metabox-section-' + section);
                var $header = $section.find('.psppno_workflow_metabox_section_header');

                invalidSections[section] = invalidSections[section] || !status;

                if (!invalidSections[section]) {
                    $header.removeClass('invalid');
                    $header.find('.psppno-invalid-marker').remove();
                    $section.removeAttr('aria-invalid');
                } else {
                    $header.addClass('invalid');
                    $section.attr('aria-invalid', 'true');

                    if ($header.find('.psppno-invalid-marker').length === 0) {
                        $header.append(
                            $('<span class="psppno-invalid-marker"></span>').text(' ' + (i18n.invalidSection || ''))
                        );
                    }
                }
            }

            function set_tooltip(section) {
                var selector = '#psppno-workflow-metabox-section-' + section + ' .psppno_workflow_metabox_section_header';

                $(selector).tooltip();
            }

            // Check the Event and Event Content sections
            $.each(sections, function (index, section) {
                // Check if the "When" and "Which content" filter has at least one selected option.
                selected = $('[name="publishpress_notif[' + section + '][]"]:checked').length;

                if (selected === 0) {
                    set_validation_status(section, false);

                    messages.push(workflowFormData.messages['selectAllIn_' + section]);
                } else {
                    set_validation_status(section, true);
                }
            });

            // Check if any status was selected for "moving to new status"
            if ($('#publishpress_notif_event_post_save:checked').length > 0) {
                set_field_invalid($('#publishpress_notif_event_post_save_filters_post_status_from'), $('#publishpress_notif_event_post_save_filters_post_status_from').val() == null);
                set_field_invalid($('#publishpress_notif_event_post_save_filters_post_status_to'), $('#publishpress_notif_event_post_save_filters_post_status_to').val() == null);

                if ($('#publishpress_notif_event_post_save_filters_post_status_from').val() == null
                    || $('#publishpress_notif_event_post_save_filters_post_status_to').val() == null) {

                    set_validation_status('event', false);

                    if ($('#publishpress_notif_event_post_save_filters_post_status_from').val() == null) {
                        messages.push(workflowFormData.messages['selectAPreviousStatus']);
                    }

                    if ($('#publishpress_notif_event_post_save_filters_post_status_to').val() == null) {
                        messages.push(workflowFormData.messages['selectANewStatus']);
                    }
                } else {
                    set_validation_status('event', true);
                }
            }

            // Check if any post type was selected (if checked)
            if ($('#publishpress_notif_event_content_post_type:checked').length > 0) {
                set_field_invalid($('#publishpress_notif_event_content_post_type_filters_post_type'), $('#publishpress_notif_event_content_post_type_filters_post_type').val() == null);

                if ($('#publishpress_notif_event_content_post_type_filters_post_type').val() == null) {
                    set_validation_status('event_content', false);

                    messages.push(workflowFormData.messages['selectPostType']);
                } else {
                    set_validation_status('event_content', true);
                }
            }

            // Check if any category was selected (if checked)
            if ($('#publishpress_notif_event_content_category:checked').length > 0) {
                set_field_invalid($('#publishpress_notif_event_content_category_filters_category'), $('#publishpress_notif_event_content_category_filters_category').val() == null);

                if ($('#publishpress_notif_event_content_category_filters_category').val() == null) {
                    set_validation_status('event_content', false);

                    messages.push(workflowFormData.messages['selectCategory']);
                } else {
                    set_validation_status('event_content', true);
                }
            }

            // Check if any taxonomy was selected (if checked)
            if ($('#publishpress_notif_event_content_taxonomy:checked').length > 0) {
                set_field_invalid($('#publishpress_notif_event_content_taxonomy_filters_term'), $('#publishpress_notif_event_content_taxonomy_filters_term').val() == null);

                if ($('#publishpress_notif_event_content_taxonomy_filters_term').val() == null) {
                    set_validation_status('event_content', false);

                    messages.push(workflowFormData.messages['selectTaxonomy']);
                } else {
                    set_validation_status('event_content', true);
                }
            }

            // Check the Receivers section
            if ($('#psppno-workflow-metabox-section-receiver input[type="checkbox"][name^="publishpress_notif"]:checked').length === 0) {
                set_validation_status('receiver', false);

                messages.push(workflowFormData.messages['selectAReceiver']);
            } else {
                set_validation_status('receiver', true);
            }

            // Check if any user was selected (if checked)
            if ($('#publishpress_notif_user:checked').length > 0) {
                set_field_invalid($('#publishpress_notif_user_list'), $('#publishpress_notif_user_list').val() == null);

                if ($('#publishpress_notif_user_list').val() == null) {
                    set_validation_status('receiver', false);

                    messages.push(workflowFormData.messages['selectAUser']);
                } else {
                    set_validation_status('receiver', true);
                }
            }

            // Check if any role was selected (if checked)
            if ($('#publishpress_notif_role:checked').length > 0) {
                set_field_invalid($('#publishpress_notif_roles'), $('#publishpress_notif_roles').val() == null);

                if ($('#publishpress_notif_roles').val() == null) {
                    set_validation_status('receiver', false);

                    messages.push(workflowFormData.messages['selectARole']);
                } else {
                    set_validation_status('receiver', true);
                }
            }

            // Check the Content section
            set_field_invalid(
                $('#publishpress_notification_content_main_subject'),
                $('#publishpress_notification_content_main_subject').val().trim() == ''
            );

            if ($('#publishpress_notification_content_main_subject').val().trim() == ''
                || getEditorContent() === '') {
                set_validation_status('content', false);

                if ($('#publishpress_notification_content_main_subject').val().trim() == '') {
                    messages.push(workflowFormData.messages['setASubject']);
                }

                if (getEditorContent() === '') {
                    messages.push(workflowFormData.messages['setABody']);
                }
            } else {
                set_validation_status('content', true);
            }

            var valid = $('form#post .invalid').length === 0;

            if (!valid) {
                if (messages.length > 0) {
                    $('#error_messages').remove();
                    var $messageBox = $('<div id="error_messages" class="notice notice-error" role="alert" tabindex="-1"></div>');
                    $('.wp-header-end').after($messageBox);

                    for (var i = 0; i < messages.length; i++) {
                        var $element = $('<p>');
                        $element.text(messages[i]);
                        $messageBox.append($element);
                    }

                    $messageBox.trigger('focus');
                }
            } else {
                $('#error_messages').remove();
            }

            return valid;
        });
    });
})(jQuery);
