<?php
/**
 * Endeavour Blocks custom post type.
 *
 * Reusable Elementor layouts stored outside the page tree, so they can be
 * maintained once and included where needed.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ENDEAVOUR_BLOCK_POST_TYPE', 'endeavour_block' );

/**
 * Register the Endeavour Blocks post type.
 */
function endeavour_child_register_block_post_type() {
	$labels = array(
		'name'                  => _x( 'Endeavour Blocks', 'post type general name', 'endeavour' ),
		'singular_name'         => _x( 'Endeavour Block', 'post type singular name', 'endeavour' ),
		'menu_name'             => _x( 'Endeavour Blocks', 'admin menu', 'endeavour' ),
		'name_admin_bar'        => _x( 'Endeavour Block', 'add new on admin bar', 'endeavour' ),
		'add_new'               => __( 'Add New', 'endeavour' ),
		'add_new_item'          => __( 'Add New Endeavour Block', 'endeavour' ),
		'new_item'              => __( 'New Endeavour Block', 'endeavour' ),
		'edit_item'             => __( 'Edit Endeavour Block', 'endeavour' ),
		'view_item'             => __( 'View Endeavour Block', 'endeavour' ),
		'view_items'            => __( 'View Endeavour Blocks', 'endeavour' ),
		'all_items'             => __( 'All Endeavour Blocks', 'endeavour' ),
		'search_items'          => __( 'Search Endeavour Blocks', 'endeavour' ),
		'parent_item_colon'     => __( 'Parent Endeavour Block:', 'endeavour' ),
		'not_found'             => __( 'No Endeavour Blocks found.', 'endeavour' ),
		'not_found_in_trash'    => __( 'No Endeavour Blocks found in Trash.', 'endeavour' ),
		'archives'              => __( 'Endeavour Block Archives', 'endeavour' ),
		'insert_into_item'      => __( 'Insert into Endeavour Block', 'endeavour' ),
		'uploaded_to_this_item' => __( 'Uploaded to this Endeavour Block', 'endeavour' ),
		'filter_items_list'     => __( 'Filter Endeavour Blocks list', 'endeavour' ),
		'items_list_navigation' => __( 'Endeavour Blocks list navigation', 'endeavour' ),
		'items_list'            => __( 'Endeavour Blocks list', 'endeavour' ),
		'item_published'        => __( 'Endeavour Block published.', 'endeavour' ),
		'item_updated'          => __( 'Endeavour Block updated.', 'endeavour' ),
	);

	register_post_type(
		ENDEAVOUR_BLOCK_POST_TYPE,
		array(
			'labels'              => $labels,
			'description'         => __( 'Reusable Elementor layouts for Endeavour.', 'endeavour' ),
			'public'              => false,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'exclude_from_search' => true,
			'has_archive'         => true,
			'rewrite'             => array(
				'slug'       => 'endeavour-block',
				'with_front' => false,
			),
			'query_var'           => true,
			'capability_type'     => 'page',
			'hierarchical'        => false,
			'menu_position'       => 21,
			'menu_icon'           => 'dashicons-screenoptions',
			'supports'            => array( 'title', 'editor', 'revisions', 'thumbnail', 'author' ),
			'show_in_rest'        => true,
		)
	);
}
add_action( 'init', 'endeavour_child_register_block_post_type' );

/**
 * Keep Elementor enabled for Endeavour Blocks regardless of the saved
 * Elementor → Settings → Post Types checklist.
 *
 * @param mixed $cpt_support Stored option value.
 * @return string[]
 */
function endeavour_child_enable_elementor_for_blocks( $cpt_support ) {
	if ( ! is_array( $cpt_support ) ) {
		$cpt_support = array( 'page', 'post' );
	}

	if ( ! in_array( ENDEAVOUR_BLOCK_POST_TYPE, $cpt_support, true ) ) {
		$cpt_support[] = ENDEAVOUR_BLOCK_POST_TYPE;
	}

	return $cpt_support;
}
add_filter( 'option_elementor_cpt_support', 'endeavour_child_enable_elementor_for_blocks' );
add_filter( 'default_option_elementor_cpt_support', 'endeavour_child_enable_elementor_for_blocks' );

/**
 * Flush rewrite rules once after this post type is introduced.
 */
function endeavour_child_flush_block_rewrite_rules() {
	endeavour_child_register_block_post_type();
	flush_rewrite_rules( false );
	update_option( 'endeavour_child_block_rewrite_flushed', endeavour_child_block_rewrite_token(), false );
}

/**
 * Changes when the block rewrite (including the archive) must be flushed once.
 */
function endeavour_child_block_rewrite_token() {
	return ENDEAVOUR_VERSION . '-archive';
}

function endeavour_child_maybe_flush_block_rewrite_rules() {
	if ( get_option( 'endeavour_child_block_rewrite_flushed' ) === endeavour_child_block_rewrite_token() ) {
		return;
	}

	endeavour_child_flush_block_rewrite_rules();
}
add_action( 'init', 'endeavour_child_maybe_flush_block_rewrite_rules', 20 );
add_action( 'after_switch_theme', 'endeavour_child_flush_block_rewrite_rules' );
