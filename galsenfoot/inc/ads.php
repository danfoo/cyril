<?php
/**
 * Google advertising and measurement:
 *  - AdSense (loader script — which also enables Auto ads when switched on in the AdSense account — and manual ad units per location) or any other ad code (Google Ad Manager…);
 *  - Google tag for Google Ads conversions / remarketing and Google Analytics 4;
 *  - Consent Mode v2 with a lightweight consent banner;
 *  - ads.txt generated from the publisher ID.
 *
 * Ad containers reserve their height to avoid layout shift (Core Web Vitals).
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ad locations offered by the theme.
 *
 * @return array key => [label, description, format].
 */
function gf_ad_locations() {
	return array(
		'header'         => array( __( 'Sous l’en-tête', 'galsenfoot' ), __( 'Bannière large en haut de toutes les pages.', 'galsenfoot' ), 'banner' ),
		'home_top'       => array( __( 'Accueil — sous la une', 'galsenfoot' ), __( 'Entre la une et les blocs championnat.', 'galsenfoot' ), 'banner' ),
		'home_middle'    => array( __( 'Accueil — milieu de page', 'galsenfoot' ), __( 'Entre les rubriques.', 'galsenfoot' ), 'banner' ),
		'in_feed'        => array( __( 'Dans les listes d’articles', 'galsenfoot' ), __( 'Inséré tous les N articles dans les archives.', 'galsenfoot' ), 'fluid' ),
		'article_top'    => array( __( 'Article — avant le texte', 'galsenfoot' ), __( 'Sous l’image principale.', 'galsenfoot' ), 'banner' ),
		'in_article'     => array( __( 'Article — dans le texte', 'galsenfoot' ), __( 'Inséré automatiquement entre les paragraphes.', 'galsenfoot' ), 'in-article' ),
		'article_bottom' => array( __( 'Article — après le texte', 'galsenfoot' ), __( 'Avant les articles liés.', 'galsenfoot' ), 'banner' ),
		'sidebar'        => array( __( 'Colonne latérale', 'galsenfoot' ), __( 'Pavé ou grand format vertical.', 'galsenfoot' ), 'rectangle' ),
		'footer'         => array( __( 'Avant le pied de page', 'galsenfoot' ), __( 'Bannière large en bas de toutes les pages.', 'galsenfoot' ), 'banner' ),
	);
}

/**
 * Ads settings with defaults.
 *
 * @param string|null $key Optional key.
 * @return mixed
 */
function gf_ads_settings( $key = null ) {
	static $settings = null;
	if ( null === $settings || is_admin() ) {
		$settings = wp_parse_args(
			(array) get_option( 'gf_ads', array() ),
			array(
				'adsense_client'    => '',
				'gtag_ads'          => '',
				'gtag_ga4'          => '',
				'consent'           => 0,
				'consent_text'      => '',
				'privacy_page'      => 0,
				'hide_logged'       => 0,
				'ads_txt'           => 1,
				'ads_txt_extra'     => '',
				'in_article_after'  => 3,
				'in_article_repeat' => 6,
				'in_article_max'    => 3,
				'in_feed_every'     => 6,
				'slots'             => array(),
			)
		);
	}
	return null === $key ? $settings : ( $settings[ $key ] ?? null );
}

/**
 * Whether ads may be shown on the current view.
 */
function gf_ads_enabled() {
	if ( is_admin() || is_feed() || is_404() || is_preview() ) {
		return false;
	}
	if ( gf_ads_settings( 'hide_logged' ) && current_user_can( 'edit_posts' ) ) {
		return false;
	}
	if ( is_singular() && get_post_meta( get_queried_object_id(), '_gf_no_ads', true ) ) {
		return false;
	}
	return (bool) apply_filters( 'gf_ads_enabled', true );
}

/**
 * Markup of an ad location.
 *
 * @param string $location Location key.
 * @return string
 */
function gf_ad( $location ) {
	$locations = gf_ad_locations();
	if ( ! isset( $locations[ $location ] ) ) {
		return '';
	}
	$settings = gf_ads_settings();
	$slot     = wp_parse_args(
		(array) ( $settings['slots'][ $location ] ?? array() ),
		array(
			'enabled' => 0,
			'slot_id' => '',
			'code'    => '',
		)
	);
	$format   = $locations[ $location ][2];
	$inner    = '';

	if ( $slot['enabled'] && gf_ads_enabled() ) {
		if ( '' !== trim( $slot['code'] ) ) {
			// Custom code (Ad Manager, direct campaign…) saved by an administrator.
			$inner = $slot['code'];
		} elseif ( $settings['adsense_client'] && $slot['slot_id'] ) {
			$attrs = 'data-ad-format="auto" data-full-width-responsive="true"';
			if ( 'in-article' === $format ) {
				$attrs = 'data-ad-layout="in-article" data-ad-format="fluid"';
			} elseif ( 'fluid' === $format ) {
				$attrs = 'data-ad-format="fluid"';
			} elseif ( 'rectangle' === $format ) {
				$attrs = 'data-ad-format="rectangle, vertical" data-full-width-responsive="false"';
			}
			$inner = sprintf(
				'<ins class="adsbygoogle" style="display:block%4$s" data-ad-client="%1$s" data-ad-slot="%2$s" %3$s></ins><script>(adsbygoogle=window.adsbygoogle||[]).push({});</script>',
				esc_attr( $settings['adsense_client'] ),
				esc_attr( $slot['slot_id'] ),
				$attrs,
				'in-article' === $format ? ';text-align:center' : ''
			);
		}
	}

	if ( '' === $inner ) {
		// Help administrators see where the locations are (never shown to visitors).
		$in_editor = class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->editor && \Elementor\Plugin::$instance->editor->is_edit_mode();
		if ( ( current_user_can( 'manage_options' ) && ! gf_ads_settings( 'hide_logged' ) && ! $slot['enabled'] && is_customize_preview() ) || $in_editor ) {
			return '<div class="gf-ad gf-ad--' . esc_attr( $format ) . ' gf-ad--placeholder"><span>' . esc_html( sprintf( /* translators: %s location */ __( 'Emplacement publicitaire : %s', 'galsenfoot' ), $locations[ $location ][0] ) ) . '</span></div>';
		}
		return '';
	}

	return '<aside class="gf-ad gf-ad--' . esc_attr( $format ) . ' gf-ad--' . esc_attr( $location ) . '" aria-label="' . esc_attr__( 'Publicité', 'galsenfoot' ) . '"><span class="gf-ad__label">' . esc_html__( 'Publicité', 'galsenfoot' ) . '</span><div class="gf-ad__inner">' . $inner . '</div></aside>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

add_shortcode(
	'gf_ad',
	static function ( $atts ) {
		$atts = shortcode_atts( array( 'slot' => 'in_article' ), $atts );
		return gf_ad( sanitize_key( $atts['slot'] ) );
	}
);

/**
 * Head: Consent Mode defaults, Google tag, AdSense loader.
 */
add_action(
	'wp_head',
	static function () {
		$s       = gf_ads_settings();
		$ids     = array_values( array_filter( array( $s['gtag_ga4'], $s['gtag_ads'] ) ) );
		$adsense = $s['adsense_client'] && gf_ads_enabled();
		if ( ! $ids && ! $adsense ) {
			return;
		}
		echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}\n";
		if ( $s['consent'] ) {
			// Consent Mode v2: everything denied until the visitor chooses; a stored choice is restored at once.
			echo "(function(){var c=null;try{c=localStorage.getItem('gf-consent')}catch(e){}var g=c==='granted'?'granted':'denied';gtag('consent','default',{ad_storage:g,ad_user_data:g,ad_personalization:g,analytics_storage:g,wait_for_update:c?0:500});})();\n";
		}
		if ( $ids ) {
			echo "gtag('js',new Date());";
			foreach ( $ids as $id ) {
				echo "gtag('config'," . wp_json_encode( $id ) . ');';
			}
		}
		echo "</script>\n";
		if ( $ids ) {
			echo '<script async src="' . esc_url( 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $ids[0] ) ) . '"></script>' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources
		}
		if ( $adsense ) {
			echo '<script async src="' . esc_url( 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . rawurlencode( $s['adsense_client'] ) ) . '" crossorigin="anonymous"></script>' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources
			// Site ownership verification for AdSense.
			echo '<meta name="google-adsense-account" content="' . esc_attr( $s['adsense_client'] ) . '">' . "\n";
		}
	},
	2
);

/**
 * Resource hints for the ad / tag hosts in use.
 */
add_filter(
	'wp_resource_hints',
	static function ( $urls, $relation ) {
		if ( 'preconnect' === $relation && ! is_admin() ) {
			$s = gf_ads_settings();
			if ( $s['adsense_client'] && gf_ads_enabled() ) {
				$urls[] = 'https://pagead2.googlesyndication.com';
			}
			if ( $s['gtag_ga4'] || $s['gtag_ads'] ) {
				$urls[] = 'https://www.googletagmanager.com';
			}
		}
		return $urls;
	},
	10,
	2
);

/**
 * Insert in-article ads between paragraphs.
 */
add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$ad = gf_ad( 'in_article' );
		if ( '' === $ad ) {
			return $content;
		}
		$s      = gf_ads_settings();
		$after  = max( 1, (int) $s['in_article_after'] );
		$repeat = max( 0, (int) $s['in_article_repeat'] );
		$max    = max( 1, (int) $s['in_article_max'] );
		$parts  = explode( '</p>', $content );
		$total  = count( $parts ) - 1;
		if ( $total < $after + 1 ) {
			return $content;
		}
		$out    = '';
		$placed = 0;
		$depth  = 0;
		foreach ( $parts as $i => $part ) {
			$out .= $part . ( $i < $total ? '</p>' : '' );
			// Never break a blockquote, list, table or figure.
			$depth += preg_match_all( '/<(blockquote|ul|ol|table|figure)\b/i', $part ) - preg_match_all( '/<\/(blockquote|ul|ol|table|figure)>/i', $part );
			$n      = $i + 1;
			$due    = $n === $after || ( $repeat && $n > $after && 0 === ( $n - $after ) % $repeat );
			if ( $due && $placed < $max && $depth <= 0 && $n < $total ) {
				$out .= $ad;
				++$placed;
			}
		}
		return $out;
	},
	15
);

/**
 * Consent banner.
 */
add_action(
	'wp_footer',
	static function () {
		$s = gf_ads_settings();
		if ( ! $s['consent'] || ( ! $s['adsense_client'] && ! $s['gtag_ga4'] && ! $s['gtag_ads'] ) ) {
			return;
		}
		$text    = $s['consent_text'] ? $s['consent_text'] : __( 'Nous utilisons des cookies pour mesurer l’audience et afficher des publicités adaptées. Vous pouvez accepter ou refuser.', 'galsenfoot' );
		$privacy = (int) $s['privacy_page'] ? get_permalink( (int) $s['privacy_page'] ) : get_privacy_policy_url();
		?>
<div class="gf-consent" id="gf-consent" role="dialog" aria-live="polite" aria-label="<?php esc_attr_e( 'Cookies', 'galsenfoot' ); ?>" hidden>
	<p><?php echo esc_html( $text ); ?>
		<?php if ( $privacy ) : ?>
			<a href="<?php echo esc_url( $privacy ); ?>"><?php esc_html_e( 'En savoir plus', 'galsenfoot' ); ?></a>
		<?php endif; ?>
	</p>
	<div class="gf-consent__actions">
		<button type="button" class="gf-btn gf-btn--ghost" data-gf-consent="denied"><?php esc_html_e( 'Refuser', 'galsenfoot' ); ?></button>
		<button type="button" class="gf-btn" data-gf-consent="granted"><?php esc_html_e( 'Accepter', 'galsenfoot' ); ?></button>
	</div>
</div>
<script>(function(){var b=document.getElementById('gf-consent');if(!b)return;var c=null;try{c=localStorage.getItem('gf-consent')}catch(e){}if(!c){b.hidden=false}b.addEventListener('click',function(e){var t=e.target.closest('[data-gf-consent]');if(!t)return;var v=t.getAttribute('data-gf-consent');try{localStorage.setItem('gf-consent',v)}catch(e){}if(window.gtag){gtag('consent','update',{ad_storage:v,ad_user_data:v,ad_personalization:v,analytics_storage:v})}b.hidden=true});document.addEventListener('click',function(e){if(e.target.closest('[data-gf-consent-open]')){e.preventDefault();b.hidden=false}})})();</script>
		<?php
	},
	50
);

/**
 * Virtual /ads.txt built from the AdSense publisher ID (skipped when a real file exists).
 */
add_action(
	'init',
	static function () {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
		$base = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( ! $path || rtrim( (string) $base, '/' ) . '/ads.txt' !== $path ) {
			return;
		}
		$s = gf_ads_settings();
		if ( ! $s['ads_txt'] ) {
			return;
		}
		$lines = array();
		if ( preg_match( '/^ca-(pub-\d+)$/', $s['adsense_client'], $found ) ) {
			$lines[] = 'google.com, ' . $found[1] . ', DIRECT, f08c47fec0942fa0';
		}
		foreach ( preg_split( '/\R/', (string) $s['ads_txt_extra'] ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}
		if ( ! $lines ) {
			return;
		}
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		echo esc_html( implode( "\n", $lines ) ) . "\n";
		exit;
	},
	1
);

/**
 * Per-content switch: disable ads on a given post or page.
 */
add_action(
	'add_meta_boxes',
	static function () {
		add_meta_box(
			'gf_no_ads',
			__( 'Publicité', 'galsenfoot' ),
			static function ( $post ) {
				wp_nonce_field( 'gf_no_ads', 'gf_no_ads_nonce' );
				echo '<label><input type="checkbox" name="gf_no_ads" value="1"' . checked( (bool) get_post_meta( $post->ID, '_gf_no_ads', true ), true, false ) . '> ' . esc_html__( 'Ne pas afficher de publicité sur ce contenu', 'galsenfoot' ) . '</label>';
			},
			array( 'post', 'page' ),
			'side'
		);
	}
);
add_action(
	'save_post',
	static function ( $post_id ) {
		if ( ! isset( $_POST['gf_no_ads_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['gf_no_ads_nonce'] ), 'gf_no_ads' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! empty( $_POST['gf_no_ads'] ) ) {
			update_post_meta( $post_id, '_gf_no_ads', 1 );
		} else {
			delete_post_meta( $post_id, '_gf_no_ads' );
		}
	}
);
