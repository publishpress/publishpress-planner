<?php
namespace PublishPress\Notifications\Workflow\Step\Receiver;

class Custom_Email extends Simple_Checkbox implements Receiver_Interface
{
    const META_KEY = '_psppno_tocustomemail';
    const META_VALUE = 'custom_email';
    const META_FIELD = '_psppno_custom_emails';

    public function __construct()
    {
        $this->name = static::META_VALUE;
        $this->label = __('Custom email addresses', 'publishpress');
        $this->option_name = 'receiver_custom_email';
        parent::__construct();
        $this->view_name = 'workflow_receiver_custom_email';
    }

    public function filter_workflow_metabox_context($context)
    {
        $context = parent::filter_workflow_metabox_context($context);
        $context['addresses'] = $this->get_metadata(static::META_FIELD, true);
        return $context;
    }

    public function save_metabox_data($id, $post)
    {
        if (!isset($_POST['publishpress_notif_metabox_events_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['publishpress_notif_metabox_events_nonce'])), 'publishpress_notif_save_metabox')) {
            return;
        }
        parent::save_metabox_data($id, $post);
        $value = isset($_POST['publishpress_notif']['custom_emails'])
            ? sanitize_textarea_field(wp_unslash($_POST['publishpress_notif']['custom_emails'])) : '';
        update_post_meta($id, static::META_FIELD, $value);
    }

    public function filter_workflow_receivers($receivers, $workflow, $args)
    {
        if (!$this->is_selected($workflow->ID)) {
            return $receivers;
        }
        $value = get_post_meta($workflow->ID, static::META_FIELD, true);
        $post_id = (int) ($args['params']['post_id'] ?? 0);
        // Expand only the supported post-meta placeholder; never execute unrelated shortcodes.
        $value = preg_replace_callback('/\[psppno_post\s+meta:([^\]\s]+)\s*\]/', static function ($match) use ($post_id) {
            $meta = $post_id ? get_post_meta($post_id, $match[1], true) : '';
            return is_scalar($meta) ? (string) $meta : '';
        }, $value);
        $emails = [];
        foreach (preg_split('/[,;\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY) as $email) {
            if (is_email($email)) {
                $emails[strtolower($email)] = $email;
            }
        }
        foreach ($emails as $email) {
            $receivers[] = ['receiver' => $email, 'channel' => 'email', 'group' => static::META_VALUE];
        }
        return $receivers;
    }
}
