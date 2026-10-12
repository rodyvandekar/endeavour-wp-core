<?php
/**
 * Endeavour Gravity Form widget.
 *
 * Picks a Gravity Form and, optionally, the sentence shown beside its
 * submit button. Field styling stays in the theme stylesheets.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class Endeavour_Gravity_Form extends Widget_Base {

	public function get_name() {
		return 'endeavour-gravity-form';
	}

	public function get_title() {
		return esc_html__( 'Gravity Form', 'endeavour' );
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	public function get_categories() {
		return array( 'endeavour' );
	}

	public function get_keywords() {
		return array( 'form', 'gravity', 'gravity forms', 'contact', 'endeavour' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_form',
			array( 'label' => esc_html__( 'Form', 'endeavour' ) )
		);

		$this->add_control(
			'form_id',
			array(
				'label'   => esc_html__( 'Gravity Form', 'endeavour' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $this->get_form_options(),
				'default' => '',
			)
		);

		$this->add_control(
			'submit_note',
			array(
				'label'       => esc_html__( 'Text next to the button', 'endeavour' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'default'     => '',
				'placeholder' => esc_html__( 'We’ll contact you within one business day', 'endeavour' ),
				'description' => esc_html__( 'Leave empty to show the button on its own.', 'endeavour' ),
			)
		);

		$this->add_control(
			'ajax',
			array(
				'label'        => esc_html__( 'Submit without reloading', 'endeavour' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Active Gravity Forms, keyed by id, for the form select.
	 *
	 * @return array<string, string>
	 */
	private function get_form_options() {
		$options = array(
			'' => esc_html__( 'Select a form', 'endeavour' ),
		);

		if ( ! class_exists( 'GFAPI' ) ) {
			return $options;
		}

		$forms = GFAPI::get_forms( true, false );

		if ( ! is_array( $forms ) ) {
			return $options;
		}

		foreach ( $forms as $form ) {
			if ( empty( $form['id'] ) ) {
				continue;
			}

			$title = isset( $form['title'] ) ? (string) $form['title'] : '';
			$options[ (string) $form['id'] ] = '' !== $title
				? $title
				: sprintf(
					/* translators: %s: Gravity Forms form id. */
					esc_html__( 'Form %s', 'endeavour' ),
					(string) $form['id']
				);
		}

		return $options;
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$form_id  = isset( $settings['form_id'] ) ? (int) $settings['form_id'] : 0;

		if ( ! function_exists( 'gravity_form' ) ) {
			$this->render_notice( esc_html__( 'Gravity Forms is not active.', 'endeavour' ) );
			return;
		}

		if ( $form_id < 1 ) {
			$this->render_notice( esc_html__( 'Select a form.', 'endeavour' ) );
			return;
		}

		$document = class_exists( '\Elementor\Plugin' )
			? \Elementor\Plugin::$instance->documents->get_current()
			: null;
		$post_id  = $document ? (int) $document->get_main_id() : (int) get_the_ID();

		endeavour_gf_render_context_open(
			$post_id,
			$this->get_id(),
			isset( $settings['submit_note'] ) ? (string) $settings['submit_note'] : ''
		);

		if ( function_exists( 'gravity_form_enqueue_scripts' ) ) {
			gravity_form_enqueue_scripts( $form_id, true );
		}

		gravity_form(
			$form_id,
			false,
			false,
			false,
			null,
			'yes' === ( $settings['ajax'] ?? '' ),
			0,
			true,
			'gravity-theme'
		);

		endeavour_gf_render_context_close();
	}

	/**
	 * Editor hint when the widget cannot render a form yet.
	 *
	 * @param string $message Notice text.
	 */
	private function render_notice( $message ) {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return;
		}

		echo '<p class="endeavour-gravity-form__notice">' . esc_html( $message ) . '</p>';
	}
}
