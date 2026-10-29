<?php

defined('ABSPATH') or die('No direct script access allowed.');

if (! class_exists('Editorial_Metadata_Input_Select_Handler')) {
    require_once 'editorial-metadata-input-handler.php';

    class Editorial_Metadata_Input_Select_Handler extends Editorial_Metadata_Input_Handler
    {
        /**
         * Class constructor that defines input type.
         *
         * @since   1.20.0
         */
        public function __construct()
        {
            $this->type = 'select';
        }

        /**
         * Get input html for public access
         * @param array $inputOptions Input options
         * @param mixed $value Actual input value
         */
        public static function getInputHtml($inputOptions = array(), $value = null)
        {
            $dropdown_data = self::get_dropdown_data($inputOptions, $value);

            $input_id = $dropdown_data['input_id'];
            $input_class = $dropdown_data['input_class'] . ' pp-calendar-form-metafied-input';
            $input_name = $dropdown_data['input_name'];
            $select_type = $dropdown_data['select_type'];
            $option_values = $dropdown_data['option_values'];
            $option_labels = $dropdown_data['option_labels'];
            $input_label = $dropdown_data['input_label'];
            $input_description = $dropdown_data['input_description'];
            $default_option_label = '';
            $select_default = $dropdown_data['select_default'];
            
            ob_start();

            if (!$dropdown_data) {
                return;
            }

            $html = '<div class="pp-editorial-select2-wrapper">';
            $html .= sprintf(
                '<select id="%1$s" class="%2$s" name="%3$s" placeholder="%4$s" data-placeholder="%5$s" %6$s>',
                esc_attr($input_id),
                esc_attr($input_class),
                esc_attr($input_name),
                esc_attr($default_option_label),
                esc_attr($default_option_label),
                esc_attr($select_type)
            );
            $html .= self::generate_option('', '');

            foreach ($option_values as $index => $option_value) {
                if (!$value && $select_default !== '') {
                    $selected = selected($index, $select_default, false);
                } else {
                    $selected = (is_array($value)) ? selected(in_array($option_value, $value), true, false) : selected($value, $option_value, false);
                }
                $html .= self::generate_option($option_value, $option_labels[$index], $selected);
            }
    
            $html .= '</select>';
            $html .= '</div>';

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $html;

            return ob_get_clean();
        }

        /**
         * Render input html.
         *
         * @access  protected
         * @param array $inputOptions Input options
         * @param mixed $value Actual input value
         * @since   1.20.0
         *
         */
        protected function renderInput($inputOptions = array(), $value = null)
        {
            $dropdown_data = self::get_dropdown_data($inputOptions, $value);

            $input_id = $dropdown_data['input_id'];
            $input_class = $dropdown_data['input_class'];
            $input_name = $dropdown_data['input_name'];
            $select_type = $dropdown_data['select_type'];
            $option_values = $dropdown_data['option_values'];
            $option_labels = $dropdown_data['option_labels'];
            $input_label = $dropdown_data['input_label'];
            $input_description = $dropdown_data['input_description'];
            $default_option_label = $dropdown_data['default_option_label'];
            $select_default = $dropdown_data['select_default'];
            
            self::renderLabel(
                $input_label,
                $input_name
            );

            echo wp_kses_post(self::generateDescriptionHtml($input_description));

            if (!$dropdown_data) {
                return;
            }

            $html = '<div class="pp-editorial-select2-wrapper">';
            $html .= sprintf(
                '<select id="%1$s" class="%2$s" name="%3$s" placeholder="%4$s" data-placeholder="%5$s" %6$s>',
                esc_attr($input_id),
                esc_attr($input_class),
                esc_attr($input_name),
                esc_attr($default_option_label),
                esc_attr($default_option_label),
                esc_attr($select_type)
            );
            $html .= self::generate_option('', '');

            foreach ($option_values as $index => $option_value) {
                if (!$value && $select_default !== '') {
                    $selected = selected($index, $select_default, false);
                } else {
                    $selected = (is_array($value)) ? selected(in_array($option_value, $value), true, false) : selected($value, $option_value, false);
                }
                $html .= self::generate_option($option_value, $option_labels[$index], $selected);
            }
    
            $html .= '</select>';
            $html .= '</div>';

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $html;
        }

        /**
         * Render input-preview html.
         *
         * @access  protected
         * @param array $inputOptions Input options
         * @param mixed $value Actual input value
         * @since   1.20.0
         *
         */
        protected function renderInputPreview($inputOptions = array(), $value = null)
        {
            $dropdown_data = self::get_dropdown_data($inputOptions, $value);

            $input_id = $dropdown_data['input_id'];
            $input_class = $dropdown_data['input_class'];
            $input_name = $dropdown_data['input_name'];
            $select_type = $dropdown_data['select_type'];
            $option_values = $dropdown_data['option_values'];
            $option_labels = $dropdown_data['option_labels'];
            $input_label = $dropdown_data['input_label'];
            $input_description = $dropdown_data['input_description'];
            $default_option_label = $dropdown_data['default_option_label'];
            $select_default = $dropdown_data['select_default'];

            self::renderLabel(
                $input_label,
                $input_name
            );

            echo wp_kses_post(self::generateDescriptionHtml($input_description));


            if (!$dropdown_data) {
                self::renderValuePlaceholder();
                return;
            }

            //match to label
            $value_labels = [];
            foreach ($option_values as $index => $option_value) {
                $value_labels[$option_value] = $option_labels[$index];
            }

            if (is_array($value) && !empty($value)) {
                $output_value = [];
                foreach ($value as $single_value) {
                    $output_value[] = sprintf(
                        '<span class="pp_editorial_metadata_value">%s</span>',
                        esc_html($value_labels[$single_value])
                    );
                }
                 // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo join(', ', $output_value);
            } elseif (mb_strlen((string)$value) > 0) {
                printf(
                    '<span class="pp_editorial_metadata_value">%s</span>',
                    esc_html($value_labels[$value])
                );
            } else {
                self::renderValuePlaceholder();
            }

            $html = '<div class="pp-editorial-select2-wrapper">';
            $html .= sprintf(
                '<select id="%1$s" class="%2$s" name="%3$s" placeholder="%4$s" data-placeholder="%5$s" %6$s>',
                esc_attr($input_id),
                esc_attr($input_class),
                esc_attr($input_name),
                esc_attr($default_option_label),
                esc_attr($default_option_label),
                esc_attr($select_type)
            );
            $html .= self::generate_option('', '');

            foreach ($option_values as $index => $option_value) {
                if (!$value && $select_default !== '') {
                    $selected = selected($index, $select_default, false);
                } else {
                    $selected = (is_array($value)) ? selected(in_array($option_value, $value), true, false) : selected($value, $option_value, false);
                }
                $html .= self::generate_option($option_value, $option_labels[$index], $selected);
            }
    
            $html .= '</select>';
            $html .= '</div>';

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $html;
        }

        /**
         * Return select dropdown data based on input options.
         *
         * @param array $inputOptions Input options
         * @param mixed $value Actual input value
         *
         * @return array $inputOptions.
         */
        protected static function get_dropdown_data($inputOptions, $value)
        {
            $input_name = isset($inputOptions['name']) ? $inputOptions['name'] : '';
            $input_label = isset($inputOptions['label']) ? $inputOptions['label'] : '';
            $input_description = isset($inputOptions['description']) ? $inputOptions['description'] : '';
            $input_term_options = isset($inputOptions['term_options']) ? $inputOptions['term_options'] : false;
            $input_term_options = isset($inputOptions['term_options']) ? $inputOptions['term_options'] : false;

            $select_type    = (isset($input_term_options->select_type) && !empty($input_term_options->select_type)) 
                ? $input_term_options->select_type : '';
            $select_default = isset($input_term_options->select_default) 
                ? $input_term_options->select_default : '';
            $select_options = (isset($input_term_options->select_options) && is_array($input_term_options->select_options)) 
                ? $input_term_options->select_options : [];
            $option_values     = isset($select_options['values']) ? $select_options['values'] : false;
            $option_labels     = isset($select_options['labels']) ? $select_options['labels'] : false;

            $default_option_label = sprintf(
                esc_html__('-- Select %s --', 'publishpress'),
                esc_attr($input_label)
            );

            $input_id    = $input_name;
            $input_type  = '';
            $input_class = $input_name;
            if ($select_type === 'multiple') {
                $input_name  .= '[]';
                $input_class .= ' pp_editorial_meta_multi_select2';
                $input_type  = $select_type;
            } else {
                $input_class .= ' pp_editorial_single_select2';
            }

            if (!$option_values || !$option_values) {
                return false;
            }


            $response_data = [
                'select_type'          => $select_type,
                'option_values'        => $option_values,
                'option_labels'        => $option_labels,
                'input_id'             => $input_id,
                'input_type'           => $input_type,
                'input_class'          => $input_class,
                'input_label'          => $input_label,
                'input_name'           => $input_name,
                'input_description'    => $input_description,
                'default_option_label' => $default_option_label,
                'select_default'       => $select_default
            ];

            return $response_data;
        }

        /**
         * Generates an <option> element.
         *
         * @param string $value The option's value.
         * @param string $label The option's label.
         * @param string $selected HTML selected attribute for an option.
         *
         * @return string The generated <option> element.
         */
        protected static function generate_option($value, $label, $selected = '')
        {
            return '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }

        /**
         * Get meta-input value html formatted.
         *
         * @static
         * @param mixed $value Actual input value
         * @param mixed $term
         *
         * @return  string
         * @since   1.20.0
         *
         */
        public static function getMetaValueHtml($value = null, $term = false)
        {
            if (empty($value) || !$term) {
                return '';
            }

            $term           = (array) $term;
            $select_options = (isset($term['select_options']) && is_array($term['select_options'])) 
                ? $term['select_options'] : [];
            $option_values     = isset($select_options['values']) ? $select_options['values'] : false;
            $option_labels     = isset($select_options['labels']) ? $select_options['labels'] : false;

            if (!$option_values || !$option_labels) {
                return '';
            }

            //match to label
            $value_labels = [];
            foreach ($option_values as $index => $option_value) {
                $value_labels[$option_value] = $option_labels[$index];
            }

            $output_value = [];
            if (is_array($value) && !empty($value)) {
                foreach ($value as $single_value) {
                    $output_value[] = $value_labels[$single_value];
                }
            } else {
                $output_value[] = $value_labels[$value];
            }

            return esc_html(join(', ', $output_value));
        }
    }
}
