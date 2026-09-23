/**
 * Base on :
 *     https://stackoverflow.com/questions/16590500/javascript-calculate-date-from-week-number
 */
export function getBeginDateOfWeekByWeekNumber(weekNumber, year, weekStartsOnSunday = true) {
    let simpleDate = new Date(year, 0, 1 + (weekNumber - 1) * 7);
    let dayOfWeek = simpleDate.getDay();
    let weekStartISO = simpleDate;


    if (dayOfWeek <= 4) {
        weekStartISO.setDate(simpleDate.getDate() - simpleDate.getDay() + 1);
    } else {
        weekStartISO.setDate(simpleDate.getDate() + 8 - simpleDate.getDay());
    }

    if (weekStartsOnSunday) {
        weekStartISO.setDate(weekStartISO.getDate() - 1);
    }

    return weekStartISO;
}

/* For a given date, get the ISO week number
 *
 * Based on information at:
 *
 *    http://www.merlyn.demon.co.uk/weekcalc.htm#WNR
 *
 * Algorithm is to find nearest thursday, it's year
 * is the year of the week number. Then get weeks
 * between that date and the first day of that year.
 *
 * Note that dates in one year can be weeks of previous
 * or next year, overlap is up to 3 days.
 *
 * e.g. 2014/12/29 is Monday in week  1 of 2015
 *      2012/1/1   is Sunday in week 52 of 2011
 */
export function getWeekNumberByDate(theDate, weekStartsOnSunday = true) {
    
    let theDateCopy = new Date(theDate.getFullYear(), theDate.getMonth(), theDate.getDate());

    let dayOfWeek = theDateCopy.getDay();
    let yearStart = new Date(theDateCopy.getFullYear(), 0, 1);

    let startOffset = weekStartsOnSunday ? yearStart.getDay() : (yearStart.getDay() || 7) - 1;

    let daysSinceYearStart = (theDateCopy - yearStart) / 86400000;
    let weekNo = Math.floor((daysSinceYearStart + startOffset) / 7) + 1;

    return [theDateCopy.getFullYear(), weekNo];
}

export function getBeginDateOfWeekByDate(theDate, weekStartsOnSunday = true) {
    let weekNumber = getWeekNumberByDate(theDate, weekStartsOnSunday);

    return getBeginDateOfWeekByWeekNumber(weekNumber[1], weekNumber[0], weekStartsOnSunday);
}

export function getHourStringOnFormat(timestamp, timeFormat = 'ga') {
    let hours = timestamp.getHours();

    if (timeFormat === 'ga' || timeFormat === 'ha') {
        if (hours === 0) {
            hours = '12am';
        } else if (hours < 12) {
            if (timeFormat === 'ha') {
                hours = hours.toString().padStart(2, '0');
            }
            hours += 'am';
        } else {
            if (hours > 12) {
                hours -= 12;
            }

            if (timeFormat === 'ha') {
                hours = hours.toString().padStart(2, '0');
            }

            hours += 'pm';
        }
    } else {
        hours = hours.toString().padStart(2, '0');
    }

    return hours;
}

export function getDateAsStringInWpFormat(theDate) {
    return theDate.getFullYear() + '-'
        + (theDate.getMonth() + 1).toString().padStart(2, '0') + '-'
        + theDate.getDate().toString().padStart(2, '0');
}

export function calculateWeeksInMilliseconds(weeks) {
    return weeks * 7 * 24 * 60 * 60 * 1000;
}

export function getMonthNameByMonthIndex(month) {
    const strings = publishpressCalendarParams.strings;

    const monthNames = [
        strings.monthJan,
        strings.monthFeb,
        strings.monthMar,
        strings.monthApr,
        strings.monthMay,
        strings.monthJun,
        strings.monthJul,
        strings.monthAug,
        strings.monthSep,
        strings.monthOct,
        strings.monthNov,
        strings.monthDec
    ];

    return monthNames[month];
}

export function getDateWithNoTimezoneOffset(dateString) {
    let date = new Date(dateString);
    const browserTimezoneOffset = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() + browserTimezoneOffset);
}

export function getPostLinksElement(linkData, handleOnClick) {
    var className = linkData.className ? linkData.className : '';
    if (linkData.url) {
        return (<a key={`link-${linkData.url}-${linkData.label}`} href={linkData.url} className={className}>{linkData.label}</a>);
    } else if (linkData.action) {
        return (<a key={`link-${linkData.url}-${linkData.label}`} className={className} onClick={(e) => handleOnClick(e, linkData)}>{linkData.label}</a>);
    }
}

export async function callAjaxAction(action, args, ajaxUrl) {
    let dataUrl = ajaxUrl + '?action=' + action;

    for (const argumentName in args) {
        if (!args.hasOwnProperty(argumentName)) {
            continue;
        }

        dataUrl += '&' + argumentName + '=' + args[argumentName];
    }

    const response = await fetch(dataUrl);
    return await response.json();
}

export async function callAjaxPostAction(action, args, ajaxUrl, body) {
    let dataUrl = ajaxUrl + '?action=' + action;

    for (const argumentName in args) {
        if (!args.hasOwnProperty(argumentName)) {
            continue;
        }

        dataUrl += '&' + argumentName + '=' + args[argumentName];
    }

    const response = await fetch(dataUrl, {
        method: 'post',
        body: body
    });
    return await response.json();
}

export function getTodayMidnight() {
    let today = new Date();
    today.setHours(0, 0, 0, 0);

    return today;
}

export function getDateInstanceFromString(dateString) {
    // The "-" char is replaced to make it compatible to Safari browser. Issue #1001.
    return new Date(String(dateString).replace(/-/g, "/"));
}

export function addCalendarPosts(posts, calendarPosts) {

    for (const date in calendarPosts) {
        if (calendarPosts.hasOwnProperty(date)) {
            calendarPosts[date].forEach(post => {
            if (post.calendar_post_data && Object.keys(post.calendar_post_data).length > 0) {
                const existingIndex = posts.findIndex(mergedPost => mergedPost.post_id === post.calendar_post_data.post_id);
                const mergedPost = {
                    ...post.calendar_post_data,
                    taxonomies: {
                      ...post.calendar_taxonomies_data
                    }
                };
                if (existingIndex > -1) {
                    // Update the existing post
                    posts[existingIndex] = mergedPost;
                  } else {
                    // Add the new post
                    posts.push(mergedPost);
                }
            }
            });
        }
    }

    return posts;
}

export function updateModalPost(e, button, handleRefreshOnClick) {
    e.preventDefault();
    var modal_form  = button.closest('.modal-content-right');
    
    button.addClass('disabled');

    var post_id         = button.attr('data-post_id');
    var post_title      = modal_form.find('.title-area').val();
    var post_date       = modal_form.find('.content_board_post_date_hidden').val();
    var post_author     = modal_form.find('.pp-modal-form-author').val();
    var post_status     = modal_form.find('.pp-modal-form-post-status').val();
    var post_taxonomies = {};
    modal_form.find('.pp-modal-form-post-taxonomy').each(function () {
        var tax_html = jQuery(this);
        post_taxonomies[tax_html.attr('data-taxonomy')] = tax_html.val();
    });
    var data = {
        action: "publishpress_content_calendar_update_post",
        post_id: post_id,
        post_title: post_title,
        post_date: post_date,
        post_author: post_author,
        post_status: post_status,
        post_taxonomies: post_taxonomies,
        nonce: publishpressCalendarParams.nonce
    };
    
    jQuery.post(ajaxurl, data, function (response) {
        if (response.status == 'success') {
            let PostData = publishpressCalendarParams.PostData;

            var target_post = jQuery('.publishpress-calendar .publishpress-calendar-item.post-' + post_id);
            var post_index = PostData.findIndex(function(p) {
                return Number(p.post_id) === Number(post_id);
            });

            // update card and post global data
            var post            = PostData[post_index];
            var taxonomies      = post.taxonomies;

            post.post_title     = post_title;
            post.raw_title      = post_title;
            post.post_status    = post_status;
            post.author_markup  = response.author_markup;
            post.date_markup    = response.date_markup;

            var response_taxonomies = response.taxonomy_terms;
            for (var taxonomyKey in response_taxonomies) {
                if (response_taxonomies.hasOwnProperty(taxonomyKey)) {
                    var taxonomyData = response_taxonomies[taxonomyKey];
                    taxonomies[taxonomyKey].terms = taxonomyData;
                }
            }
            post.taxonomies    = taxonomies;
            publishpressCalendarParams.PostData[post_index] = post;

            // update post title
            target_post.find('.publishpress-calendar-item-title').text(post.post_title);

            //refresh calendar
            if (typeof handleRefreshOnClick === 'function') {
                handleRefreshOnClick(e);
            }
        }

        // enable button
        button.removeClass('disabled');
        // show status message
        ppcTimerStatus(response.status, response.content);
    });
}

export function openPostModal(post_id) {
    
    let PostData = publishpressCalendarParams.PostData;
    
    var post_index = PostData.findIndex(function(p) {
        return Number(p.post_id) === Number(post_id);
    });

    if (post_index === -1) {
        console.error('Post with id ' + post_id + ' not found');
        console.log(PostData);
        return;
    }

    var post = PostData[post_index];

    var post_status     = post.post_status;
    // post details
    var previous_post = PostData[post_index - 1] || PostData[PostData.length - 1];
    var next_post = PostData[post_index + 1] || PostData[0];

    var status_title = post.status_label;
    var action_links = post.action_links;

    var post_taxonomies = post.taxonomies || null;

    var can_edit_post = Number(post.can_edit_post) > 0;

    var target_post = jQuery('.publishpress-calendar .publishpress-calendar-item.post-' + post_id);

    // build header
    var popup_header = '<div class="pp-popup-modal-header">';

        if (previous_post.post_id != post.post_id) {
            popup_header += '<div class="pp-modal-navigation-prev">';
            popup_header += '<a title="' + publishpressCalendarParams.strings.prev_label + '" href="#" class="modal-nav-prev" data-post_id="' + previous_post.post_id + '"><span class="dashicons dashicons-arrow-left-alt"></span> ' + previous_post.filtered_title + '</a>';
            popup_header += '</div>';
        }

        popup_header += '<div class="pp-modal-navigation-current">';
        popup_header += '<span class="modal-nav-current" style="color:' + target_post.css('background-color') + '"> ' + post.filtered_title + '</span>';
        popup_header += '</div>';

        if (next_post.post_id != post.post_id) {
            popup_header += '<div class="pp-modal-navigation-next">';
            popup_header += '<a title="' + publishpressCalendarParams.strings.next_label + '" href="#" class="modal-nav-next" data-post_id="' + next_post.post_id + '">' + next_post.filtered_title + ' <span class="dashicons dashicons-arrow-right-alt"></span></a>';
            popup_header += '</div>';
        }

        // add post edit link meta
        if (action_links.edit !== '') {
            popup_header += '<div class="meta post-edit"><span class="meta-title"><span><a href="' + action_links.edit + '">' + publishpressCalendarParams.strings.edit_label + '</a></span></span></div>';
        }
        // add post trash meta
        if (action_links.trash !== '') {
            popup_header += '<div class="meta post-delete"><span class="meta-title"><span><a href="' + action_links.trash + '">' + publishpressCalendarParams.strings.delete_label + '</a></span></span></div>';
        }
        // add post view/preview meta
        if (action_links.previewpost !== '') {
            popup_header += '<div class="meta post-preview"><span class="meta-title"><span><a href="' + action_links.previewpost + '">' + publishpressCalendarParams.strings.preview_label + '</a></span></span></div>';
        } else if (action_links.view !== '') {
            popup_header += '<div class="meta post-view"><span class="meta-title"><span><a href="' + action_links.view + '">' + publishpressCalendarParams.strings.view_label + '</a></span></span></div>';
        }

        popup_header += '</div>';

    // build content
    var popup_content = '<div class="pp-popup-modal-content">';

        popup_content = '<div class="modal-content-left">';

        popup_content += '<div class="main-post-content">';
        popup_content += post.post_content;
        popup_content += '</div>';

        popup_content += '</div>';

        popup_content += '<div class="modal-content-right">';

        popup_content += '<div class="scrollable-content">';

        // add post title
        if (can_edit_post) {
            popup_content += '<div class="modal-post-title"><textarea class="title-area">' + post.raw_title + '</textarea></div>';
        } else {
            popup_content += '<div class="modal-post-title"><div>' + post.raw_title + '</div></div>';
        }
        
        // add post date meta
        popup_content += '<div class="modal-taxonomy-info post-date"><span class="info-item">' + publishpressCalendarParams.strings.post_date_label + '</span><span class="info-item">' + post.date_markup + '</span></div>';
        // add post author meta
        popup_content += '<div class="modal-taxonomy-info post-author"><span class="info-item">' + publishpressCalendarParams.strings.post_author + '</span><span class="info-item">' + post.author_markup + '</span></div>';
        // add post status meta
        popup_content += '<div class="modal-taxonomy-info post-modified"><span class="info-item">' + post.status_field_label + '</span>';
        popup_content += '<span class="info-item">';
        if (can_edit_post) {
            popup_content += '<select class="pp-modal-form-post-status">';
            post.status_options.forEach(status => {
                var selected = status.value == post_status ? 'selected' : '';
                popup_content += '<option value="' + status.value + '" ' + selected + '>' + status.text + '</option>';
            });
            popup_content += '</select>';
        } else {
            popup_content += status_title;
        }
        popup_content += '</span>';
        popup_content += '</div>';

        // add taxonomies
        if (post_taxonomies !== null) {
            var taxonomy_terms_name = '';
            for (var key in post_taxonomies) {
                if (post_taxonomies.hasOwnProperty(key)) {
                    var taxonomy = post_taxonomies[key];
                    popup_content += '<div class="modal-taxonomy-info">';
                    popup_content += '<span class="info-item">' + taxonomy.taxonomy_label + '</span>';
                    if (can_edit_post) {
                        popup_content += '<span class="info-item">';
                        popup_content += '<select class="pp-modal-form-post-taxonomy" data-placeholder="' + taxonomy.taxonomy_placeholder + '" data-taxonomy="' + taxonomy.taxonomy + '" multiple>';
                        if (taxonomy.terms.length> 0) {
                            taxonomy.terms.forEach(term => {
                                popup_content += '<option value="' + term.slug + '" selected>' + term.name + '</option>';
                            });
                        }
                        popup_content += '</select>';
                        popup_content += '</span>';
                    } else {
                        if (taxonomy.terms.length> 0) {
                            taxonomy_terms_name = taxonomy.terms.map(function(term) {
                                return term.name;
                            });
                            popup_content += '<span class="info-item">' + taxonomy_terms_name.join(", ") + '</span>';
                        } else {
                            popup_content += '<span class="description pp-modal-description">' + publishpressCalendarParams.strings.empty_term + '</span>';
                        }
                    }
                    popup_content += '</div>';
                }
            };
        }

        popup_content += '</div>';

        popup_content += '<div class="fixed-footer">';
        if (can_edit_post) {
            popup_content += '<div class="save-post-changes" data-post_id="' + post.post_id + '"><span class="spinner is-active"></span> ' + publishpressCalendarParams.strings.update_label + '</div>';
        }
        popup_content += '</div>';

        popup_content += '</div>';

        popup_content += '</div>';

    jQuery('#pp-content-calendar-general-modal-container').html(popup_content);
    
    var height = Math.round(window.innerHeight * 0.78);

    tb_show(popup_header, '#TB_inline?width=600&height=' + height + '&inlineId=pp-content-calendar-general-modal');
    var modal_height = jQuery('body.pp-content-calendar-page #TB_window').css('height');
    if (modal_height) {
        // update inner content height for scroll bar
        var inner_height = parseInt(modal_height, 10) - 55;

        jQuery('.pp-content-calendar-general-modal-container .modal-content-right .scrollable-content').css('height', inner_height - 60 + 'px');
        jQuery('.pp-content-calendar-general-modal-container .modal-content-left').css('height', inner_height + 'px');
        jQuery('body.pp-content-calendar-page #TB_ajaxContent').css('height', inner_height + 'px');

        // adjust textarea height
        var textarea = jQuery('.pp-content-calendar-general-modal-container .modal-post-title .title-area');
        if (textarea.length > 0) {
            adjustTextareaHeight(false, textarea);
        }
    }

    // init date picker
    init_date_time_picker();
    // init select2
    initFormSelect2();
    
}

export function adjustTextareaHeight(event, textarea = false) {
    if (!textarea) {
        var textarea = jQuery('.pp-content-calendar-general-modal-container .modal-post-title .title-area');
    }
    // Reset the height so that it can shrink on deleting content
    textarea.css('height', 'auto');
    // Set the height to the scroll height of the content
    textarea.css('height', textarea[0].scrollHeight + 'px');
}
    
export function initFormSelect2() {
    jQuery('.pp-modal-form-author').pp_select2({
        allowClear: false,
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 0,
            data: function (params) {
                return {
                    action: 'publishpress_calendar_search_authors',
                    nonce: publishpressCalendarParams.nonce,
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

    jQuery('.pp-modal-form-post-taxonomy').pp_select2({
        allowClear: true,
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 0,
        data: function (params) {
                return {
                    action: 'publishpress_calendar_search_terms',
                    taxonomy: jQuery(this).attr('data-taxonomy'),
                    nonce: publishpressCalendarParams.nonce,
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

    jQuery('.pp-modal-form-post-status').pp_select2({
        allowClear: false
    });
}


export function init_date_time_picker() {
    jQuery('.pp-content-calendar-general-modal-container .modal-content-right .date-time-pick').each(function () {
        var self = jQuery(this);
        var options = getOptions(self, {
        alwaysSetTime: false,
        controlType: 'select',
        altFieldTimeOnly: false
        });
        if (self.hasClass('future-date')) {
        options.minDate = new Date();
        }
        self.datetimepicker(options);
    });
}
    
export function initToolTips() {
    jQuery('.pp-title-tooltip').each(function() {
        var $this = jQuery(this);
        var titleText = $this.attr('title');

        if (titleText && titleText !== '') {
            $this.removeAttr('title');

            var $tooltip = jQuery('<div class="pp-title-tooltip-text"></div>').text(titleText);
            jQuery('body').append($tooltip);

            $this.hover(function() {
                $tooltip.show();

                // Adjust the tooltip position to account for the arrow
                var tooltipTop = $this.offset().top - $tooltip.outerHeight() - 10; // Position 10px above the element
                var tooltipLeft = $this.offset().left + ($this.outerWidth() / 2) - ($tooltip.outerWidth() / 2);

                $tooltip.css({
                    top: tooltipTop + 'px',
                    left: tooltipLeft + 'px',
                    position: 'absolute'
                });
            }, function() {
                $tooltip.hide();
            });
        }
    });
}


export function getOptions (self, custom_options) {
  var default_options = {
    dateFormat: publishpressCalendarParams.strings.date_format,
    firstDay: publishpressCalendarParams.strings.week_first_day
  };

  var options = jQuery.extend({}, default_options, custom_options);
  var altFieldName = self.attr('data-alt-field');

  if ((!altFieldName) || typeof altFieldName == 'undefined' || altFieldName.length == 0) {
    return options;
  }

  return jQuery.extend({}, options, {
    altField: 'input[name="'+ altFieldName +'"]',
    altFormat: self.attr('data-alt-format'),
  });
}

export function ppcTimerStatus(type = "success", message = '') {
    setTimeout(function () {
        var uniqueClass = "pp-floating-msg-" + Math.round(new Date().getTime() + Math.random() * 100);
        var instances = jQuery(".pp-floating-status").length;
        jQuery("#wpbody-content").after('<span class="pp-floating-status pp-floating-status--' + type + " " + uniqueClass + '">' + message + "</span>");
        jQuery("." + uniqueClass)
            .css("bottom", instances * 45)
            .fadeIn(1e3)
            .delay(1e4)
            .fadeOut(1e3, function () {
                jQuery(this).remove();
            });
    }, 500);
}
