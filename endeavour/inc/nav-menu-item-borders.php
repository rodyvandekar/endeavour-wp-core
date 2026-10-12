<?php
/**
 * Optional borders for individual navigation menu items.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add Endeavour border controls to Appearance > Menus.
 *
 * @param int     $item_id Menu item ID.
 * @param WP_Post $item    Menu item.
 */
function endeavour_child_menu_item_border_fields( $item_id, $item ) {
	$border_top    = (bool) get_post_meta( $item_id, '_endeavour_border_top', true );
	$border_bottom = (bool) get_post_meta( $item_id, '_endeavour_border_bottom', true );
	?>
	<fieldset class="field-endeavour-borders description-wide">
		<legend><?php esc_html_e( 'Endeavour borders', 'endeavour' ); ?></legend>
		<label>
			<input
				type="checkbox"
				name="endeavour_menu_item_border_top[<?php echo esc_attr( $item_id ); ?>]"
				value="1"
				<?php checked( $border_top ); ?>
			>
			<?php esc_html_e( 'Border top', 'endeavour' ); ?>
		</label>
		&nbsp;&nbsp;
		<label>
			<input
				type="checkbox"
				name="endeavour_menu_item_border_bottom[<?php echo esc_attr( $item_id ); ?>]"
				value="1"
				<?php checked( $border_bottom ); ?>
			>
			<?php esc_html_e( 'Border bottom', 'endeavour' ); ?>
		</label>
	</fieldset>
	<?php
}
add_action( 'wp_nav_menu_item_custom_fields', 'endeavour_child_menu_item_border_fields', 10, 2 );

/**
 * Save the two menu-item checkboxes.
 *
 * @param int $menu_id         Menu term ID.
 * @param int $menu_item_db_id Menu item post ID.
 */
function endeavour_child_save_menu_item_borders( $menu_id, $menu_item_db_id ) {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$fields = array(
		'_endeavour_border_top'    => 'endeavour_menu_item_border_top',
		'_endeavour_border_bottom' => 'endeavour_menu_item_border_bottom',
	);

	foreach ( $fields as $meta_key => $request_key ) {
		if ( isset( $_POST[ $request_key ][ $menu_item_db_id ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Core verifies the menu form nonce.
			update_post_meta( $menu_item_db_id, $meta_key, '1' );
		} else {
			delete_post_meta( $menu_item_db_id, $meta_key );
		}
	}
}
add_action( 'wp_update_nav_menu_item', 'endeavour_child_save_menu_item_borders', 10, 2 );

/**
 * Expose saved borders as classes on the menu-item list element.
 *
 * @param string[] $classes Menu item classes.
 * @param WP_Post  $item    Menu item.
 * @return string[]
 */
function endeavour_child_menu_item_border_classes( $classes, $item ) {
	if ( get_post_meta( $item->ID, '_endeavour_border_top', true ) ) {
		$classes[] = 'endeavour-menu-border-top';
	}

	if ( get_post_meta( $item->ID, '_endeavour_border_bottom', true ) ) {
		$classes[] = 'endeavour-menu-border-bottom';
	}

	return $classes;
}
add_filter( 'nav_menu_css_class', 'endeavour_child_menu_item_border_classes', 10, 2 );
