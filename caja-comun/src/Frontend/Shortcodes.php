<?php

namespace CCF\Frontend;

use CCF\Repositories\SettingsRepository;
use CCF\Services\DashboardService;
use CCF\Services\FrontendSessionService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcodes {
	public function __construct(
		private DashboardService $dashboard_service,
		private FrontendSessionService $session_service,
		private SettingsRepository $settings_repository
	) {}

	public function register(): void {
		add_shortcode( 'caja_comun_dashboard', array( $this, 'render_legacy_dashboard' ) );
		add_shortcode( 'ccf_login', array( $this, 'render_login' ) );
		add_shortcode( 'ccf_app', array( $this, 'render_app' ) );
		add_shortcode( 'ccf_dashboard', array( $this, 'render_dashboard' ) );
		add_shortcode( 'ccf_income_form', array( $this, 'render_income_form' ) );
		add_shortcode( 'ccf_transaction_form', array( $this, 'render_transaction_form' ) );
		add_shortcode( 'ccf_transactions_list', array( $this, 'render_transactions_list' ) );
		add_shortcode( 'ccf_logout', array( $this, 'render_logout' ) );
	}

	public function render_legacy_dashboard( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>Debes iniciar sesión para ver Caja Común.</p>';
		}
		$atts = shortcode_atts( array( 'month' => gmdate( 'Y-m' ), 'show_incomes' => 'yes', 'show_movements' => 'yes' ), $atts, 'caja_comun_dashboard' );
		$summary = $this->dashboard_service->month_summary( sanitize_text_field( $atts['month'] ) );
		ob_start();
		require CCF_PATH . 'templates/frontend/dashboard-shortcode.php';
		return (string) ob_get_clean();
	}

	public function render_login(): string {
		$this->enqueue_assets();
		$login_url = (string) get_permalink();
		$this->settings_repository->set( 'ccf_frontend_login_url', esc_url_raw( $login_url ) );
		ob_start();
		require CCF_PATH . 'templates/frontend/login.php';
		return (string) ob_get_clean();
	}

	public function render_app(): string {
		if ( ! $this->require_auth() ) {
			return $this->blocked_message();
		}
		$this->enqueue_assets();
		ob_start();
		require CCF_PATH . 'templates/frontend/app.php';
		return (string) ob_get_clean();
	}

	public function render_dashboard(): string {
		if ( ! $this->require_auth() ) {
			return $this->blocked_message();
		}
		$this->enqueue_assets();
		ob_start();
		require CCF_PATH . 'templates/frontend/dashboard.php';
		return (string) ob_get_clean();
	}

	public function render_income_form(): string {
		if ( ! $this->require_auth() ) {
			return $this->blocked_message();
		}
		$this->enqueue_assets();
		ob_start();
		require CCF_PATH . 'templates/frontend/income-form.php';
		return (string) ob_get_clean();
	}

	public function render_transaction_form(): string {
		if ( ! $this->require_auth() ) {
			return $this->blocked_message();
		}
		$this->enqueue_assets();
		ob_start();
		require CCF_PATH . 'templates/frontend/transaction-form.php';
		return (string) ob_get_clean();
	}

	public function render_transactions_list(): string {
		if ( ! $this->require_auth() ) {
			return $this->blocked_message();
		}
		$this->enqueue_assets();
		ob_start();
		require CCF_PATH . 'templates/frontend/transactions-list.php';
		return (string) ob_get_clean();
	}

	public function render_logout(): string {
		$this->session_service->logout();
		return '<div class="ccf-card"><p>Sesión cerrada correctamente.</p></div>';
	}

	private function enqueue_assets(): void {
		wp_enqueue_style( 'ccf-frontend', CCF_URL . 'assets/css/frontend.css', array(), CCF_VERSION );
		wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', array(), '4.4.1', true );
		wp_enqueue_script( 'ccf-frontend', CCF_URL . 'assets/js/frontend.js', array( 'chart-js' ), CCF_VERSION, true );
		$session = $this->session_service->get_current_session();
		wp_localize_script(
			'ccf-frontend',
			'CCF_FRONTEND',
			array(
				'apiBase' => esc_url_raw( rest_url( 'caja-comun/v1/' ) ),
				'token' => $session['token'] ?? '',
				'nonce' => $session['payload']['nonce'] ?? '',
				'redirect' => isset( $_GET['ccf_redirect'] ) ? esc_url_raw( wp_unslash( $_GET['ccf_redirect'] ) ) : '',
			)
		);
	}

	private function require_auth(): bool {
		return (bool) $this->session_service->get_current_session();
	}

	private function blocked_message(): string {
		$login_url = (string) $this->settings_repository->get( 'ccf_frontend_login_url', home_url( '/' ) );
		return '<div class="ccf-card"><p>Acceso privado. <a href="' . esc_url( $login_url ) . '">Inicia sesión aquí</a>.</p></div>';
	}
}
