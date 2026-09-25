The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

[4.8.1] - 30 Sept, 2026

- Changed: Workflow metaboxes now support WordPress block editor compatibility. #2055
- Changed: Content Calendar keyboard navigation has been improved for better accessibility. #2032
- Changed: Planner form controls now include clearer labels to improve accessibility and usability. #2033
- Changed: Planner tables now use more semantic markup for improved accessibility and screen-reader support. #2034
- Changed: Calendar scripts now register their translations asynchronously for improved loading behavior. #2052
- Changed: The Content Board now displays checklist status, making workflow progress easier to see at a glance. #2054
- Changed: Revision statuses are now hidden when the Revisions feature is not enabled, keeping the interface cleaner. #2051
- Changed: The module URL property is now explicitly declared for improved consistency and compatibility. #2059
- Changed: CodeQL and other security workflows have been refactored and updated to improve code quality and automated security checks. #2045 #2010
- Changed: Plugin documentation and readme information have been refreshed. #2039 #2040 #2013
- Changed: The plugin's frontend and administrative assets have been refreshed. #2011
- Fixed: Security issues and code-scanning findings have been addressed to improve the overall security of the plugin. #2058 #2012 #2038 #2001
- Fixed: Workflow queries have been hardened to perform more reliably with posts containing large amounts of post meta data. #2056
- Fixed: The Content Calendar now correctly restores the calendar popup card width after recent accessibility changes. #2060
- Fixed: The Content Calendar now respects the configured date format in the date picker. #2049
- Fixed: Auto-draft content updates no longer trigger incorrect or unnecessary notifications. #1888 #2048
- Fixed: Select2 translations are now loaded correctly in the Content Calendar. #2047
- Fixed: Visual regressions in the Content Calendar caused by keyboard accessibility improvements have been corrected. #2057
- Fixed: Unsafe module descriptions are now handled more securely to prevent potentially unsafe content from being decoded. #2044
- Fixed: Planner database queries have been prepared and optimized for safer and more reliable execution. #2043
- Fixed: Missing image alternative text has been added in affected areas to improve accessibility. #2042
- Fixed: Dependency-related security advisories have been addressed through updated third-party packages. #2029 #2037 #2035 #2031 #2027 #2021 #2006 #2007 #1998
- Removed: The editorial comment workflow event has been removed. #2050
- Removed: Unused development tooling and libraries, including Grunt watch, Grunt curl, Mocha, and Babel Minify Webpack Plugin. #2004 #2003 #2002 #2000

[4.8.0] - 13 Apr, 2025

- Added: Expanded language support with improved translation system integration (up to 34 languages). #1974
- Added: Support for LANG_LOCALES configuration in translation settings. #1978
- Changed: French, Spanish, and Italian translations. #1981
- Changed: Improved translation coverage by adding and fixing text domains across multiple components.
- Changed: Plugin name now includes “Free” label in the Plugins screen. #1975
- Changed: PublishPress plugins list in readme.txt. #1973
- Changed: Refactored and simplified Composer build scripts for better maintainability. #1979
- Changed: Improved dependency version checks for development tools.
- Changed: Development environment and CI/CD workflows for better reliability and automation.
- Changed: Webpack, lodash, dompurify, minimatch, picomatch, and other dependencies for security and compatibility.
- Fixed: Fatal error caused by invalid method calls in settings under certain conditions. #1916
- Fixed: Async notification function now fails gracefully when called without required arguments. #1905
- Fixed: Editorial comment translation not working correctly. #1918
- Fixed: Duplicate HTTP protocol issue in permalinks. #1952
- Fixed: Missing scheduled and published statuses in “Display Publish Time” when Statuses plugin is active.
- Fixed: Missing publish time settings display in some configurations. #1954
- Fixed: Modal dropdowns not working properly in Calendar and other screens. #1891
- Fixed: Prevent “Save Changes” button from showing when user does not have permission. #1894
- Fixed: Plugin CSS now loads only on its own admin pages, improving performance. #1980
- Fixed: Better handling of status retrieval when integrated with the Statuses plugin.
- Fixed: General code quality, formatting, and maintainability across multiple modules.

[4.7.2] - 3 Apr, 2025

- Fixed: Duplicate copies of default Notification Workflows were created under some conditions, #1873
- Fixed: Notifications were sent without specified event name / content type under some conditions, #1874
- Fixed: If Revisions Pro is deactivated, some of its Notifications are still sent #1882
- Fixed: Trash duplicate Notification Workflows, #1808
- Fixed: Notification Skip current user not working, #1871
- Update: Update select2 and DOMPurify library, #1872

[4.7.1] - 18 Mar, 2025

- Compat: Revisions Pro: Support restoration of default Notification Workflows if they were not created due to Revisions Pro Notification Workflows being created first
- Update: Planner v.4.7.0_Translation Updates ES-FR-IT, #1865

[4.7.0] - 17 Mar, 2025

- Compat: Notification Groups: support Revisions metagroups where appropriate, #1852
- Compat: Revisions Pro: Support Revision Author as a Notifications receiver, #1854
- Compat: Revisions Pro: Support Permissions metagroups as a receiver where appropriate, #1855
- Compat: Notifications: Support Permission Groups as a Receiver, #1839
- Fixed: Problem with Async Notification not going, #1842
- Fixed: Allow "Notify Me" with edit_post_subscription capability only, #1807
- Fixed: Calendar filter changes only reflect after refreshing page, #1841
- Update: Notifications: Streamline translation, log captioning, #1840
- Update: Limit Notification Log to 1,000 maximum removing old notifications for new entry, #1837
- Update: Open Modal Edit Link in the same tab, #1799
- Update: Translation pt_BR, #1836
- Update: Planner v.4.6.1 Translation Updates-ES-FR-IT, #1835

[4.6.1] - 29 Jan, 2025

- Compat: PublishPress Revisions: corrected Revision Status display and selection in Calendar, Overview, Content Board (requires Revisions 3.6),#1809
- Compat: Revisions Pro 3.6.0: Revision Notifications, #1809
- Compat: PublishPress Statuses Pro: custom Revision Statuses in Calendar, Overview, Content Board #1809
- Update: Add settings to have Today in the top row in Calendar, #1827
- Fixed: Extra notifications breaks the "Editorial Notifications" area on the Profile page, #1819
- Update: Planner v.4.6.0 Translation Updates-ES-FR-IT, #1826
- Update: Brazil Translation Update,  #1810

[4.6.0] - 21 Jan, 2025

- Compat: Revisions Pro 3.6.0: Revision Notifications, #1809
- Compat: PublishPress Statuses Pro: custom Revision Statuses in Calendar, Content Overview, Content Board #1809
- Fixed: PP_Revision_Integration::addRevisionQuery Expects 4 Arguments, but Only 2 Are Passed in ICS Feed Generation, #1806
- Update: Notifications metabox: suppress Active Notifications list, #1817
- Fixed: Notifications Log: PHP Warning if Workflow data is missing, #1815
- Fixed: Notifications Log: Missing html title, #1813

[4.5.1] - 14 Oct, 2024

- Update: Add settings to control the maximum number of posts on Content Overview and Board page, #1772
- Update: Prevent Content Board posts from been moved to status user doesn't have capability to create post in, #1777
- Fixed: Notification Threshold and Channel settings Missing, #1771
- Update: Enable Revisions automatically, #1766
- Update: Optimize meta keys on Content Calendar, Board, and Overview by moving search to ajax, #1768
- Update: Optimize plugin select2 script
- Update: Remove jquery.inputmask.js and replace with custom function
- Update: Update Calendar "Save" and "Save and Edit" to new button style, #1790
- Update: Planner v.4.5.0 Translation Updates-ES-FR-IT, #1764

[4.5.0] - 09 Sep, 2024

- Feature: Allow users to show Revisions in the Calendar, Overview and Board page, #1734
- Added: Add "When Taxonomy is updated" to notifications options, #1759
- Added: Allow users to chose the publish date in the "New Post" box, #1743
- Update: Make default headings sortable in Content Overview, #1744
- Fixed: Prevent duplicate call for filters on page load, #1754
- Added: Add "Months" in Notification Published trigger unit, #1584
- Update: Remove the "Editorial Fields" from the filters if empty, #1741
- Fixed: 8.2 issue from Siteground, #1739
- Removed: Remove the statuses message, #1740
- Update: Planner v.4. 4.0 Translation Updates-ES-FR-IT, #1737

[4.4.0] - 13 Aug, 2024

- Added: Add modal to Content Calendar and Content Overview screens, #1687
- Remove: Remove "Make Viewable" in Editorial Fields, #1636
- Update: Add Async Notification control back to feature settings, #1726
- Update: Add new filter to abort/prevent notification based on post id or meta data, #1723
- Added: Add 2 new notifications, #1601
- Update: Update Notification metabox, #1664
- Fixed: Calendar Reset Filter not working properly, #1717
- Update: Disable Editorial Comments and Fields for new sites, #1600
- Update: Planner v.4.3.1_Translation Updates-ES-FR-IT, #1716

[4.3.1] - 24 July, 2024

- Fixed: Calendar not loading older post on navigation, #1707
- Update: Add "Private" status in Content Board, #1671
- Update: Add Scheduled status option to Content Calendar, Overview and Board status filter, #1644
- Update: Update plugin description, #1612
- Update: Update plugin footer, #1656
- Update: Update Content Board status update notification styles, #1633
- Update: Planner v.4.3.0_Translation Updates-ES-FR-IT, #1708

[4.3.0] - 22 July, 2024

- Update: Add Quick Preview and Edit Modals to Content Board Pro, #1585
- Update: Adjust default colors of core statuses to match Statuses plugin, #1670
- Update: Update the filters on Content Calendar to match the Overview and the Board, #1603
- Update: Add a search box to the Calendar, #1560
- Update: Redirect after new install, #1681
- Update: Add the remaining planner capabilities to correct tab in the Capabilities plugin, #1638
- Fixed: Not possible to disable the Notifications feature, #1680
- Update: Disable the Dashboard Widgets by default, #1642
- Fixed: User from Custom Role is not showing in Calendar form, #1674
- Update: Update promo box, #1652
- Update: Only show Posts on the Calendar by default, #1678
- Update: Planner FREE_v.4.2.1 Translation Updates ES-FR-IT, #1669

[4.2.1] - 28 May, 2024

- Update: Grant Calendar, Overview and Boards screens access to editors and authors on new installation, #1640
- Update: Tidy up the "Features" screen, #1649
- Update: Small typo after updating card data, #1653
- Update: Update "metadata" name in settings, #1641
- Fixed: Content Overview and Board using english label in meta query filter, #1654
- Update: Register pp_view_content_board with Capabilities, #1639
- Update: Planner v.4.2.0 Translation Updates-ES-FR-IT, #1647

[4.2.0] - 16 May, 2024

- Feature: Add a kanban board to display posts by status(Content Board), #1379
- Update: Change "Editorial Metadata" to "Editorial Fields", #1577
- Update: Remove Categories as a default Filters, #1581
- Update: Change Content Overview "Manage Filters" label to "Customize Filters", #1580
- Update: Add a way to find empty data in content overview filters, #1578
- Update: Update filters default date, #1602
- Update: Update content overview filters compare labels, #1582
- Update: Fixed text domain in improved-notifications.php file, #1615
- Update: Made string translatable in Base.php file, #1614
- Update: Planner FREE v.4.1.0 Translation Updates ES-FR-IT, #1586

[4.1.0] - 04 April, 2024

- Update: Improve the Content Overview screen with selectable columns, #1538
- Update: Clean up Content Overview Filters, #1558
- Update: Add "New Post" modal to Content Overview, #1557
- Update: Turkish Translation for Planner, #1568
- Update: Planner translation Updates from v4.0.3, #1549

[4.0.3] - 23 Jan, 2024

- Fixed: Time no longer showing on the calendar for some statuses, #1533
- Update: Update Content Calendar statuses default color, #1523
- Fixed: Fix missing translation string for calendar “view X more” and “hide the X last items”, #1536
- Fixed: Calendar filters too cramped with the X, #1535
- Fixed: Settings default to "Features" instead of first tab, #1524
- Fixed: Deprecation issue, #1540
- Fixed: Cannot use object of type WP_Error as array, #1525
- Update: Re-enable Dashboard Widgets, #1505
- Update: Translation Updates Planner, January 2024, #1522

[4.0.2] - 04 Jan, 2024

- Fixed: Calendar did not show status colors if PublishPress Statuses plugin is not activated
- Compat: PublishPress Statuses - iframe Install Now button did not work

[4.0.1] - 04 Jan, 2024

- Compat: PublishPress Statuses plugin integration
- Update: Add first name and last name to Notification shortcode, #1500
- Update: Make Content Overview default date filters to 1 month back and 1 month ahead, #1503
- Update: Remove javascript from href attributes on components, #1511
- Fixed: User type metadata displaying user id instead of display name in notification, #1515
- Fixed: Invalid use of props on select component in the calendar screen, #1508
- Fixed: Warning on console: Each child in a list should have a unique "key" prop, #1507
- Fixed: Warning on console: ReactDOM.render is no longer supported in React 18, #1506
- Update: Planner FREE V4.0.0 Translation Updates ES-FR-IT, #1512

[3.12.2] - 28 Sept, 2023

- Update: Show only users who have written a comment in Editorial Comment User filtter, #1220
- Fixed: "Save as" button contains duplicated text in Safari, #1389
- Fixed: Migration from Edit-Flow not importing Editorial Metadata fields, #1459
- Update: Remove the Dashboard Widgets, #1449
- Update: Add a promo sidebar in the Free version, #1203
- Fixed: Critial Error on the plugin settings page when some features are disabled, #1469
- Update: Move the Notifications Log to a button, #1407
- Fixed: Post without post_date_gmt or post_modified_date_gmt not showing in google calendar export, #1461
- Fixed: Metadata not showing on the Calendar when viewable is enabled, #1216
- Fixed: Content Overview dates filter reset not working, #1378
- Update: Adjust Content Overview Filter and Reset buttons, #1406
- Update: Replace the penguins with normal page headers, #1402
- Update: PLANNER PRO: ES-FR-IT_Transaltions Updates September 2023, #1457
- Update: PLANNER FREE- Translation Updates September 10, 2023, #1458

[3.12.1] - 06 Sept, 2023

- Fixed: Editorial comments delete capability issue, #1420
- Feature: Added deleting button on Editorial Comment Page, #1386
- Fixed: Update notification not showing under the Dashboard Updates screen or the Plugins screen, #1454
- Update: Include scheduled post in Export to Calendar, #1372
- Fixed: Issue with "&" in calendar category filter, #1446
- Fixed: Issue with fix_get_sample_permalink method, #1431
- Fixed: Deprecations notice on PHP 8.1, #1414
- Fixed: Double slug on schedule page, #1429
- Fixed: action_parse_query hook generates fatal error, #1410
- Fixed: PublishPress Planner conflect with Series Order, #1424
- Fixed: Planner FREE -ES-FR-IT Translation Updates August 2023, #1439

[3.12.0] - 15 Aug, 2023

- Changed: Replaced Pimple library with a prefixed version of the library to avoid conflicts with other plugins;
- Changed: Replaced Psr/Container library with a prefixed version of the library to avoid conflicts with other plugins;
- Changed: Change min PHP version to 7.2.5. If not compatible, the plugin will not execute;
- Changed: Change min WP version to 5.5. If not compatible, the plugin will not execute;
- Changed: Updated internal libraries to latest versions;
- Changed: Refactor some occurrences of "plugins_loaded" replacing it by a new action: "publishpress_planner_loaded" which runs after the requirements and libraries are loaded, but before the plugin is initialized;

[3.11.0] - 20 June, 2023

- Changed: Replaced Pimple library with a prefixed version of the library to avoid conflicts with other plugins;
- Changed: Replaced Psr/Container library with a prefixed version of the library to avoid conflicts with other plugins;
- Changed: Change min PHP version to 7.2.5. If not compatible, the plugin will not execute;
- Changed: Change min WP version to 5.5. If not compatible, the plugin will not execute;
- Changed: Updated internal libraries to latest versions;
- Changed: Refactor some occurrences of "plugins_loaded" replacing it by a new action: "plublishpress_<name>_loaded" which runs after the requirements and libraries are loaded, but before the plugin is initialized;

[3.10.2] - 29 May, 2023

- Fixed: Metadata reorder not working, #1228
- Fixed: Content Overview Date Filter not working for 1 day date range, #1225
- Fixed: Content Overview Modified Date Toggle not working, #1233
- Fixed: Dropdown Select metadata type update, #1214
- Fixed: Tiny typo in metadata settings, #1221
- Update: Add the new "PublishPress Planner" name in the footer, #1215
- Fixed: User profile notification channel setting not updating, #1218
- Update: ES-FR-IT Translation Updates January 2023, #1241
- Update: Italian translation Update December 6, #1217

[3.10.1] - 02 Dec, 2022

- Fixed: Fatal Error caused by 3.10.0 update, #1204

[3.10.0] - 01 Dec, 2022

- Update: Rename the plugin to "PublishPress Planner", #1077
- Update: Add Metadata top level menu link, #666
- Update: Add a Dropdown Select type to metadata, #564
- Update: Show and Add metadata on the Calendar, #284
- Update: Add Role filter for editorial metadata of User type, #801
- Update: Allow to choose post type where the metadata is displayed, #464
- Update: Notification threshold description, #1179
- Fixed: "User" metadata metabox doesn't scale well, #563
- Update: A setting to change the name showing in editorial comments, #1162
- Fixed: Rating stars in PublishPress footer are unevenly spaced, #1150
- Update: PublishPress-PRO ES-FR-IT Translation Updates November 4, 2022, #1164
- Update: PublishPress-FREE-TranslationUpdates_FR-IT_November3_2022, #1163

[3.9.0] - 3 Nov, 2022

- Update: Add Editorial Comments menu, #319
- Update: Add file upload option to Editorial Comments, #757
- Fixed: Notification User Role Keeps Reverting, #1136
- Fixed: No Save Button on Editorial Comment Setting, #1134
- Fixed: Error on console when accessing Widget, #1135
- Fixed: Fatal error preventing user update, #1152
- Fixed: Editorial Notifications channel not updating when user is updated, #1151
- Fixed: PHP Notice: Undefined property warning on notification add/edit screen, #1147
- Fixed: Add editorial comments metabox priority filter, #1084
- Fixed: Extra "General" heading in "Slack" and "Reminders", #1122
- Fixed: Publishpress-v3.8.4-ES-FR-IT_TranslationUpdate-5_October2022, #1133
- Fixed: Press-ES-FR-IT-Translation-Update-September2022, #1124

[3.8.4] - 3 Oct, 2022

- Changed: Removed Twig dependency, refactoring the views for using plain PHP templates, #1125;

[3.8.3] - 8 Sep, 2022

- Fixed: Custom Status Conflict – PublishPress, #1105
- Update: Show Content Overview Post Types Content Together, #1062
- Update: Change to Content Overview "Start Date" and "End Date" filter, #1064
- Update: Update to Settings tabs #443
- Update: Publishpress-ES-FR-IT-translation-update_August2022, #1104

[3.8.2] - 17 Aug, 2022

- Update: Sticky filters on the Calendar, #1088
- Update: Allow users to choose whether or not to show the whole page title, #1089
- Fixed: Double slug on hierarchical page when in draft mode, #1087
- Fixed: Metadata box shows errors if there are no fields, #1085
- Fixed: Problem with html characters on calendar, #1037
- Fixed: Metadata filters don't work on Content Overview, #1070
- Fixed: Fix calendar time picker time picker, #914
- Update: Stop loading assets on non-PublishPress /wp-admin/ pages, #330

[3.8.1] - 7 Jun, 2022

- Fixed: Uncaught Error: syntax error, unexpected ')' in lower php version, #1071

[3.8.0] - 6 Jun, 2022

- Update: Improve content overview date filter, #969;
- Update: Move content overview from general settings to it own tab, #971;
- Added: Add support for more taxonomies in content overview screen, #970;
- Added: Add metadata filter option to content overview screen, #935;
- Fixed: Fix all users showing in dropdow on calendar and content overview screen, #1035;
- Fixed: Fix status permissions on calendar, #1038;
- Added: Add publishpress-instance-protection package, #1034;

[3.7.1] - 21 apr, 2022

- Fixed: Fix performance, removing automatic deactivation of Edit Flow, replacing it by a simple admin notice, #998;
- Fixed: Fix PHP error on customizer, due to a inline JS script added by mistake out of the head tag, #562;
- Fixed: Fix drag and drop of pending posts, #1015;
- Fixed: Fix integration with The Events Calendar for moving events in the calendar, #1016;
- Fixed: Updated POT file, pt_BR, es_ES, fr_FR and it_IT translations;
- Fixed: Fix warning about WP_User_Query being called with an argument that is deprecated, #1014;
- Fixed: Fix conflict with The Events Calendar query, forcing to display event items in the calendar based on the post_date, #1020;
- Fixed: Removed duplicated `event` param from the workflow action;
- Fixed: Change the action used to trigger post status transition notifications, from `transition_post_status` to `wp_after_insert_post`, #940, #671;
- Fixed: Fix the nonce check for the bulk actions on the notifications log, #1019;
- Added: Add select field for the custom fields API to the calendar popup, #1010;
- Fixed: Fix performance issue with file i/o usage when trying to disable deprecated plugins on every request, #808;
- Added: Add a search box to content overview screen #972;
- Added: Add filter "publishpress_calendar_data_args" allowing to customize the query of posts on the calendar, #1017;

[3.7.0] - 02 fev, 2022

- Fixed: Fix the admin menu Debug Log that was not being displayed, #992;
- Fixed: Fix the fatal error while exporting the calendar as ICS file, #994;
- Fixed: Fix the post type filter in the calendar, #995;
- Fixed: Fix fatal error: [Unknown column 'following_users' in 'where clause'], #982;
- Fixed: Fix drag and drop of custom statuses for reordering, #986;
- Fixed: Fix the time displayed in the calendar items on Safari, #1001;
- Fixed: Fix moving items and date navigation on the calendar in Safari, #1002;
- Fixed: Fix input sanitization in all the modules, HTML and SQL scaping in all the plugin;
- Fixed: Fix an unopened script HTML tag in the editorial metadata module;
- Fixed: Fix metadata in the notifications body for new posts, #574;
- Fixed: Added better feedback on errors while ordering statuses in the admin;
- Fixed: Fix the debug button to only display it for those who has permissions. The debug info were still safe, but the button was displayed, #993;
- Fixed: Improved capability check on diverse functions and modules;
- Fixed: Only load admin assets and the admin menu action if the user has permission for seeing that;
- Fixed: Added nonce check for missed places;
- Fixed: Added cache to the user, author and category searches in the content_overview module;
- Fixed: Fix the capability check for the configure button on editorial metadata metabox. It was only looking for the capability manage_options, which is customizable;
- Fixed: Added a missed capability check before showing search results in the notifications log filters;
- Fixed: Fixed PHP warning about function not defined: esc_array_of_options;
- Fixed: Only look for default capabilities on the calendar module if in the admin;
- Fixed: Only add admin hooks if in the admin;
- Fixed: Fix the delete action for notification log items;
- Added: Add the value of the global constant DISABLE_WP_CRON to the debug info, #987;
- Added: Add the value of the global debug constants WP_DEBUG_DISPLAY, WP_DEBUG_LOGv, and WP_DEBUG to the debug info, #998;
- Changed: Removed not used and deprecated methods in the calendar module: save_post_notify_users, save_post_notify_roles, add_role_to_notify, handle_ajax_drag_and_drop;
- Changed: Improved error handling on Ajax requests on the notifications log;
- Changed: WP version updated to 5.9;
- Changed: Removed not used method remove_object_terms of the calendar module class;

[3.6.3] - 18 Nov, 2021

- Fixed: Fix notifications page that tuns the WordPress admin area purple, #966;
- Fixed: Fix "save draft" button has gone, #967;
- Changed: Show the reviews banner on all admin pages;

[3.6.2] - 25 Oct, 2021

- Fixed: Can't edit the post status in the classic editor when WP to Twitter is activated, #958;
- Fixed: Can't edit the post status in the block editor when Block Editor Colors is activated, #959;
- Fixed: Block editor crashing if we resize the window to a mobile device dimension, #960;
- Added: Add a review request banner, #949;

[3.6.1] - 04 Oct, 2021

- Fixed: Add constants to customize priority of main actions: action_init, action_init_after and action_ini_for_admin, #953;
- Fixed: Fix notification body on events triggered by Elementor pages, #951;
- Fixed: Refactor the settings GET var for fixing a compatibility issue with 3rd party plugin;

[3.6.0] - 02 Sep, 2021

- Added: Add support for notifications when post is trashed/untrashed posts, #939;
- Added: Add support for notifications when post is updated/saved, #483;
- Added: Add support on Notifications content for different data types in the meta fields, including support to ACF relationship fields for posts, link, taxonomy and user, #924;
- Removed: Remove the deprecated module "Roles". Please, use PublishPress Capabilities or other plugin for handling user roles, #925;
- Removed: Remove the deprecated module "User Groups", #926;
- Fixed: Stop creating unused user roles on install, #926;
- Fixed: Fixed spacing between fields in the calendar popup, #920;
- Fixed: Fixed PHP warning about not set configuration: duplicated_notification_threshold;

[3.5.1] - 30 Aug, 2021

- Fixed: Fix undefined property stdClass::$author, #931;
- Fixed: Notifications don't trigger for posts created in the frontend, #936;
- Fixed: PHP warnings about not set configuration;
- Fixed: Fix the order and orderby filter in the content overview;
- Added: Add Japanese translation files, #934;

[3.5.0] - 5 Aug, 2021

- Added: Add the option to edit and delete editorial comments, #277;
- Added: Add new capabilities to control who can edit or delete editorial comments: pp_delete_editorial_comment, pp_delete_others_editorial_comment, pp_edit_editorial_comment, pp_edit_others_editorial_comment, #277;
- Fixed: Fix long text on the posts attributes in the calendar popup, #917;

[3.4.1] - 19 Jul, 2021

- Fixed: Fix the visual feedback for the calendar items drag and drop, #881;
- Fixed: Fix the publishing time field in the calendar form to display the default publishing date, #882;
- Fixed: Fix post creation capability check before allowing to create posts in the calendar, #799;
- Fixed: Fix publishing capability check before allow to create posts with "publish", "future", "private" statuses, #825;
- Fixed: Fix edit other posts capability check before allow to set a different author for the post, #834;
- Fixed: Fix capabilities check before displaying the links in the footer of the items popup on the calendar, #887;
- Fixed: Fix post subscribe capability check before displaying the option to "Notify me" in the item popup on the calendar, #886;
- Fixed: Fix edit posts permission before allowing to move items in the calendar, #891;
- Fixed: Fix PHP warning: Invalid argument supplied for foreach(), #828;
- Fixed: Fix the default selected status in the post creation form on the calendar, #893;
- Fixed: Fix the "load more" behavior and links when the calendar is configured to display "All posts", #897;
- Fixed: Fix the drag and drop behavior in the calendar to trigger WP hooks after updating the post, #895;
- Fixed: Make the calendar string "Click to add" translatable, #883;
- Fixed: Fix border of calendar cells on Firefox, #901;
- Fixed: Fix date identified on clicking the calendar cell to create new posts, compensating the timezone, #903;
- Fixed: Fix the post type selection for new posts created on the calendar, #904;
- Fixed: Fix the format of the post date on the calendar popup, #905;
- Fixed: Fix the format of the date on the title of the post form on the calendar, #906;
- Fixed: Fix the JS error that the form in the calendar throws if the user can edit only one post type, #907;
- Fixed: Fix the translation for the private taxonomies: "pp_usergroup", "pp_notify_email", "pp_notify_role" and "pp_notify_user", #908;
- Fixed: Fix the click event over the "Click to add" label;

[3.4.0] - 07 Jul, 2021

- Changed: The calendar page has changed and is fully based on React now, #680;
- Changed: Minor improvements to the calendar UI, #680;
- Changed: The "Show 'n' more" option in the calendar cells now works independently and shows the number of visible or hidden posts, #680;
- Changed: The title field in the quick create form in the calendar is now focused automatically after the form is showed, #680;
- Added: Added async data loading to the calendar, with faster navigation and filtering without the need to reload the page, #680;
- Added: New quick create posts form in the calendar with specific fields per post type, #680;
- Added: Added the Post ID to the calendar item popup, #680;
- Added: Added the post status to the calendar item popup, #680;
- Added: Added the post publishing date to the calendar item popup, #680;
- Added: Added support to PublishPress Authors' multiple authors in the calendar, #680;
- Added: Added new fields for setting categories and tags when creating a post in the calendar, #680;
- Added: Added the number of editorial comments of a post in the calendar item popup, #680;
- Added: Type and create new post category or tag right in the quick create posts form in the calendar, #680;
- Added: Added a new button to the calendar navigation controls to refresh the calendar, #680;
- Fixed: Fixed support to PublishPress Authors for assigning guest authors to posts in the calendar, #680;
- Fixed: Fixed the Max visible posts per date setting in the calendar, #680;
- Fixed: Fixed the sorting option in the calendar for correctly sort items by publishing time or post status, #680;
- Fixed: Fixed a bug when dragging and dropping items in the calendar, removing the visual feedback of sorting items in the calendar cell, #680;
- Fixed: Fixed a bug after drag and drop an item in the calendar, showing the correct order of the moved item in the calendar cell, #680;
- Fixed: Fix calendar items ordering when sorted by status or time, #680;
- Fixed: Fix publishing date after publishing a post, using date_floating on custom statuses. Stops auto updating the publishing date with today's date, #741;
- Fixed: Fix adding or editing an user Role, #872;
- Fixed: Fix the scheduled time for cron events of scheduled posts that are moved to different dates in the calendar, #855;
- Fixed: Allow to publish a post with backdate in the calendar or post edit page, #715;

[3.3.3] - 16 Jun, 2021

- Fixed: Fix "Statuses" doesn't appear with a title in important areas, #846;
- Fixed: Fix links to posts with custom privacy are changed to preview links, #852;

[3.3.2] - 27 May, 2021

- Fixed: Fix the icons for statuses, #841;
- Fixed: Fix HTML syntax error in the custom status module for the admin pages;
- Changed: Add new background color for the PublishPress Debug button;

[3.3.1] - 15 Apr, 2021

- Fixed: Fix the editorial comments for non-admins, #827;
- Fixed: Check capability "edit_pp_notif_workflows" before displaying the "Active Notifications" list in the post edit page, not displaying it for those who can't edit notifications;
- Fixed: Fix PHP warning about undefined index "untrashed" after trashing a scheduled post, #831;

[3.3.0] - 18 Mar, 2021

- Added: Add filters to customize the available fields in the notifications "shortcode" help text: publishpress_notifications_shortcode_post_fields, publishpress_notifications_shortcode_actor_fields, publishpress_notifications_shortcode_workflow_fields, publishpress_notifications_shortcode_edcomments_fields, publishpress_notifications_shortcode_receiver_fields;
- Fixed: Fix the value of the notification channel for authors identified by the email, #793;
- Fixed: Fixed the admin menu icon restoring the calendar dashicon, #802;
- Fixed: Fixed PHP Fatal error Uncaught ArgumentCountError: Too few arguments to function MA_Multiple_Authors::filter_workflow_receiver_post_authors, #814;
- Fixed: Fixed bug on WP 5.7 that breaks the toggle button on accordion of metaboxes, #816;
- Fixed: Fixed PHP notice: array to string conversion in debug.php:87, #813;
- Fixed: Fixed The Upgrade to Pro banner and menu item to show only for the users who can install plugins, #599;

[3.2.0] - 10 Feb, 2021

- Added: Add option to rescheduled failed notifications in the notifications log. We only had that option for scheduled notifications, #786;
- Added: Added option to the notification workflow for avoiding notifying the user who triggered the action, #778;
- Added: Add the name of blog in the notification log content column, if in a multisite network;
- Fixed: Fix calendar picking up the wrong day, depending on the time and timezone, #572;
- Fixed: Fix styling for the error messages in the notifications log. The error lines were moved to the top of the screen due the "error" CSS class, #765;
- Fixed: Add sanitization and escape variables in some variables, increasing compatibility with WP VIP and more security, #773;
- Fixed: Fix PHP warning "Creating default object from empty value in publishpress-authors.php:772", correctly assigning the filter "pp_pre_insert_editorial_comment". (Allows PublishPress Revisions integration), #231;
- Fixed: Fixed timezone information in the calendar subscription and .ics file, #784;
- Fixed: Fixed role selection when adding a new user in a multisite, #788;

[3.1.0] - 20 Jan, 2021

- Added: Add shortcodes to the email notifications for the post content, excerpt and post type, #288
- Fixed: Fixed support to PHP 5.6, #772;

[3.0.3] - 11 Jan, 2021

- Fixed: Fix fatal error when "editor" or "author" user roles are missed in the site, #767;
- Fixed: Update the list of capabilities in the PublishPress Capabilities plugin;

[3.0.2] - 07 Jan, 2021

- Fixed: Fix JS warning: $(html) HTML text after last tag is ignored in the custom-status.js file, #754;
- Fixed: Fix JS warning: jQuery.fn.attr(‘selected’) might use property instead of attribute on custom-status.js, #753;
- Fixed: Fix JS warning: jQuery.fn.attr(‘multiple’) might use property instead of attribute on custom-status.js, #753;
- Fixed: Fix JS warning: jQuery.fn.click() event shorthand is deprecated on "publishpress/modules/calendar/lib/calendar.js", #761;
- Fixed: Fix JS warning: jQuery.fn.keydown() event shorthand is deprecated on "publishpress/modules/calendar/lib/calendar.js", #761;
- Fixed: Fix JS warning: jQuery.fn.mouseover() event shorthand is deprecated on "publishpress/modules/calendar/lib/calendar.js", #761;
- Fixed: Fix JS warning: jQuery.fn.mouseout() event shorthand is deprecated on "publishpress/modules/calendar/lib/calendar.js", #761;
- Fixed: Fix JS warning: jQuery.fn.change() event shorthand is deprecated on "publishpress/modules/calendar/lib/calendar.js", #761;
- Fixed: Fix JS warning: jQuery.isArray is deprecated; use Array.isArray on "publishpress/common/libs/select2/js/select2.min.js", #761;
- Fixed: Fix JS warning: jQuery.fn.click() event shorthand is deprecated on "publishpress/modules/content-overview/lib/content-overview.js", #761;
- Fixed: Fix JS warning: jQuery.fn.change() event shorthand is deprecated on "publishpress/modules/content-overview/lib/content-overview.js", #761;
- Fixed: Fix JS warning: jQuery.fn.click() event shorthand is deprecated on "publishpress/modules/notifications-log/assets/js/admin.js", #761;
- Fixed: Fix JS warning: jQuery.fn.click() event shorthand is deprecated on "publishpress/modules/editorial-metadata/lib/editorial-metadata-configure.js", #761;
- Fixed: Fix JS warning: jQuery.fn.keyup() event shorthand is deprecated on "publishpress/modules/custom-status/lib/custom-status-configure.js", #761;
- Fixed: Fix JS warning: jQuery.fn.click() event shorthand is deprecated on "publishpress/modules/custom-status/lib/custom-status-configure.js", #761;
- Fixed: Fix JS warning: jQuery.fn.keydown() event shorthand is deprecated on "publishpress/modules/custom-status/lib/custom-status-configure.js", #761;
- Fixed: Fix JS warning: jQuery.fn.mousedown() event shorthand is deprecated on "publishpress/modules/custom-status/lib/custom-status-configure.js", #761;
- Fixed: Fix JS warning: jQuery.fn.focus() event shorthand is deprecated on "publishpress/common/js/jquery-ui-timepicker-addon.js", #761;
- Fixed: Fix JS warning: jQuery.fn.bind() is deprecated on "publishpress/common/js/jquery-ui-timepicker-addon.js", #761;
- Fixed: Fix JS warning: jQuery.fn.bind() is deprecated on "publishpress/modules/custom-status/lib/custom-status.js", #761;
- Fixed: Fix JS warning: jQuery.fn.click() event shorthand is deprecated on "publishpress/modules/editorial-comments/lib/editorial-comments.js", #761;
- Fixed: Fix JS warning: jQuery.fn.bind() is deprecated on "publishpress/modules/improved-notifications/libs/opentip/downloads/opentip-jquery.js", #761;
- Fixed: Fix JS warning: jQuery.fn.click() event shorthand is deprecated on "publishpress/modules/improved-notifications/assets/js/multiple-select.js", #761;
- Fixed: Fix the post_id passed to the method "get_workflows_related_to_post" that lists the notification workflows related to the post being edited;
- Changed: Removed the user field in the Roles page to avoid break big sites, #750;
- Added: Add capability to control who can view ("pp_view_editorial_metadata") or edit ("pp_edit_editorial_metadata") the editorial metadata, deprecating the capability "pp_editorial_metadata_user_can_edit", #758;

[3.0.1] - 24 Nov, 2020

- Fixed: Can't delete users because the plugin redirects to the Notifications Log page, #737;
- Fixed: Fixed the arguments "old_status" and "new_status" for the shortcode "psppno_post", #713;
- Fixed: Fixed the argument "author_ip" for the shortcode "psppno_edcomment", #713;
- Fixed: Fixed the option to always notify users who edited the content, #742;
- Fixed: Fixed bug in the notification filters that was triggering notifications for unselected post types, #743;
- Fixed: Updated the Italian language files;

[3.0.0] - 16 Nov, 2020

- Added: Added sortable columns to the Content Overview post list, #709;
- Added: Added post type filter to the Content Overview page, #727;
- Added: Added new filter "publishpress_notifications_schedule_delay_in_seconds", #650;
- Added: Added new filter "publishpress_notifications_scheduled_data", #650
- Added: Added to each notification log the source of the receiver (why is the user being notified? What group does he belongs to?), #650
- Added: Added info to the log about the current user when the notification was triggered, #650
- Added: Show the scheduled time of notifications in the log, #650
- Added: Added information about the cron task status of each scheduled notification. If not exists, show a failure message, #650
- Added: Added option to try again failed notifications. Add action "Try again" (Reschedule), and bulk option, #650
- Added: Display in the log the duplicated notifications that were skipped, #650
- Added: Added a settings field to configure the duplicated notification time threshold, in minutes, #650
- Added: Added to the log the icon for the channel used in the notification, #650
- Fixed: Minor fix to the style of the Content Overview post list, #709;
- Fixed: Fixed default notifications adding the "new" and "auto-draft" to the previous status field, and "post" to the Post Type field, #721;
- Fixed: Fixed support for multiple authors in the notifications, #650
- Fixed: Fixed Strict Standards notice: ..\Dependency_Injector define the same property ($container) in the composition of ..\Role, #726;
- Fixed: Fixed Strict Standards notice: ..\Dependency_Injector define the same property ($container) in the composition of ..\Follower, #726;
- Changed: Improved error messages for failed notifications adding more descriptive error messages, #650
- Changed: Refactored the filter "publishpress_notif_run_workflow_meta_query" to "publishpress_notifications_running_workflow_meta_query", #650
- Changed: Refactored the filter publishpress_notif_async_timestamp => publishpress_notifications_scheduled_time_for_notification, #650
- Changed: Refactored the action publishpress_enqueue_notification => publishpress_notifications_scheduled_notification, #650
- Changed: Refactored the action publishpress_cron_notify => publishpress_notifications_send_notification, #650
- Changed: Refactored the filter publishpress_notif_workflow_actions => publishpress_notifications_workflow_events, #650
- Changed: The notification's content is only fixed right before sending the message. Scheduled notifications now have dynamic preview for the content, #650
- Changed: The notification's list of receivers is only fixed right before sending the message. Scheduled notifications have dynamic receivers list, #650
- Changed: The popup now displays only the content of the notification, #650
- Changed: Refactored the Content Overview screen grouping posts by post type instead of by taxonomy, #709;
- Changed: Deprecated the filter "PP_Content_Overview_term_columns" and added a new one "publishpress_content_overview_columns", #709;
- Changed: Deprecated the filter "PP_Content_Overview_term_column_value" and added a new one "publishpress_content_overview_column_value", #709;
- Removed: Removed the action "publishpress_notif_before_run_workflow", #650
- Removed: Removed the filter "publishpress_notif_workflow_receiver_post_authors", #650

[2.4.2] - 05 Nov, 2020

- Fixed: Invalid assets paths for modules on Windows servers, #712;
- Fixed: Fixed error in the calendar: Error: selected user doesn't have enough permissions to be set as the post author, #704;
- Fixed: Fixed conflict with the plugin Visual Composer: pagenow is undefined, #692;
- Fixed: Method get_inner_information was ignoring the passed information fields to the first argument, #654;
- Fixed: Updated the .POT file;

[2.4.1] - 22 Oct Oct, 2020

- Fixed: Fix the assets URL when the plugin is not installed in a standard folder;

[2.4.0] - 22 Oct, 2020

- Fixed: Fix PHP notice on Ajax call after clicking a filter without typing anything in the calendar or content overview, #693;
- Fixed: Fix JS error: No select2/compat/containerCss, #695;
- Fixed: Fix JS error: Failed to load resource: the server responded with a status of 404 () - select2.min.js, #696;
- Fixed: Fix JS error: notifications.js:2 Uncaught TypeError: $(...).pp_select2 is not a function, #696;
- Fixed: Fix PHP error: undefined property $default_pulish_time, #698;
- Fixed: Fixed assets loading when installed as dependency of the Pro plugin, #697;
- Added: Added option to sort calendar items by publishing date, #457;
- Added: Added option to show all posts, or specific number of posts, on a date in the calendar, #675;
- Changed: Updated the Twig library to 1.42.5;

[2.3.0] - 07 Oct, 2020

- Fixed: Fixed performance and memory issue for the calendar and content overview pages adding filters with asynchronous data search, removing the bloat of rendering all the users/tags in fields for each calendar cell, and content overview filters, #674;
- Fixed: Fixed language domain loading and updated the POT file, #670;
- Fixed: Removed a not used JS library: remodal, #517;
- Fixed: Stop loading the Chosen JS library where it is not used, #330;
- Fixed: Fixed support to Cyrillic chars on post status, #439;
- Added: Added support for displaying editorial comments in post status transition notifications, #676;
- Changed: Updated the Select2 JS library to version 4.0.13. The library instance was refactored to pp_select2;
- Changed: Converted the select field for notifications in the post edit page from Chosen to Select2;

[2.2.1] - 13 Aug, 2020

- Fixed: Fixed PHP warning about variable $key being used outside and inside the context;
- Added: Added new filter "publishpress_new_custom_status_args" to customize the post status arguments, #640;
- Fixed: Fixed a PHP Fatal error: Trait Dependency_Injector not found, #652;
- Fixed: Fixed PHP warning: Invalid argument supplied for foreach in TopNotice/Module.php;
- Fixed: Fixed warnings about mixed content when the site uses HTTPS;
- Fixed: Fixed JS error related to jQuery "live" function being deprecated and not found;
- Fixed: Fixed DOM errors in the browser related to multiple elements using the same ID, #660;
- Fixed: Compatibility with WP 5.5;

[2.2.0] - 17 Jun, 2020

- Removed: Fixed conflict with Gutenberg and other plugins keeping draft as the default status, always. Removed the option to set another status as default, #621;
- Removed: Removed the notice asking for reviews after a few weeks of usage, #637;
- Removed: Removed the following statuses from the Status dropdown on posts - on Gutenberg: Pending Review, Privately published, Scheduled. To set them, use the respective Gutenberg's UI;
- Fixed: Protect the WordPress post statuses "Pending" and "Draft", blocking edition of those statuses;
- Fixed: Fix the post status selection and the "Save as" link for Gutenberg for posts in published statuses. For changing the status you have to unpublish the post first;
- Fixed: Fix the "Save as" button when the current status doesn't exist;
- Fixed: Fix compatibility with the Nested Page plugin, #623;
- Fixed: Fix the title of Editorial Meta meta box in the options panel for Gutenberg, #631;
- Fixed: Load languages from the relative path, #626;
- Fixed: Updated the PT-BR translation strings;

[2.1.0] - 28 May, 2020

- Added: Added support to PublishPress Authors (requires at least 3.3.1), #610, #614;
- Added: Added the user email to the notifications log entries and details popup, #602;
- Added: Added option to choose which statuses can show the time in the calendar, #607;
- Added: Added option to select custom publish time in the calendar for all post statuses, #554;
- Added: Added "read only" label to calendar items you can't edit, #608, #615;
- Changed: Removed debug statements from the Custom Status module;
- Fixed: PHP error related to the undefined "current_datetime" function;
- Fixed: Ajax calls are saying the Notification Workflow post type is not registered, #601;
- Fixed: Removed the selection from the calendar to avoid messing up with the drag-and-drop;
- Fixed: Added visual feedback and error messages when errors happens while dragging and dropping items in the calendar, #609;
- Fixed: Fixed compatibility with PHP < 7.3 removing the call to the function "array_key_first";

[2.0.6] - 15 Apr, 2020

- Fixed: Fixed the duplicated posts after publishing using another algorithm due to new reports of a similar issue (#546);

[2.0.5] - 15 Apr, 2020

- Fixed: Fixed duplicated posts after publishing from custom post statuses, a bug introduced by the fix for #546;
- Fixed: Fixes the metadata form in the settings to display the errors after a form submission; (#592)
- Fixed: Updated the build script to remove test files from the built package to avoid false positive on security warnings issued by some hosts;

[2.0.4] - 08 Apr, 2020

- Fixed: Wrong publish date when using custom statuses - Now the publish date is always updated when the post is published;
- Fixed: Fixed the error displayed on Windows servers when the constant DIRECTORY_SEPARATOR is not defined;
- Added: Added link in the menu for upgrading to Pro;

[2.0.3] - 16 Mar, 2020

- Fixed: JS error related to undefined editor when subject or content is empty;
- Fixed: Permalinks for scheduled posts removing the preview param;
- Fixed: Not all custom posts were available for notifications;
- Added: Add top banner for the Pro version;

[2.0.2] - 18 Feb, 2020

- Fixed: Bulk actions in the notifications log page not working;
- Fixed: Notifications sent to wrong roles on some cases. If the post has any user role set to be notified, and the notification workflow doesn't have that option selected, notifications were being sent to the followers - #571;
- Fixed: If PublishPress\Notifications\Shortcodes::handle_psppno_edcomment is called with null string attrs a fatal error occurs;
- Fixed: The Notifications Log text is bigger than the other h3 elements in the post editor metabox;

[2.0.1] - 11 Feb, 2020

- Fixed: Fixed the hidden publish status and notifications for published posts;
- Fixed: Fixed the default order for custom statuses;

[2.0.0] - 06 Feb, 2020

- Fixed: Fixed the default status after sort statuses;
- Fixed: Removed TypeError exception to keep backward compatibility with PHP 5.6;
- Fixed: Fixed custom order for statuses;
- Fixed: Fixed default capabilities for administrators adding pp_view_content_overview and pp_view_calendar;
- Changed: Changed min PHP required version to 5.6.20;
- Changed: Removed the Add-ons menu. They are now standalone plugins;
- Changed: Removed the 20% discount banner;
- Added: Added new capabilities for controlling the Notifications permissions: edit_pp_notif_workflow, read_pp_notif_workflow, etc.

[1.21.2] - 19 Nov, 2019

- Added: New action for writing a debug log message: publishpress_debug_write_log;
- Added: Show receiver's data in the notifications log;
- Fixed: Email errors in the log for the Post SMTP plugin;
- Fixed: Wrong URL protocols in the Notifications\Table\Base class;
- Fixed: Wrong ordering for items in the calendar;
- Fixed: UTC time is always used in the exported ICS file. Now it is exporting time in the current timezone;
- Fixed: The React library is being downgraded (overridden) in WP 5.3;
- Changed: Removed not used methods from the Notifications\Table\Base class: hide_months_dropdown_filter and months_dropdown;

[1.21.1] - 23 Oct, 2019

- Fixed: Updated the Plugin Framework removing a debug statement;
- Changed: Removed the Async column from the Notifications Log and added text to the Status column for async notifications;

[1.21.0] - 22 Oct, 2019

- Feature: Implement a log for notifications, #500;
- Feature: Implement support for pages in the content overview, #503
- Fixed: Fix PHP notices with statuses coming from PressShack, #506;
- Fixed: Sending notifications when there is no status change, #515;
- Fixed: Fix error when there is no follower for a post, #509;
- Fixed: Uncaught TypeError: Cannot read property 'length' of undefined, #499;
- Fixed: Error "Can't read prop of null" in the pp_date.js file;
- Fixed: Custom statuses was not accepting numbers in the name;
- Fixed: Fixed message for async notifications when a post changes the status;
- Fixed: Remove a PHP short tag;
- Removed: Removed Multiple Authors from the add-ons list since the plugin is standalone now;
- Removed: Removed leftovers from the Freemius integration;
- Changed: Renamed the file common/js/admin.js to common/js/admin-menu.js;

[1.20.9] - 11 Sep, 2019

- Fixed: Too many Notifications sent for wc_admin_unsnooze_admin_notes posts created by WooCommerce Admin. Notifications were being sent for non supported post types;
- Fixed: JavaScript breaks when Alledia framework object is not found;
- Fixed: Notification workflows are not saving when ACF is installed;
- Fixed: Wrong help text for [psppno_post] shortcode in the notification workflows;
- Fixed: Missed rewrite rules for the post types: dashboard-note and psppnotif_workflow.
- Fixed: Undefined index: REQUEST_METHOD for $_SERVER.
- Fixed: The logo is missed on the Notification Workflows page.

[1.20.8] - 19 Aug, 2019

- Feature: Support post meta fields in notification body (also requires PublishPress Reminders 1.1.1)
- Feature: Added general debug information and settings to the Site Health page
- Feature: Added a list of installed modules to the Site Health page
- Feature: Added a list of scheduled notifications ot the Site Health page
- Changed: Convert debug panel to read only
- Changed: symfony/polyfill-ctype library updated from 1.11.0 to 1.12.0
- Fixed: If Async Notifications enabled and more than one workflow notification applies to a post, the additional notifications were not sent (corresponding fix in PublishPress Reminders 1.1.1)
- Fixed: PHP warning "date() expects parameter 2 to be int, string given"
- Fixed: Hidden calendar on datepicker fields in Gutenberg due to negative z-index
- Fixed: The column "Last Updated" on content overview had a wrong date format
- Fixed: Missed "use" statement for Dependency Injection on the custom status module

[1.20.7] - 17 Jun, 2019

- Fix fatal error in wp-admin when active alongside WPML or another plugin that uses an obsolete version of the Twig library
- Fix alignment of stars on "please leave us a rating" footer
- Fix redirect behavior on "Already reviewed" selection from "Please leave a review" notice
- Fix PHP warning when a invalid taxonomy is loaded
- Fix an empty space on the statuses screen
- Add publish time field on New Post popup within Calendar
- Add post status field on New Post popup within Calendar
- Fix call-time passed-by-reference occurrences
- Changed calendar to display or hide the publish time according to the post status
- Add new PublishPress icon to the Settings screen
- Update the menu icon
- Fix calendar subscription after a fresh install, don't require saving the settings before it works
- Fix the empty author list in the calendar popup
- For PublishPress Reminders, support status selection in "Before Publication" workflow notifications
- Fix double slash on some assets URLs

[1.20.6] - 16 May, 2019

- Fix fatal error on Edit Notification Workflow screen
- Fix JS error in wp-admin (blocking other plugin JS) for sites running on localhost and Windows servers

[1.20.5] - 15 May, 2019

- Fix blacklisting taxonomies causing WSOD in some envs containing a lot of Terms
- Fix Calendar displaying times on wrong Timezones
- Fix Custom Statuses listing on Settings page not being reordable anymore

[1.20.4] - 03 May, 2019

- Fix JS error in wp-admin (blocking other plugin JS) for sites running on localhost and Windows servers

[1.20.3] - 03 May, 2019

- Fix fatal error in Calendar module: "undefined function mb_strtolower()" on servers that don't have PHP multibyte string extension
- Fix PHP notice in Unpublished Content dashboard metabox

[1.20.2] - 02 May, 2019

- Partially adds support for Gutenberg-Ramp plugin
- Fix PublishPress icon missing on admin sidebar in some envs
- Fix PHP warnings on Calendar module
- Fix issue on posts where MetaData date fields were losing their formats and values after saving
- Fix Calendar date filter going back to 1970 if user edits filter value but doesn't change it
- Fix PHP warnings on Notifications section on Posts form if WP_DEBUG is on
- Add option to blacklist taxonomies-slugs on the taxonomies filter for adding New Notification Workflow

[1.20.1] - 16 Apr, 2019

- Fix PHP warning regarding missing variable reference
- Fix custom statuses not being available for Quick/Bulk post editing
- Fix publish time being displayed on any post regardless of its status on Calendar
- Fix missing default value for the Display Publish Time calendar option
- Fix a performance issue caused by duplicated DB queries calls on Calendar

[1.20.0] - 08 Apr, 2019

- Fix metadata not showing up on Content Overview;
- Fix Content Overview Start Date filter not detecting current date;
- Fix minor inconsistency on Content Overview datepicker filter output format;
- Fix Custom Statuses table squeezing up content on settings page when a lot of custom post types are in use;
- Add "new" to available statuses for notification workflow;
- Add Author field on Calendar item pop up;
- Add option to toggle posts/pages publish time on Calendar;

[1.19.4] - 22 Feb, 2019

- Fixed the list of notification receivers for not excluding emails with numeric chars;
- Fixed email notifications for email addresses added directly into the post, using the "Notify" box;
- Fixed the method to detect when a post is using the block editor, checking additionally the filter "use_block_editor_for_post_type";
- Fixed default state for custom statuses, do not disabling it by default when the block editor is being used;
- Updated Tested Up To version to 5.1 and added kevinb as contributor;
- Fixed compatibility with Revisionary < v1.2.3 and Statuses;

[1.19.3] - 15 Feb, 2019

- Fixed the publishing workflow, removing Published from the select box of post statuses;
- Fixed the list of statuses applying the filter which allows to add-ons like Permissions to filter the list;

[1.19.2] - 12 Feb, 2019

- Fixed bug preventing to unpublish posts;

[1.19.1] - 12 Feb, 2019

- Fixed method that detects the block editor, restoring the publish button in the classic editor;
- Fixed the Save button for custom statuses in the block editor;

[1.19.0] - 11 Feb, 2019

- Fixed PHP Warning about to_notify variable;
- Removed the requirement for the classic editor when the Content Checklist is active;
- Removed the 20% discount subscription form and replaced with a simple banner;
- Added support to custom statuses for posts in Gutenberg;
- Improved text for custom status settings options;

[1.18.5] - 30 Jan, 2019

- Fixed warning message when a string is added as param for the shortcode psppno_workflow;
- Fixed redirection after dismissing the alert regarding reviewing PublishPress, when calendar is not activated;
- Fixed Gutenberg compatibility do not falling off to the classic editor;
- Fixed compatibility with Bedrock, fixing paths for assets and the plugin;

[1.18.4] - 25 Jan, 2019

- Fixed incompatibility with UpStream 1.23.1;

[1.18.3] - 23 Jan, 2019

- Released only to trigger a new update on sites due to corrupted package for 1.18.2;

[1.18.2] - 23 Jan, 2019

- Updated the subscription form for discount coupon, for the new Mailchimp account;
- Updated the settings tabs for the editorial metadata and custom statuses moving Options before Add New;
- Removed warning about Classic Editor as requirement;
- Updated the default value for selected post types for the custom status module, disabling by default if Gutenberg is installed;

[1.18.1] - 14 Jan, 2019

- Fixed minor performance issue in the settings page removing a code from a loop;
- Added an option for selecting a default notification channel;
- Fixed the position of the editorial comments metabox removing from the sidebar and added as high priority;
- Fixed the output of boolean values in the Debug page;

[1.18.0] - 06 Dec, 2018

- Fixed non-escaped attributes and URLs in the whole plugin;
- Fixed style of editorial comments for fitting the sidebar in Gutenberg;
- Added a new filter for get_post_types methods: "publishpress_supported_module_post_types";
- Fixed a missed ";" from the admin interface;
- Changed the action publishpress is hooked to. From "plugins_loaded" to "init";
- Fixed double "use" statement for Dependence_Injector in the PublishPress\Notifications\Workflow\Step\Channel\Base class;
- Added the option to add non-users emails (and name) in the notify box for posts;
- Improved the title and text for the notify box;
- Added a new shortcode "psppno_receiver" for the notification content, which supports name and email params;
- Added a list of active workflows to the notify box;
- Added a slightly darker background color to the month name row in the calendar;
- Added a check for Gutenberg, disabling the block editor for post types where custom statuses and the content-checklist (add-on) are enabled;
- Added a check for Classic Editor in WordPress 5.0, showing a notice advising to install and activate it;
- Fixed broken HTML syntax in some settings panels;
- Updated the "Tested up" version to 5.0

[1.17.0] - 08 Nov, 2018

- Fixed permalink for posts in multisite URLs;
- Fixed the position for the editorial comments metabox for Gutenberg;
- Fixed dashboard widget hiding Published and Private Posts counts, since the box relates to unpublished content;
- Fixed method that changes user's data to make a verification before change anything;
- Added a new capability, "pp_set_notification_channel", for controlling who can select a different notification channel in the profile page;
- Added a new option to disable PublishPress' branding for who has at least one activated license key;
- Added a debug module with basic information for debugging, and log viewer;

[1.16.4] - 03 Oct, 2018

- Fixed: Fixed properties (color and icon) for customizing core statuses;
- Fixed: Fixed fatal error in shortcode processing when the param is sent as string - for notifications' body;

[1.16.3] - 19 Sep, 2018

- Fixed: Fixed compatibility with UpStream fixing a PHP warning displayed in the UpStream settings page, related to PublishPress getting confuse with both using Allex Framework for dealing with add-ons. You need to update UpStream as well;
- Fixed: Fixed some queries executed when they were not useful. The queries are related to the options for icon and color of custom statuses;
- Fixed: Fixed license key activation and upgrade form when installed alongside UpStream - requires to update UpStream as well;
- Fixed: Fixed icon of Multiple Authors add-on in the add-ons page;
- Fixed: Fixed wrong URL for assets on Windows machines;

[1.16.2] - 28 Aug, 2018

- Fixed: Fixed a bug in the URL sent in the validation of license keys for add-ons;

[1.16.1] - 27 Aug, 2018

- Fixed: Fixed a bug in the validation of license keys for add-ons;

[1.16.0] - 27 Aug, 2018

- Changed: Refactored the add-ons page centralizing the license key management;
- Changed: Rebranded with the new logo;
- Changed: Added sidebar with form offering discount of 20% in a plan when the user doesn't have any plugin installed;

[1.15.0] - 19 Jul, 2018

- Added: Added filter for taxonomies and improved custom post type support in notification workflows;

[1.14.1] - 12 Jul, 2018

- Fixed: Fixed a PHP warning when we receive an array as receiver for notifications;
- Fixed: Fixed notifications when roles are selected as receivers;

[1.14.0] - 12 Jun, 2018

- Fixed: Fixed the menu structure when the calendar and notification workflow modules are deactivated;
- Fixed: Fixed saving post when you remove all the notification receivers. Before the fix it wasn't removing the receivers if you remove all, only if you left at least one;
- Changed: Increased the timeout for duplicated notifications to 10 minutes and added filter to customize the timeout;
- Changed: Added chosen JS library for add-ons;
- Changed: Changed the filter publishpress_notif_async_timestamp to send the post id as the 3rd param;
- Added: Added a new action (action_enqueue_notification) after enqueuing  notifications;

[1.13.0] - 16 May, 2018

- Added: Added new shortcodes for displaying author's data in the notification content: author_display_name, author_email, author_login;
- Changed: Improved some help texts;
- Changed: Updated language files;

[1.12.1] - 09 May, 2018

- Fixed: Fixed PHP strict warning about trait and a redefined property;
- Fixed: Fixed duplicated notifications on some scenarios;
- Changed: Increased the priority of the hook user_register to have the multiple roles loaded and available for add-ons;

[1.12.0] - 18 Apr, 2018

- Added: Added option to display the edit link of the post in a notification's message;
- Fixed: Fixed the warning about migration of legacy data in fresh installs;
- Fixed: Fixed the post link on notifications;
- Changed: Improved async notifications module for allowing extending workflows with add-ons (specially Reminders);

[1.11.4] - 05 Apr, 2018

- Fixed: Fixed error 500 while saving users;
- Fixed: Fixed Preview and Publish button for posts;
- Fixed: Fixed compatibility with Forum Role field of bbPress;
- Fixed: Fixed start date for the calendar to display current week if a custom date is not set;

[1.11.3] - 20 Mar, 2018

- Fixed: Fixed roles editing form, denying to remove current user from the administrator role;
- Fixed: Fixed error in the dashboard widget "My Content Notifications"
- Fixed: Fixed default permissions for administrators for being able to see the Roles menu item;
- Changed: Removed option to delete the administrator role;
- Changed: Improved some text;

[1.11.2] - 19 Mar, 2018

- Fixed: Fixed migration of following users and user groups in notifications;

[1.11.1] - 13 Mar, 2018

- Fixed: Fixed backward compatibility with legacy PublishPress Permissions;

[1.11.0] - 13 Mar, 2018

- Fixed: Fixed workflows and notifications for new posts;
- Fixed: Fixed issue when installed from composer, related to the vendor dir not being found;
- Fixed: Fixed style for icons in the buttons of the popup for iCal subscriptions in the calendar;
- Fixed: Fixed hidden submenus adding basic capabilities after installing for the first time;
- Changed: Changed the workflow form, adding all fields as required;
- Changed: Removed support for User Groups - they are deprecated in favor of Roles, in PublishPress;
- Changed: Moved Notifications metabox to the sidebar with high priority for posts;
- Changed: Cleaned up UI removing logo from the title in the admin;
- Added: Added new "From" status: New. Allowing to create workflows specifically from new posts;
- Added: Added new submenu for managing Roles;
- Added: Added new receiver option for notification workflows to reach Roles;
- Added: Added support for multiple Roles per user. A new field is displayed in the user's profile allowing to select multiple roles;

[1.10.0] - 21 Feb Feb, 2018

- Added: Added async notifications using WP Cron;
- Added: Added form to configure filters for the calendar subscription link;
- Added: Added option to the notifications to subscribe or not the author and current user by default;
- Added: Allow to configure custom slugs for statuses, making easy to fix issues with titles and UTF-8 chars;
- Fixed: Fixed the issue where posts with "Pending" status where hidden;
- Fixed: Fixed the subscription link for the calendar and add download link;
- Fixed: Fixed issue where custom post types where not recognized by some modules;
- Fixed: Fixed the publishing date for unpublished posts. Now it is not changed when saved so unscheduled posts keep ready for publishing immediately;

[1.9.8] - 06 Feb, 2018

- Fixed: Fixed icon styling issues in non-default themes;
- Fixed: Fixed missed submenus after activating the plugin for the first time;
- Changed: Removed Date column from the notification workflows list;

[1.9.7] - 01 Feb, 2018

- Fixed: Fixed broken menu;

[1.9.6] - 01 Feb, 2018

- Fixed: Fixed PublishPress menu ordering;
- Fixed: Fixed last release, which changed version but didn't include all the changes;

[1.9.5] - 31 Jan, 2018

- Fixed: Fixed fatal error when saving posts and triggering notification;
- Changed: Removed Freemius integration and contact form;

[1.9.4] - 25 Jan, 2018

- Fixed: Fixed the filters on the notification workflow form, allowing to creating new notifications again;
- Fixed: Fixed PHP warning when user does not have permission to see the menu;
- Fixed: Fixed a typo removing a PHP warning;
- Fixed: Fixed the status description on the date column for posts;
- Fixed: Fixed notifications for users who selected "Notify me" for the content;
- Fixed: Fixed string "Draft" and "Pending" after the post title in the post lists;
- Added: Added a new optional receiver to the notification workflow form, "Users who selected Notify Me for the content";

[1.9.3] - 18 Jan, 2018

- Fixed: Fixed file permissions;
- Fixed: Fixed blank status dropdown on quick and build edit;
- Fixed: Fixed modified date for scheduled posts;
- Fixed: Fixed suppressed warning about undefined offset;
- Changed: Rebranded for PublishPress;
- Changed: Added footer to the addons page;

[1.9.2] - 14 Dec, 2017

- Fixed: Fixed view link for published posts in the list of posts. It was displaying "preview" instead of "view";
- Fixed: Fixed CSS loader to only load it in the admin pages;

[1.9.1] - 10 Nov, 2017

- Fixed: Fixed compatibility with the plugin Custom Permalink;
- Fixed: Fixed PHP fatal error after activate plugin on fresh installs;
- Changed: Updated required PHP version to 5.4;

[1.9.0] - 09 Nov, 2017

- Changed: Improved the form of notification workflows adding consistency between all the filters and fields;
- Changed: Added search box for each list field on the notification workflow form;
- Changed: Updated the Post Type and Category filters for notification workflows. If not selected, they disable the filter and all the content would be picked;

[1.8.1] - 01 Nov, 2017

- Changed: Updated the label for the setting field “Allow ‘following’ on these post types” to make it clear;
- Changed: Updated the placeholder for the “Email from” field to display the default values instead of a static label;
- Changed: Added text domain into the plugin header;
- Fixed: Fixed missed “published” status on filters in notifications if “custom status” feature is disabled;
- Fixed: Fixed PHP warning when some features are disabled;

[1.8.0] - 26 Oct, 2017

- Fixed: Fixed duplicated "Scheduled" status in the dashboard widget;
- Changed: Improved style for the dashboard widget;
- Changed: Updated form of notification workflow moving some fields to a new column;
- Changed: Updated the POT file;
- Added: Added a setting field to configure the "email from" information for email notifications;

[1.7.5] - 11 Oct, 2017

- Fixed: Fixed the empty pubDate for posts with custom status;
- Fixed: Fixed the title of the Notification Workflows module in the settings panel;
- Fixed: Fixed the verification of the notifications module statue before create content type and display the menu;
- Fixed: Fixed the drag and drop of metadata items in the settings tab;
- Changed: Removed quick edit option from the metadata tab;
- Changed: Updated the Twig library to v1.35.0;
- Changed: Adjusted filters on notification workflows to only consider selected items. Before that, if no post type, category, or statuses where selected, all the items would be considered selected.

[1.7.4] - 13 Sep, 2017

- Fixed: Fixed the button Submit for Review which was hidden for contributors, or users who can't publish;
- Fixed: Fixed empty permalink when publishing posts, or saving new posts with empty title;

[1.7.3] - 04 Sep, 2017

- Added: Added shortcode for displaying the date and time set for the content on the notifications;
- Added: Added shortcode for displaying the old and new statuses on the notifications;
- Fixed: Fixed the save event filters when no event is selected;
- Fixed: Fixed the separator param on shortcodes for notifications;
- Fixed: Fixed notifications avoiding messages for auto-drafts;
- Changed: Improved the column titles on the workflows list to match the labels from the form;
- Changed: Replaced the string 'Follow' to 'Notify' on the UI;
- Changed: Removed the filter for months on the Workflows list;

[1.7.2] - 31 Aug, 2017

- Fixed: Fixed default notification workflows to avoid notifications on every post save, but only when the content transition to Published;
- Fixed: Fixed the message after delete workflows;
- Fixed: Fixed the notifications workflows to ignore autosaves;
- Fixed: Fixed post type options in the calendar settings, selecting the Post post type by default, if nothing is selected - Displaying a warning;

[1.7.1] - 30 Aug, 2017

- Fixed: Fixed bug which blocked pages for guests after installing;
- Fixed: Fixed error 500 on saving the calendar settings;
- Fixed: Fixed bug on saving modules settings where changes were not being saved;
- Fixed: Fixed default color and icon for existent statuses;
- Changed: Improved default colors for custom statuses;

[1.7.0] - 29 Aug, 2017

- Added: Added notification workflows for more control over notifications with custom text and more;
- Added: Added special fields on the user profile to configure where to receive the new notifications;
- Added: Added the option for users to mute notifications from specific workflows;
- Added: Allow to customize color and icon for all post statuses in the calendar;
- Added: Allow to send emails in HTML format for notifications;
- Fixed: Fixed spacing for content overview;
- Fixed: Fixed compatibility with Capsman Enhanced and Press Permit, refactoring the action pp_admin_menu to publishpress_admin_menu;
- Fixed: Fixed the creation and drag-and-drop of content on the calendar to set the scheduled date. Moving the content, if published or scheduled, now updates the status (and icon) according to the current time;
- Fixed: Fixed the issue with notifications disappearing from the admin after a few seconds;
- Fixed: Fixed empty filter for tags in the calendar if there are no tags;
- Changed: Filter content in the calendar page on the change event;
- Changed: Filter content in content overview page on the change event;
- Changed: Allow to drag-and-drop published content on the calendar;
- Changed: Improved responsive support for calendar page;
- Changed: Improved responsive support for content overview page;
- Changed: Improved users and user groups layout;
- Changed: Changed the label of the setting for the notification module adjusting to the new notification workflows;
- Changed: Removed field "Always notify the blog admin" from the notification settings. This is now set on the notification workflow;

[1.6.1] - 27 Jul, 2017

- Changed: Updated the add-ons page for supporting the new add-on: Multiple Authors
- Fixed: Fixed the icon size for calendar

[1.6.0] - 12 Jul, 2017

- Fixed: Fixed an error message after install after redirect to the calendar;
- Changed: Moved the Add-ons tab to a menu item;
- Changed: Updated internal method to show post type settings fields for add-ons;

[1.5.1] - 28 Jun, 2017

- Fixed: Fixes required capability to see the calendar

[1.5.0] - 27 Jun, 2017

- Changed: Moved Calendar to the PublishPress menu
- Changed: Moved Content Overview to the PublishPress menu
- Changed: Changed the Calendar page as the main page
- Changed: Renamed story-budget module to content-overview
- Changed: Removed the Featured module and tab in settings
- Changed: Moved the General tab as the first in the settings page
- Changed: Changed calendar settings enabling all post type by default
- Changed: Improved styling in the calendar view
- Fixed: Fixed link and filename of the .ics file downloaded from the calendar\
- Fixed: Fixed the "Click to create" on Chrome label in Firefox
- Fixed: Fixed last column popup display in Internet Explorer 11

[1.4.3] - 21 Jun, 2017

- Added:Added filter to control if users can or not edit metadata
- Added: Added new add-on PublishPress Permissions to the add-ons tab
- Fixed: Fixed the pt-BR translations
- Fixed: Fixed datetime format in metadata fields for non-english languages
- Fixed: Fixed the metadata editing on the calendar
- Fixed: Fixed PHP warning after save options
- Changed: Updated "Tested up" to 4.8
- Changed: Improved the output for unset medatada

[1.4.2] - 06 Jun, 2017

- Fixed: Fixed French translation. Thanks [Thierry Pasquier](https://github.com/jeau)

[1.4.1] - 29 May, 2017

- Changed: Added the Slack add-on as available in the Add-on tab.

[1.4.0] - 25 May, 2017

- Fixed: Restores the icon in the freemius sdk
- Fixed: Fixed minor JavaScript warning in the Editorial Comment module
- Changed: Refactor notification module allowing add-ons to use any other message system
- Changed: Allow to customize priority for the action triggered on the status change

[1.3.3] - 22 May, 2017

- Fixed: Fixed the context in translations for date picker in the Story Budget module
- Fixed: Fixed the "Hello Dolly" message in the Freemius opt-in dialog
- Fixed: Fixed date fields in the Editorial Metadata box
- Changed: Added partial pt-PT translation
- Changed: Increased the minimum WordPress version to 4.6

[1.3.2] - 04 May, 2017

- Fixed: Fixed the list of Add-ons, to list correctly the installation and activation status
- Fixed: Fixed selector for the active admin menu
- Fixed: Fixed readme.txt file with better title, tags and description

[1.3.1] - 02 May, 2017

- Fixed: Fixed the calendar quick-edit popup
- Fixed: Fixed minor issue in the code style
- Added: Added WooCommerce add-on to the add-ons tab
- Changed: Removed message after update settings
- Changed: Updated name of add-ons in the settings tab
- Changed: Improved content from the readme file

[1.3.0] - 27 Apr, 2017

- Fixed: Fixed popup for items created as quick post
- Fixed: Fixed typos
- Added: Added integration with Freemius for feedback and contact form
- Added: Added filters and actions allowing to extend modules with add-ons
- Added: Added default modal window scripts and styles for add-ons
- Added: Added new tab to showcase the add-ons
- Changed: Changed code standards for WordPress
- Changed: Cleaned up the metadata removing default registers for "Needs photo" and "Word Count"
- Changed: Improved twig files removing hardcoded strings

[1.2.2] - 16 Mar, 2017

- Changed: Add icon to the print link on the Overview screen
- Changed: Update the language files

[1.2.1] - 15 Mar, 2017

- Changed: Better label for Comments metadata box
- Changed: Set Calendar Subscriptions enabled by default
- Changed: Set Always Show Dropdown enabled by default on custom statuses
- Changed: Add small notes to some tabs
- Changed: Update POT file
- Fixed: Fixed empty messages while deleting custom statuses, metadata and user groups
- Fixed: Fixed link to redirect after the EditFlow migration

[1.2.0] - 15 Mar, 2017

- Changed: Better style for the calendar
- Changed: Click anywhere on the calendar cell to create content, instead show a button
- Changed: Extends the drag-and-drop feature to custom statuses
- Changed: Minor improvements on the code
- Added: Allow to create multiple types of content on the calendar

[1.1.0] - 07 Mar, 2017

- Changed: Complete rebranding to PublishPress and PressShack
- Changed: Clean up on the UI
- Changed: Move sub-pages to a common settings page
- Changed: Clean up on the text
- Changed: Refactor Story Budget to Content Overview
- Changed: Move PublishPress menu to after Comments menu

[1.0.5] - 16 Feb, 2017

- Changed: Update version to trigger a new release fixing SVN issues on the last release

[1.0.4] - 15 Feb, 2017

- Changed: Cleanup on the code
- Changed: Cleanup on the admin interface
- Changed: Add PressShack logo to the top of admin pages
- Changed: Set minimum WordPress version to 4.4
- Changed: Set minimum PHP version to 5.4
- Changed: Move the Settings menu item to the main PublishPress menu
- Changed: Minor improvement to the icons
- Fixed: Update language strings and some links
- Fixed: Fix bug on editorial comments box in the post form

[1.0.3] - 01 Feb, 2017

- Changed: Update language .mo files

[1.0.2] - 01 Feb, 2017

- Changed: Update plugin's description

[1.0.1] - 01 Feb, 2017

- Changed: Update plugin's description
- Changed: Update language strings

[1.0.0] - 01 Feb, 2017

- Changed: Renamed to PublishPress
- Changed: Rebrand for Joomlashack
