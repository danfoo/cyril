<?php
/**
 * Admin menu, settings and tools.
 *
 * @package GalsenFootLeague
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function () {
		add_menu_page( __( 'GalsenFoot', 'galsenfoot-league' ), __( 'GalsenFoot', 'galsenfoot-league' ), 'edit_posts', 'gsf', 'gsf_admin_page', 'dashicons-awards', 5 );
		add_submenu_page( 'gsf', __( 'Tableau de bord', 'galsenfoot-league' ), __( 'Tableau de bord', 'galsenfoot-league' ), 'edit_posts', 'gsf', 'gsf_admin_page', 0 );
		add_submenu_page( 'gsf', __( 'Compétitions', 'galsenfoot-league' ), __( 'Compétitions', 'galsenfoot-league' ), 'manage_categories', 'edit-tags.php?taxonomy=gsf_competition&post_type=gsf_match' );
		add_submenu_page( 'gsf', __( 'Saisons', 'galsenfoot-league' ), __( 'Saisons', 'galsenfoot-league' ), 'manage_categories', 'edit-tags.php?taxonomy=gsf_season&post_type=gsf_match' );
	},
	9
);

/**
 * Keep the GalsenFoot menu open on the taxonomy screens.
 */
add_filter(
	'parent_file',
	static function ( $parent_file ) {
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->taxonomy, array( 'gsf_competition', 'gsf_season' ), true ) ) {
			return 'gsf';
		}
		return $parent_file;
	}
);
add_filter(
	'submenu_file',
	static function ( $submenu_file ) {
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->taxonomy, array( 'gsf_competition', 'gsf_season' ), true ) ) {
			return 'edit-tags.php?taxonomy=' . $screen->taxonomy . '&post_type=gsf_match';
		}
		return $submenu_file;
	}
);

add_action(
	'admin_init',
	static function () {
		register_setting(
			'gsf_settings',
			'gsf_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'gsf_sanitize_settings',
			)
		);
	}
);

/**
 * Sanitize the settings array.
 *
 * @param mixed $input Raw input.
 * @return array
 */
function gsf_sanitize_settings( $input ) {
	$input = (array) $input;
	$old   = gsf_settings();
	$freq  = sanitize_key( $input['api_frequency'] ?? 'off' );
	$clean = array(
		'competition'   => absint( $input['competition'] ?? 0 ),
		'season'        => absint( $input['season'] ?? 0 ),
		'points_win'    => min( 10, absint( $input['points_win'] ?? 3 ) ),
		'points_draw'   => min( 10, absint( $input['points_draw'] ?? 1 ) ),
		'zone_top'      => min( 10, absint( $input['zone_top'] ?? 1 ) ),
		'zone_top2'     => min( 10, absint( $input['zone_top2'] ?? 1 ) ),
		'zone_bottom'   => min( 10, absint( $input['zone_bottom'] ?? 2 ) ),
		'api_key'       => preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $input['api_key'] ?? '' ) ),
		'api_league'    => absint( $input['api_league'] ?? 0 ) ?: '',
		'api_season'    => absint( $input['api_season'] ?? 0 ) ?: '',
		'api_frequency' => in_array( $freq, array( 'off', 'gsf_15min', 'hourly', 'gsf_6h', 'twicedaily', 'daily' ), true ) ? $freq : 'off',
		'api_images'    => empty( $input['api_images'] ) ? 0 : 1,
		'api_events'    => empty( $input['api_events'] ) ? 0 : 1,
	);
	// The key field is rendered masked: an untouched mask keeps the stored key.
	if ( isset( $input['api_key'] ) && false !== strpos( (string) $input['api_key'], '•' ) ) {
		$clean['api_key'] = $old['api_key'];
	}
	if ( $clean['api_league'] !== $old['api_league'] || $clean['api_season'] !== $old['api_season'] ) {
		delete_option( 'gsf_api_squad_offset' );
		delete_option( 'gsf_api_stats_page' );
	}
	gsf_flush_cache();
	return $clean;
}

/**
 * Tool actions (admin-post.php?action=gsf_tool).
 */
add_action(
	'admin_post_gsf_tool',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'galsenfoot-league' ), 403 );
		}
		check_admin_referer( 'gsf_tool' );
		$tool    = sanitize_key( $_POST['tool'] ?? '' );
		$notice  = '';
		$success = true;
		$report  = static function ( $result, $label ) use ( &$notice, &$success ) {
			if ( is_wp_error( $result ) ) {
				$success = false;
				$notice  = $result->get_error_message();
			} elseif ( is_array( $result ) ) {
				$notice = sprintf( '%s : %d / %d%s', $label, $result['done'], $result['total'], $result['finished'] ? ' ✔' : ' — ' . __( 'la suite s’exécute en arrière-plan.', 'galsenfoot-league' ) );
			} else {
				$notice = sprintf( '%s : %d', $label, (int) $result );
			}
		};

		switch ( $tool ) {
			case 'sync_teams':
				$result = gsf_api_sync_teams();
				gsf_api_log( 'teams', $result );
				$report( $result, __( 'Clubs synchronisés', 'galsenfoot-league' ) );
				break;
			case 'sync_fixtures':
				$result = gsf_api_sync_fixtures();
				gsf_api_log( 'fixtures', $result );
				$report( $result, __( 'Matchs synchronisés', 'galsenfoot-league' ) );
				break;
			case 'sync_events':
				$result = gsf_api_sync_events( 8 );
				gsf_api_log( 'events', $result );
				$report( $result, __( 'Matchs mis à jour (buts et cartons)', 'galsenfoot-league' ) );
				break;
			case 'sync_squads':
			case 'sync_stats':
				$step   = 'sync_squads' === $tool ? 'squads' : 'stats';
				$result = 'squads' === $step ? gsf_api_sync_squads() : gsf_api_sync_player_stats();
				gsf_api_log( $step, $result );
				if ( is_array( $result ) && ! $result['finished'] && ! wp_next_scheduled( 'gsf_api_continue', array( $step ) ) ) {
					wp_schedule_single_event( time() + 70, 'gsf_api_continue', array( $step ) );
				}
				$report( $result, 'squads' === $step ? __( 'Effectifs importés (clubs)', 'galsenfoot-league' ) : __( 'Statistiques importées (pages)', 'galsenfoot-league' ) );
				break;
			case 'search_leagues':
				$country = sanitize_text_field( wp_unslash( $_POST['country'] ?? 'Senegal' ) );
				$result  = gsf_api_search_leagues( $country );
				if ( is_wp_error( $result ) ) {
					$report( $result, '' );
				} else {
					set_transient( 'gsf_league_search_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS );
					$notice = sprintf( /* translators: %d count */ __( '%d compétition(s) trouvée(s) — voir la liste ci-dessous.', 'galsenfoot-league' ), count( $result ) );
				}
				break;
			case 'demo_install':
				$notice = gsf_demo_install();
				break;
			case 'demo_remove':
				$notice = gsf_demo_remove();
				break;
			case 'flush':
				gsf_flush_cache();
				$notice = __( 'Classements et statistiques recalculés.', 'galsenfoot-league' );
				break;
			default:
				$success = false;
				$notice  = __( 'Outil inconnu.', 'galsenfoot-league' );
		}
		set_transient(
			'gsf_notice_' . get_current_user_id(),
			array(
				'ok'      => $success,
				'message' => $notice,
			),
			60
		);
		wp_safe_redirect( admin_url( 'admin.php?page=gsf' ) );
		exit;
	}
);

/**
 * Tool button form.
 *
 * @param string $tool    Tool slug.
 * @param string $label   Button label.
 * @param string $class   Button class.
 * @param string $confirm Optional confirmation message.
 */
function gsf_tool_button( $tool, $label, $class = 'button', $confirm = '' ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gsf-tool"' . ( $confirm ? ' onsubmit="return confirm(\'' . esc_js( $confirm ) . '\')"' : '' ) . '>';
	wp_nonce_field( 'gsf_tool' );
	echo '<input type="hidden" name="action" value="gsf_tool"><input type="hidden" name="tool" value="' . esc_attr( $tool ) . '">';
	echo '<button class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
}

/**
 * Dashboard / settings page.
 */
function gsf_admin_page() {
	$s      = gsf_settings();
	$notice = get_transient( 'gsf_notice_' . get_current_user_id() );
	delete_transient( 'gsf_notice_' . get_current_user_id() );
	$counts = array(
		'gsf_club'   => wp_count_posts( 'gsf_club' )->publish ?? 0,
		'gsf_player' => wp_count_posts( 'gsf_player' )->publish ?? 0,
		'gsf_match'  => wp_count_posts( 'gsf_match' )->publish ?? 0,
	);
	$can    = current_user_can( 'manage_options' );
	?>
	<div class="wrap gsf-admin">
		<h1><?php esc_html_e( 'GalsenFoot League', 'galsenfoot-league' ); ?></h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-<?php echo $notice['ok'] ? 'success' : 'error'; ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
		<?php endif; ?>
		<?php settings_errors( 'gsf_settings' ); ?>

		<div class="gsf-admin-cards">
			<a class="gsf-admin-card" href="<?php echo esc_url( admin_url( 'edit.php?post_type=gsf_club' ) ); ?>"><b><?php echo (int) $counts['gsf_club']; ?></b><?php esc_html_e( 'Clubs', 'galsenfoot-league' ); ?></a>
			<a class="gsf-admin-card" href="<?php echo esc_url( admin_url( 'edit.php?post_type=gsf_player' ) ); ?>"><b><?php echo (int) $counts['gsf_player']; ?></b><?php esc_html_e( 'Joueurs', 'galsenfoot-league' ); ?></a>
			<a class="gsf-admin-card" href="<?php echo esc_url( admin_url( 'edit.php?post_type=gsf_match' ) ); ?>"><b><?php echo (int) $counts['gsf_match']; ?></b><?php esc_html_e( 'Matchs', 'galsenfoot-league' ); ?></a>
			<a class="gsf-admin-card is-cta" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=gsf_match' ) ); ?>"><b>+</b><?php esc_html_e( 'Saisir un match', 'galsenfoot-league' ); ?></a>
		</div>

		<?php if ( ! $can ) : ?>
			<p><?php esc_html_e( 'Les réglages sont réservés aux administrateurs.', 'galsenfoot-league' ); ?></p></div>
			<?php
			return;
		endif;
		?>

		<form method="post" action="options.php" class="gsf-admin-panel">
			<?php settings_fields( 'gsf_settings' ); ?>
			<h2><?php esc_html_e( 'Championnat affiché par défaut', 'galsenfoot-league' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="gsf_competition"><?php esc_html_e( 'Compétition', 'galsenfoot-league' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_categories(
							array(
								'taxonomy'         => 'gsf_competition',
								'name'             => 'gsf_settings[competition]',
								'id'               => 'gsf_competition',
								'selected'         => (int) $s['competition'],
								'hide_empty'       => false,
								'show_option_none' => __( '— La plus récente —', 'galsenfoot-league' ),
								'option_none_value' => 0,
							)
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="gsf_season"><?php esc_html_e( 'Saison', 'galsenfoot-league' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_categories(
							array(
								'taxonomy'         => 'gsf_season',
								'name'             => 'gsf_settings[season]',
								'id'               => 'gsf_season',
								'selected'         => (int) $s['season'],
								'hide_empty'       => false,
								'show_option_none' => __( '— La plus récente —', 'galsenfoot-league' ),
								'option_none_value' => 0,
							)
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Barème', 'galsenfoot-league' ); ?></th>
					<td>
						<label><?php esc_html_e( 'Victoire', 'galsenfoot-league' ); ?> <input type="number" class="small-text" min="0" max="10" name="gsf_settings[points_win]" value="<?php echo (int) $s['points_win']; ?>"></label>
						&nbsp; <label><?php esc_html_e( 'Nul', 'galsenfoot-league' ); ?> <input type="number" class="small-text" min="0" max="10" name="gsf_settings[points_draw]" value="<?php echo (int) $s['points_draw']; ?>"></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Zones du classement', 'galsenfoot-league' ); ?></th>
					<td>
						<label><?php esc_html_e( 'Places Ligue des champions', 'galsenfoot-league' ); ?> <input type="number" class="small-text" min="0" max="10" name="gsf_settings[zone_top]" value="<?php echo (int) $s['zone_top']; ?>"></label>
						&nbsp; <label><?php esc_html_e( 'Places Coupe CAF', 'galsenfoot-league' ); ?> <input type="number" class="small-text" min="0" max="10" name="gsf_settings[zone_top2]" value="<?php echo (int) $s['zone_top2']; ?>"></label>
						&nbsp; <label><?php esc_html_e( 'Relégués', 'galsenfoot-league' ); ?> <input type="number" class="small-text" min="0" max="10" name="gsf_settings[zone_bottom]" value="<?php echo (int) $s['zone_bottom']; ?>"></label>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Synchronisation automatique (API-Football)', 'galsenfoot-league' ); ?></h2>
			<p class="description">
				<?php
				echo wp_kses_post(
					__( 'Optionnel. Créez une clé sur <a href="https://www.api-football.com/" target="_blank" rel="noopener">api-football.com</a> (offre gratuite : 100 requêtes/jour, saisons limitées), puis indiquez l’identifiant de la compétition et l’année de début de saison. Sans clé, tout se gère à la main depuis les menus Clubs, Joueurs et Matchs.', 'galsenfoot-league' )
				);
				?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="gsf_api_key"><?php esc_html_e( 'Clé API', 'galsenfoot-league' ); ?></label></th>
					<td><input type="text" id="gsf_api_key" name="gsf_settings[api_key]" class="regular-text" autocomplete="off" value="<?php echo esc_attr( $s['api_key'] ? str_repeat( '•', 20 ) . substr( $s['api_key'], -4 ) : '' ); ?>">
						<?php
						$remaining = get_option( 'gsf_api_remaining', null );
						if ( null !== $remaining && $s['api_key'] ) {
							echo '<p class="description">' . esc_html( sprintf( /* translators: %d requests */ __( 'Requêtes restantes aujourd’hui : %d', 'galsenfoot-league' ), (int) $remaining ) ) . '</p>';
						}
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="gsf_api_league"><?php esc_html_e( 'ID de la compétition', 'galsenfoot-league' ); ?></label></th>
					<td><input type="number" id="gsf_api_league" name="gsf_settings[api_league]" class="small-text" value="<?php echo esc_attr( $s['api_league'] ); ?>">
						&nbsp; <label><?php esc_html_e( 'Saison (année de début)', 'galsenfoot-league' ); ?> <input type="number" name="gsf_settings[api_season]" class="small-text" style="width:6em" placeholder="<?php echo esc_attr( gmdate( 'Y' ) ); ?>" value="<?php echo esc_attr( $s['api_season'] ); ?>"></label>
						<p class="description"><?php esc_html_e( 'Utilisez « Rechercher les compétitions » ci-dessous pour trouver l’identifiant.', 'galsenfoot-league' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="gsf_api_frequency"><?php esc_html_e( 'Mise à jour des matchs', 'galsenfoot-league' ); ?></label></th>
					<td><select id="gsf_api_frequency" name="gsf_settings[api_frequency]">
						<?php
						$frequencies = array(
							'off'        => __( 'Manuelle uniquement', 'galsenfoot-league' ),
							'gsf_15min'  => __( 'Toutes les 15 minutes (offre payante conseillée)', 'galsenfoot-league' ),
							'hourly'     => __( 'Toutes les heures', 'galsenfoot-league' ),
							'gsf_6h'     => __( 'Toutes les 6 heures', 'galsenfoot-league' ),
							'twicedaily' => __( 'Deux fois par jour', 'galsenfoot-league' ),
							'daily'      => __( 'Une fois par jour', 'galsenfoot-league' ),
						);
						foreach ( $frequencies as $key => $label ) {
							printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $s['api_frequency'], $key, false ), esc_html( $label ) );
						}
						?>
					</select></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Options', 'galsenfoot-league' ); ?></th>
					<td>
						<label><input type="checkbox" name="gsf_settings[api_events]" value="1"<?php checked( $s['api_events'] ); ?>> <?php esc_html_e( 'Importer les buteurs et cartons des derniers matchs (1 requête par match)', 'galsenfoot-league' ); ?></label><br>
						<label><input type="checkbox" name="gsf_settings[api_images]" value="1"<?php checked( $s['api_images'] ); ?>> <?php esc_html_e( 'Copier les logos des clubs dans la médiathèque', 'galsenfoot-league' ); ?></label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<div class="gsf-admin-panel">
			<h2><?php esc_html_e( 'Outils de synchronisation', 'galsenfoot-league' ); ?></h2>
			<?php if ( gsf_api_ready() ) : ?>
				<p><?php esc_html_e( 'Ordre conseillé pour un premier import : 1. Clubs → 2. Matchs → 3. Effectifs → 4. Statistiques des joueurs.', 'galsenfoot-league' ); ?></p>
				<div class="gsf-tools">
					<?php
					gsf_tool_button( 'sync_teams', __( '1. Clubs', 'galsenfoot-league' ), 'button button-primary' );
					gsf_tool_button( 'sync_fixtures', __( '2. Matchs et scores', 'galsenfoot-league' ), 'button button-primary' );
					gsf_tool_button( 'sync_squads', __( '3. Effectifs', 'galsenfoot-league' ), 'button button-primary' );
					gsf_tool_button( 'sync_stats', __( '4. Statistiques des joueurs', 'galsenfoot-league' ), 'button button-primary' );
					gsf_tool_button( 'sync_events', __( 'Buts et cartons des derniers matchs', 'galsenfoot-league' ) );
					?>
				</div>
			<?php else : ?>
				<p><em><?php esc_html_e( 'Renseignez la clé API, l’ID de compétition et la saison pour activer la synchronisation.', 'galsenfoot-league' ); ?></em></p>
			<?php endif; ?>

			<?php if ( $s['api_key'] ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gsf-tool-inline">
					<?php wp_nonce_field( 'gsf_tool' ); ?>
					<input type="hidden" name="action" value="gsf_tool"><input type="hidden" name="tool" value="search_leagues">
					<label><?php esc_html_e( 'Pays (en anglais)', 'galsenfoot-league' ); ?> <input type="text" name="country" value="Senegal"></label>
					<button class="button"><?php esc_html_e( 'Rechercher les compétitions', 'galsenfoot-league' ); ?></button>
				</form>
				<?php
				$leagues = get_transient( 'gsf_league_search_' . get_current_user_id() );
				if ( is_array( $leagues ) && $leagues ) {
					echo '<table class="widefat striped" style="max-width:640px"><thead><tr><th>ID</th><th>' . esc_html__( 'Compétition', 'galsenfoot-league' ) . '</th><th>' . esc_html__( 'Type', 'galsenfoot-league' ) . '</th><th>' . esc_html__( 'Saisons', 'galsenfoot-league' ) . '</th></tr></thead><tbody>';
					foreach ( $leagues as $league ) {
						printf( '<tr><td><code>%d</code></td><td>%s</td><td>%s</td><td>%s</td></tr>', (int) $league['id'], esc_html( $league['name'] ), esc_html( $league['type'] ), esc_html( $league['seasons'] ) );
					}
					echo '</tbody></table>';
				}
			endif;

			$log = (array) get_option( 'gsf_api_log', array() );
			if ( $log ) {
				echo '<h3>' . esc_html__( 'Journal', 'galsenfoot-league' ) . '</h3><ul class="gsf-log">';
				foreach ( $log as $entry ) {
					printf( '<li class="%s"><time>%s</time> <b>%s</b> — %s</li>', $entry['ok'] ? 'is-ok' : 'is-error', esc_html( wp_date( 'd/m H:i', (int) $entry['time'] ) ), esc_html( $entry['step'] ), esc_html( $entry['message'] ) );
				}
				echo '</ul>';
			}
			?>
		</div>

		<div class="gsf-admin-panel">
			<h2><?php esc_html_e( 'Données de démonstration', 'galsenfoot-league' ); ?></h2>
			<p><?php esc_html_e( 'Installe un championnat fictif complet (clubs, joueurs, calendrier, résultats, buteurs) pour découvrir le thème et les widgets. Les joueurs et les scores sont inventés. Tout se supprime en un clic.', 'galsenfoot-league' ); ?></p>
			<div class="gsf-tools">
				<?php
				gsf_tool_button( 'demo_install', __( 'Installer la démo', 'galsenfoot-league' ) );
				gsf_tool_button( 'demo_remove', __( 'Supprimer la démo', 'galsenfoot-league' ), 'button button-link-delete', __( 'Supprimer toutes les données de démonstration ?', 'galsenfoot-league' ) );
				gsf_tool_button( 'flush', __( 'Recalculer les classements', 'galsenfoot-league' ) );
				?>
			</div>
		</div>

		<div class="gsf-admin-panel">
			<h2><?php esc_html_e( 'Shortcodes', 'galsenfoot-league' ); ?></h2>
			<p><?php esc_html_e( 'Tous ces blocs existent aussi en widgets Elementor (catégorie « GalsenFoot »).', 'galsenfoot-league' ); ?></p>
			<table class="widefat striped" style="max-width:900px"><tbody>
				<tr><td><code>[gsf_match_center]</code></td><td><?php esc_html_e( 'Centre des matchs à onglets : L’affiche, Derniers résultats, Prochains matchs. Options : default="results", results="6", fixtures="6", club, link', 'galsenfoot-league' ); ?></td></tr>
				<tr><td><code>[gsf_standings]</code></td><td><?php esc_html_e( 'Classement. Options : limit="5" compact="1" form="0"', 'galsenfoot-league' ); ?></td></tr>
				<tr><td><code>[gsf_matches type="fixtures"]</code></td><td><?php esc_html_e( 'Calendrier. type = fixtures, results, live ou all. Options : limit, club, matchday', 'galsenfoot-league' ); ?></td></tr>
				<tr><td><code>[gsf_ticker]</code></td><td><?php esc_html_e( 'Bandeau défilant des scores et prochains matchs', 'galsenfoot-league' ); ?></td></tr>
				<tr><td><code>[gsf_next_match]</code></td><td><?php esc_html_e( 'Prochain match avec compte à rebours. Options : club, match', 'galsenfoot-league' ); ?></td></tr>
				<tr><td><code>[gsf_scorers stat="goals"]</code></td><td><?php esc_html_e( 'Classement des joueurs. stat = goals, assists, yellow, red, apps', 'galsenfoot-league' ); ?></td></tr>
				<tr><td><code>[gsf_clubs]</code></td><td><?php esc_html_e( 'Grille des clubs. Options : layout="strip", scope="all"', 'galsenfoot-league' ); ?></td></tr>
				<tr><td><code>[gsf_squad club="12"]</code></td><td><?php esc_html_e( 'Effectif d’un club', 'galsenfoot-league' ); ?></td></tr>
			</tbody></table>
			<p class="description"><?php esc_html_e( 'Attributs communs : competition et season (ID ou identifiant), title.', 'galsenfoot-league' ); ?></p>
		</div>
	</div>
	<?php
}
