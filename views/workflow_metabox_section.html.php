<div id="psppno-workflow-metabox-section-<?php echo esc_attr($context['id']); ?>"
     class="<?php echo esc_attr(isset($context['class']) ? $context['class'] : ''); ?> psppno-workflow-metabox-section"
    <?php if (! empty($context['header'])) : ?>
     role="group" aria-labelledby="psppno-workflow-metabox-section-<?php echo esc_attr($context['id']); ?>-header"
    <?php endif; ?>>
    <?php if (! empty($context['header'])) : ?>
        <div class="psppno_workflow_metabox_section_header" id="psppno-workflow-metabox-section-<?php echo esc_attr($context['id']); ?>-header">
            <?php echo $context['header']; ?>
            <?php if (! empty($context['required'])) : ?>
                <span class="psppno-required-marker"><?php esc_html_e('(required)', 'publishpress'); ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php echo $context['html']; ?>
</div>
