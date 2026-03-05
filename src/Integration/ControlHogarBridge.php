<?php

namespace CCF\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridge ligero: si detecta [ccf_app] y Control Hogar está activo, inyecta [control_hogar_app] en la MISMA página.
 * (Y viceversa lo hace Control Hogar).
 */
class ControlHogarBridge {

	public function register(): void {
		add_action( 'wp', array( $this, 'maybe_inject_shortcodes' ), 1 );
	}

	private function control_hogar_is_active(): bool {
		// Control Hogar define CH_VERSION cuando está cargado.
		if ( defined( 'CH_VERSION' ) ) {
			return true;
		}

		return class_exists( 'CH_Core' ) || class_exists( 'CH_Frontend' );
	}

	private function lista_compra_is_active(): bool {
		return defined( 'LC_PLUGIN_VERSION' ) || class_exists( 'LC_DB' );
	}

	public function maybe_inject_shortcodes(): void {
		if ( ! is_page() ) {
			return;
		}

		global $post;

		if ( ! $post || empty( $post->post_content ) ) {
			return;
		}

		$has_ccf = strpos( $post->post_content, '[ccf_app]' ) !== false;
		$has_ch  = strpos( $post->post_content, '[control_hogar_app]' ) !== false;
		$has_lc  = strpos( $post->post_content, '[lista_compra_app]' ) !== false;

		if ( $has_ccf && ! $has_ch && $this->control_hogar_is_active() ) {
			$post->post_content .= "\n\n[control_hogar_app]\n";
		}

		if ( $has_ccf && ! $has_lc && $this->lista_compra_is_active() ) {
			$post->post_content .= "\n\n[lista_compra_app]\n";
		}
	}
}
