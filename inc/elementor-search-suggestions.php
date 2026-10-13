<?php
/**
 * Suggestions for the Elementor Search widget.
 *
 * Live results stay Elementor's: a loop item, the query and the dropdown.
 * What shows before a visitor has typed enough is not a control Elementor
 * has, so it is added here. Recent searches are remembered in the browser.
 * Every other block, such as Popular, is a section on the Search widget.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the suggestion sections to the Search widget.
 *
 * @param \Elementor\Widget_Base $element Search widget.
 */
function endeavour_register_search_suggestion_controls( $element ) {
	// One flat repeater. A repeater inside a repeater makes Elementor's CSS
	// builder read a setting that does not exist, and that warning stops the
	// editor from opening the document.
	$rows = new \Elementor\Repeater();

	$rows->add_control(
		'row_type',
		array(
			'label'   => esc_html__( 'Row', 'endeavour' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => array(
				'heading' => esc_html__( 'Heading', 'endeavour' ),
				'link'    => esc_html__( 'Link', 'endeavour' ),
			),
			'default' => 'link',
		)
	);

	$rows->add_control(
		'label',
		array(
			'label'       => esc_html__( 'Label', 'endeavour' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'label_block' => true,
			'default'     => esc_html__( 'Search result', 'endeavour' ),
		)
	);

	$rows->add_control(
		'url',
		array(
			'label'     => esc_html__( 'Link', 'endeavour' ),
			'type'      => \Elementor\Controls_Manager::URL,
			'condition' => array(
				'row_type' => 'link',
			),
		)
	);

	$element->start_controls_section(
		'endeavour_suggestions_section',
		array(
			'label' => esc_html__( 'Suggestions', 'endeavour' ),
		)
	);

	$element->add_control(
		'endeavour_suggestions',
		array(
			'label'        => esc_html__( 'Suggestions', 'endeavour' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => esc_html__( 'Show', 'endeavour' ),
			'label_off'    => esc_html__( 'Hide', 'endeavour' ),
			'return_value' => 'yes',
			'description'  => esc_html__(
				'Shown in the dropdown before the visitor has typed enough for live results.',
				'endeavour'
			),
		)
	);

	$element->add_control(
		'endeavour_recent_searches',
		array(
			'label'        => esc_html__( 'Recent searches', 'endeavour' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => esc_html__( 'Show', 'endeavour' ),
			'label_off'    => esc_html__( 'Hide', 'endeavour' ),
			'return_value' => 'yes',
			'default'      => 'yes',
			'condition'    => array(
				'endeavour_suggestions' => 'yes',
			),
		)
	);

	$element->add_control(
		'endeavour_recent_heading',
		array(
			'label'     => esc_html__( 'Recent searches heading', 'endeavour' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => esc_html__( 'Recent searches', 'endeavour' ),
			'condition' => array(
				'endeavour_suggestions'    => 'yes',
				'endeavour_recent_searches' => 'yes',
			),
		)
	);

	$element->add_control(
		'endeavour_recent_limit',
		array(
			'label'     => esc_html__( 'Recent searches to show', 'endeavour' ),
			'type'      => \Elementor\Controls_Manager::NUMBER,
			'default'   => 3,
			'min'       => 1,
			'max'       => 8,
			'condition' => array(
				'endeavour_suggestions'    => 'yes',
				'endeavour_recent_searches' => 'yes',
			),
		)
	);

	$element->add_control(
		'endeavour_suggestion_sections',
		array(
			'label'       => esc_html__( 'Sections', 'endeavour' ),
			'type'        => \Elementor\Controls_Manager::REPEATER,
			'fields'      => $rows->get_controls(),
			'default'     => array(),
			'title_field' => '{{{ label }}}',
			'button_text' => esc_html__( 'Add row', 'endeavour' ),
			'description' => esc_html__(
				'A heading row starts a block such as Popular. Link rows under it are the results.',
				'endeavour'
			),
			'condition'   => array(
				'endeavour_suggestions' => 'yes',
			),
		)
	);

	$element->end_controls_section();
}
add_action(
	'elementor/element/search/content_section_results/after_section_end',
	'endeavour_register_search_suggestion_controls'
);

/**
 * URL stored by the link control.
 *
 * @param mixed $url Control value.
 */
function endeavour_search_suggestion_url( $url ): string {
	if ( is_array( $url ) ) {
		$url = $url['url'] ?? '';
	}

	return is_string( $url ) ? $url : '';
}

/**
 * Markup for one configured section.
 *
 * @param array $section Repeater row.
 */
function endeavour_search_suggestion_section_html( array $section ): string {
	$heading = isset( $section['heading'] ) ? trim( (string) $section['heading'] ) : '';
	$items   = isset( $section['items'] ) && is_array( $section['items'] ) ? $section['items'] : array();
	$links   = '';

	foreach ( $items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$label = isset( $item['label'] ) ? trim( (string) $item['label'] ) : '';
		$url   = endeavour_search_suggestion_url( $item['url'] ?? '' );

		if ( '' === $label || '' === $url ) {
			continue;
		}

		$links .= sprintf(
			'<li><a href="%1$s">%2$s</a></li>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	if ( '' === $links ) {
		return '';
	}

	$heading_html = '' === $heading
		? ''
		: '<h3 class="endeavour-search-suggestions__heading">' . esc_html( $heading ) . '</h3>';

	return '<section class="endeavour-search-suggestions__section">'
		. $heading_html
		. '<ul class="endeavour-search-suggestions__list">' . $links . '</ul>'
		. '</section>';
}

/**
 * Turn repeater rows into sections.
 *
 * A heading row opens a block. The link rows after it belong to that block.
 * Older saves stored each block as a heading plus a nested items list.
 *
 * @param array $rows Repeater rows.
 */
function endeavour_search_suggestion_rows_html( array $rows ): string {
	$html    = '';
	$heading = '';
	$links   = '';

	$flush = static function () use ( &$html, &$heading, &$links ): void {
		if ( '' === $links ) {
			$heading = '';

			return;
		}

		$heading_html = '' === $heading
			? ''
			: '<h3 class="endeavour-search-suggestions__heading">' . esc_html( $heading ) . '</h3>';

		$html   .= '<section class="endeavour-search-suggestions__section">'
			. $heading_html
			. '<ul class="endeavour-search-suggestions__list">' . $links . '</ul>'
			. '</section>';
		$heading = '';
		$links   = '';
	};

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		if ( isset( $row['items'] ) && is_array( $row['items'] ) ) {
			$flush();
			$html .= endeavour_search_suggestion_section_html( $row );
			continue;
		}

		$label = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
		$type  = $row['row_type'] ?? 'link';

		if ( 'heading' === $type ) {
			$flush();
			$heading = $label;
			continue;
		}

		$url = endeavour_search_suggestion_url( $row['url'] ?? '' );

		if ( '' === $label || '' === $url ) {
			continue;
		}

		$links .= sprintf(
			'<li><a href="%1$s">%2$s</a></li>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	$flush();

	return $html;
}

/**
 * Dropdown contents for an empty search field.
 *
 * @param array $settings Widget settings.
 */
function endeavour_search_suggestions_html( array $settings ): string {
	$sections = isset( $settings['endeavour_suggestion_sections'] ) && is_array( $settings['endeavour_suggestion_sections'] )
		? $settings['endeavour_suggestion_sections']
		: array();

	$sections_html = endeavour_search_suggestion_rows_html( $sections );

	$show_recent = 'yes' === ( $settings['endeavour_recent_searches'] ?? '' );
	$limit       = max( 1, (int) ( $settings['endeavour_recent_limit'] ?? 3 ) );
	$minimum     = max( 1, (int) ( $settings['minimum_search_characters'] ?? 3 ) );
	$heading     = isset( $settings['endeavour_recent_heading'] ) && '' !== trim( (string) $settings['endeavour_recent_heading'] )
		? (string) $settings['endeavour_recent_heading']
		: __( 'Recent searches', 'endeavour' );

	if ( ! $show_recent && '' === $sections_html ) {
		return '';
	}

	$recent_html = '';

	if ( $show_recent ) {
		$recent_html = '<section class="endeavour-search-suggestions__section" data-recent hidden>'
			. '<h3 class="endeavour-search-suggestions__heading">' . esc_html( $heading ) . '</h3>'
			. '<ul class="endeavour-search-suggestions__list"></ul>'
			. '</section>';
	}

	return sprintf(
		'<div class="endeavour-search-suggestions" data-recent-limit="%1$d" data-minimum="%2$d">%3$s%4$s</div>',
		$limit,
		$minimum,
		$recent_html,
		$sections_html
	);
}

/**
 * Place the suggestions inside Elementor's results dropdown.
 *
 * @param string                 $content Rendered widget HTML.
 * @param \Elementor\Widget_Base $widget  Widget instance.
 */
function endeavour_inject_search_suggestions( $content, $widget ) {
	if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || 'search' !== $widget->get_name() ) {
		return $content;
	}

	$settings = $widget->get_settings_for_display();

	if ( 'yes' !== ( $settings['endeavour_suggestions'] ?? '' ) ) {
		return $content;
	}

	$html = endeavour_search_suggestions_html( $settings );

	if ( '' === $html ) {
		return $content;
	}

	$needle = '<div class="e-search-results"></div>';

	if ( ! str_contains( $content, $needle ) ) {
		return $content;
	}

	return str_replace( $needle, $needle . $html, $content );
}
add_filter( 'elementor/widget/render_content', 'endeavour_inject_search_suggestions', 10, 2 );
