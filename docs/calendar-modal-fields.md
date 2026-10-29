# Custom fields in the existing-post Calendar popup

Use `publishpress_calendar_modal_fields` to add fields to the popup opened by clicking an existing Calendar item in Planner Pro. This is separate from `publishpress_calendar_get_post_type_fields`, which configures the Add New Post popup.

```php
add_filter('publishpress_calendar_modal_fields', function ($fields, $post) {
    if ($post->post_type !== 'post') {
        return $fields;
    }
    $fields['campaign'] = [
        'label' => __('Campaign', 'my-plugin'),
        'type' => 'select',
        'meta_key' => 'campaign',
        'options' => ['' => 'None', 'spring' => 'Spring', 'summer' => 'Summer'],
    ];
    $fields['reference'] = [
        'label' => __('Reference', 'my-plugin'),
        'value' => 'Reference #' . $post->ID,
    ];
    return $fields;
}, 10, 2);
```

Supported types are `text`, `textarea`, `number`, `checkbox`, and single `select`. Labels, options, and values are escaped when rendered. Textareas save plain text with line breaks; select values must match their configured options, and numbers must be numeric or empty.

Fields with a `meta_key` read their current value from post meta and are saved with the popup's Save Changes action after the post update. Users must be allowed to edit both the post and that meta key. Existing WordPress meta sanitization also applies. Set `readonly` to `true` to display a meta value without allowing edits. Fields without `meta_key` display their `value` and are never saved. Define only meta keys you intend to expose; the filter receives the post so access and post-type restrictions can be applied there.
