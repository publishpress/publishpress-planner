<?php
namespace PublishPress\Notifications\Workflow\Step\Event;

class Metadata_Update extends Post_TaxonomyUpdate
{
    const META_KEY_SELECTED = '_psppno_evtmetadata';
    const META_VALUE_SELECTED = 'metadata_update';
    const EVENT_NAME = 'publishpress_post_metadata_updated';

    public function __construct()
    {
        parent::__construct();
        $this->label = __('When post metadata is added, changed, or removed', 'publishpress');
        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
            add_action($hook, [$this, 'metadata_changed'], 10, 4);
        }
    }

    protected function get_filters($filters = [])
    {
        if (empty($this->cache_filters)) {
            $this->cache_filters = [new Filter\Metadata_Key($this->attr_prefix . '_' . $this->name)];
        }
        return $this->cache_filters;
    }

    public function metadata_changed($meta_id, $post_id, $key, $value)
    {
        $type = get_post_type($post_id);
        if (!$type || in_array($type, ['revision', 'psppnotif_workflow'], true)
            || in_array($key, ['_edit_lock', '_edit_last'], true)
            || strpos($key, '_psppno_') === 0 || strpos($key, '_ppnotif_') === 0) {
            return;
        }
        do_action('publishpress_notifications_trigger_workflows', [
            'event' => static::EVENT_NAME, 'event_key' => static::META_KEY_SELECTED,
            'user_id' => get_current_user_id(),
            'params' => ['post_id' => (int) $post_id, 'meta_key' => $key],
        ]);
    }
}
