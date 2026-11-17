---
title: "How to Use Reminder Notifications (Pro)"
order: 23
doc_category: publishpress
section: Notifications
---

# How to Use Reminder Notifications (Pro)

Table of Contents

- [Use-cases for the Reminders feature](#h-use-cases-for-the-reminders-feature)
- [How to use Reminders](#h-how-to-use-reminders)
- [Reminder settings](#h-reminder-settings)

This feature requires the pro version of the [PublishPress Planner](https://publishpress.com/links/planner) plugin.

[PublishPress Planner](https://publishpress.com/links/planner) Pro has a feature that automatically sends [notifications](https://publishpress.com/docs/notifications/) before or after content is published.

This Reminders feature uses the WordPress publication date to trigger reminders for published content. This feature uses the scheduled date to trigger reminders for unpublished content.

## Use-cases for the Reminders feature [#](#h-use-cases-for-the-reminders-feature)

Here are some of the ways that people use the Reminders feature in Planner Pro:

- **In the weeks before publication**: Send emails to authors, explaining your editorial process, giving them deadlines and instructions.
- **2 days before publication**: Send a reminder to editors, asking them to proof-read the post for publication.
- **2 or 3 days after publication**: Send a reminder to various team members, asking them to promote the post on social media.
- **6 months after publication**: Send a reminder to the authors, asking them to check the post again and make sure the content is still valid.

## How to use Reminders [#](#h-how-to-use-reminders)

The Reminders feature does require you to have PublishPress Planner Pro installed.

- Download and install the Reminders add-on.
- In your site, go to “Planner” then “Notifications”.
- Create a new notification workflow, or edit an existing workflow.
- Under “When to notify”, look for the “Before the content is published” or “After the content is published”. Choose one of these options. In the example below, a notification will be sent exactly 1 day before a post is scheduled to be published.

![](https://docs-media.publishpress.com/publishpress-planner/reminder-options-1c90f0aba8.webp)

The “Amount” option allows you to choose any number from 1 to 60:

![](https://docs-media.publishpress.com/publishpress-planner/amount-timer-1a80d5ec9c.webp)

The “Unit of timer” option allows you to choose “Hours”, “Days” or “Weeks”:

![](https://docs-media.publishpress.com/publishpress-planner/unit-timer-3529f9f478.webp)

## Reminder settings [#](#h-reminder-settings)

Reminders uses the WordPress core to automatically check for notifications to send. You do not need to set up any cron jobs.

- Go to “Planner” then “Settings”.
- Click the “Reminders” tab.

Here you can see how often WordPress will check for notifications. You can also see when the next check is due to run.

![](https://docs-media.publishpress.com/publishpress-planner/reminder-settings-fd83400113.webp)

You can choose how often WordPress checks for notifications to send.

- If you have a very busy site and want to send notifications just hours before or after a scheduled date, choose a high frequency such as “Every 10 minutes” or “Every 30 minutes”.
- If you have a quiet site and want to send notifications a few days before or after content, choose less frequent checks.

![](https://docs-media.publishpress.com/publishpress-planner/reminder-settings-options-e2e814d4a8.webp)
