<?php
namespace PublishPress\Notifications\Workflow\Step\Receiver;

class User_Metadata extends Simple_Checkbox implements Receiver_Interface
{
    const META_KEY = '_psppno_tousermeta';
    const META_VALUE = 'user_metadata';
    const META_FIELD = '_psppno_user_meta_key';

    public function __construct()
    {
        $this->name = static::META_VALUE;
        $this->label = __('Users selected in a post metadata field', 'publishpress');
        $this->option_name = 'receiver_user_metadata';
        parent::__construct();
        $this->view_name = 'workflow_receiver_user_metadata';
    }

    public function filter_workflow_metabox_context($context)
    {
        $context = parent::filter_workflow_metabox_context($context);
        $context['meta_key'] = $this->get_metadata(static::META_FIELD, true);
        return $context;
    }

    public function save_metabox_data($id, $post)
    {
        if (!isset($_POST['publishpress_notif_metabox_events_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['publishpress_notif_metabox_events_nonce'])), 'publishpress_notif_save_metabox')) {
            return;
        }
        parent::save_metabox_data($id, $post);
        $key = isset($_POST['publishpress_notif']['user_metadata_key'])
            ? sanitize_text_field(wp_unslash($_POST['publishpress_notif']['user_metadata_key'])) : '';
        update_post_meta($id, static::META_FIELD, $key);
    }

    public function filter_workflow_receivers($receivers, $workflow, $args)
    {
        if (!$this->is_selected($workflow->ID) || empty($args['params']['post_id'])) {
            return $receivers;
        }
        $key = get_post_meta($workflow->ID, static::META_FIELD, true);
        if ($key === '') {
            return $receivers;
        }
        $values = get_post_meta((int) $args['params']['post_id'], $key, false);
        $users = [];
        foreach ($values as $value) {
            foreach ((array) $value as $id) {
                if (is_numeric($id) && (int) $id > 0 && is_user_member_of_blog((int) $id) && get_user_by('id', (int) $id)) {
                    $users[(int) $id] = true;
                }
            }
        }
        foreach (array_keys($users) as $id) {
            $receivers[] = ['receiver' => $id, 'group' => static::META_VALUE];
        }
        return $receivers;
    }
}
