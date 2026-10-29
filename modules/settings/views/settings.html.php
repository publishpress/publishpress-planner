<div class="wrap publishpress-admin">
    <div class="pp-columns-wrapper<?php echo (!$context['pro_active']) ? ' pp-enable-sidebar' : '' ?>">
        <div class="pp-column-left">
            <h2 class="nav-tab-wrapper">
                <?php foreach ($context['modules'] as $module) : ?>
                    <?php if (! empty($module->options_page) && $module->options->enabled === 'on') : ?>
                        <a
                                href="?page=<?php echo esc_attr($context['slug']); ?>&settings_module=<?php echo esc_attr($module->settings_slug); ?>"
                                class="nav-tab <?php if ($context['settings_slug'] === $module->settings_slug) : ?>nav-tab-active<?php endif; ?>">

                            <?php echo esc_html($module->title); ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </h2>
            <div class="pp-module-settings"><?php echo $context['module_output']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Module callbacks escape their settings form fields. ?></div>
        </div><!-- .pp-column-left -->
        <?php if (!$context['pro_active']) { ?>
            <div class="pp-column-right">
                <?php echo $context['pro_sidebar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div><!-- .pp-column-right -->
        <?php } ?>
    </div><!-- .pp-columns-wrapper -->
</div>
