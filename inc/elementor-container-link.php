<?php
/**
 * Whole-container link for Loop Item containers.
 *
 * Layout > Container > "Create a link within this container". Yes prints an
 * empty anchor as the container's first child, stretched over the container by
 * `assets/scss/elementor-container-link.scss`. Every other link and control in
 * the container is lifted above it, so the title, category and buttons keep
 * their own destinations.
 *
 * This is different from Elementor's own container Link: that turns the
 * container itself into an `<a>`, and an `<a>` may not contain other links.
 *
 * The overlay is hidden from assistive tech and the tab order. The card is
 * expected to keep a real, labelled link of its own (the title), which is what
 * a keyboard or screen reader user follows.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the editor is working on a Loop Item template.
 *
 * Outside the editor the control is always registered, so a Loop Grid on any
 * page can render the setting it was saved with.
 *
 * @return bool
 */
function endeavour_container_link_is_editing_other_document() {
	if ( ! class_exists( '\Elementor\Plugin' ) ) {
		return false;
	}

	$plugin = \Elementor\Plugin::$instance;

	$is_editor = ( isset( $plugin->editor ) && $plugin->editor->is_edit_mode() )
		|| ( isset( $plugin->preview ) && $plugin->preview->is_preview_mode() );

	if ( ! $is_editor ) {
		return false;
	}

	$document = $plugin->documents->get_current();

	return $document && 'loop-item' !== $document->get_name();
}

/**
 * Add the switch and its URL to Container > Layout > Container.
 *
 * Outside a Loop Item the controls are still registered, as Hidden, so a
 * container that already carries the setting keeps it on save.
 *
 * @param object $element Elementor container instance.
 */
function endeavour_register_container_link_controls( $element ) {
	if ( endeavour_container_link_is_editing_other_document() ) {
		$element->add_control(
			'endeavour_container_link',
			array(
				'type'    => \Elementor\Controls_Manager::HIDDEN,
				'default' => '',
			)
		);
		$element->add_control(
			'endeavour_container_link_url',
			array(
				'type'    => \Elementor\Controls_Manager::HIDDEN,
				'default' => array(),
			)
		);
		return;
	}

	$element->add_control(
		'endeavour_container_link',
		array(
			'label'        => esc_html__( 'Create a link within this container', 'endeavour' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => esc_html__( 'Yes', 'endeavour' ),
			'label_off'    => esc_html__( 'No', 'endeavour' ),
			'return_value' => 'yes',
			'default'      => '',
			'prefix_class' => 'endeavour-container-link-',
			'separator'    => 'before',
			'description'  => esc_html__(
				'Makes the whole container clickable. Links and buttons inside it keep working and stay on top. Shown on the live page, not in the editor.',
				'endeavour'
			),
		)
	);

	$element->add_control(
		'endeavour_container_link_url',
		array(
			'label'       => esc_html__( 'Container link', 'endeavour' ),
			'type'        => \Elementor\Controls_Manager::URL,
			'dynamic'     => array( 'active' => true ),
			'placeholder' => esc_html__( 'Empty: the item\'s own page', 'endeavour' ),
			'default'     => array( 'url' => '' ),
			'condition'   => array( 'endeavour_container_link' => 'yes' ),
		)
	);
}
add_action(
	'elementor/element/container/section_layout_container/before_section_end',
	'endeavour_register_container_link_controls'
);

/**
 * A linked container has to render again for every loop item.
 *
 * Elementor treats a container as static. The first loop item is stored, and
 * later items only re-render the widgets inside it. The overlay would keep the
 * first item's address. Marking the container dynamic makes Elementor run it
 * once per item, while the current post is that item.
 *
 * @param bool   $is_dynamic Whether Elementor already treats the element as dynamic.
 * @param array  $raw_data   Element data.
 * @param object $element    Element instance.
 * @return bool
 */
function endeavour_container_link_is_dynamic_content( $is_dynamic, $raw_data, $element ) {
	unset( $element );

	if ( $is_dynamic ) {
		return true;
	}

	$settings = isset( $raw_data['settings'] ) && is_array( $raw_data['settings'] ) ? $raw_data['settings'] : array();

	return isset( $settings['endeavour_container_link'] ) && 'yes' === $settings['endeavour_container_link'];
}
add_filter( 'elementor/element/is_dynamic_content', 'endeavour_container_link_is_dynamic_content', 10, 3 );

/**
 * Remember which loop item a linked container belongs to.
 *
 * Pass an id to push it. Call with no argument to pop the latest id.
 *
 * @param int|null $post_id Post id to push, or null to pop.
 * @return int
 */
function endeavour_container_link_post_stack( $post_id = null ) {
	static $stack = array();

	if ( null === $post_id ) {
		return (int) array_pop( $stack );
	}

	$stack[] = (int) $post_id;

	return (int) $post_id;
}

/**
 * Buffer a linked container so the anchor can go inside its opening tag.
 *
 * Elementor prints the opening tag in `before_render()` and exposes no hook
 * between that tag and the children, so the markup is captured whole and the
 * anchor inserted after the first `>`. Attribute values are escaped by
 * Elementor, so that `>` is always the end of the opening tag.
 *
 * @param object $element Elementor container instance.
 */
function endeavour_container_link_start( $element ) {
	if ( 'yes' !== $element->get_settings_for_display( 'endeavour_container_link' ) ) {
		return;
	}

	// Elementor renders the children before after_render, and a loop item
	// resets the global post while doing that. Remember the item now.
	endeavour_container_link_post_stack( get_the_ID() );

	ob_start();
}
add_action( 'elementor/frontend/container/before_render', 'endeavour_container_link_start' );

/**
 * Print the captured container with its overlay link.
 *
 * @param object $element Elementor container instance.
 */
function endeavour_container_link_end( $element ) {
	if ( 'yes' !== $element->get_settings_for_display( 'endeavour_container_link' ) ) {
		return;
	}

	$html    = (string) ob_get_clean();
	$link    = (array) $element->get_settings_for_display( 'endeavour_container_link_url' );
	$post_id = endeavour_container_link_post_stack();
	$url     = ! empty( $link['url'] ) ? $link['url'] : get_permalink( $post_id );

	$end = strpos( $html, '>' );

	if ( ! $url || false === $end ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's own rendered markup.
		echo $html;
		return;
	}

	$rel = array();

	if ( ! empty( $link['nofollow'] ) ) {
		$rel[] = 'nofollow';
	}

	$attributes = sprintf(
		' class="endeavour-container-link" href="%s" tabindex="-1" aria-hidden="true"',
		esc_url( $url )
	);

	if ( ! empty( $link['is_external'] ) ) {
		$attributes .= ' target="_blank"';
		$rel[]       = 'noopener';
	}

	if ( $rel ) {
		$attributes .= sprintf( ' rel="%s"', esc_attr( implode( ' ', $rel ) ) );
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's markup plus an anchor escaped above.
	echo substr( $html, 0, $end + 1 ) . '<a' . $attributes . '></a>' . substr( $html, $end + 1 );
}
add_action( 'elementor/frontend/container/after_render', 'endeavour_container_link_end' );
