<?php

namespace CCF\Frontend;

use CCF\Services\DashboardService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcodes {
	public function __construct(
		private DashboardService $dashboard_service
	) {}

	public function register(): void {
		add_shortcode( 'caja_comun_dashboard', array( $this, 'render_legacy_dashboard' ) );
		add_shortcode( 'ccf_app', array( $this, 'render_app' ) );
		add_shortcode( 'ccf_dashboard', array( $this, 'render_deprecated_frontend_shortcode' ) );
		add_shortcode( 'ccf_income_form', array( $this, 'render_deprecated_frontend_shortcode' ) );
		add_shortcode( 'ccf_transaction_form', array( $this, 'render_deprecated_frontend_shortcode' ) );
		add_shortcode( 'ccf_transactions_list', array( $this, 'render_deprecated_frontend_shortcode' ) );
		add_shortcode( 'ccf_login', array( $this, 'render_deprecated_access_shortcode' ) );
		add_shortcode( 'ccf_logout', array( $this, 'render_deprecated_access_shortcode' ) );
	}

	public function render_legacy_dashboard( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>Debes iniciar sesión para ver Caja Común.</p>';
		}
		$atts    = shortcode_atts( array( 'month' => gmdate( 'Y-m' ) ), $atts, 'caja_comun_dashboard' );
		$summary = $this->dashboard_service->month_summary( sanitize_text_field( $atts['month'] ) );
		ob_start();
		require CCF_PATH . 'templates/frontend/dashboard-shortcode.php';
		return (string) ob_get_clean();
	}

	public function render_app(): string {
		$this->enqueue_assets();
		ob_start();
		require CCF_PATH . 'templates/frontend/app.php';
		return (string) ob_get_clean();
	}

	public function render_deprecated_frontend_shortcode(): string {
		$this->enqueue_assets();
		return '<div class="ccf-card ccf-legacy-notice"><p>Este shortcode permanece por compatibilidad, pero la experiencia recomendada es <code>[ccf_app]</code>.</p></div>';
	}

	public function render_deprecated_access_shortcode(): string {
		return '<div class="ccf-card ccf-legacy-notice"><p>Este shortcode ya no es necesario. Protege la página con contraseña nativa de WordPress y usa <code>[ccf_app]</code>.</p></div>';
	}

	private function enqueue_assets(): void {
		wp_enqueue_style( 'ccf-frontend', CCF_URL . 'assets/css/frontend.css', array(), CCF_VERSION );
		wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', array(), '4.4.1', true );
		wp_enqueue_script( 'ccf-frontend', CCF_URL . 'assets/js/frontend.js', array( 'chart-js' ), CCF_VERSION, true );
		wp_localize_script(
			'ccf-frontend',
			'CCF_FRONTEND',
			array(
				'apiBase' => esc_url_raw( rest_url( 'caja-comun/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
