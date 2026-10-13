<?php
/**
 * Endeavour Share widget.
 *
 * A row of share actions. The networks chosen as "other" stay hidden until
 * the visitor presses that button, then sit in the same row and hide again
 * on the next press.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use Elementor\Widget_Base;

class Endeavour_Share extends Widget_Base {

	public function get_name() {
		return 'endeavour-share';
	}

	public function get_title() {
		return esc_html__( 'Share', 'endeavour' );
	}

	public function get_icon() {
		return 'eicon-share';
	}

	public function get_categories() {
		return array( 'endeavour' );
	}

	public function get_keywords() {
		return array( 'share', 'social', 'whatsapp', 'linkedin', 'endeavour' );
	}

	public function get_style_depends(): array {
		return array( 'endeavour-share' );
	}

	public function get_script_depends(): array {
		return array( 'endeavour-share' );
	}

	/**
	 * Networks the editor can add or remove. The address may use
	 * {url}, {title}, {text}, {message} and {app_id}.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function network_catalog() {
		return array(
			'whatsapp'  => array(
				'label'    => 'WhatsApp',
				'icon'     => 'endv endv-whatsapp',
				'template' => 'https://wa.me/?text={message}',
			),
			'linkedin'  => array(
				'label'    => 'LinkedIn',
				'icon'     => 'endv endv-linkedin',
				'template' => 'https://www.linkedin.com/sharing/share-offsite/?url={url}',
			),
			'x'         => array(
				'label'    => 'X',
				'icon'     => 'endv endv-x',
				'template' => 'https://x.com/intent/post?url={url}&text={text}',
			),
			'facebook'  => array(
				'label'    => 'Facebook',
				'icon'     => 'endv endv-facebook',
				'template' => 'https://www.facebook.com/sharer/sharer.php?u={url}',
			),
			'messenger' => array(
				'label'    => 'Messenger',
				'icon'     => 'endv endv-messenger',
				'template' => 'https://www.facebook.com/dialog/send?app_id={app_id}&link={url}&redirect_uri={url}',
			),
			'email'     => array(
				'label'    => 'Email',
				'icon'     => 'endv endv-email',
				'template' => 'mailto:?subject={title}&body={message}',
			),
			'pinterest' => array(
				'label'    => 'Pinterest',
				'icon'     => 'endv endv-pinterest',
				'template' => 'https://www.pinterest.com/pin/create/button/?url={url}&description={text}',
			),
			'sms'       => array(
				'label'    => __( 'Text message', 'endeavour' ),
				'icon'     => 'endv endv-message',
				'template' => 'sms:?&body={message}',
			),
			'telegram'  => array(
				'label'    => 'Telegram',
				'icon'     => 'endv endv-send',
				'template' => 'https://t.me/share/url?url={url}&text={text}',
			),
			'copy'      => array(
				'label'  => __( 'Copy link', 'endeavour' ),
				'icon'   => 'endv endv-link',
				'action' => 'copy',
			),
			'native'    => array(
				'label'  => __( 'Share', 'endeavour' ),
				'icon'   => 'endv endv-share',
				'action' => 'native',
			),
			'custom'    => array(
				'label'  => __( 'Custom address', 'endeavour' ),
				'icon'   => 'endv endv-share',
				'action' => 'custom',
			),
		);
	}

	protected function register_controls() {
		$this->register_content_controls();
		$this->register_style_controls();
	}

	private function register_content_controls() {
		$catalog = self::network_catalog();
		$options = array();

		foreach ( $catalog as $name => $network ) {
			$options[ $name ] = $network['label'];
		}

		$this->start_controls_section(
			'section_content',
			array( 'label' => esc_html__( 'Share', 'endeavour' ) )
		);

		$this->add_control(
			'show_title',
			array(
				'label'        => esc_html__( 'Title', 'endeavour' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => esc_html__( 'Title text', 'endeavour' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Share', 'endeavour' ),
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
				'condition'   => array( 'show_title' => 'yes' ),
			)
		);

		$this->add_control(
			'title_tag',
			array(
				'label'     => esc_html__( 'Title tag', 'endeavour' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'h2',
				'options'   => array(
					'h2'  => 'H2',
					'h3'  => 'H3',
					'h4'  => 'H4',
					'div' => 'div',
				),
				'condition' => array( 'show_title' => 'yes' ),
			)
		);

		$this->add_control(
			'url_source',
			array(
				'label'   => esc_html__( 'Address to share', 'endeavour' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'current',
				'options' => array(
					'current' => esc_html__( 'Current page', 'endeavour' ),
					'custom'  => esc_html__( 'Custom address', 'endeavour' ),
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'custom_url',
			array(
				'label'       => esc_html__( 'Custom address', 'endeavour' ),
				'type'        => Controls_Manager::URL,
				'options'     => false,
				'dynamic'     => array( 'active' => true ),
				'label_block' => true,
				'condition'   => array( 'url_source' => 'custom' ),
			)
		);

		$this->add_control(
			'share_title',
			array(
				'label'       => esc_html__( 'Shared title', 'endeavour' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
				'description' => esc_html__( 'Leave empty to use the page title.', 'endeavour' ),
			)
		);

		$this->add_control(
			'share_text',
			array(
				'label'       => esc_html__( 'Shared text', 'endeavour' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'dynamic'     => array( 'active' => true ),
				'description' => esc_html__( 'Leave empty to reuse the shared title. The address is added where a network expects a message.', 'endeavour' ),
			)
		);

		$this->add_control(
			'facebook_app_id',
			array(
				'label'       => esc_html__( 'Facebook App ID', 'endeavour' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'description' => esc_html__( 'Messenger is skipped until this is filled in.', 'endeavour' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_networks',
			array( 'label' => esc_html__( 'Visible networks', 'endeavour' ) )
		);

		$this->add_control(
			'networks',
			array(
				'label'       => esc_html__( 'Networks', 'endeavour' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $this->network_fields( $options ),
				'default'     => array(
					array( 'network' => 'whatsapp' ),
					array( 'network' => 'linkedin' ),
					array( 'network' => 'x' ),
				),
				'title_field' => '{{{ network }}}',
				'prevent_empty' => false,
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_other',
			array( 'label' => esc_html__( 'Other', 'endeavour' ) )
		);

		$this->add_control(
			'show_other',
			array(
				'label'        => esc_html__( 'Other button', 'endeavour' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'other_display',
			array(
				'label'     => esc_html__( 'Show as', 'endeavour' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'text',
				'options'   => array(
					'text'      => esc_html__( 'Text', 'endeavour' ),
					'icon'      => esc_html__( 'Icon', 'endeavour' ),
					'icon_text' => esc_html__( 'Icon and text', 'endeavour' ),
				),
				'condition' => array( 'show_other' => 'yes' ),
			)
		);

		$this->add_control(
			'other_label',
			array(
				'label'       => esc_html__( 'Button text', 'endeavour' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Other', 'endeavour' ),
				'dynamic'     => array( 'active' => true ),
				'description' => esc_html__( 'Also read out by screen readers when only the icon shows.', 'endeavour' ),
				'condition'   => array( 'show_other' => 'yes' ),
			)
		);

		$this->add_control(
			'other_label_open',
			array(
				'label'       => esc_html__( 'Button text when open', 'endeavour' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'description' => esc_html__( 'Leave empty to keep the same text.', 'endeavour' ),
				'condition'   => array( 'show_other' => 'yes' ),
			)
		);

		$this->add_control(
			'other_icon',
			array(
				'label'     => esc_html__( 'Icon', 'endeavour' ),
				'type'      => Controls_Manager::ICONS,
				'default'   => array(
					'value'   => 'endv endv-more',
					'library' => 'endeavour-icons',
				),
				'condition' => array(
					'show_other'     => 'yes',
					'other_display!' => 'text',
				),
			)
		);

		$this->add_control(
			'other_icon_open',
			array(
				'label'       => esc_html__( 'Icon when open', 'endeavour' ),
				'type'        => Controls_Manager::ICONS,
				'default'     => array(
					'value'   => 'endv endv-cross',
					'library' => 'endeavour-icons',
				),
				'description' => esc_html__( 'Leave empty to keep the same icon.', 'endeavour' ),
				'condition'   => array(
					'show_other'     => 'yes',
					'other_display!' => 'text',
				),
			)
		);

		$this->add_control(
			'copied_label',
			array(
				'label'   => esc_html__( 'Copied confirmation', 'endeavour' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Link copied', 'endeavour' ),
			)
		);

		$this->add_control(
			'start_open',
			array(
				'label'        => esc_html__( 'Start expanded', 'endeavour' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'condition'    => array( 'show_other' => 'yes' ),
			)
		);

		$this->add_control(
			'other_networks',
			array(
				'label'       => esc_html__( 'Networks behind Other', 'endeavour' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $this->network_fields( $options ),
				'default'     => array(
					array( 'network' => 'facebook' ),
					array( 'network' => 'email' ),
					array( 'network' => 'pinterest' ),
					array( 'network' => 'sms' ),
					array( 'network' => 'telegram' ),
					array( 'network' => 'copy' ),
					array( 'network' => 'native' ),
				),
				'title_field' => '{{{ network }}}',
				'prevent_empty' => false,
				'condition'   => array( 'show_other' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Fields shared by the visible row and the Other row.
	 *
	 * @param array<string, string> $options Network select options.
	 * @return array<int, array<string, mixed>>
	 */
	private function network_fields( array $options ) {
		$repeater = new Repeater();

		$repeater->add_control(
			'network',
			array(
				'label'   => esc_html__( 'Network', 'endeavour' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $options,
				'default' => 'whatsapp',
			)
		);

		$repeater->add_control(
			'label',
			array(
				'label'       => esc_html__( 'Label', 'endeavour' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
				'description' => esc_html__( 'Leave empty to use the network name.', 'endeavour' ),
			)
		);

		$repeater->add_control(
			'show_label',
			array(
				'label'        => esc_html__( 'Show label', 'endeavour' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$repeater->add_control(
			'icon_class',
			array(
				'label'       => esc_html__( 'Icon class', 'endeavour' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'endv endv-share',
				'label_block' => true,
				'condition'   => array( 'network' => 'custom' ),
			)
		);

		$repeater->add_control(
			'custom_template',
			array(
				'label'       => esc_html__( 'Share address', 'endeavour' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'label_block' => true,
				'description' => esc_html__( 'Placeholders: {url}, {title}, {text}, {message}, {app_id}.', 'endeavour' ),
				'condition'   => array( 'network' => 'custom' ),
			)
		);

		$repeater->add_control(
			'custom_url',
			array(
				'label'       => esc_html__( 'Replace the share address', 'endeavour' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'description' => esc_html__( 'Optional. The same placeholders work here.', 'endeavour' ),
				'condition'   => array(
					'network!' => 'custom',
				),
			)
		);

		return $repeater->get_controls();
	}

	private function register_style_controls() {
		$this->start_controls_section(
			'section_layout',
			array(
				'label' => esc_html__( 'Layout', 'endeavour' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'title_typography',
				'selector'  => '{{WRAPPER}} .endeavour-share__title',
				'condition' => array( 'show_title' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'title_spacing',
			array(
				'label'      => esc_html__( 'Space below the title', 'endeavour' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'max' => 128 ),
				),
				'default'    => array(
					'size' => 32,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--endv-share-title-gap: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array( 'show_title' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'title_max_width',
			array(
				'label'      => esc_html__( 'Title max width', 'endeavour' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array( 'max' => 1200 ),
				),
				'default'    => array(
					'size' => 770,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--endv-share-title-max: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array( 'show_title' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'gap',
			array(
				'label'      => esc_html__( 'Space between buttons', 'endeavour' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'max' => 64 ),
				),
				'default'    => array(
					'size' => 16,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--endv-share-gap: {{SIZE}}{{UNIT}};',
				),
				'separator'  => 'before',
			)
		);

		$this->add_responsive_control(
			'button_size',
			array(
				'label'      => esc_html__( 'Icon button size', 'endeavour' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 24,
						'max' => 80,
					),
				),
				'default'    => array(
					'size' => 40,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--endv-share-button-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'icon_size',
			array(
				'label'      => esc_html__( 'Icon size', 'endeavour' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 12,
						'max' => 48,
					),
				),
				'default'    => array(
					'size' => 20,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--endv-share-icon-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'alignment',
			array(
				'label'     => esc_html__( 'Alignment', 'endeavour' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => esc_html__( 'Start', 'endeavour' ),
						'icon'  => 'eicon-align-start-h',
					),
					'center'     => array(
						'title' => esc_html__( 'Center', 'endeavour' ),
						'icon'  => 'eicon-align-center-h',
					),
					'flex-end'   => array(
						'title' => esc_html__( 'End', 'endeavour' ),
						'icon'  => 'eicon-align-end-h',
					),
				),
				'default'   => 'flex-start',
				'selectors' => array(
					'{{WRAPPER}} .endeavour-share'         => 'align-items: {{VALUE}};',
					'{{WRAPPER}} .endeavour-share__actions' => 'justify-content: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$visible  = isset( $settings['networks'] ) && is_array( $settings['networks'] ) ? $settings['networks'] : array();
		$other    = isset( $settings['other_networks'] ) && is_array( $settings['other_networks'] ) ? $settings['other_networks'] : array();
		$show_other = 'yes' === ( $settings['show_other'] ?? '' );
		$context  = $this->share_context( $settings );

		if ( array() === $visible && ( ! $show_other || array() === $other ) ) {
			return;
		}

		$title_id  = 'endeavour-share-title-' . $this->get_id();
		$more_id   = 'endeavour-share-more-' . $this->get_id();
		$open      = $show_other && 'yes' === ( $settings['start_open'] ?? '' );
		$tag       = $this->title_tag( $settings['title_tag'] ?? 'h2' );
		$has_title = 'yes' === ( $settings['show_title'] ?? '' ) && '' !== trim( (string) ( $settings['title'] ?? '' ) );

		$this->add_render_attribute(
			'root',
			array(
				'class'              => 'endeavour-share',
				'data-url-source'    => $context['source'],
				'data-url'           => $context['url'],
				'data-title'         => $context['title'],
				'data-text'          => $context['text'],
				'data-app-id'        => $context['app_id'],
				'data-copied-label'  => $context['copied'],
			)
		);
		?>
		<div <?php $this->print_render_attribute_string( 'root' ); ?>>
			<?php if ( $has_title ) : ?>
				<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> id="<?php echo esc_attr( $title_id ); ?>" class="endeavour-share__title"><?php echo esc_html( $settings['title'] ); ?></<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php endif; ?>
			<div class="endeavour-share__actions" role="group" <?php echo $has_title ? 'aria-labelledby="' . esc_attr( $title_id ) . '"' : 'aria-label="' . esc_attr__( 'Share', 'endeavour' ) . '"'; ?>>
				<?php $this->render_networks( $visible, $context ); ?>
				<?php if ( $show_other && array() !== $other ) : ?>
					<div id="<?php echo esc_attr( $more_id ); ?>" class="endeavour-share__more" <?php echo $open ? '' : 'hidden'; ?>>
						<?php $this->render_networks( $other, $context ); ?>
					</div>
					<?php $this->render_toggle( $settings, $more_id, $open ); ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * The Other button. Both states are rendered so the script only flips
	 * which one shows.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @param string               $more_id  Id of the extra networks.
	 * @param bool                 $open     Whether it starts expanded.
	 */
	private function render_toggle( array $settings, $more_id, $open ) {
		$display    = (string) ( $settings['other_display'] ?? 'text' );
		$show_icon  = in_array( $display, array( 'icon', 'icon_text' ), true );
		$show_text  = 'icon' !== $display;
		$label      = trim( (string) ( $settings['other_label'] ?? '' ) );
		$label      = '' !== $label ? $label : esc_html__( 'Other', 'endeavour' );
		$label_open = trim( (string) ( $settings['other_label_open'] ?? '' ) );
		$label_open = '' !== $label_open ? $label_open : $label;
		$icon       = $settings['other_icon'] ?? array();
		$icon_open  = $settings['other_icon_open'] ?? array();

		if ( empty( $icon_open['value'] ) ) {
			$icon_open = $icon;
		}

		$classes = array( 'elementor-button', 'endeavour-share__toggle' );

		if ( $show_icon && ! $show_text ) {
			$classes[] = 'endeavour-share__button--icon';
		}

		printf(
			'<button type="button" class="%1$s" aria-expanded="%2$s" aria-controls="%3$s" data-label="%4$s" data-label-open="%5$s"%6$s>',
			esc_attr( implode( ' ', $classes ) ),
			$open ? 'true' : 'false',
			esc_attr( $more_id ),
			esc_attr( $label ),
			esc_attr( $label_open ),
			$show_text ? '' : ' aria-label="' . esc_attr( $open ? $label_open : $label ) . '"'
		);

		if ( $show_icon ) {
			foreach ( array( 'closed' => $icon, 'open' => $icon_open ) as $state => $state_icon ) {
				if ( empty( $state_icon['value'] ) ) {
					continue;
				}

				$hidden = ( 'open' === $state ) !== $open;

				printf(
					'<span class="endeavour-share__toggle-icon endeavour-share__toggle-icon--%1$s"%2$s>',
					esc_attr( $state ),
					$hidden ? ' hidden' : ''
				);
				Icons_Manager::render_icon(
					$state_icon,
					array(
						'aria-hidden' => 'true',
						'class'       => 'endeavour-share__icon',
					)
				);
				echo '</span>';
			}
		}

		if ( $show_text ) {
			echo '<span class="endeavour-share__label">' . esc_html( $open ? $label_open : $label ) . '</span>';
		}

		echo '</button>';
	}

	/**
	 * Values the share addresses are built from.
	 *
	 * JavaScript replaces the current-page address with the address the
	 * visitor is actually on. The values here keep the links working
	 * before that script runs.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @return array<string, string>
	 */
	private function share_context( array $settings ) {
		$source = ( $settings['url_source'] ?? 'current' ) === 'custom' ? 'custom' : 'current';
		$custom = '';

		if ( isset( $settings['custom_url']['url'] ) ) {
			$custom = (string) $settings['custom_url']['url'];
		}

		$url = 'custom' === $source && '' !== $custom ? $custom : (string) get_permalink();

		if ( '' === $url ) {
			$url = home_url( '/' );
		}

		$title = trim( (string) ( $settings['share_title'] ?? '' ) );

		if ( '' === $title ) {
			$title = trim( wp_strip_all_tags( get_the_title() ) );
		}

		$text = trim( (string) ( $settings['share_text'] ?? '' ) );

		if ( '' === $text ) {
			$text = $title;
		}

		return array(
			'source'  => $source,
			'url'     => $url,
			'title'   => $title,
			'text'    => $text,
			'message' => trim( $text . ' ' . $url ),
			'app_id'  => trim( (string) ( $settings['facebook_app_id'] ?? '' ) ),
			'copied'  => trim( (string) ( $settings['copied_label'] ?? '' ) ),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $items Repeater rows.
	 * @param array<string, string>            $context Share values.
	 */
	private function render_networks( array $items, array $context ) {
		foreach ( $items as $item ) {
			$this->render_network( $item, $context );
		}
	}

	/**
	 * @param array<string, mixed>  $item Repeater row.
	 * @param array<string, string> $context Share values.
	 */
	private function render_network( array $item, array $context ) {
		$catalog = self::network_catalog();
		$name    = isset( $item['network'] ) ? (string) $item['network'] : '';

		if ( ! isset( $catalog[ $name ] ) ) {
			return;
		}

		if ( 'messenger' === $name && '' === $context['app_id'] ) {
			return;
		}

		$network = $catalog[ $name ];
		$label   = trim( (string) ( $item['label'] ?? '' ) );

		if ( '' === $label ) {
			$label = (string) $network['label'];
		}

		$show_label = 'yes' === ( $item['show_label'] ?? '' );
		$icon       = 'custom' === $name ? (string) ( $item['icon_class'] ?? '' ) : (string) $network['icon'];
		$action     = (string) ( $network['action'] ?? 'link' );
		$template   = trim( (string) ( $item['custom_url'] ?? '' ) );

		if ( 'custom' === $name ) {
			$template = trim( (string) ( $item['custom_template'] ?? '' ) );
			$action   = '' === $template ? '' : 'link';
		}

		if ( '' === $template && isset( $network['template'] ) ) {
			$template = (string) $network['template'];
		}

		if ( 'link' === $action && '' === $template ) {
			return;
		}

		$classes = array( 'elementor-button', 'endeavour-share__button' );

		if ( ! $show_label ) {
			$classes[] = 'endeavour-share__button--icon';
		}

		$href = 'link' === $action ? $this->fill_template( $template, $context ) : '';
		$tag  = 'link' === $action ? 'a' : 'button';

		echo '<' . $tag . ' class="' . esc_attr( implode( ' ', $classes ) ) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( 'link' === $action ) {
			echo ' href="' . esc_url( $href ) . '" data-template="' . esc_attr( $template ) . '"';

			if ( '' !== trim( (string) ( $item['custom_url'] ?? '' ) ) && 'custom' !== $name ) {
				echo ' data-custom-url="' . esc_attr( (string) $item['custom_url'] ) . '"';
			}

			if ( 0 === strpos( $template, 'http://' ) || 0 === strpos( $template, 'https://' ) ) {
				echo ' target="_blank" rel="noopener noreferrer"';
			}
		} else {
			echo ' type="button" data-action="' . esc_attr( $action ) . '"';
		}

		if ( ! $show_label ) {
			echo ' aria-label="' . esc_attr( $label ) . '"';
		}

		echo '>';

		if ( '' !== trim( $icon ) ) {
			Icons_Manager::render_icon(
				array(
					'value'   => $icon,
					'library' => 'endeavour-icons',
				),
				array(
					'aria-hidden' => 'true',
					'class'       => 'endeavour-share__icon',
				)
			);
		}

		if ( $show_label ) {
			echo '<span class="endeavour-share__label">' . esc_html( $label ) . '</span>';
		}

		echo '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @param array<string, string> $context Share values.
	 */
	private function fill_template( $template, array $context ) {
		$replace = array(
			'{url}'     => rawurlencode( $context['url'] ),
			'{title}'   => rawurlencode( $context['title'] ),
			'{text}'    => rawurlencode( $context['text'] ),
			'{message}' => rawurlencode( $context['message'] ),
			'{app_id}'  => rawurlencode( $context['app_id'] ),
		);

		return strtr( $template, $replace );
	}

	/**
	 * @param string $tag Requested title tag.
	 */
	private function title_tag( $tag ) {
		$allowed = array( 'h2', 'h3', 'h4', 'div' );

		return in_array( $tag, $allowed, true ) ? $tag : 'h2';
	}
}
