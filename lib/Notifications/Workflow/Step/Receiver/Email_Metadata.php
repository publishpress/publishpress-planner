<?php
namespace PublishPress\Notifications\Workflow\Step\Receiver;

class Email_Metadata extends Simple_Checkbox implements Receiver_Interface
{
    const META_KEY = '_psppno_toemailmeta';
    const META_VALUE = 'email_metadata';
    const META_FIELD = '_psppno_email_meta_key';

    public function __construct()
    {
        $this->name = static::META_VALUE;
        $this->label = __('Email addresses in a post metadata field', 'publishpress');
        $this->option_name = 'receiver_email_metadata';
        parent::__construct();
        $this->view_name = 'workflow_receiver_email_metadata';
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
        $key = isset($_POST['publishpress_notif']['email_metadata_key'])
            ? sanitize_text_field(wp_unslash($_POST['publishpress_notif']['email_metadata_key'])) : '';
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
        $emails = [];
        foreach ($values as $value) {
            foreach ((array) $value as $entry) {
                if (!is_scalar($entry)) {
                    continue;
                }
                foreach (preg_split('/[,;\s]+/', (string) $entry, -1, PREG_SPLIT_NO_EMPTY) as $email) {
                    if (is_email($email)) {
                        $emails[strtolower($email)] = $email;
                    }
                }
            }
        }
        foreach ($emails as $email) {
            $receivers[] = ['receiver' => $email, 'channel' => 'email', 'group' => static::META_VALUE];
        }
        return $receivers;
    }
}
