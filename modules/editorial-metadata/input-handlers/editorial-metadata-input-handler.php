<?php

defined('ABSPATH') or die('No direct script access allowed.');

if (! class_exists('Editorial_Metadata_Input_Handler')) {
    require_once 'editorial-metadata-input-handler-interface.php';

    abstract class Editorial_Metadata_Input_Handler implements Editorial_Metadata_Input_Handler_Contract
    {
        /**
         * Next node in the chain.
         *
         * @access  private
         * @since   1.20.0
         *
         * @var     Editorial_Metadata_Input_Handler_Contract $nextHandler
         */
        private $nextHandler = null;

        /**
         * Input type.
         *
         * @access  protected
         * @since   1.20.0
         *
         * @var     string $type
         */
        protected $type = null;

        /**
         * Check if the input can handle a given action based on $type.
         *
         * @final
         * @param string $type Input type
         *
         * @return  bool
         * @since   1.20.0
         *
         */
        final public function canHandle($type)
        {
            return $type === $this->type;
        }

        /**
         * Register a new handler in the chain.
         *
         * @final
         * @param Editorial_Metadata_Input_Handler $handler An input handler instance
         * @throws Exception
         *
         * @since   1.20.0
         *
         */
        final public function registerHandler($handler)
        {
            if (! ($handler instanceof Editorial_Metadata_Input_Handler)) {
                throw new Exception('Invalid type for handler parameter.');
            }

            if (! is_null($this->nextHandler)) {
                return $this->nextHandler->registerHandler($handler);
            }

            $this->nextHandler = $handler;
        }

        /**
         * Iterate through the chain until a node handles the action and render
         * the appropriated input based on $type.
         *
         * @final
         * @param string $type Input type
         * @param array $inputOptions Input options
         * @param mixed $value Actual input value
         * @since   1.20.0
         *
         */
        final public function handleHtmlRendering($type, $inputOptions = array(), $value = null)
        {
            if (! $this->canHandle($type)) {
                return ! is_null($this->nextHandler)
                    ? $this->nextHandler->handleHtmlRendering($type, $inputOptions, $value)
                    : printf("<p>" . esc_html__('This editorial fields type is not yet supported.', 'publishpress') . "</p>");
            }

            return $this->renderInput($inputOptions, $value);
        }

        /**
         * Iterate through the chain until a node handles the action and render
         * the appropriated input-preview based on $type.
         *
         * @final
         * @param string $type Input type
         * @param array $inputOptions Input options
         * @param mixed $value Actual input value
         * @since   1.20.0
         *
         */
        final public function handlePreviewRendering($type, $inputOptions = array(), $value = null)
        {
            if (! $this->canHandle($type)) {
                return ! is_null($this->nextHandler)
                    ? $this->nextHandler->handlePreviewRendering($type, $inputOptions, $value)
                    : printf("<p>" . esc_html__('This editorial fields type is not yet supported.', 'publishpress') . "</p>");
            }

            return $this->renderInputPreview($inputOptions, $value);
        }

        /**
         * Iterate through the chain until a node handles the action and render
         * the appropriated meta-input raw value based on $type.
         *
         * @param string $type Input type
         * @param mixed $value Actual input value
         * @param mixed $term
         * @since   1.20.0
         *
         */
        final public function handleMetaValueHtmling($type, $value = null, $term = false)
        {
            if (! $this->canHandle($type)) {
                return ! is_null($this->nextHandler)
                    ? $this->nextHandler->handleMetaValueHtmling($type, $value, $term)
                    : printf("<p>" . esc_html__('This editorial fields type is not yet supported.', 'publishpress') . "</p>");
            }
            return static::getMetaValueHtml($value, $term);
        }

        /**
         * Get input-handler type.
         *
         * @return  string
         * @since   1.20.0
         *
         */
        public function getType()
        {
            return $this->type;
        }

        /**
         * Return the input type in case the class is treated as string.
         *
         * @return  string
         * @since   1.20.0
         *
         */
        public function __toString()
        {
            return $this->type;
        }

        /**
         * Echoes a Label html element.
         *
         * @static
         * @access  protected
         * @param string $content Label content
         * @param string $related_input_id Related input id
         * @since   1.20.0
         *
         */
        protected static function renderLabel($content, $related_input_id = null)
        {
            printf(
                '<label for="%s">%s</label>',
                esc_attr($related_input_id),
                esc_html($content)
            );
        }

        /**
         * Returns a Description html string.
         *
         * @static
         * @access  protected
         * @param string $description The description content
         *
         * @return  string
         * @since   1.20.0
         *
         */
        protected static function generateDescriptionHtml($description, $input_name = '')
        {
            if (mb_strlen($description) === 0) {
                return '';
            }

            if (mb_strlen((string)$input_name) > 0) {
                return sprintf(
                    '<span class="%s" id="%s">%s</span>',
                    'description',
                    esc_attr($input_name . '-desc'),
                    esc_html($description)
                );
            }

            return sprintf(
                '<span class="%s">%s</span>',
                'description',
                esc_html($description)
            );
        }

        /**
         * Returns an escaped aria-describedby attribute pointing to the
         * description element, or an empty string if there is no description.
         *
         * @static
         * @access  protected
         * @param string $description The description content
         * @param string $input_name The related input name
         *
         * @return  string
         */
        protected static function generateDescribedByAttr($description, $input_name)
        {
            if (mb_strlen((string)$description) === 0 || mb_strlen((string)$input_name) === 0) {
                return '';
            }

            return sprintf(' aria-describedby="%s"', esc_attr($input_name . '-desc'));
        }

        /**
         * Echoes a Description html element.
         *
         * @static
         * @access  protected
         * @param string $description The description content
         * @since   1.20.0
         *
         */
        protected static function renderDescription($description, $input_name = '')
        {
            echo self::generateDescriptionHtml($description, $input_name);
        }

        /**
         * Render a default placeholder for when there's no value to be shown.
         *
         * @static
         * @access  protected
         * @since   1.20.0
         */
        protected static function renderValuePlaceholder()
        {
            echo '<span class="pp_editorial_metadata_not_set">';
            esc_html_e('Not set', 'publishpress');
            echo '</span>';
        }
    }
}
