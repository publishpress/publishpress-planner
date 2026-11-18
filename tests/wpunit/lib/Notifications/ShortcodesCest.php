<?php

namespace lib\Notifications;

use PP_Editorial_Metadata;
use PublishPress\Notifications\Shortcodes;
use WpunitTester;

class ShortcodesCest
{
    public function getEditorialMetaPrefixUsesGlobalClass(WpunitTester $I)
    {
        $shortcodes = new Shortcodes();
        $method     = new \ReflectionMethod(Shortcodes::class, 'get_editorial_meta_prefix');
        $method->setAccessible(true);

        $prefix = $method->invoke($shortcodes);

        $I->assertEquals(PP_Editorial_Metadata::metadata_postmeta_key, $prefix);
    }

    public function metaShortcodePrintsEditorialMetaWithoutFatal(WpunitTester $I)
    {
        PublishPress()->action_init();

        $postId = wp_insert_post(
            [
                'post_title'  => 'Notification meta shortcode test',
                'post_status' => 'draft',
                'post_type'   => 'post',
            ]
        );

        $metaKey = PP_Editorial_Metadata::metadata_postmeta_key . '_text_test-field';
        update_post_meta($postId, $metaKey, 'expected-value');

        $shortcodes = new Shortcodes();
        $shortcodes->register(
            get_post($postId),
            [
                'user_id' => 1,
                'params'  => ['post_id' => $postId],
            ]
        );

        $output = $shortcodes->handle_psppno_post(['meta:' . $metaKey]);

        $shortcodes->unregister();

        $I->assertEquals('expected-value', $output);
    }
}