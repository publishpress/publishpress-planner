---
title: "Hooks and Filters for the Content Calendar"
order: 17
doc_category: publishpress
section: Advanced
---

# Hooks and Filters for the Content Calendar

Using WordPress’ hooks and filters, you can extend the [PublishPress Planner](https://publishpress.com/links/planner) calendar in the following ways:

- `pp_view_calendar_cap` (filter) – Modify the capability or role required to view the calendar. By default, administrators, editors, authors and contributors can view the calendar. On install, [PublishPress Planner](https://publishpress.com/links/planner) adds a capability of ‘pp_view_calendar’ to these roles.
- `pp_calendar_allow_ajax_to_set_timestamp` (filter) – Whether or not dragging an unpublished post to a new date changes the publication timestamp for the post. This is off by default. You can enable it by placing the following in your theme’s functions.php file:  
`add_filter( 'pp_calendar_allow_ajax_to_set_timestamp', '__return_true');`
- `pp_calendar_total_weeks` (filter) – Number of weeks to show on the calendar. This will override the user’s screen options setting and lock the calendar to a specific range.
- `pp_calendar_weekend_days` (filter) – Which days of the week are considered the weekend for the calendar. By default, this is Saturday and Sunday.
- `pp_calendar_item_information_fields` (filter) – Post details to be presented in the overlay that appears when you click on the post title. You can unset default fields, or include your own.
- `pp_show_scheduled_as_unpublished` (filter) – Include scheduled posts when filtering the calendar to display unpublished content. This is off by default.
- `publishpress_calendar_get_post_type_fields` (filter) – Post fields that will be displayed in the popup for entering post information for new posts created from the calendar. New fields can be added inserting items in the passed array. Read more information on [how to create custom fields in the calendar popup](https://publishpress.com/knowledge-base/custom-fields-in-the-calendar-popup/).
- `publishpress_calendar_after_create_post` (action) – Action called after a post is created from the calendar. The form data is available in the “$_POST” global array. Can be used to intercept custom fields created using the filter above. Read more info on [saving custom fields in the calendar popup](https://publishpress.com/knowledge-base/custom-fields-in-the-calendar-popup/).
