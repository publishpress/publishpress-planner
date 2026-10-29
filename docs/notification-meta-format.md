# Formatting metadata in notifications

The `psppno_post` shortcode accepts `type` and `format` for metadata values:

```text
[psppno_post meta:_pp_editorial_meta_user_jr-editor type="user" format="display_name"]
[psppno_post meta:assigned_editor type="user" format="user.user_email"]
[psppno_post meta:deadline type="date" format="Y-m-d"]
[psppno_post meta:priority type="filter" format="priority_label"]
```

User formatting supports the existing public user fields: `id`, `login`, `url`, `name`, `display_name`, `first_name`, `last_name`, and `email` (`user_email` is also accepted). A leading `user.` is optional. Missing users produce empty output. Dates accept Unix timestamps or date strings; omitted formats use the site's date format. Invalid dates produce empty output.

Custom formatting uses a dedicated filter namespace rather than invoking arbitrary WordPress filters:

```php
add_filter('publishpress_notif_meta_format_priority_label', function ($value, $post, $meta_key, $attributes) {
    return $value === '1' ? 'High priority' : 'Normal priority';
}, 10, 4);
```

Existing `meta-date`, `meta-user`, and relationship shortcode syntax remains supported. Plain metadata values, including numeric zero, are returned unchanged when no type is provided.
