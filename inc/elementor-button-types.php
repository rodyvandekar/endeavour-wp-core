<?php
/**
 * Endeavour button types for Elementor's core Button widget.
 *
 * Replaces Elementor's generic Default / Info / Success / Warning / Danger
 * choices with the three variants from the Endeavour design system.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replace the options in Button > Content > Type.
 *
 * The existing `prefix_class` remains untouched, so Elementor adds classes
 * such as `elementor-button-endeavour-prominent` to the widget wrapper.
 *
 * @param object $element Elementor Button widget instance.
 */
function endeavour_register_elementor_button_types( $element ) {
	$element->update_control(
		'button_type',
		array(
			'default' => 'endeavour-default',
			'options' => array(
				'endeavour-default'   => esc_html__( 'Default', 'endeavour' ),
				'endeavour-prominent' => esc_html__( 'Prominent', 'endeavour' ),
				'endeavour-link'      => esc_html__( 'Link', 'endeavour' ),
			),
		)
	);

	// Figma puts 8px between icon and label. Setting the control default keeps
	// the spacing adjustable in the editor instead of frozen in the stylesheet.
	$element->update_control(
		'icon_indent',
		array(
			'default' => array(
				'unit' => 'px',
				'size' => 8,
			),
		)
	);

	$element->add_control(
		'endeavour_aria_label',
		array(
			'label'       => esc_html__( 'Accessible label', 'endeavour' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'description' => esc_html__(
				'Required for an icon-only button. Describe the action, for example “Next slide”.',
				'endeavour'
			),
			'condition'   => array(
				'text'                      => '',
				'selected_icon[value]!'     => '',
			),
		)
	);

	$element->add_control(
		'endeavour_disabled',
		array(
			'label'        => esc_html__( 'Disabled', 'endeavour' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
		)
	);
}
add_action(
	'elementor/element/button/section_button/before_section_end',
	'endeavour_register_elementor_button_types'
);

/**
 * Set the hover transition to the design system's 100ms.
 *
 * Elementor ships Transition Duration empty, which leaves its own
 * `transition: all .3s` on `.elementor-button` in charge — three times longer
 * than the design asks for. Setting the control's default rather than writing
 * the number into the stylesheet keeps it an Elementor setting: it shows up in
 * Style > Button > Hover, and a single button can still deviate.
 *
 * Only the duration. The curve has no control behind it and lives in
 * `assets/scss/elementor-button-types.scss`, as does the pressed state, which
 * has to land in the frame it happens in.
 *
 * @param object $element Elementor Button widget instance.
 */
function endeavour_set_elementor_button_transition( $element ) {
	$element->update_control(
		'button_hover_transition_duration',
		array(
			'default' => array(
				'unit' => 'ms',
				'size' => 100,
			),
		)
	);
}
add_action(
	'elementor/element/button/section_style/before_section_end',
	'endeavour_set_elementor_button_transition'
);

/**
 * Apply accessibility and disabled-state render attributes.
 *
 * @param object $widget Elementor widget instance.
 */
function endeavour_prepare_button_render_attributes( $widget ) {
	if ( 'button' !== $widget->get_name() ) {
		return;
	}

	$settings = $widget->get_settings();
	$label    = isset( $settings['endeavour_aria_label'] )
		? trim( (string) $settings['endeavour_aria_label'] )
		: '';

	if ( 'yes' === ( $settings['endeavour_disabled'] ?? '' ) ) {
		/*
		 * Remove the URL at render time only. Elementor will output a
		 * non-activating anchor with role="button"; the saved link is retained
		 * and returns when the Disabled switch is turned off.
		 */
		$widget->set_settings( 'link', array( 'url' => '' ) );
		$widget->reset_render_state();
		$widget->add_render_attribute(
			'button',
			array(
				'aria-disabled' => 'true',
				'class'         => 'is-disabled',
				'tabindex'      => '-1',
			)
		);
	}

	if ( '' === (string) ( $settings['text'] ?? '' ) && '' !== $label ) {
		$widget->add_render_attribute(
			'button',
			'aria-label',
			sanitize_text_field( $label )
		);
	}
}
add_action(
	'elementor/widget/before_render_content',
	'endeavour_prepare_button_render_attributes'
);
