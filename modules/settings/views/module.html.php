<?php if (! empty($context['url'])) : ?>
<a href="<?php echo esc_url($context['url']); ?>">
<?php endif; ?>

    <div
            class="publishpress-module module-enabled <?php echo esc_attr($context['has_config_link'] ? 'has-configure-link' : ''); ?>"
            id="<?php echo esc_attr($context['slug']); ?>">

        <?php if (! empty($context['icon_class'])) : ?>
        <span class="<?php echo esc_attr($context['icon_class']); ?> float-right module-icon"></span>
        <?php endif; ?>

        <form
                method="GET"
                action="<?php echo esc_url($context['form_action']); ?>">

            <h4><?php echo esc_html($context['title']); ?></h4>
            <p><?php echo esc_html($context['description']); ?></p>
        </form>
    </div>

<?php if (! empty($context['url'])) : ?>
</a>
<?php endif; ?>
