<?php
/**
 * Two buttons that drive a Loop Carousel.
 *
 * Each button uses an Endeavour button type, so Default, Prominent and Link
 * stay the same styles as the Button widget. The pair is linked to one
 * Loop Carousel on the page.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Icons_Manager;
use Elementor\Widget_Base;

class Endeavour_Carousel_Nav extends Widget_Base {

	public function get_name() {
		return 'endeavour-carousel-nav';
	}

	public function get_title() {
		return esc_html__( 'Carousel navigation', 'endeavour' );
	}

	public function get_icon() {
		return 'eicon-arrow-right';
	}

	public function get_categories() {
		return array( 'endeavour' );
	}

	public function get_keywords() {
		return array( 'carousel', 'navigation', 'previous', 'next', 'arrows', 'endeavour' );
	}

	public function get_style_depends(): array {
		return array( 'endeavour-carousel-nav' );
	}

	public function get_script_depends(): array {
		return array( 'endeavour-carousel-nav' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_carousel',
			array( 'label' => esc_html__( 'Carousel', 'endeavour' ) )
		);

		$this->add_control(
			'carousel_id',
			array(
				'label'       => esc_html__( 'Carousel', 'endeavour' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => $this->loop_carousel_options(),
				'label_block' => true,
				'description' => esc_html__(
					'The previous and next buttons control this carousel.',
					'endeavour'
				),
			)
		);

		$this->add_responsive_control(
			'gap',
			array(
				'label'     => esc_html__( 'Gap', 'endeavour' ),
				'type'      => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'     => array(
					'px' => array(
						'min' => 0,
						'max' => 64,
					),
				),
				'default'   => array(
					'unit' => 'px',
					'size' => 16,
				),
				'selectors' => array(
					'{{WRAPPER}} .endeavour-carousel-nav' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->register_button_controls(
			'prev',
			esc_html__( 'Previous', 'endeavour' ),
			array(
				'value'   => 'endv endv-chevron-left',
				'library' => 'endeavour-icons',
			),
			esc_html__( 'Previous slide', 'endeavour' )
		);

		$this->register_button_controls(
			'next',
			esc_html__( 'Next', 'endeavour' ),
			array(
				'value'   => 'endv endv-chevron-right',
				'library' => 'endeavour-icons',
			),
			esc_html__( 'Next slide', 'endeavour' )
		);
	}

	/**
	 * Loop Carousels on the document being edited.
	 *
	 * The select only keeps a value that exists in its options. Those options
	 * have to come from PHP: the editor re-renders the control from this list,
	 * and a value that is not in it shows up blank.
	 *
	 * @return array<string, string>
	 */
	private function loop_carousel_options() {
		$options = array(
			'' => esc_html__( 'Select a carousel', 'endeavour' ),
		);

		$post_id = $this->editor_post_id();

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return $options;
		}

		$raw  = get_post_meta( $post_id, '_elementor_data', true );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : $raw;

		if ( ! is_array( $data ) ) {
			return $options;
		}

		$this->collect_loop_carousels( $data, $options );

		return $options;
	}

	/**
	 * Post the Elementor editor is working on.
	 */
	private function editor_post_id() {
		if ( ! empty( $_REQUEST['editor_post_id'] ) ) {
			return (int) $_REQUEST['editor_post_id'];
		}

		if ( isset( $_GET['post'], $_GET['action'] ) && 'elementor' === $_GET['action'] ) {
			return (int) $_GET['post'];
		}

		return 0;
	}

	/**
	 * @param array<int, mixed>    $elements Element tree.
	 * @param array<string, string> $options  Select options, by reference.
	 */
	private function collect_loop_carousels( array $elements, array &$options ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if (
				isset( $element['widgetType'], $element['id'] ) &&
				in_array( $element['widgetType'], array( 'loop-carousel', 'image-carousel' ), true ) &&
				'' !== $element['id']
			) {
				$title = '';

				if ( isset( $element['settings']['_title'] ) && is_string( $element['settings']['_title'] ) ) {
					$title = $element['settings']['_title'];
				}

				$fallback = 'image-carousel' === $element['widgetType']
					? __( 'Image carousel', 'endeavour' )
					: __( 'Loop carousel', 'endeavour' );

				$options[ $element['id'] ] = '' !== $title
					? $title . ' (' . $element['id'] . ')'
					: $fallback . ' (' . $element['id'] . ')';
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$this->collect_loop_carousels( $element['elements'], $options );
			}
		}
	}

	/**
	 * One button: type, label, icon. Styling comes from the button type.
	 *
	 * @param string               $side    prev|next.
	 * @param string               $heading Section label.
	 * @param array<string,string> $icon    Default icon.
	 * @param string               $label   Default accessible name.
	 */
	private function register_button_controls( $side, $heading, $icon, $label ) {
		$this->start_controls_section(
			'section_' . $side,
			array( 'label' => $heading )
		);

		$this->add_control(
			$side . '_button_type',
			array(
				'label'   => esc_html__( 'Type', 'endeavour' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'endeavour-default',
				'options' => array(
					'endeavour-default'   => esc_html__( 'Default', 'endeavour' ),
					'endeavour-prominent' => esc_html__( 'Prominent', 'endeavour' ),
					'endeavour-link'      => esc_html__( 'Link', 'endeavour' ),
				),
			)
		);

		$this->add_control(
			$side . '_text',
			array(
				'label'       => esc_html__( 'Text', 'endeavour' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			$side . '_selected_icon',
			array(
				'label'   => esc_html__( 'Icon', 'endeavour' ),
				'type'    => Controls_Manager::ICONS,
				'default' => $icon,
			)
		);

		$this->add_control(
			$side . '_icon_align',
			array(
				'label'     => esc_html__( 'Icon position', 'endeavour' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'row',
				'options'   => array(
					'row'         => esc_html__( 'Before', 'endeavour' ),
					'row-reverse' => esc_html__( 'After', 'endeavour' ),
				),
				'condition' => array(
					$side . '_text!' => '',
				),
				'selectors' => array(
					'{{WRAPPER}} .endeavour-carousel-nav__' . $side . ' .elementor-button-content-wrapper' => 'flex-direction: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			$side . '_icon_gap',
			array(
				'label'      => esc_html__( 'Icon spacing', 'endeavour' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 8,
				),
				'condition'  => array(
					$side . '_text!' => '',
				),
				'selectors'  => array(
					'{{WRAPPER}} .endeavour-carousel-nav__' . $side . ' .elementor-button-content-wrapper' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			$side . '_aria_label',
			array(
				'label'       => esc_html__( 'Accessible label', 'endeavour' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $label,
				'description' => esc_html__(
					'Required when the button has an icon and no text.',
					'endeavour'
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$carousel = isset( $settings['carousel_id'] ) ? (string) $settings['carousel_id'] : '';

		$this->add_render_attribute(
			'nav',
			array(
				'class'         => 'endeavour-carousel-nav',
				'data-carousel' => $carousel,
			)
		);
		?>
		<div <?php $this->print_render_attribute_string( 'nav' ); ?>>
			<?php
			$this->render_nav_button( 'prev', $settings );
			$this->render_nav_button( 'next', $settings );
			?>
		</div>
		<?php
	}

	/**
	 * @param string               $side     prev|next.
	 * @param array<string, mixed> $settings Widget settings.
	 */
	private function render_nav_button( $side, $settings ) {
		$type = $settings[ $side . '_button_type' ] ?? 'endeavour-default';
		$text = trim( (string) ( $settings[ $side . '_text' ] ?? '' ) );
		$label = trim( (string) ( $settings[ $side . '_aria_label' ] ?? '' ) );

		$allowed = array( 'endeavour-default', 'endeavour-prominent', 'endeavour-link' );

		if ( ! in_array( $type, $allowed, true ) ) {
			$type = 'endeavour-default';
		}
		?>
		<div class="elementor-element elementor-widget elementor-widget-button elementor-button-<?php echo esc_attr( $type ); ?> endeavour-carousel-nav__<?php echo esc_attr( $side ); ?>">
			<button
				type="button"
				class="elementor-button"
				data-direction="<?php echo esc_attr( $side ); ?>"
				<?php echo '' === $text && '' !== $label ? 'aria-label="' . esc_attr( $label ) . '"' : ''; ?>
			>
				<span class="elementor-button-content-wrapper">
					<?php if ( ! empty( $settings[ $side . '_selected_icon' ]['value'] ) ) : ?>
						<span class="elementor-button-icon">
							<?php Icons_Manager::render_icon( $settings[ $side . '_selected_icon' ], array( 'aria-hidden' => 'true' ) ); ?>
						</span>
					<?php endif; ?>
					<?php if ( '' !== $text ) : ?>
						<span class="elementor-button-text"><?php echo esc_html( $text ); ?></span>
					<?php endif; ?>
				</span>
			</button>
		</div>
		<?php
	}
}
