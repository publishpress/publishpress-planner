<?php
namespace PublishPress\Notifications\Workflow\Option;

class RequireEditAccess extends SkipUser
{
    protected function getName() { return '_psppno_option_require_edit_access'; }
    protected function getFieldName() { return 'publishpress_notif_require_edit_access'; }
    protected function getLabel() { return esc_html__('Only notify users who can edit this post', 'publishpress'); }
    protected function getDescription() { return esc_html__('Check each recipient against the specific post, rather than assuming their role grants access.', 'publishpress'); }
}
