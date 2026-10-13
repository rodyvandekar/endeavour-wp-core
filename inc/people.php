<?php
/**
 * People custom post type and its ACF fields.
 *
 * The People block (Figma 1872:7079) is an Elementor loop grid over this
 * post type. The name is the post title. Photo, role, bio, phone and email
 * are ACF fields, so a loop item can bind them with Elementor's ACF tags.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ENDEAVOUR_PERSON_POST_TYPE', 'person' );

/**
 * Register the People post type.
 *
 * Public in the admin and in Elementor's query list, without its own URLs:
 * a person only appears inside a loop.
 */
function endeavour_child_register_person_post_type() {
	$labels = array(
		'name'               => _x( 'People', 'post type general name', 'endeavour' ),
		'singular_name'      => _x( 'Person', 'post type singular name', 'endeavour' ),
		'menu_name'          => _x( 'People', 'admin menu', 'endeavour' ),
		'name_admin_bar'     => _x( 'Person', 'add new on admin bar', 'endeavour' ),
		'add_new'            => __( 'Add New', 'endeavour' ),
		'add_new_item'       => __( 'Add New Person', 'endeavour' ),
		'new_item'           => __( 'New Person', 'endeavour' ),
		'edit_item'          => __( 'Edit Person', 'endeavour' ),
		'view_item'          => __( 'View Person', 'endeavour' ),
		'all_items'          => __( 'All People', 'endeavour' ),
		'search_items'       => __( 'Search People', 'endeavour' ),
		'not_found'          => __( 'No people found.', 'endeavour' ),
		'not_found_in_trash' => __( 'No people found in Trash.', 'endeavour' ),
		'item_published'     => __( 'Person published.', 'endeavour' ),
		'item_updated'       => __( 'Person updated.', 'endeavour' ),
	);

	register_post_type(
		ENDEAVOUR_PERSON_POST_TYPE,
		array(
			'labels'              => $labels,
			'description'         => __( 'People shown in the People loop.', 'endeavour' ),
			'public'              => true,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => true,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'capability_type'     => 'post',
			'hierarchical'        => false,
			'menu_position'       => 22,
			'menu_icon'           => 'dashicons-groups',
			'supports'            => array( 'title', 'thumbnail', 'revisions', 'page-attributes' ),
			'show_in_rest'        => true,
		)
	);
}
add_action( 'init', 'endeavour_child_register_person_post_type' );

/**
 * Elementor lists queryable post types from show_in_nav_menus.
 * People stay out of menus, so add the type to the loop source list directly.
 */
function endeavour_child_elementor_person_post_type( $post_types ) {
	$post_types[ ENDEAVOUR_PERSON_POST_TYPE ] = __( 'People', 'endeavour' );

	return $post_types;
}
add_filter( 'elementor_pro/utils/get_public_post_types', 'endeavour_child_elementor_person_post_type' );

/**
 * Flush rewrite rules once, so the new post type is queryable by the loop.
 */
function endeavour_child_maybe_flush_person_rewrite_rules() {
	if ( get_option( 'endeavour_child_person_rewrite_flushed' ) === ENDEAVOUR_VERSION ) {
		return;
	}

	endeavour_child_register_person_post_type();
	flush_rewrite_rules( false );
	update_option( 'endeavour_child_person_rewrite_flushed', ENDEAVOUR_VERSION, false );
}
add_action( 'init', 'endeavour_child_maybe_flush_person_rewrite_rules', 20 );

/**
 * ACF field group for a person.
 *
 * Field keys stay stable so the loop item's dynamic tags keep resolving.
 */
function endeavour_child_register_person_fields() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_endeavour_person',
			'title'    => __( 'Person', 'endeavour' ),
			'fields'   => array(
				array(
					'key'           => 'field_endeavour_person_photo',
					'label'         => __( 'Photo', 'endeavour' ),
					'name'          => 'person_photo',
					'type'          => 'image',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'instructions'  => __( 'Square photo. The card shows the placeholder when this is empty.', 'endeavour' ),
				),
				array(
					'key'   => 'field_endeavour_person_role',
					'label' => __( 'Role', 'endeavour' ),
					'name'  => 'person_role',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_endeavour_person_bio',
					'label' => __( 'Bio', 'endeavour' ),
					'name'  => 'person_bio',
					'type'  => 'textarea',
					'rows'  => 3,
					'new_lines' => '',
				),
				array(
					'key'          => 'field_endeavour_person_phone',
					'label'        => __( 'Phone', 'endeavour' ),
					'name'         => 'person_phone',
					'type'         => 'text',
					'instructions' => __( 'Shown as written. The card links it as a phone number.', 'endeavour' ),
				),
				array(
					'key'   => 'field_endeavour_person_email',
					'label' => __( 'Email', 'endeavour' ),
					'name'  => 'person_email',
					'type'  => 'email',
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => ENDEAVOUR_PERSON_POST_TYPE,
					),
				),
			),
			'position' => 'normal',
			'style'    => 'default',
			'active'   => true,
		)
	);
}
add_action( 'acf/init', 'endeavour_child_register_person_fields' );

/**
 * The three cards in Figma 1872:7079, so the loop has something to show.
 * Runs once. People have no public URL; deleting them does not bring them back.
 */
function endeavour_child_seed_people() {
	if ( get_option( 'endeavour_person_seeded' ) || ! function_exists( 'update_field' ) ) {
		return;
	}

	$existing = get_posts(
		array(
			'post_type'      => ENDEAVOUR_PERSON_POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);

	if ( $existing ) {
		update_option( 'endeavour_person_seeded', '1', false );
		return;
	}

	$shared = array(
		'person_role'  => 'Role',
		'person_bio'   => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
		'person_phone' => '06 12345678',
		'person_email' => 'name@example.com',
	);

	foreach ( array( 'john-malkovich', 'john-malkovich-2', 'john-malkovich-3' ) as $index => $slug ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => ENDEAVOUR_PERSON_POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'John Malkovich',
				'post_name'   => $slug,
				'menu_order'  => $index + 1,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return;
		}

		foreach ( $shared as $name => $value ) {
			update_field( $name, $value, $post_id );
		}
	}

	update_option( 'endeavour_person_seeded', '1', false );
}
add_action( 'acf/init', 'endeavour_child_seed_people', 20 );

/**
 * Phone link for the loop card.
 *
 * ACF's URL tag turns an email field into mailto:, but a text phone number
 * stays plain text. This tag reads the same phone field and returns tel:.
 * The class is declared here because the parent class only exists once
 * Elementor has loaded.
 */
function endeavour_child_register_person_phone_tag( $dynamic_tags ) {
	if ( ! class_exists( 'Endeavour_Person_Phone_Link', false ) ) {
		/**
		 * Dynamic tag: person phone as a tel: link.
		 */
		class Endeavour_Person_Phone_Link extends \Elementor\Core\DynamicTags\Data_Tag {

			public function get_name() {
				return 'endeavour-person-phone';
			}

			public function get_title() {
				return esc_html__( 'Person phone', 'endeavour' );
			}

			public function get_group() {
				return 'post';
			}

			public function get_categories() {
				return array( \Elementor\Modules\DynamicTags\Module::URL_CATEGORY );
			}

			public function get_value( array $options = array() ) {
				if ( ! function_exists( 'get_field' ) ) {
					return '';
				}

				$phone = (string) get_field( 'person_phone' );
				$dial  = preg_replace( '/[^\d+]/', '', $phone );

				if ( '' === $dial ) {
					return '';
				}

				return 'tel:' . $dial;
			}
		}
	}

	$dynamic_tags->register( new Endeavour_Person_Phone_Link() );
}
add_action( 'elementor/dynamic_tags/register', 'endeavour_child_register_person_phone_tag' );
