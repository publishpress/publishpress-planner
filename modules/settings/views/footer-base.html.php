<?php
$plugin_name_markup = "<strong>{$context['plugin_name']}</strong>";
$rating_stars_markup = "
        <span class=\"dashicons dashicons-star-filled\"></span>
        <span class=\"dashicons dashicons-star-filled\"></span>
        <span class=\"dashicons dashicons-star-filled\"></span>
        <span class=\"dashicons dashicons-star-filled\"></span>
        <span class=\"dashicons dashicons-star-filled\"></span>";
?>
<br style="clear: both;">
<footer>
    <div class="pp-rating">
        <a href="//wordpress.org/support/plugin/<?php echo esc_attr($context['plugin_slug']); ?>/reviews/#new-post"
           target="_blank"
           rel="noopener noreferrer"><?php echo sprintf($context['rating_message'], $plugin_name_markup, $rating_stars_markup); ?></a>
    </div>
    <hr>
    <nav>
        <ul>
            <li>
                <a href="//publishpress.com/planner" target="_blank" rel="noopener noreferrer" title="About <?php echo esc_attr($context['plugin_name']); ?>"><?php echo esc_html(__('About', 'publishpress')); ?></a>
            </li>
            <li>
                <a href="//publishpress.com/knowledge-base/" target="_blank" rel="noopener noreferrer"
                   title="<?php echo esc_attr($context['plugin_name']); ?> Documentation"><?php echo esc_html(__('Documentation', 'publishpress')); ?></a>
            </li>
            <li>
                <a href="//publishpress.com/publishpress-support/" target="_blank" rel="noopener noreferrer"
                   title="Contact the PublishPress team"><?php echo esc_html(__('Contact', 'publishpress')); ?></a>
            </li>
        </ul>
    </nav>
    <div class="pp-pressshack-logo">
        <a href="//publishpress.com" target="_blank" rel="noopener noreferrer">
            <img src="<?php echo esc_url($context['plugin_url']); ?>common/img/publishpress-logo.png" alt="<?php esc_attr_e('PublishPress', 'publishpress'); ?>">
        </a>
    </div>
</footer>
</div>
