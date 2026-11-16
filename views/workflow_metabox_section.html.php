<div id="psppno-workflow-metabox-section-<?php echo esc_attr($context['id']); ?>"
     class="<?php echo esc_attr(isset($context['class']) ? $context['class'] : ''); ?> psppno-workflow-metabox-section">
    <?php if (! empty($context['header'])) : ?>
        <div class="psppno_workflow_metabox_section_header">
            <?php echo wp_kses_post($context['header']); ?>
        </div>
    <?php endif; ?>

    <?php echo $context['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Form markup is escaped in the field templates. ?>
</div>
