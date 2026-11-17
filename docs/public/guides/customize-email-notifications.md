---
title: "Customize Content in Planner Notifications"
order: 7
doc_category: publishpress
section: Notifications
---

# Customize Content in Planner Notifications

Table of Contents

- [Shortcodes for Content](#h-shortcodes-for-content)
- [Shortcodes for the Author](#h-shortcodes-for-the-author)
- [Shortcodes for Editorial Comments](#h-shortcodes-for-editorial-comments)
- [Shortcodes for post metadata](#h-shortcodes-for-post-metadata)
- [Shortcodes for the Notification](#h-shortcodes-for-the-notification)
- [Shortcodes for the Notification Receiver](#h-shortcodes-for-the-notification-receiver)
- [How to Use Multiple Shortcode Fields Together](#h-how-to-use-multiple-shortcode-fields-together)

The [PublishPress Planner](https://publishpress.com/links/planner) plugin allows you to customize the notifications that are sent out.

The text of the notifications is controlled from the “What to say?” area of each notification workflow.

[PublishPress Planner](https://publishpress.com/links/planner) shortcodes can be dropped directly into the Subject or Body. The image below has an example of notification shortcodes in action:

![Planner notifications](https://docs-media.publishpress.com/publishpress-planner/notification-example-ff1420d917.webp)

## Shortcodes for Content [#](#h-shortcodes-for-content)

These shortcodes will show details from the content that triggers the notification.

This is the most basic shortcode, which will show the title of the post:

```
[psppno_post]
```

You can add fields to this shortcode. For example, you can add “title”. This will also show the title of the post:

```
[psppno_post title]
```

Here are more fields you can use inside your content notifications:

- `[psppno_post content]` shows the content of the post. This option may be messy because of the code produced by some page-builders.
- `[psppno_post excerpt]` shows the excerpt of the post.
- `[psppno_post post_type]` shows the post type of the post.
- `[psppno_post id]` shows the ID number of the post.
- `[psppno_post permalink]` shows the URL of the post.
- `[psppno_post date]` shows the publishing date of the post. If the post is scheduled, this will show the future publishing date.
- `[psppno_post time]` shows the publishing time of the post.
- `[psppno_post old_status]` shows the previous status, before the post was saved.
- `[psppno_post new_status]` shows the updated status, after the post was saved.
- `[psppno_post edit_link]` shows a link to edit the post in the WordPress admin.
- `[psppno_post author_display_name]` shows the Display Name of the post’s author.
- `[psppno_post author_email]` shows the author’s email.
- `[psppno_post author_login]` shows the author’s login name.
- `[psppno_revision permalink]` shows the URL of the revision.
- `[psppno_revision edit_link]` shows a link to edit the revision in the WordPress admin.

## Shortcodes for the Author [#](#h-shortcodes-for-the-author)

These shortcodes will display information about the author of the content that triggers the notification.

This is the most basic shortcode, which will show the Display Name of the author:

```
[psppno_actor]
```

You can add fields to this shortcode. For example, you can add “display_name”. This will also show the Display Name of the author:

```
[psppno_actor display_name]
```

Here are more author fields you can use inside your content notifications:

- `[psppno_actor email]` shows the author’s email address.
- `[psppno_actor id]` shows the author’s ID number.
- `[psppno_actor login]` shows the author’s login name.
- `[psppno_actor url]` shows the website URL.

## Shortcodes for Editorial Comments [#](#h-shortcodes-for-editorial-comments)

These shortcodes will show information about the [editorial comment](https://publishpress.com/knowledge-base/editorial-comments/) that triggers the notification.

This is the most basic shortcode, which will show the content of the comment:

```
[psppno_edcomment]
```

You can add fields to this shortcode. For example, you can add “content”. This will also show the content of the comment:

```
[psppno_edcomment content]
```

Here are more editorial comment fields you can use inside your content notifications:

- `[psppno_edcomment author]` shows of the Display Name user who wrote the comment.
- `[psppno_edcomment author_email]` shows the email of the user who wrote the comment.
- `[psppno_edcomment author_url]` shows the URL of the user who wrote the comment.
- `[psppno_edcomment ip]` shows of the IP address of the user who wrote the comment.
- `[psppno_edcomment date]` shows of the date and time of the comment.
- `[psppno_edcomment id]` shows the ID number of the comment.

## Shortcodes for post metadata [#](#h-shortcodes-for-post-metadata)

This feature requires the Pro version of the PublishPress Planner plugin.

The Pro version of the PublishPress Planner plugin supports post meta in notifications.

You can find the metadata for these shortcodes in the **_postmeta** table in your database.

The PublishPress Planner plugin has it’s [own metadata fields](https://publishpress.com/knowledge-base/editorial-metadata/). This is the syntax you can use for a text field called “Penguins”.

```
[psppno_post meta:_pp_editorial_meta_text_penguins]
```

This is the syntax you can use for a date field called “Penguin Birthday”:

```
[psppno_post meta:_pp_editorial_meta_date_penguin_birthday]
```

You can use any kind of post metadata for notifications, including data from third party plugins.

For example, if you’re really focused on SEO, you can include the Yoast SEO metadata for each post in your notifications. This is screenshot from the _postmeta table, showing some of the Yoast SEO data for a single post:

![Planner notifications with metadata](https://docs-media.publishpress.com/publishpress-planner/yoast-keywords-metadata-4749bf1bd5.webp)

Using the information in the image above, here’s how you can include the Yoast SEO title in each notification:

```
[psppno_post meta:_yoast_wpseo_title]
```

Here’s how you can include the Yoast SEO Content Score in each notification:

```
[psppno_post meta:_yoast_wpseo_content_score]
```

Here’s how you can include the Yoast SEO meta description in each notification:

```
[psppno_post meta:_yoast_wpseo_metadesc]
```

Let me show you an example of how this works in practice. We created a notification that looks like this:

![Planner notifications](https://docs-media.publishpress.com/publishpress-planner/notification-with-yoast-f344ea0448.webp)

Now every time a post is published on our site, we get a notification that looks like this:

![](https://docs-media.publishpress.com/publishpress-planner/notification-email-metadata-9dc418649d.webp)

## Shortcodes for the Notification [#](#h-shortcodes-for-the-notification)

These shortcodes will show information about the notification itself.

This is the most basic shortcode, which will show the name of the workflow:

```
[psppno_workflow]
```

You can add fields to this shortcode. For example, you can add “title”. This will also show the name of the workflow:

```
[psppno_workflow title]
```

The other option is to show the ID number of the workflow:

```
[psppno_workflow id]
```

## Shortcodes for the Notification Receiver [#](#h-shortcodes-for-the-notification-receiver)

These shortcodes will show information about the user receiving the notification.

This is the most basic shortcode, which will show the name of the person receiving the notification:

```
[psppno_receiver]
```

You can add fields to this shortcode. For example, you can add “name”. This will also show the name of the receiver:

```
[psppno_receiver name]
```

The other option is to show the email address of the receiver:

```
[psppno_receiver email]
```

## How to Use Multiple Shortcode Fields Together [#](#h-how-to-use-multiple-shortcode-fields-together)

To display multiple fields in a shortcode, simply list the fields with a space to separate them:

```
[shortcode field1 field2 field3]
```
