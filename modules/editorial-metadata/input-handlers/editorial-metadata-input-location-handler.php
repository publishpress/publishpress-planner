<?php

defined('ABSPATH') or die('No direct script access allowed.');

if (! class_exists('Editorial_Metadata_Input_Location_Handler')) {
    require_once 'editorial-metadata-input-text-handler.php';

    class Editorial_Metadata_Input_Location_Handler extends Editorial_Metadata_Input_Text_Handler
    {
        /**
         * Class constructor that defines input type.
         *
         * @since   1.20.0
         */
        public function __construct()
        {
            $this->type = 'location';
        }

        /**
         * Get input html for public access
         * @param array $inputOptions Input options
         * @param mixed $value Actual input value
         */
        public static function getInputHtml($inputOptions = array(), $value = null)
        {
            $input_name = isset($inputOptions['name']) ? $inputOptions['name'] : '';

            ob_start();

            printf(
                '<input
                    type="text"
                    class="pp-calendar-form-metafied-input"
                    id="%s"
                    name="%1$s"
                    value="%2$s"
                />',
                esc_attr($input_name),
                esc_attr($value)
            );

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
            parent::renderInput($inputOptions, $value);

            if (! empty($value)) {
                echo wp_kses_post(self::generateMapLinkWithLocation($value));
            }
        }

        /**
         * Generate a link that leads to a google maps location.
         *
         * @access  private
         * @static
         * @param string $location
         *
         * @return  string
         * @since   1.20.0
         *
         */
        private static function generateMapLinkWithLocation($location)
        {
            return sprintf(
                '<div><a href="%s" target="_blank">%s</a></div>',
                esc_url("http://maps.google.com/?q={$location}&t=m"),
                esc_html(
                    sprintf(
                        __('View &#8220;%s&#8221; on Google Maps', 'publishpress'),
                        $location
                    )
                )
            );
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
            $input_name = isset($inputOptions['name']) ? $inputOptions['name'] : '';
            $input_label = isset($inputOptions['label']) ? $inputOptions['label'] : '';
            $input_description = isset($inputOptions['description']) ? $inputOptions['description'] : '';

            self::renderLabel(
                $input_label,
                $input_name
            );

            echo wp_kses_post(self::generateDescriptionHtml($input_description));

            if (mb_strlen((string)$value) > 0) {
                printf(
                    '<span class="pp_editorial_metadata_value">%s</span>',
                    esc_html($value)
                );

                echo wp_kses_post(self::generateMapLinkWithLocation($value));
            } else {
                self::renderValuePlaceholder();
            }

            printf(
                '<input
                    type="hidden"
                    id="%s"
                    name="%1$s"
                    value="%2$s"
                />',
                esc_attr($input_name),
                esc_attr($value)
            );
        }

        /**
         * Get meta-input value html formatted.
         *
         * @static
         * @param mixed $value Actual input value
         *
         * @return  string
         * @since   1.20.0
         *
         */
        public static function getMetaValueHtml($value = null)
        {
            return ! empty($value)
                ? esc_html($value)
                : '';
        }
    }
}
