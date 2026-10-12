<?php
/**
 * Collapsible Read more support for Elementor's Text Editor widget.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add Read more controls to every Text Editor widget.
 *
 * @param \Elementor\Widget_Base $element Text Editor widget instance.
 */
function endeavour_add_text_read_more_controls( $element ) {
	$element->add_control(
		'endeavour_enable_read_more',
		array(
			'label'        => esc_html__( 'Enable read more', 'endeavour' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
			'separator'    => 'before',
		)
	);

	$element->add_control(
		'endeavour_read_more_lines',
		array(
			'label'      => esc_html__( 'Number of lines', 'endeavour' ),
			'type'       => \Elementor\Controls_Manager::NUMBER,
			'min'        => 1,
			'step'       => 1,
			'default'    => 3,
			'condition'  => array(
				'endeavour_enable_read_more' => 'yes',
			),
			'selectors'  => array(
				'{{WRAPPER}}' => '--endv-read-more-lines: {{VALUE}}',
			),
		)
	);

	$element->add_control(
		'endeavour_read_more_text',
		array(
			'label'       => esc_html__( 'Read more text', 'endeavour' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => esc_html__( 'Read more', 'endeavour' ),
			'label_block' => true,
			'condition'   => array(
				'endeavour_enable_read_more' => 'yes',
			),
		)
	);

	$element->add_control(
		'endeavour_read_less_text',
		array(
			'label'       => esc_html__( 'Read less text', 'endeavour' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => esc_html__( 'Read less', 'endeavour' ),
			'label_block' => true,
			'condition'   => array(
				'endeavour_enable_read_more' => 'yes',
			),
		)
	);
}
add_action(
	'elementor/element/text-editor/section_editor/before_section_end',
	'endeavour_add_text_read_more_controls'
);

/**
 * Wrap enabled Text Editor output and append its accessible toggle.
 *
 * JavaScript only applies the clamp when the text actually overflows, so a
 * short text gets neither truncation nor a redundant button.
 *
 * @param string                 $content Rendered widget content.
 * @param \Elementor\Widget_Base $widget  Current Elementor widget.
 * @return string
 */
function endeavour_render_text_read_more( $content, $widget ) {
	if ( 'text-editor' !== $widget->get_name() ) {
		return $content;
	}

	$settings = $widget->get_settings_for_display();

	if ( 'yes' !== ( $settings['endeavour_enable_read_more'] ?? '' ) ) {
		return $content;
	}

	$more_text = trim( (string) ( $settings['endeavour_read_more_text'] ?? '' ) );
	$less_text = trim( (string) ( $settings['endeavour_read_less_text'] ?? '' ) );
	$more_text = '' !== $more_text ? $more_text : esc_html__( 'Read more', 'endeavour' );
	$less_text = '' !== $less_text ? $less_text : esc_html__( 'Read less', 'endeavour' );
	$content_id = 'endv-read-more-' . sanitize_html_class( $widget->get_id() );

	$icon = '<svg class="endeavour-read-more__icon" aria-hidden="true" viewBox="0 0 20 20" focusable="false">'
		. '<path d="M5 7.5L10 12.5L15 7.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/>'
		. '</svg>';

	return sprintf(
		'<div class="endeavour-read-more__teaser" hidden></div>'
		. '<div id="%1$s" class="endeavour-read-more__content">%2$s</div>'
		. '<button class="endeavour-read-more__toggle" type="button" aria-expanded="false" aria-controls="%1$s" data-more-text="%3$s" data-less-text="%4$s" hidden>'
		. '<span class="endeavour-read-more__label">%5$s</span>%6$s</button>',
		esc_attr( $content_id ),
		$content,
		esc_attr( $more_text ),
		esc_attr( $less_text ),
		esc_html( $more_text ),
		$icon // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	);
}
add_filter( 'elementor/widget/render_content', 'endeavour_render_text_read_more', 10, 2 );

/**
 * Load Read more behaviour after Elementor's frontend runtime.
 */
function endeavour_enqueue_text_read_more_script() {
	$relative_path = '/assets/js/elementor-text-read-more.js';
	$absolute_path = endeavour_path() . $relative_path;

	if ( ! is_file( $absolute_path ) ) {
		return;
	}

	wp_enqueue_script(
		'endeavour-elementor-text-read-more',
		endeavour_url() . $relative_path,
		array( 'elementor-frontend' ),
		(string) filemtime( $absolute_path ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'endeavour_enqueue_text_read_more_script', 30 );
