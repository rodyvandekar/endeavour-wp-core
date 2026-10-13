<?php
/**
 * Fixed slide width for the Elementor Loop Carousel.
 *
 * Slides on display only offers a count, so a card can never keep the width
 * from the design while the next card stays in view. Item width does both:
 * the slide is that wide, Slides on display becomes Auto, and the carousel
 * stops clipping the following slide.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add Auto to Slides on display, and the Item width control beside it.
 *
 * @param object $element Loop Carousel widget.
 */
function endeavour_register_loop_carousel_item_width( $element ) {
	$element->add_control(
		'endeavour_custom_slide_width',
		array(
			'label'        => esc_html__( 'Enable custom width loop item', 'endeavour' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => esc_html__( 'Yes', 'endeavour' ),
			'label_off'    => esc_html__( 'No', 'endeavour' ),
			'return_value' => 'yes',
			'default'      => '',
			'separator'    => 'before',
		)
	);

	$element->add_responsive_control(
		'endeavour_slide_width',
		array(
			'label'              => esc_html__( 'Custom width loop item', 'endeavour' ),
			'type'               => \Elementor\Controls_Manager::SLIDER,
			'size_units'         => array( 'px' ),
			'range'              => array(
				'px' => array(
					'min' => 120,
					'max' => 800,
				),
			),
			'selectors'          => array(
				'{{WRAPPER}}' => '--endeavour-slide-width: {{SIZE}}{{UNIT}};',
			),
			'frontend_available' => true,
			'condition'          => array(
				'endeavour_custom_slide_width' => 'yes',
			),
			'description'        => esc_html__(
				'Responsive fixed width for each loop item.',
				'endeavour'
			),
		)
	);
}
add_action(
	'elementor/element/loop-carousel/section_layout/before_section_end',
	'endeavour_register_loop_carousel_item_width'
);

/**
 * Add Auto to Slides on display.
 *
 * The layout section closes before Loop Carousel injects that select, so the
 * option has to be added once the next section starts and the control exists.
 *
 * @param object $element Loop Carousel widget.
 */
function endeavour_loop_carousel_add_auto_option( $element ) {
	foreach ( array( 'slides_to_show', 'slides_to_scroll' ) as $control_id ) {
		$control = $element->get_controls( $control_id );

		if ( empty( $control['options'] ) || isset( $control['options']['auto'] ) ) {
			continue;
		}

		$element->update_control(
			$control_id,
			array(
				'options' => array( 'auto' => esc_html__( 'Auto', 'endeavour' ) ) + $control['options'],
			)
		);
	}
}
add_action(
	'elementor/element/loop-carousel/section_query/before_section_start',
	'endeavour_loop_carousel_add_auto_option'
);

/**
 * Widths that are actually filled in, keyed by device.
 *
 * @param array $settings Widget settings for display.
 * @return array<string, string>
 */
function endeavour_loop_carousel_slide_widths( $settings ) {
	$widths = array();

	foreach ( array( 'desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile' ) as $device => $suffix ) {
		$value = $settings[ 'endeavour_slide_width' . $suffix ] ?? null;

		if ( ! is_array( $value ) || '' === ( $value['size'] ?? '' ) || null === $value['size'] ) {
			continue;
		}

		$unit              = $value['unit'] ?? 'px';
		$widths[ $device ] = $value['size'] . $unit;
	}

	return $widths;
}

/**
 * Mark a carousel whose slides have a fixed width.
 *
 * @param object $widget Widget being rendered.
 */
function endeavour_loop_carousel_mark_fixed_width( $widget ) {
	if ( 'loop-carousel' !== $widget->get_name() ) {
		return;
	}

	$settings = $widget->get_settings_for_display();

	// Existing carousels created before the switch keep working until saved.
	if (
		array_key_exists( 'endeavour_custom_slide_width', $settings )
		&& 'yes' !== $settings['endeavour_custom_slide_width']
	) {
		return;
	}

	$widths = endeavour_loop_carousel_slide_widths( $settings );

	if ( ! $widths ) {
		return;
	}

	$widget->add_render_attribute( '_wrapper', 'class', 'endeavour-fixed-slide-width' );
	$widget->add_render_attribute( '_wrapper', 'data-endeavour-slide-width', wp_json_encode( $widths ) );
}
add_action( 'elementor/frontend/widget/before_render', 'endeavour_loop_carousel_mark_fixed_width' );

/**
 * Tell Swiper to size slides from their own width.
 */
function endeavour_loop_carousel_enqueue_script() {
	$path = endeavour_path() . '/assets/js/elementor-loop-carousel.js';

	if ( ! is_file( $path ) ) {
		return;
	}

	wp_enqueue_script(
		'endeavour-loop-carousel',
		endeavour_url() . '/assets/js/elementor-loop-carousel.js',
		array( 'elementor-frontend' ),
		(string) filemtime( $path ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'endeavour_loop_carousel_enqueue_script', 30 );
add_action( 'elementor/frontend/after_enqueue_scripts', 'endeavour_loop_carousel_enqueue_script' );

/**
 * Let a logo carousel size each slide from the image.
 */
function endeavour_image_carousel_enqueue_script() {
	$path = endeavour_path() . '/assets/js/elementor-image-carousel.js';

	if ( ! is_file( $path ) ) {
		return;
	}

	wp_enqueue_script(
		'endeavour-image-carousel',
		endeavour_url() . '/assets/js/elementor-image-carousel.js',
		array( 'elementor-frontend' ),
		(string) filemtime( $path ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'endeavour_image_carousel_enqueue_script', 30 );
add_action( 'elementor/frontend/after_enqueue_scripts', 'endeavour_image_carousel_enqueue_script' );

/**
 * When Item width is filled in, switch Slides on display to Auto in the panel.
 */
function endeavour_loop_carousel_enqueue_editor_script() {
	$path = endeavour_path() . '/assets/js/elementor-loop-carousel-editor.js';

	if ( ! is_file( $path ) ) {
		return;
	}

	wp_enqueue_script(
		'endeavour-loop-carousel-editor',
		endeavour_url() . '/assets/js/elementor-loop-carousel-editor.js',
		array( 'elementor-editor' ),
		(string) filemtime( $path ),
		true
	);
}
add_action( 'elementor/editor/after_enqueue_scripts', 'endeavour_loop_carousel_enqueue_editor_script' );
