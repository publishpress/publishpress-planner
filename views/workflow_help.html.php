<p><?php echo esc_html($context['labels']['validation_help']); ?></p>
<h3><?php echo esc_html__('Content', 'publishpress'); ?></h3>
<p><?php echo esc_html($context['labels']['pre_text']); ?></p>

<h4><?php echo esc_html($context['labels']['content']); ?></h4>
<pre>[psppno_post]</pre>
<p><?php echo esc_html($context['labels']['available_fields']); ?>: <em><?php echo esc_html($context['psppno_post_fields_list']); ?></em></p>
<p><?php echo esc_html($context['labels']['meta_fields']); ?>: <em>meta:meta_key, <br />meta-date:meta_key, meta-relationship:meta_key.post_field</em></p>

<h4><?php echo esc_html($context['labels']['actor']); ?></h4>
<pre>[psppno_actor]</pre>
<p><?php echo esc_html($context['labels']['available_fields']); ?>: <em><?php echo esc_html($context['psppno_actor_fields_list']); ?></em></p>

<h4><?php echo esc_html($context['labels']['workflow']); ?></h4>
<pre>[psppno_workflow]</pre>
<p><?php echo esc_html($context['labels']['available_fields']); ?>: <em><?php echo esc_html($context['psppno_workflow_fields_list']); ?></em></p>

<h4><?php echo esc_html($context['labels']['edcomment']); ?></h4>
<pre>[psppno_edcomment]</pre>
<p><?php echo esc_html($context['labels']['available_fields']); ?>: <em><?php echo esc_html($context['psppno_edcomment_fields_list']); ?></em></p>

<h4><?php echo esc_html($context['labels']['receiver']); ?></h4>
<pre>[psppno_receiver]</pre>
<p><?php echo esc_html($context['labels']['available_fields']); ?>: <em><?php echo esc_html($context['psppno_receiver_fields_list']); ?></em></p>

<hr>
<a href="https://publishpress.com/docs/the-notification-workflows/" aria-label="<?php esc_attr_e('Read more about notification workflows', 'publishpress'); ?>"><?php echo esc_html($context['labels']['read_more']); ?></a>
