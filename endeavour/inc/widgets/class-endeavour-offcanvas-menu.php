<?php
/**
 * Endeavour Off-Canvas Menu widget.
 *
 * Renders a WordPress menu as a drill-down navigator, meant to be dropped
 * inside Elementor Pro's Off-Canvas widget. The Off-Canvas widget keeps
 * owning the panel itself — position, width, background, overlay, open and
 * close behaviour and its focus trap. This widget only supplies what lives
 * inside it: the menu, the slide to a submenu, and the way back.
 *
 * Styling follows the project rule that Elementor settings beat CSS. Every
 * value a designer would want to change is a control here and is written to a
 * custom property on the widget wrapper; the stylesheet only consumes those
 * properties. The two exceptions are states Elementor has no control for
 * (`:active`, `:focus-visible`) and the slide itself, which are in the SCSS.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Plugin;
use Elementor\Utils;
use Elementor\Widget_Base;

class Endeavour_Offcanvas_Menu extends Widget_Base {

	/**
	 * Global color tokens that have no Global_Colors constant.
	 *
	 * Support / Hover and Support / Pressed are custom kit colors, so their
	 * ids are spelled out. They are still Site Settings values — binding to
	 * them here keeps the rule that nothing is hardcoded in a widget.
	 */
	const COLOR_SUPPORT_HOVER = 'globals/colors?id=c00000f';

	public function get_name() {
		return 'endeavour-offcanvas-menu';
	}

	public function get_title() {
		return esc_html__( 'Off-Canvas Menu', 'endeavour' );
	}

	public function get_icon() {
		return 'eicon-nav-menu';
	}

	public function get_categories() {
		return array( 'endeavour' );
	}

	public function get_keywords() {
		return array( 'menu', 'nav', 'navigation', 'off-canvas', 'mobile', 'endeavour' );
	}

	public function get_style_depends(): array {
		return array( 'endeavour-offcanvas-menu' );
	}

	public function get_script_depends(): array {
		return array( 'endeavour-offcanvas-menu' );
	}

	// -----------------------------------------------------------------------
	// Controls
	// -----------------------------------------------------------------------

	protected function register_controls() {
		$this->register_menu_section();
		$this->register_item_style_section();
		$this->register_indicator_style_section();
		$this->register_back_style_section();
		$this->register_title_style_section();
	}

	/**
	 * Menus available to pick from, keyed by slug.
	 *
	 * @return array
	 */
	private function get_available_menus() {
		$options = array();

		foreach ( wp_get_nav_menus() as $menu ) {
			$options[ $menu->slug ] = $menu->name;
		}

		return $options;
	}

	protected function register_menu_section() {
		$this->start_controls_section(
			'section_menu',
			array( 'label' => esc_html__( 'Menu', 'endeavour' ) )
		);

		$menus = $this->get_available_menus();

		if ( empty( $menus ) ) {
			$this->add_control(
				'menu_missing_notice',
				array(
					'type'       => Controls_Manager::ALERT,
					'alert_type' => 'info',
					'content'    => sprintf(
						/* translators: %s: link to the WordPress menus screen. */
						esc_html__( 'There are no menus yet. %s and assign it to the "Endeavour Off-Canvas" location.', 'endeavour' ),
						'<a href="' . esc_url( admin_url( 'nav-menus.php?action=edit&menu=0' ) ) . '" target="_blank">'
							. esc_html__( 'Create one', 'endeavour' ) . '</a>'
					),
				)
			);
		} else {
			$this->add_control(
				'menu',
				array(
					'label'       => esc_html__( 'Menu', 'endeavour' ),
					'type'        => Controls_Manager::SELECT,
					'options'     => array(
						'' => esc_html__( 'Endeavour Off-Canvas location', 'endeavour' ),
					) + $menus,
					'default'     => '',
					'description' => sprintf(
						/* translators: %s: link to the WordPress menus screen. */
						esc_html__( 'Leave this on the location to follow whichever menu is assigned there. %s', 'endeavour' ),
						'<a href="' . esc_url( admin_url( 'nav-menus.php' ) ) . '" target="_blank">'
							. esc_html__( 'Manage menus', 'endeavour' ) . '</a>'
					),
				)
			);
		}

		$this->add_control(
			'submenu_icon',
			array(
				'label'            => esc_html__( 'Submenu indicator', 'endeavour' ),
				'type'             => Controls_Manager::ICONS,
				'skin'             => 'inline',
				'label_block'      => false,
				'default'          => array(
					'value'   => 'endv endv-chevron-right',
					'library' => 'endeavour-icons',
				),
				'separator'        => 'before',
				'exclude_inline_options' => array( 'svg' ),
			)
		);

		$this->add_control(
			'back_heading',
			array(
				'label'     => esc_html__( 'Back', 'endeavour' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'back_text',
			array(
				'label'       => esc_html__( 'Back text', 'endeavour' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Back', 'endeavour' ),
				'placeholder' => esc_html__( 'Back', 'endeavour' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'back_icon',
			array(
				'label'            => esc_html__( 'Back icon', 'endeavour' ),
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
			'show_title',
			array(
				'label'        => esc_html__( 'Show section title', 'endeavour' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'separator'    => 'before',
				'description'  => esc_html__( 'Repeats the parent item as a heading above its submenu.', 'endeavour' ),
			)
		);

		$this->add_control(
			'title_tag',
			array(
				'label'     => esc_html__( 'Title HTML tag', 'endeavour' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'h2'  => 'H2',
					'h3'  => 'H3',
					'h4'  => 'H4',
					'h5'  => 'H5',
					'h6'  => 'H6',
					'div' => 'div',
				),
				'default'   => 'h2',
				'condition' => array( 'show_title' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	protected function register_item_style_section() {
		$this->start_controls_section(
			'section_item_style',
			array(
				'label' => esc_html__( 'Menu items', 'endeavour' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'item_typography',
				'selector' => '{{WRAPPER}} .endeavour-offcanvas-menu__label',
				'global'   => array( 'default' => Global_Typography::TYPOGRAPHY_SECONDARY ),
			)
		);

		$this->add_responsive_control(
			'item_padding',
			array(
				'label'      => esc_html__( 'Padding', 'endeavour' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'default'    => array(
					'top'      => 16,
					'right'    => 0,
					'bottom'   => 16,
					'left'     => 0,
					'unit'     => 'px',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--endv-menu-item-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->start_controls_tabs( 'item_state_tabs' );

		$this->start_controls_tab(
			'item_state_normal',
			array( 'label' => esc_html__( 'Normal', 'endeavour' ) )
		);

		$this->add_control(
			'item_color',
			array(
				'label'     => esc_html__( 'Text color', 'endeavour' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_SECONDARY ),
				'selectors' => array(
					'{{WRAPPER}}' => '--endv-menu-item-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'item_state_hover',
			array( 'label' => esc_html__( 'Hover', 'endeavour' ) )
		);

		$this->add_control(
			'item_color_hover',
			array(
				'label'     => esc_html__( 'Text color', 'endeavour' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_SECONDARY ),
				'selectors' => array(
					'{{WRAPPER}}' => '--endv-menu-item-color-hover: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'item_background_hover',
			array(
				'label'     => esc_html__( 'Background', 'endeavour' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => self::COLOR_SUPPORT_HOVER ),
				'selectors' => array(
					'{{WRAPPER}}' => '--endv-menu-item-background-hover: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'divider_heading',
			array(
				'label'     => esc_html__( 'Divider', 'endeavour' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'divider_style',
			array(
				'label'     => esc_html__( 'Style', 'endeavour' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'none'   => esc_html__( 'None', 'endeavour' ),
					'solid'  => esc_html__( 'Solid', 'endeavour' ),
					'dashed' => esc_html__( 'Dashed', 'endeavour' ),
					'dotted' => esc_html__( 'Dotted', 'endeavour' ),
				),
				'default'   => 'solid',
				'selectors' => array(
					'{{WRAPPER}}' => '--endv-menu-divider-style: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'divider_width',
			array(
				'label'      => esc_html__( 'Width', 'endeavour' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'max' => 10 ) ),
				'default'    => array(
					'unit' => 'px',
					'size' => 1,
				),
				'condition'  => array( 'divider_style!' => 'none' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--endv-menu-divider-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'divider_color',
			array(
				'label'     => esc_html__( 'Color', 'endeavour' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_ACCENT ),
				'condition' => array( 'divider_style!' => 'none' ),
				'selectors' => array(
					'{{WRAPPER}}' => '--endv-menu-divider-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function register_indicator_style_section() {
		$this->start_controls_section(
			'section_indicator_style',
			array(
				'label' => esc_html__( 'Submenu indicator', 'endeavour' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'indicator_size',
			array(
				'label'      => esc_html__( 'Size', 'endeavour' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'max' => 60 ) ),
				'default'    => array(
					'unit' => 'px',
					'size' => 20,
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--endv-menu-indicator-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'indicator_gap',
			array(
				'label'      => esc_html__( 'Gap', 'endeavour' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'max' => 100 ) ),
				'default'    => array(
					'unit' => 'px',
					'size' => 16,
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--endv-menu-indicator-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'indicator_color',
			array(
				'label'     => esc_html__( 'Color', 'endeavour' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_SECONDARY ),
				'selectors' => array(
					'{{WRAPPER}}' => '--endv-menu-indicator-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function register_back_style_section() {
		$this->start_controls_section(
			'section_back_style',
			array(
				'label' => esc_html__( 'Back button', 'endeavour' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'back_typography',
				'selector' => '{{WRAPPER}} .endeavour-offcanvas-menu__back-text',
				'global'   => array( 'default' => Global_Typography::TYPOGRAPHY_SECONDARY ),
			)
		);

		$this->add_control(
			'back_color',
			array(
				'label'     => esc_html__( 'Color', 'endeavour' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_PRIMARY ),
				'selectors' => array(
					'{{WRAPPER}}' => '--endv-menu-back-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'back_icon_size',
			array(
				'label'      => esc_html__( 'Icon size', 'endeavour' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'max' => 60 ) ),
				'default'    => array(
					'unit' => 'px',
					'size' => 20,
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--endv-menu-back-icon-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'back_gap',
			array(
				'label'      => esc_html__( 'Gap', 'endeavour' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'max' => 60 ) ),
				'default'    => array(
					'unit' => 'px',
					'size' => 8,
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--endv-menu-back-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'back_spacing',
			array(
				'label'      => esc_html__( 'Space below', 'endeavour' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'max' => 160 ) ),
				'default'    => array(
					'unit' => 'px',
					'size' => 16,
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--endv-menu-back-spacing: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function register_title_style_section() {
		$this->start_controls_section(
			'section_title_style',
			array(
				'label'     => esc_html__( 'Section title', 'endeavour' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_title' => 'yes' ),
			)
		);

		/*
		 * No global default on purpose. Headings are not Global Fonts in this
		 * kit — they are set per tag in Site Settings > Theme Style. Leaving
		 * this empty lets the chosen tag inherit that, and an editor can still
		 * override it here for one instance.
		 */
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .endeavour-offcanvas-menu__title',
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => esc_html__( 'Color', 'endeavour' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_SECONDARY ),
				'selectors' => array(
					'{{WRAPPER}}' => '--endv-menu-title-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'title_spacing',
			array(
				'label'      => esc_html__( 'Space below', 'endeavour' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'max' => 200 ) ),
				'default'    => array(
					'unit' => 'px',
					'size' => 64,
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--endv-menu-title-spacing: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	// -----------------------------------------------------------------------
	// Menu data
	// -----------------------------------------------------------------------

	/**
	 * The menu this instance renders.
	 *
	 * A specific menu wins when one is picked; otherwise the widget follows
	 * whatever is assigned to the Endeavour Off-Canvas location, so swapping
	 * the menu in Appearance > Menus needs no edit in Elementor.
	 *
	 * @return \WP_Term|null
	 */
	private function get_menu_object() {
		$slug = (string) $this->get_settings_for_display( 'menu' );

		if ( '' !== $slug ) {
			$menu = wp_get_nav_menu_object( $slug );

			if ( $menu ) {
				return $menu;
			}
		}

		$locations = get_nav_menu_locations();
		$location  = defined( 'ENDEAVOUR_OFFCANVAS_MENU_LOCATION' )
			? ENDEAVOUR_OFFCANVAS_MENU_LOCATION
			: 'endeavour_offcanvas';

		if ( empty( $locations[ $location ] ) ) {
			return null;
		}

		$menu = wp_get_nav_menu_object( $locations[ $location ] );

		return $menu ? $menu : null;
	}

	/**
	 * Turn the flat menu-item list into a nested tree.
	 *
	 * @param array $items Menu items from wp_get_nav_menu_items().
	 * @return array
	 */
	private function build_tree( array $items ) {
		$by_parent = array();

		foreach ( $items as $item ) {
			$by_parent[ (int) $item->menu_item_parent ][] = $item;
		}

		return $this->build_branch( $by_parent, 0 );
	}

	/**
	 * @param array $by_parent Menu items grouped by parent id.
	 * @param int   $parent_id Parent to collect children for.
	 * @return array
	 */
	private function build_branch( array $by_parent, $parent_id ) {
		$branch = array();

		if ( empty( $by_parent[ $parent_id ] ) ) {
			return $branch;
		}

		foreach ( $by_parent[ $parent_id ] as $item ) {
			$branch[] = array(
				'item'     => $item,
				'children' => $this->build_branch( $by_parent, (int) $item->ID ),
			);
		}

		return $branch;
	}

	/**
	 * Flatten the tree into panels.
	 *
	 * Panels are siblings in the DOM rather than nested, because they are
	 * stacked on top of each other and slid sideways. Nesting them would make
	 * a child inherit its parent's transform.
	 *
	 * @param array  $nodes    Branch of the menu tree.
	 * @param int    $level    Depth, 0 for the root panel.
	 * @param string $panel_id Id of the panel being collected.
	 * @param string $title    Parent item title, empty for the root.
	 * @param string $parent   Id of the panel one level up, empty for the root.
	 * @param array  $panels   Collected panels, by reference.
	 */
	private function collect_panels( array $nodes, $level, $panel_id, $title, $parent, array &$panels ) {
		// Claim a slot before recursing, so panels stay in breadth order and
		// the root is first in the DOM.
		$slot            = count( $panels );
		$panels[ $slot ] = null;

		$rows = array();

		foreach ( $nodes as $node ) {
			$item = $node['item'];

			if ( empty( $node['children'] ) ) {
				$rows[] = array(
					'type' => 'link',
					'item' => $item,
				);

				continue;
			}

			$child_id = $this->panel_id( (int) $item->ID );

			$rows[] = array(
				'type'   => 'parent',
				'item'   => $item,
				'target' => $child_id,
			);

			$this->collect_panels( $node['children'], $level + 1, $child_id, $item->title, $panel_id, $panels );
		}

		$panels[ $slot ] = array(
			'id'     => $panel_id,
			'level'  => (int) $level,
			'title'  => (string) $title,
			'parent' => (string) $parent,
			'rows'   => $rows,
		);
	}

	/**
	 * A DOM id that is unique per widget instance.
	 *
	 * @param string|int $suffix Root marker or menu item id.
	 * @return string
	 */
	private function panel_id( $suffix ) {
		return 'endv-menu-' . $this->get_id() . '-' . $suffix;
	}

	// -----------------------------------------------------------------------
	// Render
	// -----------------------------------------------------------------------

	protected function render() {
		$menu = $this->get_menu_object();

		if ( ! $menu ) {
			$this->render_editor_notice(
				esc_html__( 'Assign a menu to the "Endeavour Off-Canvas" location, or pick one in this widget.', 'endeavour' )
			);

			return;
		}

		$items = wp_get_nav_menu_items( $menu->term_id );

		if ( empty( $items ) ) {
			$this->render_editor_notice(
				sprintf(
					/* translators: %s: menu name. */
					esc_html__( 'The menu "%s" has no items yet.', 'endeavour' ),
					$menu->name
				)
			);

			return;
		}

		// Adds current-menu-item / current-menu-ancestor and sets ->current.
		_wp_menu_item_classes_by_context( $items );

		$panels  = array();
		$root_id = $this->panel_id( 'root' );

		$this->collect_panels( $this->build_tree( $items ), 0, $root_id, '', '', $panels );

		$this->add_render_attribute(
			'menu',
			array(
				'class'                         => 'endeavour-offcanvas-menu',
				'data-endeavour-offcanvas-menu' => '',
				'data-root'                     => $root_id,
			)
		);
		?>
		<div <?php $this->print_render_attribute_string( 'menu' ); ?>>
			<?php
			/**
			 * Two boxes, because they do two jobs. The outer one is the clip
			 * that hides the levels waiting off to the side, widened so the
			 * Back control's hover surface is not sliced off at the column
			 * edge. The inner one is the stack the levels slide through, and it
			 * keeps the column's own width, which is what the levels measure
			 * themselves against.
			 */
			?>
			<div class="endeavour-offcanvas-menu__levels">
				<?php foreach ( $panels as $panel ) : ?>
					<?php $this->render_panel( $panel ); ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array $panel One entry from collect_panels().
	 */
	private function render_panel( array $panel ) {
		$settings = $this->get_settings_for_display();
		$is_root  = ( 0 === $panel['level'] );

		$classes = 'endeavour-offcanvas-menu__panel';

		if ( $is_root ) {
			$classes .= ' is-current';
		}
		?>
		<div
			class="<?php echo esc_attr( $classes ); ?>"
			id="<?php echo esc_attr( $panel['id'] ); ?>"
			data-level="<?php echo esc_attr( (string) $panel['level'] ); ?>"
			<?php if ( ! $is_root ) : ?>
				data-parent="<?php echo esc_attr( $panel['parent'] ); ?>"
			<?php endif; ?>
		>
			<?php if ( ! $is_root ) : ?>
				<div class="endeavour-offcanvas-menu__header">
					<?php
					/**
					 * A link rather than a button, and the parent level is a
					 * real element on the page, so the fragment is a genuine
					 * destination: with the script running it is intercepted
					 * and animated, without it the browser still moves to the
					 * level being returned to.
					 */
					?>
					<a
						class="endeavour-offcanvas-menu__back"
						href="#<?php echo esc_attr( $panel['parent'] ); ?>"
						data-endeavour-menu-back="<?php echo esc_attr( $panel['parent'] ); ?>"
					>
						<?php if ( ! empty( $settings['back_icon']['value'] ) ) : ?>
							<span class="endeavour-offcanvas-menu__back-icon">
								<?php Icons_Manager::render_icon( $settings['back_icon'], array( 'aria-hidden' => 'true' ) ); ?>
							</span>
						<?php endif; ?>
						<span class="endeavour-offcanvas-menu__back-text">
							<?php echo esc_html( $settings['back_text'] ); ?>
						</span>
					</a>

					<?php if ( 'yes' === $settings['show_title'] && '' !== $panel['title'] ) : ?>
						<?php $tag = Utils::validate_html_tag( $settings['title_tag'] ); ?>
						<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput ?> class="endeavour-offcanvas-menu__title">
							<?php echo esc_html( $panel['title'] ); ?>
						</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<ul class="endeavour-offcanvas-menu__list">
				<?php foreach ( $panel['rows'] as $row ) : ?>
					<?php $this->render_row( $row ); ?>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * @param array $row One entry from a panel's rows.
	 */
	private function render_row( array $row ) {
		$settings = $this->get_settings_for_display();
		$item     = $row['item'];
		$is_parent = ( 'parent' === $row['type'] );

		$classes = array( 'endeavour-offcanvas-menu__item' );

		if ( $is_parent ) {
			$classes[] = 'endeavour-offcanvas-menu__item--parent';
		}

		if ( ! empty( $item->current ) ) {
			$classes[] = 'is-current';
		} elseif ( ! empty( $item->current_item_ancestor ) || ! empty( $item->current_item_parent ) ) {
			$classes[] = 'is-ancestor';
		}

		/** This filter is documented in wp-includes/post-template.php */
		$label = apply_filters( 'the_title', $item->title, $item->ID );
		?>
		<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<?php if ( $is_parent ) : ?>
				<button
					type="button"
					class="endeavour-offcanvas-menu__row"
					aria-expanded="false"
					aria-controls="<?php echo esc_attr( $row['target'] ); ?>"
					data-endeavour-menu-open="<?php echo esc_attr( $row['target'] ); ?>"
				>
					<span class="endeavour-offcanvas-menu__label"><?php echo wp_kses_post( $label ); ?></span>
					<?php if ( ! empty( $settings['submenu_icon']['value'] ) ) : ?>
						<span class="endeavour-offcanvas-menu__indicator">
							<?php Icons_Manager::render_icon( $settings['submenu_icon'], array( 'aria-hidden' => 'true' ) ); ?>
						</span>
					<?php endif; ?>
				</button>
			<?php else : ?>
				<a
					class="endeavour-offcanvas-menu__row"
					href="<?php echo esc_url( $item->url ); ?>"
					<?php if ( ! empty( $item->target ) ) : ?>
						target="<?php echo esc_attr( $item->target ); ?>"
						rel="noopener"
					<?php endif; ?>
					<?php if ( ! empty( $item->xfn ) ) : ?>
						rel="<?php echo esc_attr( $item->xfn ); ?>"
					<?php endif; ?>
					<?php if ( ! empty( $item->current ) ) : ?>
						aria-current="page"
					<?php endif; ?>
				>
					<span class="endeavour-offcanvas-menu__label"><?php echo wp_kses_post( $label ); ?></span>
				</a>
			<?php endif; ?>
		</li>
		<?php
	}

	/**
	 * Explain an empty render, but only to someone who can fix it.
	 *
	 * @param string $message Already-escaped message.
	 */
	private function render_editor_notice( $message ) {
		if ( ! Plugin::$instance->editor->is_edit_mode() ) {
			return;
		}

		printf(
			'<div class="elementor-alert elementor-alert-info">%s</div>',
			esc_html( $message )
		);
	}
}
