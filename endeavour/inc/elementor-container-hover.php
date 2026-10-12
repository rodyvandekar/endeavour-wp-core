<?php
/**
 * Hover switch for Elementor containers.
 *
 * Layout > Container > "Enable container hover styling". Yes adds the class
 * `endeavour-container-hover-yes`. The shadow itself is not set here: it lives
 * once in assets/scss/_tokens.scss as `$card-hover-shadow`, and the stylesheet
 * applies it. Flip the switch per container. Change the shadow in that one token.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the hover switch to Container > Layout > Container.
 *
 * @param object $element Elementor container instance.
 */
function endeavour_register_container_hover_switch( $element ) {
	$element->add_control(
		'endeavour_container_hover',
		array(
			'label'        => esc_html__( 'Enable container hover styling', 'endeavour' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => esc_html__( 'Yes', 'endeavour' ),
			'label_off'    => esc_html__( 'No', 'endeavour' ),
			'return_value' => 'yes',
			'default'      => '',
			'prefix_class' => 'endeavour-container-hover-',
			'separator'    => 'before',
			'description'  => esc_html__(
				'Shows the shared card hover shadow. The shadow is set once in the theme, so you only turn this on or off.',
				'endeavour'
			),
		)
	);
}
add_action(
	'elementor/element/container/section_layout_container/before_section_end',
	'endeavour_register_container_hover_switch'
);
