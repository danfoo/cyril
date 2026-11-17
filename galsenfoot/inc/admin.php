<?php
/**
 * Admin screens: Google ads & tags settings, quick start tools.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function () {
		add_theme_page( __( 'Publicités & Google', 'galsenfoot' ), __( 'Publicités & Google', 'galsenfoot' ), 'manage_options', 'gf-ads', 'gf_ads_page' );
		add_theme_page( __( 'Démarrage GalsenFoot', 'galsenfoot' ), __( 'Démarrage GalsenFoot', 'galsenfoot' ), 'manage_options', 'gf-setup', 'gf_setup_page' );
	}
);

add_action(
	'admin_init',
	static function () {
		register_setting(
			'gf_ads',
			'gf_ads',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'gf_ads_sanitize',
			)
		);
	}
);

/**
 * Sanitize ads settings.
 *
 * @param mixed $input Raw input.
 * @return array
 */
function gf_ads_sanitize( $input ) {
	$input  = (array) $input;
	$old    = (array) get_option( 'gf_ads', array() );
	$client = strtolower( trim( (string) ( $input['adsense_client'] ?? '' ) ) );
	if ( preg_match( '/^pub-\d+$/', $client ) ) {
		$client = 'ca-' . $client;
	}
	if ( '' !== $client && ! preg_match( '/^ca-pub-\d{10,20}$/', $client ) ) {
		add_settings_error( 'gf_ads', 'client', __( 'Identifiant éditeur AdSense invalide. Format attendu : ca-pub-1234567890123456.', 'galsenfoot' ) );
		$client = '';
	}
	$tag = static function ( $value, $pattern, $message ) {
		$value = strtoupper( trim( (string) $value ) );
		if ( '' !== $value && ! preg_match( $pattern, $value ) ) {
			add_settings_error( 'gf_ads', 'tag', $message );
			return '';
		}
		return $value;
	};
	$clean = array(
		'adsense_client'    => $client,
		'gtag_ads'          => $tag( $input['gtag_ads'] ?? '', '/^AW-\d{6,14}$/', __( 'ID Google Ads invalide. Format attendu : AW-123456789.', 'galsenfoot' ) ),
		'gtag_ga4'          => $tag( $input['gtag_ga4'] ?? '', '/^G-[A-Z0-9]{6,14}$/', __( 'ID Google Analytics invalide. Format attendu : G-XXXXXXXXXX.', 'galsenfoot' ) ),
		'consent'           => empty( $input['consent'] ) ? 0 : 1,
		'consent_text'      => sanitize_textarea_field( $input['consent_text'] ?? '' ),
		'privacy_page'      => absint( $input['privacy_page'] ?? 0 ),
		'hide_logged'       => empty( $input['hide_logged'] ) ? 0 : 1,
		'ads_txt'           => empty( $input['ads_txt'] ) ? 0 : 1,
		'ads_txt_extra'     => sanitize_textarea_field( $input['ads_txt_extra'] ?? '' ),
		'in_article_after'  => min( 20, max( 1, absint( $input['in_article_after'] ?? 3 ) ) ),
		'in_article_repeat' => min( 30, absint( $input['in_article_repeat'] ?? 6 ) ),
		'in_article_max'    => min( 10, max( 1, absint( $input['in_article_max'] ?? 3 ) ) ),
		'in_feed_every'     => min( 30, max( 2, absint( $input['in_feed_every'] ?? 6 ) ) ),
		'slots'             => array(),
	);
	foreach ( array_keys( gf_ad_locations() ) as $location ) {
		$slot = (array) ( $input['slots'][ $location ] ?? array() );
		// Raw ad code may contain scripts: only users allowed to post unfiltered HTML can change it.
		$code = current_user_can( 'unfiltered_html' ) ? trim( (string) ( $slot['code'] ?? '' ) ) : (string) ( $old['slots'][ $location ]['code'] ?? '' );
		$clean['slots'][ $location ] = array(
			'enabled' => empty( $slot['enabled'] ) ? 0 : 1,
			'slot_id' => preg_replace( '/\D/', '', (string) ( $slot['slot_id'] ?? '' ) ),
			'code'    => $code,
		);
	}
	return $clean;
}

/**
 * Ads settings page.
 */
function gf_ads_page() {
	$s = gf_ads_settings();
	?>
	<div class="wrap gf-admin">
		<h1><?php esc_html_e( 'Publicités & outils Google', 'galsenfoot' ); ?></h1>
		<?php settings_errors( 'gf_ads' ); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'gf_ads' ); ?>

			<h2 class="title"><?php esc_html_e( '1. Google AdSense (monétisation)', 'galsenfoot' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="gf_adsense_client"><?php esc_html_e( 'Identifiant éditeur', 'galsenfoot' ); ?></label></th>
					<td>
						<input type="text" id="gf_adsense_client" name="gf_ads[adsense_client]" value="<?php echo esc_attr( $s['adsense_client'] ); ?>" class="regular-text" placeholder="ca-pub-1234567890123456">
						<p class="description"><?php esc_html_e( 'Dès qu’il est renseigné, le script AdSense est chargé sur tout le site et sert aussi à valider le site dans AdSense. Les annonces automatiques (y compris ancrées et interstitielles) s’activent ensuite dans votre compte AdSense → Annonces → Par site.', 'galsenfoot' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'ads.txt', 'galsenfoot' ); ?></th>
					<td>
						<label><input type="checkbox" name="gf_ads[ads_txt]" value="1"<?php checked( $s['ads_txt'] ); ?>> <?php esc_html_e( 'Générer automatiquement le fichier ads.txt', 'galsenfoot' ); ?></label>
						<p class="description"><a href="<?php echo esc_url( home_url( '/ads.txt' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( home_url( '/ads.txt' ) ); ?></a></p>
						<p><label for="gf_ads_txt_extra"><?php esc_html_e( 'Lignes supplémentaires (autres régies, une par ligne)', 'galsenfoot' ); ?></label><br>
						<textarea id="gf_ads_txt_extra" name="gf_ads[ads_txt_extra]" rows="3" class="large-text code"><?php echo esc_textarea( $s['ads_txt_extra'] ); ?></textarea></p>
					</td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( '2. Emplacements publicitaires', 'galsenfoot' ); ?></h2>
			<p><?php esc_html_e( 'Pour chaque emplacement, collez l’ID du bloc d’annonces AdSense (10 chiffres, « data-ad-slot ») ou bien un code personnalisé (Google Ad Manager, campagne directe, bannière d’un sponsor…). Le code personnalisé est prioritaire.', 'galsenfoot' ); ?></p>
			<table class="widefat striped gf-ads-table">
				<thead><tr>
					<th><?php esc_html_e( 'Actif', 'galsenfoot' ); ?></th>
					<th><?php esc_html_e( 'Emplacement', 'galsenfoot' ); ?></th>
					<th><?php esc_html_e( 'ID du bloc AdSense', 'galsenfoot' ); ?></th>
					<th><?php esc_html_e( 'Ou code personnalisé (HTML / JavaScript)', 'galsenfoot' ); ?></th>
				</tr></thead>
				<tbody>
				<?php
				foreach ( gf_ad_locations() as $key => $location ) :
					$slot = wp_parse_args(
						(array) ( $s['slots'][ $key ] ?? array() ),
						array(
							'enabled' => 0,
							'slot_id' => '',
							'code'    => '',
						)
					);
					?>
					<tr>
						<td><input type="checkbox" name="gf_ads[slots][<?php echo esc_attr( $key ); ?>][enabled]" value="1"<?php checked( $slot['enabled'] ); ?> aria-label="<?php echo esc_attr( $location[0] ); ?>"></td>
						<td><strong><?php echo esc_html( $location[0] ); ?></strong><br><span class="description"><?php echo esc_html( $location[1] ); ?></span><br><code>[gf_ad slot="<?php echo esc_html( $key ); ?>"]</code></td>
						<td><input type="text" inputmode="numeric" name="gf_ads[slots][<?php echo esc_attr( $key ); ?>][slot_id]" value="<?php echo esc_attr( $slot['slot_id'] ); ?>" placeholder="1234567890" size="14"></td>
						<td><textarea name="gf_ads[slots][<?php echo esc_attr( $key ); ?>][code]" rows="2" class="large-text code"<?php disabled( ! current_user_can( 'unfiltered_html' ) ); ?>><?php echo esc_textarea( $slot['code'] ); ?></textarea></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Dans le texte des articles', 'galsenfoot' ); ?></th>
					<td>
						<label><?php esc_html_e( 'Première annonce après le paragraphe', 'galsenfoot' ); ?> <input type="number" class="small-text" min="1" max="20" name="gf_ads[in_article_after]" value="<?php echo (int) $s['in_article_after']; ?>"></label><br>
						<label><?php esc_html_e( 'puis tous les', 'galsenfoot' ); ?> <input type="number" class="small-text" min="0" max="30" name="gf_ads[in_article_repeat]" value="<?php echo (int) $s['in_article_repeat']; ?>"> <?php esc_html_e( 'paragraphes (0 = une seule annonce)', 'galsenfoot' ); ?></label><br>
						<label><?php esc_html_e( 'Maximum par article', 'galsenfoot' ); ?> <input type="number" class="small-text" min="1" max="10" name="gf_ads[in_article_max]" value="<?php echo (int) $s['in_article_max']; ?>"></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Dans les listes', 'galsenfoot' ); ?></th>
					<td><label><?php esc_html_e( 'Une annonce tous les', 'galsenfoot' ); ?> <input type="number" class="small-text" min="2" max="30" name="gf_ads[in_feed_every]" value="<?php echo (int) $s['in_feed_every']; ?>"> <?php esc_html_e( 'articles', 'galsenfoot' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Équipe du site', 'galsenfoot' ); ?></th>
					<td><label><input type="checkbox" name="gf_ads[hide_logged]" value="1"<?php checked( $s['hide_logged'] ); ?>> <?php esc_html_e( 'Masquer les publicités pour les rédacteurs et administrateurs connectés (évite les clics accidentels)', 'galsenfoot' ); ?></label></td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( '3. Google Ads et Google Analytics (balise Google)', 'galsenfoot' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="gf_gtag_ads"><?php esc_html_e( 'ID de conversion Google Ads', 'galsenfoot' ); ?></label></th>
					<td><input type="text" id="gf_gtag_ads" name="gf_ads[gtag_ads]" value="<?php echo esc_attr( $s['gtag_ads'] ); ?>" class="regular-text" placeholder="AW-123456789">
						<p class="description"><?php esc_html_e( 'Active le suivi des conversions et le remarketing pour vos campagnes Google Ads.', 'galsenfoot' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="gf_gtag_ga4"><?php esc_html_e( 'ID de mesure Google Analytics 4', 'galsenfoot' ); ?></label></th>
					<td><input type="text" id="gf_gtag_ga4" name="gf_ads[gtag_ga4]" value="<?php echo esc_attr( $s['gtag_ga4'] ); ?>" class="regular-text" placeholder="G-XXXXXXXXXX">
						<p class="description"><?php esc_html_e( 'Laissez vide si un autre plugin (Site Kit, etc.) installe déjà Analytics, pour ne pas compter les visites deux fois.', 'galsenfoot' ); ?></p></td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( '4. Consentement aux cookies', 'galsenfoot' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Bandeau de consentement', 'galsenfoot' ); ?></th>
					<td>
						<label><input type="checkbox" name="gf_ads[consent]" value="1"<?php checked( $s['consent'] ); ?>> <?php esc_html_e( 'Afficher le bandeau et appliquer le « Consent Mode v2 » de Google', 'galsenfoot' ); ?></label>
						<p class="description"><?php esc_html_e( 'Recommandé si vous avez des visiteurs en Europe (diaspora). Pour les annonces AdSense personnalisées dans l’EEE, Google exige une plateforme de consentement certifiée : activez alors le message « Confidentialité et messages » dans AdSense et laissez cette case décochée.', 'galsenfoot' ); ?></p>
						<p><textarea name="gf_ads[consent_text]" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Texte du bandeau (vide = texte par défaut)', 'galsenfoot' ); ?>"><?php echo esc_textarea( $s['consent_text'] ); ?></textarea></p>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'gf_ads[privacy_page]',
								'selected'          => (int) $s['privacy_page'],
								'show_option_none'  => esc_html__( '— Page de confidentialité de WordPress —', 'galsenfoot' ),
								'option_none_value' => 0,
							)
						);
						?>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Create a page once (by slug) and return its ID.
 *
 * @param string $title   Title.
 * @param string $slug    Slug.
 * @param string $content Content.
 * @return int
 */
function gf_setup_page_once( $title, $slug, $content ) {
	$existing = get_page_by_path( $slug );
	if ( $existing ) {
		return (int) $existing->ID;
	}
	return (int) wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
		)
	);
}

/**
 * Elementor data for the ready-made home page.
 *
 * @return array
 */
function gf_setup_elementor_home() {
	$uid     = static function () {
		return substr( md5( uniqid( '', true ) . wp_rand() ), 0, 7 );
	};
	$widget  = static function ( $type, $settings = array() ) use ( $uid ) {
		return array(
			'id'         => $uid(),
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => (object) $settings,
			'elements'   => array(),
		);
	};
	$section = static function ( $columns, $settings = array() ) use ( $uid ) {
		$cols = array();
		foreach ( $columns as $column ) {
			$cols[] = array(
				'id'       => $uid(),
				'elType'   => 'column',
				'settings' => array( '_column_size' => $column[0] ),
				'elements' => $column[1],
			);
		}
		return array(
			'id'       => $uid(),
			'elType'   => 'section',
			'settings' => (object) array_merge( array( 'gap' => 'wide' ), $settings ),
			'elements' => $cols,
		);
	};
	$league  = function_exists( 'gsf_render_standings' );
	$data    = array();
	$data[]  = $section( array( array( 100, array( $widget( 'gf-hero-news' ), $widget( 'gf-ad', array( 'location' => 'home_top' ) ) ) ) ) );
	if ( $league ) {
		$data[] = $section(
			array(
				array(
					66,
					array(
						$widget( 'gsf-next-match', array( 'title' => __( 'L’affiche', 'galsenfoot' ) ) ),
						$widget( 'gsf-matches', array( 'title' => __( 'Derniers résultats', 'galsenfoot' ), 'type' => 'results', 'limit' => 7 ) ),
						$widget( 'gsf-matches', array( 'title' => __( 'Prochains matchs', 'galsenfoot' ), 'type' => 'fixtures', 'limit' => 7 ) ),
					),
				),
				array(
					33,
					array(
						$widget( 'gsf-standings', array( 'title' => __( 'Classement', 'galsenfoot' ), 'compact' => 'yes', 'limit' => 10 ) ),
						$widget( 'gsf-leaders', array( 'title' => __( 'Buteurs', 'galsenfoot' ), 'limit' => 5 ) ),
						$widget( 'gf-ad', array( 'location' => 'sidebar' ) ),
					),
				),
			)
		);
	}
	$data[] = $section( array( array( 100, array( $widget( 'gf-posts', array( 'title' => __( 'Dernières infos', 'galsenfoot' ), 'count' => 6, 'offset' => 5, 'columns' => '3' ) ), $widget( 'gf-ad', array( 'location' => 'home_middle' ) ) ) ) ) );
	if ( $league ) {
		$data[] = $section( array( array( 100, array( $widget( 'gsf-clubs', array( 'title' => __( 'Les clubs', 'galsenfoot' ) ) ) ) ) ) );
	}
	return $data;
}

add_action(
	'admin_post_gf_setup',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'galsenfoot' ), 403 );
		}
		check_admin_referer( 'gf_setup' );
		$task    = sanitize_key( $_POST['task'] ?? '' );
		$message = '';
		if ( 'pages' === $task ) {
			$standings = gf_setup_page_once( __( 'Classement', 'galsenfoot' ), 'classement', "[gsf_standings]\n\n[gsf_scorers title=\"Meilleurs buteurs\" limit=\"10\"]\n\n[gsf_scorers title=\"Meilleurs passeurs\" stat=\"assists\" limit=\"10\"]" );
			$matches   = gf_setup_page_once( __( 'Calendrier et résultats', 'galsenfoot' ), 'calendrier', "[gsf_matches type=\"fixtures\" limit=\"14\" title=\"Prochains matchs\"]\n\n[gsf_matches type=\"results\" limit=\"28\" title=\"Résultats\"]" );
			set_theme_mod( 'gf_page_standings', $standings );
			set_theme_mod( 'gf_page_matches', $matches );
			$message = __( 'Pages « Classement » et « Calendrier et résultats » créées.', 'galsenfoot' );
		} elseif ( 'menu' === $task ) {
			$menu = wp_get_nav_menu_object( 'GalsenFoot' );
			if ( ! $menu ) {
				$menu_id = wp_create_nav_menu( 'GalsenFoot' );
				if ( ! is_wp_error( $menu_id ) ) {
					$add = static function ( $title, $url ) use ( $menu_id ) {
						if ( $url ) {
							wp_update_nav_menu_item(
								$menu_id,
								0,
								array(
									'menu-item-title'  => $title,
									'menu-item-url'    => $url,
									'menu-item-status' => 'publish',
									'menu-item-type'   => 'custom',
								)
							);
						}
					};
					$add( __( 'Accueil', 'galsenfoot' ), home_url( '/' ) );
					$add( __( 'Classement', 'galsenfoot' ), gf_page_url( 'standings' ) );
					$add( __( 'Matchs', 'galsenfoot' ), gf_page_url( 'matches' ) );
					if ( post_type_exists( 'gsf_club' ) ) {
						$add( __( 'Clubs', 'galsenfoot' ), get_post_type_archive_link( 'gsf_club' ) );
						$add( __( 'Joueurs', 'galsenfoot' ), get_post_type_archive_link( 'gsf_player' ) );
					}
					foreach ( get_categories( array( 'orderby' => 'count', 'order' => 'DESC', 'number' => 3, 'exclude' => array( (int) get_option( 'default_category' ) ) ) ) as $category ) {
						$add( $category->name, get_category_link( $category ) );
					}
					$locations            = get_theme_mod( 'nav_menu_locations', array() );
					$locations['primary'] = $menu_id;
					set_theme_mod( 'nav_menu_locations', $locations );
				}
			}
			$message = __( 'Menu principal créé et assigné. Modifiez-le dans Apparence → Menus.', 'galsenfoot' );
		} elseif ( 'home' === $task ) {
			if ( ! did_action( 'elementor/loaded' ) ) {
				$message = __( 'Activez d’abord Elementor.', 'galsenfoot' );
			} else {
				$page_id = wp_insert_post(
					array(
						'post_type'   => 'page',
						'post_status' => 'publish',
						'post_title'  => __( 'Accueil', 'galsenfoot' ),
					)
				);
				update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
				update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
				update_post_meta( $page_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
				update_post_meta( $page_id, '_elementor_data', wp_slash( wp_json_encode( gf_setup_elementor_home() ) ) );
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $page_id );
				$message = __( 'Page d’accueil Elementor créée et définie comme page d’accueil. Ouvrez-la avec « Modifier avec Elementor » pour la personnaliser.', 'galsenfoot' );
			}
		}
		set_transient( 'gf_setup_notice_' . get_current_user_id(), $message, 60 );
		wp_safe_redirect( admin_url( 'themes.php?page=gf-setup' ) );
		exit;
	}
);

/**
 * Quick start page.
 */
function gf_setup_page() {
	$notice = get_transient( 'gf_setup_notice_' . get_current_user_id() );
	delete_transient( 'gf_setup_notice_' . get_current_user_id() );
	$button = static function ( $task, $label, $primary = false ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'gf_setup' );
		echo '<input type="hidden" name="action" value="gf_setup"><input type="hidden" name="task" value="' . esc_attr( $task ) . '"><button class="button' . ( $primary ? ' button-primary' : '' ) . '">' . esc_html( $label ) . '</button></form>';
	};
	$league = function_exists( 'gsf_render_standings' );
	?>
	<div class="wrap gf-admin">
		<h1><?php esc_html_e( 'Démarrage GalsenFoot', 'galsenfoot' ); ?></h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<ol class="gf-steps">
			<li>
				<h2><?php esc_html_e( 'Extension GalsenFoot League', 'galsenfoot' ); ?> <?php echo $league ? '✅' : '⚠️'; ?></h2>
				<p><?php echo $league ? esc_html__( 'Active : classements, matchs, clubs et joueurs sont disponibles.', 'galsenfoot' ) : esc_html__( 'Installez et activez l’extension « GalsenFoot League » (fournie avec le thème) pour gérer le championnat.', 'galsenfoot' ); ?></p>
				<?php if ( $league ) : ?>
					<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=gsf' ) ); ?>"><?php esc_html_e( 'Réglages du championnat, API et démo', 'galsenfoot' ); ?></a></p>
				<?php endif; ?>
			</li>
			<li>
				<h2><?php esc_html_e( 'Pages Classement et Calendrier', 'galsenfoot' ); ?></h2>
				<p><?php esc_html_e( 'Crée les deux pages avec les bons blocs et les relie au thème.', 'galsenfoot' ); ?></p>
				<?php $button( 'pages', __( 'Créer les pages', 'galsenfoot' ) ); ?>
			</li>
			<li>
				<h2><?php esc_html_e( 'Menu principal', 'galsenfoot' ); ?></h2>
				<p><?php esc_html_e( 'Crée un menu de départ : Accueil, Classement, Matchs, Clubs, Joueurs et vos principales rubriques.', 'galsenfoot' ); ?></p>
				<?php $button( 'menu', __( 'Créer le menu', 'galsenfoot' ) ); ?>
			</li>
			<li>
				<h2><?php esc_html_e( 'Page d’accueil', 'galsenfoot' ); ?></h2>
				<p><?php esc_html_e( 'Sans rien faire, le thème affiche une page d’accueil complète (une, scores, classement, rubriques). Pour la composer vous-même dans Elementor, générez une page de départ modifiable avec les widgets GalsenFoot.', 'galsenfoot' ); ?></p>
				<?php $button( 'home', __( 'Créer la page d’accueil Elementor', 'galsenfoot' ), true ); ?>
			</li>
			<li>
				<h2><?php esc_html_e( 'Apparence et publicités', 'galsenfoot' ); ?></h2>
				<p>
					<a class="button" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[panel]=gf_panel' ) ); ?>"><?php esc_html_e( 'Couleurs, logo, réseaux sociaux', 'galsenfoot' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'themes.php?page=gf-ads' ) ); ?>"><?php esc_html_e( 'Publicités & Google', 'galsenfoot' ); ?></a>
				</p>
			</li>
		</ol>
	</div>
	<style>.gf-steps{max-width:820px;padding:0;margin:20px 0;list-style:none;counter-reset:s}.gf-steps li{counter-increment:s;position:relative;margin:0 0 12px;padding:16px 20px 16px 72px;background:#fff;border:1px solid #dcdcde;border-radius:12px}.gf-steps li:before{content:counter(s);position:absolute;left:20px;top:18px;width:36px;height:36px;border-radius:50%;background:#00853f;color:#fff;font-weight:700;display:grid;place-items:center}.gf-steps h2{margin:0 0 4px;font-size:16px}.gf-steps p{margin:4px 0 8px}.gf-ads-table td{vertical-align:top}</style>
	<?php
}

/**
 * Suggest the companion plugin and the setup page after activation.
 */
add_action(
	'admin_notices',
	static function () {
		$screen = get_current_screen();
		if ( ! current_user_can( 'manage_options' ) || ! $screen || ! in_array( $screen->id, array( 'themes', 'dashboard' ), true ) || get_user_meta( get_current_user_id(), 'gf_setup_dismissed', true ) ) {
			return;
		}
		echo '<div class="notice notice-info"><p><strong>GalsenFoot</strong> — ' . esc_html__( 'Bienvenue ! Terminez l’installation en quelques clics.', 'galsenfoot' ) . ' <a class="button button-primary" href="' . esc_url( admin_url( 'themes.php?page=gf-setup' ) ) . '">' . esc_html__( 'Démarrage', 'galsenfoot' ) . '</a> <a href="' . esc_url( wp_nonce_url( add_query_arg( 'gf_dismiss', 1 ), 'gf_dismiss' ) ) . '">' . esc_html__( 'Masquer', 'galsenfoot' ) . '</a></p></div>';
	}
);
add_action(
	'admin_init',
	static function () {
		if ( isset( $_GET['gf_dismiss'] ) && check_admin_referer( 'gf_dismiss' ) ) {
			update_user_meta( get_current_user_id(), 'gf_setup_dismissed', 1 );
			wp_safe_redirect( remove_query_arg( array( 'gf_dismiss', '_wpnonce' ) ) );
			exit;
		}
	}
);
