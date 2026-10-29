<?php foreach ($context['options'] as $option) : ?>
    <?php echo $option->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The option template escapes its form fields. ?>
<?php endforeach;
