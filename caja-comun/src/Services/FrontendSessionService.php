<?php

namespace CCF\Services;

use CCF\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FrontendSessionService {
	private const COOKIE_NAME = 'ccf_front_session';
	private const DEFAULT_DURATION_HOURS = 12;
	private const DEFAULT_MAX_ATTEMPTS = 5;
	private const DEFAULT_BLOCK_MINUTES = 15;

	public function __construct( private SettingsRepository $settings_repository ) {}

	public function is_frontend_enabled(): bool {
		return '1' === $this->settings_repository->get( 'ccf_enable_frontend_app', '1' );
	}

	public function get_session_duration_hours(): int {
		$hours = (int) $this->settings_repository->get( 'ccf_frontend_session_hours', (string) self::DEFAULT_DURATION_HOURS );
		return max( 1, min( 168, $hours ) );
	}

	public function set_frontend_password( string $plain_password ): bool {
		$hash = password_hash( $plain_password, PASSWORD_DEFAULT );
		if ( ! $hash ) {
			return false;
		}
		return $this->settings_repository->set( 'ccf_frontend_password_hash', $hash );
	}

	public function has_password_configured(): bool {
		$hash = (string) $this->settings_repository->get( 'ccf_frontend_password_hash', '' );
		return '' !== $hash;
	}

	public function login( string $plain_password ): array {
		if ( ! $this->is_frontend_enabled() ) {
			return array( 'ok' => false, 'error' => 'frontend_disabled' );
		}

		$rate_limit = $this->rate_limit_state();
		if ( $rate_limit['blocked'] ) {
			return array( 'ok' => false, 'error' => 'rate_limited', 'retry_after' => $rate_limit['retry_after'] );
		}

		$hash = (string) $this->settings_repository->get( 'ccf_frontend_password_hash', '' );
		if ( '' === $hash || ! password_verify( $plain_password, $hash ) ) {
			$this->record_login_failure();
			return array( 'ok' => false, 'error' => 'invalid_password' );
		}

		$this->clear_login_failures();
		$token = $this->create_session_token();
		$this->set_cookie( $token );

		return array(
			'ok' => true,
			'token' => $token,
			'expires_at' => $this->token_payload( $token )['exp'] ?? 0,
			'nonce' => $this->token_payload( $token )['nonce'] ?? '',
		);
	}

	public function logout( ?string $token = null ): void {
		$token = $token ?: $this->read_cookie_token();
		if ( $token ) {
			$payload = $this->token_payload( $token );
			if ( ! empty( $payload['jti'] ) ) {
				delete_transient( 'ccf_front_session_' . $payload['jti'] );
			}
		}
		$this->clear_cookie();
	}

	public function get_current_session(): ?array {
		$token = $this->read_header_token();
		if ( ! $token ) {
			$token = $this->read_cookie_token();
		}
		if ( ! $token ) {
			return null;
		}

		$payload = $this->verify_session_token( $token );
		if ( ! $payload ) {
			return null;
		}

		return array(
			'token' => $token,
			'payload' => $payload,
		);
	}

	public function validate_action_nonce( ?string $nonce ): bool {
		$session = $this->get_current_session();
		if ( ! $session ) {
			return false;
		}
		return hash_equals( (string) $session['payload']['nonce'], (string) $nonce );
	}

	public function require_action_nonce( string $method, ?string $nonce ): bool {
		if ( in_array( strtoupper( $method ), array( 'GET', 'HEAD', 'OPTIONS' ), true ) ) {
			return true;
		}
		return $this->validate_action_nonce( $nonce );
	}

	public function maybe_redirect_to_login(): void {
		if ( is_admin() || ! $this->is_frontend_enabled() ) {
			return;
		}
		if ( ! is_page() ) {
			return;
		}
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$content = (string) $post->post_content;
		$has_protected_shortcode = has_shortcode( $content, 'ccf_app' ) || has_shortcode( $content, 'ccf_dashboard' ) || has_shortcode( $content, 'ccf_income_form' ) || has_shortcode( $content, 'ccf_transaction_form' ) || has_shortcode( $content, 'ccf_transactions_list' );
		if ( ! $has_protected_shortcode ) {
			return;
		}
		if ( $this->get_current_session() ) {
			return;
		}
		$login_url = (string) $this->settings_repository->get( 'ccf_frontend_login_url', '' );
		$login_url = $login_url ?: home_url( '/' );
		wp_safe_redirect( add_query_arg( 'ccf_redirect', rawurlencode( (string) get_permalink( $post ) ), $login_url ) );
		exit;
	}

	private function create_session_token(): string {
		$ttl     = $this->get_session_duration_hours() * HOUR_IN_SECONDS;
		$payload = array(
			'iat' => time(),
			'exp' => time() + $ttl,
			'jti' => wp_generate_uuid4(),
			'nonce' => wp_generate_password( 20, false, false ),
		);

		$payload_json = wp_json_encode( $payload );
		$encoded      = rtrim( strtr( base64_encode( $payload_json ), '+/', '-_' ), '=' );
		$signature    = hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ) );
		set_transient( 'ccf_front_session_' . $payload['jti'], 1, $ttl );
		return $encoded . '.' . $signature;
	}

	private function verify_session_token( string $token ): ?array {
		$parts = explode( '.', $token, 2 );
		if ( 2 !== count( $parts ) ) {
			return null;
		}
		list( $encoded, $signature ) = $parts;
		$expected = hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ) );
		if ( ! hash_equals( $expected, $signature ) ) {
			return null;
		}
		$payload = $this->token_payload( $token );
		if ( ! $payload || empty( $payload['exp'] ) || time() > (int) $payload['exp'] ) {
			return null;
		}
		if ( empty( $payload['jti'] ) || ! get_transient( 'ccf_front_session_' . $payload['jti'] ) ) {
			return null;
		}
		return $payload;
	}

	private function token_payload( string $token ): ?array {
		$parts = explode( '.', $token, 2 );
		if ( empty( $parts[0] ) ) {
			return null;
		}
		$decoded = base64_decode( strtr( $parts[0], '-_', '+/' ) );
		if ( ! $decoded ) {
			return null;
		}
		$data = json_decode( $decoded, true );
		return is_array( $data ) ? $data : null;
	}

	private function set_cookie( string $token ): void {
		$expires = time() + ( $this->get_session_duration_hours() * HOUR_IN_SECONDS );
		setcookie(
			self::COOKIE_NAME,
			$token,
			array(
				'expires' => $expires,
				'path' => COOKIEPATH ?: '/',
				'domain' => COOKIE_DOMAIN ?: '',
				'secure' => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ self::COOKIE_NAME ] = $token;
	}

	private function clear_cookie(): void {
		setcookie(
			self::COOKIE_NAME,
			'',
			array(
				'expires' => time() - HOUR_IN_SECONDS,
				'path' => COOKIEPATH ?: '/',
				'domain' => COOKIE_DOMAIN ?: '',
				'secure' => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		unset( $_COOKIE[ self::COOKIE_NAME ] );
	}

	private function read_cookie_token(): ?string {
		$token = $_COOKIE[ self::COOKIE_NAME ] ?? '';
		return is_string( $token ) && '' !== $token ? sanitize_text_field( wp_unslash( $token ) ) : null;
	}

	private function read_header_token(): ?string {
		$header = $_SERVER['HTTP_X_CCF_SESSION'] ?? '';
		return is_string( $header ) && '' !== $header ? sanitize_text_field( wp_unslash( $header ) ) : null;
	}

	private function client_key(): string {
		$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		return 'ccf_front_login_attempts_' . md5( (string) $ip );
	}

	private function rate_limit_state(): array {
		$data = get_transient( $this->client_key() );
		if ( ! is_array( $data ) ) {
			$data = array( 'attempts' => 0, 'blocked_until' => 0 );
		}
		$blocked_until = (int) ( $data['blocked_until'] ?? 0 );
		$retry_after   = max( 0, $blocked_until - time() );
		return array( 'blocked' => $retry_after > 0, 'retry_after' => $retry_after );
	}

	private function record_login_failure(): void {
		$max_attempts = (int) $this->settings_repository->get( 'ccf_frontend_max_attempts', (string) self::DEFAULT_MAX_ATTEMPTS );
		$block_mins   = (int) $this->settings_repository->get( 'ccf_frontend_block_minutes', (string) self::DEFAULT_BLOCK_MINUTES );
		$max_attempts = max( 3, min( 20, $max_attempts ) );
		$block_mins   = max( 1, min( 60, $block_mins ) );

		$key  = $this->client_key();
		$data = get_transient( $key );
		if ( ! is_array( $data ) ) {
			$data = array( 'attempts' => 0, 'blocked_until' => 0 );
		}
		$data['attempts'] = (int) $data['attempts'] + 1;
		if ( $data['attempts'] >= $max_attempts ) {
			$data['blocked_until'] = time() + ( $block_mins * MINUTE_IN_SECONDS );
			$data['attempts']      = 0;
		}
		set_transient( $key, $data, $block_mins * MINUTE_IN_SECONDS );
	}

	private function clear_login_failures(): void {
		delete_transient( $this->client_key() );
	}
}
