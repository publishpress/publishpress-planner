<h1><?php echo esc_html($context['label']['title']); ?></h1>

<?php if (! empty($context['messages'])) : ?>
    <div id="message" class="upstream-messages notice is-dismissible">
        <?php foreach ($context['messages'] as $message) : ?>
            <p><?php echo $message; ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div id="upstream-debug-data">
    <h2><?php echo esc_html($context['label']['debug_data']); ?></h2>
    <textarea readonly><?php echo $context['debug_data']; ?></textarea>
</div>

<hr>

<div id="upstream-debug-log">

    <?php if ($context['is_log_found']) : ?>
        <h2><?php echo $context['label']['log_file']; ?></h2>
        
        <h3><?php echo esc_html($context['label']['file_info']); ?></h3>
        <table id="upstream-debug-log-info">
            <tbody>
            <tr>
                <th scope="row"><?php echo esc_html($context['label']['path']); ?>:</th>
                <td><?php echo esc_html($context['file']['path']); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html($context['label']['size']); ?>:</th>
                <td><?php echo esc_html($context['file']['size']); ?> KB</td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html($context['label']['modification_time']); ?>:</th>
                <td><?php echo esc_html($context['file']['modification_time']); ?></td>
            </tr>
            </tbody>
        </table>

        <p><?php echo esc_html($context['message']['click_to_delete']); ?></p>
        <a class="button button-danger" href="<?php echo esc_url($context['link_delete']); ?>"><?php echo esc_html($context['label']['delete_file']); ?></a>

        <h3><?php echo esc_html($context['label']['log_content']); ?></h3>
        <pre id="upstream-debug-log"><?php echo esc_html($context['file']['content']); ?></pre>
    <?php endif; ?>
</div>
