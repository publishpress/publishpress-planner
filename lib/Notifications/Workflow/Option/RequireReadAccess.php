<?php
namespace PublishPress\Notifications\Workflow\Option;

class RequireReadAccess extends SkipUser
{
    protected function getName() { return '_psppno_option_require_read_access'; }
    protected function getFieldName() { return 'publishpress_notif_require_read_access'; }
    protected function getLabel() { return esc_html__('Only notify users who can view this post', 'publishpress'); }
    protected function getDescription() { return esc_html__('Check access to the specific post. Addresses without a registered user are excluded.', 'publishpress'); }
}
