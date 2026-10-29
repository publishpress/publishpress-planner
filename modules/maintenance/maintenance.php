<?php
use PublishPress\NotificationsLog\NotificationsLogModel;

#[\AllowDynamicProperties]
class PP_Maintenance extends PP_Module
{
    public function __construct()
    {
        $this->module_url = $this->get_module_url(__FILE__);
        $this->module = PublishPress()->register_module('maintenance', [
            'title' => __('Maintenance', 'publishpress'),
            'slug' => 'maintenance',
            'module_url' => $this->module_url,
            'default_options' => ['enabled' => 'on'],
            'autoload' => true,
            'options_page' => true,
            'configure_page_cb' => 'print_configure_view',
        ]);
        parent::__construct();
    }

    public function init()
    {
        add_action('wp_ajax_publishpress_clear_notification_logs', [$this, 'clear_log_batch']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts()
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'pp-maintenance-settings') {
            return;
        }
        wp_enqueue_script('publishpress-maintenance', $this->module_url . 'assets/maintenance.js', ['jquery'], PUBLISHPRESS_VERSION, true);
        wp_localize_script('publishpress-maintenance', 'ppMaintenance', [
            'confirm' => __('Clear notification history? Scheduled deliveries will be kept.', 'publishpress'),
            'progress' => __('Deleted entries: %d', 'publishpress'),
            'complete' => __('Cleanup complete. Deleted entries: %d', 'publishpress'),
            'error' => __('Cleanup stopped. Please try again.', 'publishpress'),
        ]);
    }

    public function print_configure_view()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        global $publishpress;
        $publishpress->settings->print_default_header($this->module);
        echo '<h3>' . esc_html__('Clear Notifications Log', 'publishpress') . '</h3>';
        echo '<p>' . esc_html__('Remove notification history in small batches. Scheduled notifications and their delivery records are preserved.', 'publishpress') . '</p>';
        echo '<button type="button" class="button" id="pp-clear-notification-logs" data-nonce="' . esc_attr(wp_create_nonce('pp-clear-notification-logs')) . '">' . esc_html__('Clear Notifications Log', 'publishpress') . '</button>';
        echo '<p id="pp-maintenance-status" role="status" aria-live="polite"></p>';
        do_action('publishpress_maintenance_tools');
        $publishpress->settings->print_default_footer($this->module);
    }

    public function clear_log_batch()
    {
        check_ajax_referer('pp-clear-notification-logs', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(null, 403);
            return;
        }
        $ids = get_comments([
            'type' => NotificationsLogModel::COMMENT_TYPE,
            'status' => NotificationsLogModel::COMMENT_ACTIVE,
            'fields' => 'ids', 'number' => 50, 'orderby' => 'comment_ID', 'order' => 'ASC',
            'meta_query' => [
                'relation' => 'OR',
                ['key' => NotificationsLogModel::META_NOTIF_STATUS, 'value' => 'scheduled', 'compare' => '!='],
                ['key' => NotificationsLogModel::META_NOTIF_STATUS, 'compare' => 'NOT EXISTS'],
            ],
        ]);
        $deleted = 0;
        foreach ($ids as $id) {
            if (wp_delete_comment($id, true)) {
                $deleted++;
            }
        }
        if ($deleted !== count($ids)) {
            wp_send_json_error(['message' => __('Some log entries could not be removed.', 'publishpress')], 500);
            return;
        }
        wp_send_json_success(['deleted' => $deleted, 'more' => count($ids) === 50]);
    }
}
