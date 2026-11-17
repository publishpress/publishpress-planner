---
title: "Custom Fields in the Content Calendar Popup"
order: 6
doc_category: publishpress
section: Advanced
---

# Custom Fields in the Content Calendar Popup

Table of Contents

- [Adding a custom field to the calendar popup](#h-adding-a-custom-field-to-the-calendar-popup)
- [Field types](#h-field-types)[Authors](#h-authors)[Checkbox](#h-checkbox)[Date Time](#datetime)[Location](#datetime)[Number](#datetime)[Post Status](#datetime)[Taxonomy](#datetime)[Select](#datetime)[Html](#datetime)[Text](#datetime)[User](#datetime)[Time](#datetime)
- [Setting the values available in the new Select field](#h-setting-the-values-available-in-the-new-select-field)
- [Saving the select data when creating the post](#h-saving-the-select-data-when-creating-the-post)

For adding new fields to the Calendar “Add content” popup you can use a set of hooks available that creates an API for displaying, filtering and saving custom fields for the new posts created on the calendar.

## Adding a custom field to the calendar popup [#](#h-adding-a-custom-field-to-the-calendar-popup)

For adding a custom field you have to filter the default list of fields for the selected post type using the following filter (in the example we add a new select field):

```
add_filter('publishpress_calendar_get_post_type_fields', 'addCustomFieldsToCalendar', 10, 2);
function addCustomFieldsToCalendar($fields, $postType) {
    if ($postType !== 'post') {
        return $fields;
    }

    $fields['myfield'] = [
        'label' => __('My field', 'publishpress'),
        'value' => null,
        'type' => 'select',
        'ajaxAction' => 'fetchFieldValues',
        'multiple' => false,
    ];

    return $fields;
}
```

The new field will appear at the end of the popup, by default:

![Calendar Popup Custom Field Select](https://docs-media.publishpress.com/publishpress-planner/calendar-popup-custom-field-select-bbae0537ea.webp)

*Calendar Popup Custom Field Select*

## Field types [#](#h-field-types)

You can create multiple custom fields using any of the following field types:

### Authors [#](#h-authors)

```
$fields['myauthorfield'] = [
        'label' => __('My author field', 'publishpress'),
        'value' => null,
        'type' => 'author',
        'multiple' => false,
    ];
```

### Checkbox [#](#h-checkbox)

```
$fields['mycheckboxfield'] = [
        'label' => __('My checkbox field', 'publishpress'),
        'value' => null,
        'type' => 'checkbox',
    ];
```

### Date Time [#](#datetime)

```
$fields['mydatetimefield'] = [
        'label' => __('My datetime field', 'publishpress'),
        'value' => null,
        'type' => 'date',
    ];
```

### Location [#](#datetime)

```
$fields['mylocationfield'] = [
        'label' => __('My location field', 'publishpress'),
        'value' => null,
        'type' => 'location',
    ];
```

### Number [#](#datetime)

```
$fields['mynumberfield'] = [
        'label' => __('My number field', 'publishpress'),
        'value' => null,
        'type' => 'number',
    ];
```

### Post Status [#](#datetime)

```
$fields['mypoststatusfield'] = [
        'label' => __('My poststatus field', 'publishpress'),
        'value' => null,
        'type' => 'status',
    ];
```

### Taxonomy [#](#datetime)

```
$fields['mytaxonomyfield'] = [
        'label' => __('My taxonomy field', 'publishpress'),
        'value' => null,
        'type' => 'taxonomy',
        'multiple' => false,
    ];
```

### Select [#](#datetime)

This fields supports custom items that can be queried. But it requires PublishPress version 3.7.1 or later.

```
$fields['myselectfield'] = [
        'label' => __('My select field', 'publishpress'),
        'value' => null,
        'type' => 'select',
        'ajaxAction' => 'fetchFieldValues',
        'multiple' => false,
    ];
```

### Html [#](#datetime)

```
$fields['myhtmlfield'] = [
        'label' => __('My HTML field', 'publishpress'),
        'value' => null,
        'type' => 'html',
    ];
```

### Text [#](#datetime)

```
$fields['mytextfield'] = [
        'label' => __('My text field', 'publishpress'),
        'value' => null,
        'type' => 'text',
    ];
```

### User [#](#datetime)

```
$fields['myuserfield'] = [
        'label' => __('My user field', 'publishpress'),
        'value' => null,
        'type' => 'user',
    ];
```

### Time [#](#datetime)

```
$fields['mytimefield'] = [
        'label' => __('My time field', 'publishpress'),
        'value' => null,
        'type' => 'time',
    ];
```

## Setting the values available in the new Select field [#](#h-setting-the-values-available-in-the-new-select-field)

For fields of type “select” you can return a set of values filtered by the typed text in the field.

You just need to create an Ajax action for the action you set on the param “ajaxAction” when defined the field.

On our first example of this article, we defined the following params:

```

    $fields['myfield'] = [
        'label' => __('My field', 'publishpress'),
        'value' => null,
        'type' => 'select',
        'ajaxAction' => 'fetchFieldValues',
        'multiple' => false,
    ];
```

So, we have set “fetchFieldValues” as the action. That allow us to create the new ajax action for it, where we process and return the set of values we wanna display on the field.

```
add_action('wp_ajax_fetchFieldValues', 'fetchFieldValues');
function fetchFieldValues() {
    if (! wp_verify_nonce(sanitize_text_field($_GET['nonce']), 'publishpress-calendar-get-data')) {
        wp_send_json([]);
    }

    $queryText = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

    // Filter the result set here, based on the typed query...

    $results = [
        [
            'id' => 0,
            'text' => 'Zero',
        ],
        [
            'id' => 1,
            'text' => 'One',
        ],
        [
            'id' => 2,
            'text' => 'Two',
        ],
        [
            'id' => 3,
            'text' => 'Three',
        ],
    ];

    wp_send_json($results);
}
```

## Saving the select data when creating the post [#](#h-saving-the-select-data-when-creating-the-post)

Before saving or processing any input data, always be sure to sanitize the data in a proper way.

```
add_action('publishpress_calendar_after_create_post', 'saveFieldValues');
function saveFieldValues($postId)
{
    if (!isset($_POST['myfield'])) {
        return;
    }

    $value = sanitize_text_field($_POST['myfield']);

    update_post_meta($postId, 'myfield', $value);
}
```
