<?php
/**
 * Gravity Forms submit note.
 *
 * The Endeavour Gravity Form widget sets the sentence beside the submit
 * button. A shortcode embed still uses the form description, so existing
 * forms keep their note until they move to the widget. Embed those with
 * `description="false"`, otherwise the text also appears above the form.
 *
 * Ajax re-renders the form without the widget, so the widget leaves its
 * post and element id in the form and the note is read back from the
 * saved Elementor data.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget render currently in progress.
 *
 * @var array{post_id: int, element_id: string, note: string}|null
 */
$endeavour_gf_render_context = null;

/**
 * Open the widget render context for the form about to be printed.
 *
 * @param int    $post_id    Post that stores the Elementor data.
 * @param string $element_id Elementor element id of the widget.
 * @param string $note       Note text from the widget setting.
 */
function endeavour_gf_render_context_open( $post_id, $element_id, $note ) {
	global $endeavour_gf_render_context;

	$endeavour_gf_render_context = array(
		'post_id'    => (int) $post_id,
		'element_id' => (string) $element_id,
		'note'       => trim( (string) $note ),
	);
}

/**
 * Close the widget render context.
 */
function endeavour_gf_render_context_close() {
	global $endeavour_gf_render_context;

	$endeavour_gf_render_context = null;
}

/**
 * Note context for this render, or null when the form is a plain shortcode.
 *
 * @param array $form Gravity Forms form. Unused; kept for call-site clarity.
 * @return array{post_id: int, element_id: string, note: string}|null
 */
function endeavour_gf_note_context( $form ) {
	unset( $form );

	global $endeavour_gf_render_context;

	if ( is_array( $endeavour_gf_render_context ) ) {
		return $endeavour_gf_render_context;
	}

	if ( empty( $_POST['endeavour_gf_post'] ) || empty( $_POST['endeavour_gf_widget'] ) ) {
		return null;
	}

	$post_id    = absint( wp_unslash( $_POST['endeavour_gf_post'] ) );
	$element_id = sanitize_key( wp_unslash( $_POST['endeavour_gf_widget'] ) );
	$settings   = endeavour_gf_widget_settings( $post_id, $element_id );

	if ( ! is_array( $settings ) ) {
		return null;
	}

	return array(
		'post_id'    => $post_id,
		'element_id' => $element_id,
		'note'       => isset( $settings['submit_note'] ) ? trim( (string) $settings['submit_note'] ) : '',
	);
}

/**
 * Settings of an Endeavour Gravity Form widget stored on a post.
 *
 * @param int    $post_id    Post id.
 * @param string $element_id Elementor element id.
 * @return array<string, mixed>|null Null when that widget is not on the post.
 */
function endeavour_gf_widget_settings( $post_id, $element_id ) {
	if ( $post_id < 1 || '' === $element_id ) {
		return null;
	}

	$data = get_post_meta( $post_id, '_elementor_data', true );

	if ( is_string( $data ) ) {
		$data = json_decode( $data, true );
	}

	if ( ! is_array( $data ) ) {
		return null;
	}

	return endeavour_gf_find_widget_settings( $data, $element_id );
}

/**
 * Walk an Elementor tree for the widget.
 *
 * @param array  $elements   Elementor elements.
 * @param string $element_id Element id.
 * @return array<string, mixed>|null
 */
function endeavour_gf_find_widget_settings( array $elements, $element_id ) {
	foreach ( $elements as $element ) {
		if ( ! is_array( $element ) ) {
			continue;
		}

		if ( ( $element['id'] ?? '' ) === $element_id ) {
			return ( 'endeavour-gravity-form' === ( $element['widgetType'] ?? '' ) )
				? (array) ( $element['settings'] ?? array() )
				: null;
		}

		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$found = endeavour_gf_find_widget_settings( $element['elements'], $element_id );

			if ( null !== $found ) {
				return $found;
			}
		}
	}

	return null;
}

/**
 * Append the note, and the widget identity the next ajax render needs.
 *
 * @param string $button Submit button markup.
 * @param array  $form   Gravity Forms form.
 * @return string
 */
function endeavour_gravity_forms_submit_note( $button, $form ) {
	$context = endeavour_gf_note_context( $form );

	if ( null === $context ) {
		$note = isset( $form['description'] ) ? trim( wp_strip_all_tags( (string) $form['description'] ) ) : '';

		return '' === $note ? $button : $button . endeavour_gf_note_markup( $note );
	}

	$hidden  = '<input type="hidden" name="endeavour_gf_post" value="' . esc_attr( (string) $context['post_id'] ) . '">';
	$hidden .= '<input type="hidden" name="endeavour_gf_widget" value="' . esc_attr( $context['element_id'] ) . '">';

	return $button . $hidden . ( '' === $context['note'] ? '' : endeavour_gf_note_markup( $context['note'] ) );
}
add_filter( 'gform_submit_button', 'endeavour_gravity_forms_submit_note', 10, 2 );

/**
 * The note paragraph.
 *
 * @param string $note Plain note text. Line breaks are kept.
 * @return string
 */
function endeavour_gf_note_markup( $note ) {
	return '<p class="endeavour-form-note">' . nl2br( esc_html( $note ), false ) . '</p>';
}
