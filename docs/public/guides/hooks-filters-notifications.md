---
title: "Hooks and Filters for Notifications"
order: 19
doc_category: publishpress
---

# Hooks and Filters for Notifications

Using WordPress’ hooks and filters, you can extend [PublishPress Planner](https://publishpress.com/links/planner) notifications in the following ways:

- `pp_edit_post_subscriptions_cap` (filter) – Change the capability required to modify who’s subscribed to a post.
- `pp_notification_auto_subscribe_current_user` (filter) – Whether or not to auto-subscribe a user editing the post or leaving an editorial comment. The former action passes ‘subscription_action’ and the latter ‘comment’. Default is to auto-subscribe.
- `pp_notification_status_change` (filter) – Kill notifications for specific post status changes (or all).
- `pp_notification_ignored_statuses` (filter) – Another way to kill notifications for specific post status changes.
- `pp_notification_editorial_comment` (filter) – Kill notifications for editorial comments.
- `pp_notification_recipients` (filter) – Modify who receives a notification. Default is all subscribers.
