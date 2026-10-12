<?php
/**
 * Endeavour Overview Link widget.
 *
 * Resolves the most useful overview destination for the current singular
 * object, while retaining explicit text, URL and style controls in Elementor.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Widget_Base;

class Endeavour_Overview_Link extends Widget_Base {

	const COLOR_SUPPORT_HOVER   = 'globals/colors?id=c00000f';
	const COLOR_SUPPORT_PRESSED = 'globals/colors?id=c000010';

	public function get_name() {
		return 'endeavour-overview-link';
	}

	public function get_title() {
		return esc_html__( 'Overview Link', 'endeavour' );
	}

	public function get_icon() {
		return 'eicon-chevron-left';
	}

	public function get_categories() {
		return array( 'endeavour' );
	}

	public function get_keywords() {
		return array( 'overview', 'back', 'archive', 'parent', 'link', 'endeavour' );
	}

	public function get_style_depends(): array {
		return array( 'endeavour-overview-link' );
	}

	protected function register_controls() {
		$this->register_content_controls();
		$this->register_style_controls();
	}

	private function register_content_controls() {
		$this->start_controls_section(
			'section_content',
			array( 'label' => esc_html__( 'Overview link', 'endeavour' ) )
		);

		$this->add_control(
			'text',
			array(
				'label'       => esc_html__( 'Text', 'endeavour' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Overview', 'endeavour' ),
				'placeholder' => esc_html__( 'Overview', 'endeavour' ),
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'link_source',
			array(
				'label'       => esc_html__( 'Destination', 'endeavour' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'automatic',
				'options'     => array(
					'automatic' => esc_html__( 'Automatic overview', 'endeavour' ),
					'custom'    => esc_html__( 'Custom link', 'endeavour' ),
				),
				'description' => esc_html__(
					'Automatic uses the parent page, posts page, post type archive, or finally the homepage.',
					'endeavour'
				),
			)
		);

		$this->add_control(
			'custom_link',
			array(
				'label'         => esc_html__( 'Link', 'endeavour' ),
				'type'          => Controls_Manager::URL,
				'placeholder'   => 'https://example.com/overview/',
				'show_external' => true,
				'dynamic'       => array( 'active' => true ),
				'condition'     => array( 'link_source' => 'custom' ),
			)
		);

		$this->add_control(
			'selected_icon',
			array(
				'label'            => esc_html__( 'Icon', 'endeavour' ),
				'type'             => Controls_Manager::ICONS,
				'skin'             => 'inline',
				'label_block'      => false,
				'default'          => array(
					'value'   => 'endv endv-chevron-left',
					'library' => 'endeavour-icons',
				),
				'exclude_inline_options' => array( 'svg' ),
			)
		);

		$this->add_control(
			'icon_position',
			array(
				'label'   => esc_html__( 'Icon position', 'endeavour' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'before',
				'options' => array(
					'before' => array(
						'title' => esc_html__( 'Before', 'endeavour' ),
						'icon'  => 'eicon-h-align-left',
					),
					'after'  => array(
						'title' => esc_html__( 'After', 'endeavour' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'toggle'  => false,
			)
		);

		$this->end_controls_section();
	}

	private function register_style_controls() {
		$this->start_controls_section(
			'section_style',
			array(
				'label' => esc_html__( 'Link', 'endeavour' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography',
				'selector' => '{{WRAPPER}} .endeavour-overview-link',
				'global'   => array( 'default' => Global_Typography::TYPOGRAPHY_SECONDARY ),
			)
		);

		$this->add_responsive_control(
			'gap',
			array(
				'label'      => esc_html__( 'Gap', 'endeavour' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'max' => 80 ) ),
				'default'    => array(
					'unit' => 'px',
					'size' => 8,
				),
				'selectors'  => array(
					'{{WRAPPER}} .endeavour-overview-link' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'padding',
			array(
				'label'      => esc_html__( 'Padding', 'endeavour' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'default'    => array(
					'top'      => 4,
					'right'    => 8,
					'bottom'   => 4,
					'left'     => 8,
					'unit'     => 'px',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}} .endeavour-overview-link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'border_radius',
			array(
				'label'      => esc_html__( 'Border radius', 'endeavour' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'default'    => array(
					'top'      => 8,
					'right'    => 8,
					'bottom'   => 8,
					'left'     => 8,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .endeavour-overview-link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'border',
				'selector' => '{{WRAPPER}} .endeavour-overview-link',
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_shadow',
				'selector' => '{{WRAPPER}} .endeavour-overview-link',
			)
		);

		$this->start_controls_tabs( 'state_tabs' );

		$this->start_controls_tab(
			'state_normal',
			array( 'label' => esc_html__( 'Normal', 'endeavour' ) )
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( 'Text color', 'endeavour' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_PRIMARY ),
				'selectors' => array(
					'{{WRAPPER}} .endeavour-overview-link' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'icon_color',
			array(
				'label'     => esc_html__( 'Icon color', 'endeavour' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_PRIMARY ),
				'selectors' => array(
					'{{WRAPPER}} .endeavour-overview-link__icon' => 'color: {{VALUE}}; fill: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'background_color',
			array(
				'label'     => esc_html__( 'Background', 'endeavour' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .endeavour-overview-link' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'state_hover',
			array( 'label' => esc_html__( 'Hover', 'endeavour' ) )
		);

		// Hover only adds the Support/Hover surface; the label keeps its colour.
		$this->add_control(
			'text_color_hover',
			array(
				'label'     => esc_html__( 'Text color', 'endeavour' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_PRIMARY ),
				'selectors' => array(
					'{{WRAPPER}} .endeavour-overview-link:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'icon_color_hover',
			array(
				'label'     => esc_html__( 'Icon color', 'endeavour' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_PRIMARY ),
				'selectors' => array(
					'{{WRAPPER}} .endeavour-overview-link:hover .endeavour-overview-link__icon' => 'color: {{VALUE}}; fill: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'background_color_hover',
			array(
				'label'     => esc_html__( 'Background', 'endeavour' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => self::COLOR_SUPPORT_HOVER ),
				'selectors' => array(
					'{{WRAPPER}} .endeavour-overview-link:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'state_pressed',
			array( 'label' => esc_html__( 'Pressed', 'endeavour' ) )
		);

		$this->add_control(
			'background_color_pressed',
			array(
				'label'     => esc_html__( 'Background', 'endeavour' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => self::COLOR_SUPPORT_PRESSED ),
				'selectors' => array(
					'{{WRAPPER}} .endeavour-overview-link:active' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_responsive_control(
			'icon_size',
			array(
				'label'      => esc_html__( 'Icon size', 'endeavour' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'max' => 80 ) ),
				'default'    => array(
					'unit' => 'px',
					'size' => 20,
				),
				'separator'  => 'before',
				'selectors'  => array(
					'{{WRAPPER}} .endeavour-overview-link__icon' => 'font-size: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .endeavour-overview-link__icon :is(i, svg)' => 'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Resolve an automatic overview for the currently queried singular object.
	 *
	 * @return string
	 */
	private function get_automatic_url() {
		$post_id = get_queried_object_id();
		$post    = $post_id ? get_post( $post_id ) : null;
		$url     = '';

		if ( $post instanceof WP_Post ) {
			$post_type = get_post_type_object( $post->post_type );

			if ( is_post_type_hierarchical( $post->post_type ) && $post->post_parent ) {
				$url = get_permalink( $post->post_parent );
			} elseif ( 'post' === $post->post_type && (int) get_option( 'page_for_posts' ) ) {
				$url = get_permalink( (int) get_option( 'page_for_posts' ) );
			} elseif ( $post_type && $post_type->has_archive ) {
				$url = get_post_type_archive_link( $post->post_type );
			}
		}

		if ( ! $url ) {
			$url = home_url( '/' );
		}

		/**
		 * Filter the automatically resolved overview URL.
		 *
		 * @param string       $url     Resolved URL.
		 * @param WP_Post|null $post    Current queried post.
		 * @param object       $widget  Widget instance.
		 */
		return (string) apply_filters( 'endeavour_overview_link_url', $url, $post, $this );
	}

	/**
	 * @return array{url:string,is_external?:string,nofollow?:string,custom_attributes?:string}
	 */
	private function get_link() {
		$settings = $this->get_settings_for_display();

		if ( 'custom' === $settings['link_source'] && ! empty( $settings['custom_link']['url'] ) ) {
			return $settings['custom_link'];
		}

		return array( 'url' => $this->get_automatic_url() );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$link     = $this->get_link();
		$text     = trim( (string) $settings['text'] );

		if ( empty( $link['url'] ) || '' === $text ) {
			return;
		}

		$this->add_link_attributes( 'link', $link );
		$this->add_render_attribute( 'link', 'class', 'endeavour-overview-link' );

		$icon = static function () use ( $settings ) {
			if ( empty( $settings['selected_icon']['value'] ) ) {
				return;
			}
			?>
			<span class="endeavour-overview-link__icon" aria-hidden="true">
				<?php Icons_Manager::render_icon( $settings['selected_icon'], array( 'aria-hidden' => 'true' ) ); ?>
			</span>
			<?php
		};
		?>
		<a <?php $this->print_render_attribute_string( 'link' ); ?>>
			<?php if ( 'before' === $settings['icon_position'] ) : ?>
				<?php $icon(); ?>
			<?php endif; ?>
			<span class="endeavour-overview-link__text"><?php echo esc_html( $text ); ?></span>
			<?php if ( 'after' === $settings['icon_position'] ) : ?>
				<?php $icon(); ?>
			<?php endif; ?>
		</a>
		<?php
	}
}
