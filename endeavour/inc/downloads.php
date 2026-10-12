<?php
/**
 * Downloads custom post type and its file field.
 *
 * The Downloads block (Figma 1872:7086) is an Elementor loop grid over this
 * post type. The name on the card is the post title. The file itself is one
 * ACF file field; the type and the size are read from that file, and the
 * button downloads it. The language dropdown in the design is a WPML choice
 * and is not part of this post type.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ENDEAVOUR_DOWNLOAD_POST_TYPE', 'download' );

/**
 * Register the Downloads post type.
 *
 * Public in the admin and in Elementor's query list, without its own URLs:
 * a download only appears inside a loop.
 */
function endeavour_child_register_download_post_type() {
	$labels = array(
		'name'               => _x( 'Downloads', 'post type general name', 'endeavour' ),
		'singular_name'      => _x( 'Download', 'post type singular name', 'endeavour' ),
		'menu_name'          => _x( 'Downloads', 'admin menu', 'endeavour' ),
		'name_admin_bar'     => _x( 'Download', 'add new on admin bar', 'endeavour' ),
		'add_new'            => __( 'Add New', 'endeavour' ),
		'add_new_item'       => __( 'Add New Download', 'endeavour' ),
		'new_item'           => __( 'New Download', 'endeavour' ),
		'edit_item'          => __( 'Edit Download', 'endeavour' ),
		'view_item'          => __( 'View Download', 'endeavour' ),
		'all_items'          => __( 'All Downloads', 'endeavour' ),
		'search_items'       => __( 'Search Downloads', 'endeavour' ),
		'not_found'          => __( 'No downloads found.', 'endeavour' ),
		'not_found_in_trash' => __( 'No downloads found in Trash.', 'endeavour' ),
		'item_published'     => __( 'Download published.', 'endeavour' ),
		'item_updated'       => __( 'Download updated.', 'endeavour' ),
	);

	register_post_type(
		ENDEAVOUR_DOWNLOAD_POST_TYPE,
		array(
			'labels'              => $labels,
			'description'         => __( 'Files shown in the Downloads loop.', 'endeavour' ),
			'public'              => true,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'capability_type'     => 'post',
			'hierarchical'        => false,
			'menu_position'       => 23,
			'menu_icon'           => 'dashicons-download',
			'supports'            => array( 'title', 'revisions', 'page-attributes' ),
			'show_in_rest'        => true,
		)
	);
}
add_action( 'init', 'endeavour_child_register_download_post_type' );

/**
 * Elementor lists queryable post types from show_in_nav_menus.
 * Downloads stay out of menus, so add the type to the loop source list directly.
 */
function endeavour_child_elementor_download_post_type( $post_types ) {
	$post_types[ ENDEAVOUR_DOWNLOAD_POST_TYPE ] = __( 'Downloads', 'endeavour' );

	return $post_types;
}
add_filter( 'elementor_pro/utils/get_public_post_types', 'endeavour_child_elementor_download_post_type' );

/**
 * Flush rewrite rules once, so the new post type is queryable by the loop.
 */
function endeavour_child_maybe_flush_download_rewrite_rules() {
	if ( get_option( 'endeavour_child_download_rewrite_flushed' ) === ENDEAVOUR_VERSION ) {
		return;
	}

	endeavour_child_register_download_post_type();
	flush_rewrite_rules( false );
	update_option( 'endeavour_child_download_rewrite_flushed', ENDEAVOUR_VERSION, false );
}
add_action( 'init', 'endeavour_child_maybe_flush_download_rewrite_rules', 20 );

/**
 * ACF field group for a download.
 *
 * The field key stays stable so the loop item's dynamic tags keep resolving.
 */
function endeavour_child_register_download_fields() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_endeavour_download',
			'title'    => __( 'Download', 'endeavour' ),
			'fields'   => array(
				array(
					'key'           => 'field_endeavour_download_file',
					'label'         => __( 'File', 'endeavour' ),
					'name'          => 'download_file',
					'type'          => 'file',
					'return_format' => 'array',
					'library'       => 'all',
					'mime_types'    => '',
					'instructions'  => __( 'The card shows this file’s type and size, and the button downloads it. The title of the download is the name on the card.', 'endeavour' ),
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => ENDEAVOUR_DOWNLOAD_POST_TYPE,
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'endeavour_child_register_download_fields' );

/**
 * The file attached to a download, as ACF returns it.
 *
 * @param int $post_id Download post ID. Empty uses the current post.
 * @return array|null
 */
function endeavour_child_download_file( $post_id = 0 ) {
	if ( ! function_exists( 'get_field' ) ) {
		return null;
	}

	$file = get_field( 'download_file', $post_id ? (int) $post_id : false );

	return is_array( $file ) && ! empty( $file['ID'] ) ? $file : null;
}

/**
 * Uppercase extension, taken from the file name.
 *
 * @param array $file ACF file array.
 * @return string
 */
function endeavour_child_download_extension( array $file ) {
	$filename = isset( $file['filename'] ) ? (string) $file['filename'] : '';
	$extension = pathinfo( $filename, PATHINFO_EXTENSION );

	return '' === $extension ? '' : strtoupper( $extension );
}

/**
 * File size in the design's form: a comma decimal and a unit, as in "32,4 MB".
 *
 * @param array $file ACF file array.
 * @return string
 */
function endeavour_child_download_size_label( array $file ) {
	$bytes = isset( $file['filesize'] ) ? (int) $file['filesize'] : 0;

	if ( $bytes < 1 ) {
		return '';
	}

	if ( $bytes < 1024 ) {
		return $bytes . ' B';
	}

	$kilobytes = $bytes / 1024;

	if ( $kilobytes < 1024 ) {
		$precision = $kilobytes >= 10 ? 0 : 1;

		return number_format( $kilobytes, $precision, ',', '.' ) . ' KB';
	}

	return number_format( $kilobytes / 1024, 1, ',', '.' ) . ' MB';
}

/**
 * URL that sends the file as a download.
 *
 * @param int $post_id Download post ID. Empty uses the current post.
 * @return string
 */
function endeavour_child_download_url( $post_id = 0 ) {
	$file = endeavour_child_download_file( $post_id );

	if ( ! $file ) {
		return '';
	}

	return add_query_arg( 'endeavour_download', (int) $file['ID'], home_url( '/' ) );
}

/**
 * Send a download's file with an attachment header.
 *
 * Only files that belong to a published download are served, so the endpoint
 * is not a general pass-through for the media library.
 */
function endeavour_child_serve_download() {
	if ( empty( $_GET['endeavour_download'] ) ) {
		return;
	}

	$attachment_id = absint( wp_unslash( $_GET['endeavour_download'] ) );

	if ( ! $attachment_id ) {
		status_header( 404 );
		exit;
	}

	$owners = get_posts(
		array(
			'post_type'      => ENDEAVOUR_DOWNLOAD_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => 'download_file',
			'meta_value'     => (string) $attachment_id,
			'no_found_rows'  => true,
		)
	);

	$path = get_attached_file( $attachment_id );
	$uploads = wp_get_upload_dir();
	$real    = $path ? realpath( $path ) : false;
	$base    = ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;

	if ( ! $owners || ! $real || ! $base || ! str_starts_with( $real, $base . DIRECTORY_SEPARATOR ) || ! is_readable( $real ) ) {
		status_header( 404 );
		exit;
	}

	$filetype = wp_check_filetype( $real );
	$filename = basename( $real );
	$filename = str_replace( array( '"', '\\', "\r", "\n" ), '', $filename );

	nocache_headers();
	header( 'Content-Type: ' . ( $filetype['type'] ? $filetype['type'] : 'application/octet-stream' ) );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Content-Length: ' . (string) filesize( $real ) );
	header( 'X-Content-Type-Options: nosniff' );

	readfile( $real );
	exit;
}
add_action( 'template_redirect', 'endeavour_child_serve_download', 0 );

/**
 * Three specimen downloads, matching the first row of the design.
 *
 * They share one small PDF. The card prints that file's real type and size.
 */
function endeavour_child_seed_downloads() {
	if ( get_option( 'endeavour_download_seeded' ) ) {
		return;
	}

	$existing = get_posts(
		array(
			'post_type'      => ENDEAVOUR_DOWNLOAD_POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);

	if ( $existing ) {
		update_option( 'endeavour_download_seeded', '1', false );
		return;
	}

	$attachment_id = endeavour_child_seed_download_file();

	if ( ! $attachment_id ) {
		return;
	}

	foreach ( array( 'file-name', 'file-name-2', 'file-name-3' ) as $index => $slug ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => ENDEAVOUR_DOWNLOAD_POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'File name',
				'post_name'   => $slug,
				'menu_order'  => $index + 1,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return;
		}

		update_field( 'download_file', $attachment_id, $post_id );
	}

	update_option( 'endeavour_download_seeded', '1', false );
}
add_action( 'acf/init', 'endeavour_child_seed_downloads', 20 );

/**
 * Store the specimen PDF in the media library.
 *
 * @return int Attachment ID, or 0 when the upload failed.
 */
function endeavour_child_seed_download_file() {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$pdf = "%PDF-1.4\n"
		. "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
		. "2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n"
		. "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\n"
		. "trailer<</Size 4/Root 1 0 R>>\n"
		. "%%EOF\n";

	$upload = wp_upload_bits( 'file-name.pdf', null, $pdf );

	if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
		return 0;
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'application/pdf',
			'post_title'     => 'File name',
			'post_status'    => 'inherit',
		),
		$upload['file']
	);

	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		return 0;
	}

	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

	return (int) $attachment_id;
}

/**
 * Dynamic tags for the file's type, size and download URL.
 *
 * The classes are declared here because their parent classes only exist once
 * Elementor has loaded.
 *
 * @param object $dynamic_tags Elementor dynamic tags manager.
 */
function endeavour_child_register_download_tags( $dynamic_tags ) {
	if ( ! class_exists( 'Endeavour_Download_Type', false ) ) {
		/**
		 * Dynamic tag: uppercase file extension.
		 */
		class Endeavour_Download_Type extends \Elementor\Core\DynamicTags\Tag {

			public function get_name() {
				return 'endeavour-download-type';
			}

			public function get_title() {
				return esc_html__( 'Download type', 'endeavour' );
			}

			public function get_group() {
				return 'post';
			}

			public function get_categories() {
				return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
			}

			public function render() {
				$file = endeavour_child_download_file();

				if ( ! $file ) {
					return;
				}

				echo esc_html( endeavour_child_download_extension( $file ) );
			}
		}

		/**
		 * Dynamic tag: formatted file size.
		 */
		class Endeavour_Download_Size extends \Elementor\Core\DynamicTags\Tag {

			public function get_name() {
				return 'endeavour-download-size';
			}

			public function get_title() {
				return esc_html__( 'Download size', 'endeavour' );
			}

			public function get_group() {
				return 'post';
			}

			public function get_categories() {
				return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
			}

			public function render() {
				$file = endeavour_child_download_file();

				if ( ! $file ) {
					return;
				}

				echo esc_html( endeavour_child_download_size_label( $file ) );
			}
		}

		/**
		 * Dynamic tag: URL that downloads the file.
		 */
		class Endeavour_Download_Link extends \Elementor\Core\DynamicTags\Data_Tag {

			public function get_name() {
				return 'endeavour-download-url';
			}

			public function get_title() {
				return esc_html__( 'Download file', 'endeavour' );
			}

			public function get_group() {
				return 'post';
			}

			public function get_categories() {
				return array( \Elementor\Modules\DynamicTags\Module::URL_CATEGORY );
			}

			public function get_value( array $options = array() ) {
				return endeavour_child_download_url();
			}
		}
	}

	$dynamic_tags->register( new Endeavour_Download_Type() );
	$dynamic_tags->register( new Endeavour_Download_Size() );
	$dynamic_tags->register( new Endeavour_Download_Link() );
}
add_action( 'elementor/dynamic_tags/register', 'endeavour_child_register_download_tags' );
