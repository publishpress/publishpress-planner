<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2022 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow\Step\Event_Content\Filter;

use PublishPress\Notifications\Workflow\Step\Event\Filter\Base as Event_Filter_Base;

class Base extends Event_Filter_Base
{
    protected function get_term_path($term, $terms_by_id)
    {
        $names = [$term->name];
        $seen = [$term->term_id => true];
        $parent = (int) $term->parent;
        while ($parent && isset($terms_by_id[$parent]) && !isset($seen[$parent])) {
            $ancestor = $terms_by_id[$parent];
            if ($ancestor->taxonomy !== $term->taxonomy) {
                break;
            }
            array_unshift($names, $ancestor->name);
            $seen[$parent] = true;
            $parent = (int) $ancestor->parent;
        }
        return implode(' › ', $names);
    }
}
