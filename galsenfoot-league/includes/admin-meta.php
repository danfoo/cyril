<?php
/**
 * Edit screens: meta boxes for clubs, players and matches, list columns.
 *
 * @package GalsenFootLeague
 */

defined( 'ABSPATH' ) || exit;

/**
 * Field definitions for clubs and players: key => [label, type, options].
 *
 * @param string $post_type Post type.
 * @return array
 */
function gsf_admin_fields( $post_type ) {
	if ( 'gsf_club' === $post_type ) {
		return array(
			'_gsf_short'     => array( __( 'Abréviation (3 lettres)', 'galsenfoot-league' ), 'text' ),
			'_gsf_city'      => array( __( 'Ville', 'galsenfoot-league' ), 'text' ),
			'_gsf_founded'   => array( __( 'Année de fondation', 'galsenfoot-league' ), 'number' ),
			'_gsf_stadium'   => array( __( 'Stade', 'galsenfoot-league' ), 'text' ),
			'_gsf_capacity'  => array( __( 'Capacité du stade', 'galsenfoot-league' ), 'number' ),
			'_gsf_coach'     => array( __( 'Entraîneur', 'galsenfoot-league' ), 'text' ),
			'_gsf_president' => array( __( 'Président', 'galsenfoot-league' ), 'text' ),
			'_gsf_website'   => array( __( 'Site web', 'galsenfoot-league' ), 'url' ),
			'_gsf_color'     => array( __( 'Couleur principale', 'galsenfoot-league' ), 'color' ),
			'_gsf_color2'    => array( __( 'Couleur secondaire', 'galsenfoot-league' ), 'color' ),
		);
	}
	return array(
		'_gsf_club'        => array( __( 'Club', 'galsenfoot-league' ), 'club' ),
		'_gsf_position'    => array( __( 'Poste', 'galsenfoot-league' ), 'select', gsf_positions() ),
		'_gsf_number'      => array( __( 'Numéro', 'galsenfoot-league' ), 'number' ),
		'_gsf_birth'       => array( __( 'Date de naissance', 'galsenfoot-league' ), 'date' ),
		'_gsf_nationality' => array( __( 'Nationalité', 'galsenfoot-league' ), 'text' ),
		'_gsf_height'      => array( __( 'Taille (cm)', 'galsenfoot-league' ), 'number' ),
		'_gsf_foot'        => array(
			__( 'Pied fort', 'galsenfoot-league' ),
			'select',
			array(
				'R' => __( 'Droit', 'galsenfoot-league' ),
				'L' => __( 'Gauche', 'galsenfoot-league' ),
				'B' => __( 'Les deux', 'galsenfoot-league' ),
			),
		),
	);
}

/**
 * <select> of clubs.
 *
 * @param string $name     Field name.
 * @param int    $selected Selected club.
 * @param string $attrs    Extra attributes.
 */
function gsf_admin_club_select( $name, $selected, $attrs = '' ) {
	static $clubs = null;
	if ( null === $clubs ) {
		$clubs = get_posts(
			array(
				'post_type'      => 'gsf_club',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}
	echo '<select name="' . esc_attr( $name ) . '" ' . $attrs . '><option value="">— ' . esc_html__( 'Choisir', 'galsenfoot-league' ) . ' —</option>'; // phpcs:ignore WordPress.Security.EscapeOutput
	foreach ( $clubs as $club ) {
		printf( '<option value="%d"%s>%s</option>', (int) $club->ID, selected( (int) $selected, $club->ID, false ), esc_html( $club->post_title ) );
	}
	echo '</select>';
}

add_action(
	'add_meta_boxes',
	static function () {
		add_meta_box( 'gsf_club_box', __( 'Informations du club', 'galsenfoot-league' ), 'gsf_admin_fields_box', 'gsf_club', 'normal', 'high' );
		add_meta_box( 'gsf_player_box', __( 'Fiche du joueur', 'galsenfoot-league' ), 'gsf_admin_fields_box', 'gsf_player', 'normal', 'high' );
		add_meta_box( 'gsf_player_stats', __( 'Performances (saison en cours)', 'galsenfoot-league' ), 'gsf_admin_player_stats_box', 'gsf_player', 'side' );
		add_meta_box( 'gsf_match_box', __( 'Feuille de match', 'galsenfoot-league' ), 'gsf_admin_match_box', 'gsf_match', 'normal', 'high' );
	}
);

/**
 * Generic field grid for clubs and players.
 *
 * @param WP_Post $post Post.
 */
function gsf_admin_fields_box( $post ) {
	wp_nonce_field( 'gsf_save_meta', 'gsf_meta_nonce' );
	echo '<div class="gsf-admin-grid">';
	foreach ( gsf_admin_fields( $post->post_type ) as $key => $field ) {
		$value = get_post_meta( $post->ID, $key, true );
		echo '<p><label for="' . esc_attr( $key ) . '">' . esc_html( $field[0] ) . '</label>';
		switch ( $field[1] ) {
			case 'club':
				gsf_admin_club_select( $key, (int) $value, 'id="' . esc_attr( $key ) . '"' );
				break;
			case 'select':
				echo '<select id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '"><option value="">—</option>';
				foreach ( $field[2] as $option => $label ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $option ), selected( $value, $option, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;
			case 'color':
				printf( '<input type="color" id="%1$s" name="%1$s" value="%2$s">', esc_attr( $key ), esc_attr( $value ?: ( '_gsf_color' === $key ? '#00853f' : '#ffffff' ) ) );
				break;
			default:
				printf( '<input type="%3$s" id="%1$s" name="%1$s" value="%2$s" class="widefat">', esc_attr( $key ), esc_attr( $value ), esc_attr( $field[1] ) );
		}
		echo '</p>';
	}
	echo '</div>';
	if ( 'gsf_club' === $post->post_type ) {
		echo '<p class="description">' . esc_html__( 'Le logo se définit avec « Logo du club » (image mise en avant). Sans logo, un écusson aux couleurs du club est généré automatiquement.', 'galsenfoot-league' ) . '</p>';
	}
}

/**
 * Read-only statistics of the player.
 *
 * @param WP_Post $post Post.
 */
function gsf_admin_player_stats_box( $post ) {
	$season = gsf_resolve_term( '', 'gsf_season' );
	$s      = gsf_get_player_stats( $post->ID, 0, $season );
	$labels = array(
		'apps'    => __( 'Matchs', 'galsenfoot-league' ),
		'goals'   => __( 'Buts', 'galsenfoot-league' ),
		'assists' => __( 'Passes décisives', 'galsenfoot-league' ),
		'yellow'  => __( 'Cartons jaunes', 'galsenfoot-league' ),
		'red'     => __( 'Cartons rouges', 'galsenfoot-league' ),
		'minutes' => __( 'Minutes', 'galsenfoot-league' ),
	);
	echo '<table class="widefat striped"><tbody>';
	foreach ( $labels as $key => $label ) {
		echo '<tr><td>' . esc_html( $label ) . '</td><td><strong>' . (int) $s[ $key ] . '</strong></td></tr>';
	}
	echo '</tbody></table><p class="description">';
	echo 'api' === $s['source']
		? esc_html__( 'Source : statistiques synchronisées depuis l’API.', 'galsenfoot-league' )
		: esc_html__( 'Calculé automatiquement à partir des feuilles de match (buts, cartons, compositions).', 'galsenfoot-league' );
	echo '</p>';
}

/**
 * Match sheet.
 *
 * @param WP_Post $post Post.
 */
function gsf_admin_match_box( $post ) {
	wp_nonce_field( 'gsf_save_meta', 'gsf_meta_nonce' );
	$m     = gsf_match( $post->ID );
	$local = $m['ts'] ? wp_date( 'Y-m-d\TH:i', $m['ts'] ) : '';
	?>
	<div class="gsf-admin-match">
		<div class="gsf-admin-score">
			<div>
				<label><?php esc_html_e( 'Domicile', 'galsenfoot-league' ); ?></label>
				<?php gsf_admin_club_select( '_gsf_home', $m['home'], 'required data-gsf-side="home"' ); ?>
			</div>
			<input type="number" min="0" max="99" name="_gsf_home_score" value="<?php echo esc_attr( null === $m['home_score'] ? '' : $m['home_score'] ); ?>" aria-label="<?php esc_attr_e( 'Score domicile', 'galsenfoot-league' ); ?>">
			<span>–</span>
			<input type="number" min="0" max="99" name="_gsf_away_score" value="<?php echo esc_attr( null === $m['away_score'] ? '' : $m['away_score'] ); ?>" aria-label="<?php esc_attr_e( 'Score extérieur', 'galsenfoot-league' ); ?>">
			<div>
				<label><?php esc_html_e( 'Extérieur', 'galsenfoot-league' ); ?></label>
				<?php gsf_admin_club_select( '_gsf_away', $m['away'], 'required data-gsf-side="away"' ); ?>
			</div>
		</div>
		<div class="gsf-admin-grid">
			<p><label for="gsf_date"><?php esc_html_e( 'Date et heure du coup d’envoi', 'galsenfoot-league' ); ?></label>
				<input type="datetime-local" id="gsf_date" name="_gsf_date" value="<?php echo esc_attr( $local ); ?>" class="widefat"></p>
			<p><label for="gsf_status"><?php esc_html_e( 'Statut', 'galsenfoot-league' ); ?></label>
				<select id="gsf_status" name="_gsf_status" class="widefat">
					<?php foreach ( gsf_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $m['status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select></p>
			<p><label for="gsf_matchday"><?php esc_html_e( 'Journée', 'galsenfoot-league' ); ?></label>
				<input type="number" min="0" id="gsf_matchday" name="_gsf_matchday" value="<?php echo esc_attr( $m['matchday'] ?: '' ); ?>" class="widefat"></p>
			<p><label for="gsf_minute"><?php esc_html_e( 'Minute (si en direct)', 'galsenfoot-league' ); ?></label>
				<input type="number" min="0" max="130" id="gsf_minute" name="_gsf_minute" value="<?php echo esc_attr( $m['minute'] ?: '' ); ?>" class="widefat"></p>
			<p class="gsf-span-2"><label for="gsf_venue"><?php esc_html_e( 'Stade', 'galsenfoot-league' ); ?></label>
				<input type="text" id="gsf_venue" name="_gsf_venue" value="<?php echo esc_attr( $m['venue'] ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Laisser vide pour utiliser le stade du club à domicile', 'galsenfoot-league' ); ?>"></p>
		</div>

		<h4><?php esc_html_e( 'Buts et cartons', 'galsenfoot-league' ); ?></h4>
		<p class="description"><?php esc_html_e( 'Ces événements alimentent automatiquement le classement des buteurs, des passeurs et les statistiques des joueurs. Enregistrez le match après avoir choisi les clubs pour charger la liste de leurs joueurs.', 'galsenfoot-league' ); ?></p>
		<?php
		$players = array(
			'home' => $m['home'] ? gsf_club_players( $m['home'] ) : array(),
			'away' => $m['away'] ? gsf_club_players( $m['away'] ) : array(),
		);
		$options = static function ( $selected, $side = '' ) use ( $players, $m ) {
			$html = '<option value="">—</option>';
			foreach ( $players as $key => $list ) {
				if ( ! $list ) {
					continue;
				}
				$html .= '<optgroup label="' . esc_attr( gsf_club( $m[ $key ] )['name'] ) . '" data-side="' . esc_attr( $key ) . '">';
				foreach ( $list as $player ) {
					$number = get_post_meta( $player->ID, '_gsf_number', true );
					$html  .= sprintf( '<option value="%d"%s>%s%s</option>', (int) $player->ID, selected( (int) $selected, $player->ID, false ), '' !== $number ? (int) $number . '. ' : '', esc_html( $player->post_title ) );
				}
				$html .= '</optgroup>';
			}
			return $html;
		};
		$row     = static function ( $index, $event ) use ( $options ) {
			$event = wp_parse_args(
				$event,
				array(
					'minute' => '',
					'type'   => 'goal',
					'side'   => 'home',
					'player' => 0,
					'assist' => 0,
				)
			);
			$name  = '_gsf_events[' . $index . ']';
			$html  = '<tr><td><input type="number" min="0" max="130" name="' . esc_attr( $name ) . '[minute]" value="' . esc_attr( $event['minute'] ) . '" class="small-text" placeholder="min"></td>';
			$html .= '<td><select name="' . esc_attr( $name ) . '[type]">';
			foreach ( gsf_event_types() as $key => $label ) {
				$html .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $event['type'], $key, false ), esc_html( $label ) );
			}
			$html .= '</select></td><td><select name="' . esc_attr( $name ) . '[side]"><option value="home"' . selected( $event['side'], 'home', false ) . '>' . esc_html__( 'Domicile', 'galsenfoot-league' ) . '</option><option value="away"' . selected( $event['side'], 'away', false ) . '>' . esc_html__( 'Extérieur', 'galsenfoot-league' ) . '</option></select></td>';
			$html .= '<td><select name="' . esc_attr( $name ) . '[player]">' . $options( $event['player'] ) . '</select>';
			if ( empty( $event['player'] ) && ! empty( $event['player_name'] ) ) {
				$html .= '<br><small>' . esc_html( $event['player_name'] ) . '</small><input type="hidden" name="' . esc_attr( $name ) . '[player_name]" value="' . esc_attr( $event['player_name'] ) . '">';
			}
			$html .= '</td><td><select name="' . esc_attr( $name ) . '[assist]">' . $options( $event['assist'] ) . '</select></td>';
			$html .= '<td><button type="button" class="button-link-delete gsf-remove-row" aria-label="' . esc_attr__( 'Supprimer', 'galsenfoot-league' ) . '">✕</button></td></tr>';
			return $html;
		};
		?>
		<table class="widefat gsf-events">
			<thead><tr>
				<th><?php esc_html_e( 'Minute', 'galsenfoot-league' ); ?></th>
				<th><?php esc_html_e( 'Événement', 'galsenfoot-league' ); ?></th>
				<th><?php esc_html_e( 'Équipe', 'galsenfoot-league' ); ?></th>
				<th><?php esc_html_e( 'Joueur', 'galsenfoot-league' ); ?></th>
				<th><?php esc_html_e( 'Passeur', 'galsenfoot-league' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
				<?php
				foreach ( array_values( $m['events'] ) as $i => $event ) {
					echo $row( $i, $event ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				?>
			</tbody>
		</table>
		<script type="text/html" id="gsf-event-template"><?php echo $row( '__i__', array() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></script>
		<p><button type="button" class="button gsf-add-row">+ <?php esc_html_e( 'Ajouter un événement', 'galsenfoot-league' ); ?></button></p>

		<h4><?php esc_html_e( 'Compositions (joueurs ayant participé)', 'galsenfoot-league' ); ?></h4>
		<div class="gsf-admin-lineups">
			<?php
			foreach ( $players as $side => $list ) :
				$lineup = array_map( 'intval', (array) get_post_meta( $post->ID, '_gsf_lineup_' . $side, true ) );
				?>
				<fieldset>
					<legend><?php echo esc_html( gsf_club( $m[ $side ] )['name'] ); ?></legend>
					<?php if ( ! $list ) : ?>
						<em><?php esc_html_e( 'Aucun joueur rattaché à ce club.', 'galsenfoot-league' ); ?></em>
					<?php endif; ?>
					<?php foreach ( $list as $player ) : ?>
						<label><input type="checkbox" name="_gsf_lineup_<?php echo esc_attr( $side ); ?>[]" value="<?php echo (int) $player->ID; ?>"<?php checked( in_array( $player->ID, $lineup, true ) ); ?>> <?php echo esc_html( $player->post_title ); ?></label>
					<?php endforeach; ?>
				</fieldset>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/**
 * Save meta boxes.
 */
add_action(
	'save_post',
	static function ( $post_id, $post ) {
		if ( ! in_array( $post->post_type, array( 'gsf_club', 'gsf_player', 'gsf_match' ), true ) ) {
			return;
		}
		if ( ! isset( $_POST['gsf_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['gsf_meta_nonce'] ), 'gsf_save_meta' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( 'gsf_match' !== $post->post_type ) {
			foreach ( gsf_admin_fields( $post->post_type ) as $key => $field ) {
				$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				switch ( $field[1] ) {
					case 'number':
					case 'club':
						$value = '' === $raw ? '' : absint( $raw );
						break;
					case 'url':
						$value = esc_url_raw( $raw );
						break;
					case 'color':
						$value = sanitize_hex_color( $raw );
						break;
					case 'date':
						$value = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ? $raw : '';
						break;
					case 'select':
						$value = isset( $field[2][ $raw ] ) ? $raw : '';
						break;
					default:
						$value = sanitize_text_field( $raw );
				}
				if ( '_gsf_short' === $key ) {
					$value = strtoupper( function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 4 ) : substr( $value, 0, 4 ) );
				}
				update_post_meta( $post_id, $key, $value );
			}
			return;
		}

		// Match.
		$home = absint( $_POST['_gsf_home'] ?? 0 );
		$away = absint( $_POST['_gsf_away'] ?? 0 );
		update_post_meta( $post_id, '_gsf_home', $home );
		update_post_meta( $post_id, '_gsf_away', $away );

		$date = sanitize_text_field( wp_unslash( $_POST['_gsf_date'] ?? '' ) );
		$ts   = 0;
		if ( $date ) {
			$dt = date_create_immutable( $date, wp_timezone() );
			$ts = $dt ? $dt->getTimestamp() : 0;
		}
		update_post_meta( $post_id, '_gsf_ts', $ts );

		$status = sanitize_key( $_POST['_gsf_status'] ?? 'scheduled' );
		update_post_meta( $post_id, '_gsf_status', isset( gsf_statuses()[ $status ] ) ? $status : 'scheduled' );
		update_post_meta( $post_id, '_gsf_matchday', absint( $_POST['_gsf_matchday'] ?? 0 ) );
		update_post_meta( $post_id, '_gsf_minute', absint( $_POST['_gsf_minute'] ?? 0 ) );

		$venue = sanitize_text_field( wp_unslash( $_POST['_gsf_venue'] ?? '' ) );
		if ( '' === $venue && $home ) {
			$venue = (string) get_post_meta( $home, '_gsf_stadium', true );
		}
		update_post_meta( $post_id, '_gsf_venue', $venue );

		foreach ( array( '_gsf_home_score', '_gsf_away_score' ) as $key ) {
			$raw = isset( $_POST[ $key ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) ) : '';
			update_post_meta( $post_id, $key, '' === $raw ? '' : absint( $raw ) );
		}

		$events = array();
		$types  = gsf_event_types();
		$raw    = isset( $_POST['_gsf_events'] ) && is_array( $_POST['_gsf_events'] ) ? wp_unslash( $_POST['_gsf_events'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		foreach ( $raw as $event ) {
			if ( ! is_array( $event ) || ! isset( $types[ $event['type'] ?? '' ] ) ) {
				continue;
			}
			$player = absint( $event['player'] ?? 0 );
			$name   = sanitize_text_field( $event['player_name'] ?? '' );
			if ( ! $player && '' === $name && '' === (string) ( $event['minute'] ?? '' ) ) {
				continue;
			}
			$events[] = array(
				'minute'      => absint( $event['minute'] ?? 0 ),
				'type'        => $event['type'],
				'side'        => 'away' === ( $event['side'] ?? '' ) ? 'away' : 'home',
				'player'      => $player,
				'player_name' => $player ? '' : $name,
				'assist'      => absint( $event['assist'] ?? 0 ),
			);
		}
		usort(
			$events,
			static function ( $a, $b ) {
				return $a['minute'] <=> $b['minute'];
			}
		);
		update_post_meta( $post_id, '_gsf_events', $events );

		foreach ( array( '_gsf_lineup_home', '_gsf_lineup_away' ) as $key ) {
			$ids = isset( $_POST[ $key ] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST[ $key ] ) ) ) ) : array();
			update_post_meta( $post_id, $key, $ids );
		}

		// Default competition / season when none was ticked.
		foreach ( array( 'gsf_competition', 'gsf_season' ) as $taxonomy ) {
			if ( ! wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) ) ) {
				$default = gsf_resolve_term( '', $taxonomy );
				if ( $default ) {
					wp_set_object_terms( $post_id, array( $default ), $taxonomy );
				}
			}
		}

		gsf_sync_match_title( $post_id );
		gsf_flush_cache();
	},
	20,
	2
);

/**
 * List table columns.
 */
add_filter(
	'manage_gsf_match_posts_columns',
	static function ( $columns ) {
		$new = array( 'cb' => $columns['cb'] ?? '' );
		$new['title']      = __( 'Match', 'galsenfoot-league' );
		$new['gsf_score']  = __( 'Score', 'galsenfoot-league' );
		$new['gsf_date']   = __( 'Coup d’envoi', 'galsenfoot-league' );
		$new['gsf_status'] = __( 'Statut', 'galsenfoot-league' );
		foreach ( $columns as $key => $label ) {
			if ( 0 === strpos( $key, 'taxonomy-' ) ) {
				$new[ $key ] = $label;
			}
		}
		return $new;
	}
);
add_action(
	'manage_gsf_match_posts_custom_column',
	static function ( $column, $post_id ) {
		$m = gsf_match( $post_id );
		if ( 'gsf_score' === $column ) {
			echo $m['has_score'] ? '<strong>' . (int) $m['home_score'] . ' – ' . (int) $m['away_score'] . '</strong>' : '—';
		} elseif ( 'gsf_date' === $column ) {
			echo esc_html( $m['ts'] ? gsf_date( $m['ts'], 'D j M Y · H:i' ) : '—' );
			echo $m['matchday'] ? ' <small>(J' . (int) $m['matchday'] . ')</small>' : '';
		} elseif ( 'gsf_status' === $column ) {
			echo '<span class="gsf-admin-status is-' . esc_attr( $m['status'] ) . '">' . esc_html( gsf_statuses()[ $m['status'] ] ) . '</span>';
		}
	},
	10,
	2
);
add_filter(
	'manage_edit-gsf_match_sortable_columns',
	static function ( $columns ) {
		$columns['gsf_date'] = 'gsf_date';
		return $columns;
	}
);
add_action(
	'pre_get_posts',
	static function ( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'gsf_match' !== $query->get( 'post_type' ) ) {
			return;
		}
		if ( ! $query->get( 'orderby' ) || 'gsf_date' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', '_gsf_ts' );
			$query->set( 'orderby', 'meta_value_num' );
			if ( ! isset( $_GET['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$query->set( 'order', 'DESC' );
			}
		}
	}
);

add_filter(
	'manage_gsf_player_posts_columns',
	static function ( $columns ) {
		$date = $columns['date'] ?? '';
		unset( $columns['date'] );
		$columns['gsf_club']  = __( 'Club', 'galsenfoot-league' );
		$columns['gsf_pos']   = __( 'Poste', 'galsenfoot-league' );
		$columns['gsf_stats'] = __( 'Matchs / Buts / Passes', 'galsenfoot-league' );
		if ( $date ) {
			$columns['date'] = $date;
		}
		return $columns;
	}
);
add_action(
	'manage_gsf_player_posts_custom_column',
	static function ( $column, $post_id ) {
		if ( 'gsf_club' === $column ) {
			$club = gsf_club( (int) get_post_meta( $post_id, '_gsf_club', true ) );
			echo esc_html( $club['id'] ? $club['name'] : '—' );
		} elseif ( 'gsf_pos' === $column ) {
			echo esc_html( gsf_positions()[ get_post_meta( $post_id, '_gsf_position', true ) ] ?? '—' );
		} elseif ( 'gsf_stats' === $column ) {
			$s = gsf_get_player_stats( $post_id, 0, gsf_resolve_term( '', 'gsf_season' ) );
			echo esc_html( $s['apps'] . ' / ' . $s['goals'] . ' / ' . $s['assists'] );
		}
	},
	10,
	2
);

/**
 * Filter players by club in the list table.
 */
add_action(
	'restrict_manage_posts',
	static function ( $post_type ) {
		if ( 'gsf_player' === $post_type ) {
			gsf_admin_club_select( 'gsf_club_filter', absint( $_GET['gsf_club_filter'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
	}
);
add_action(
	'pre_get_posts',
	static function ( $query ) {
		if ( is_admin() && $query->is_main_query() && 'gsf_player' === $query->get( 'post_type' ) && ! empty( $_GET['gsf_club_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$query->set( 'meta_key', '_gsf_club' );
			$query->set( 'meta_value', absint( $_GET['gsf_club_filter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
	}
);

/**
 * Admin assets.
 */
add_action(
	'admin_enqueue_scripts',
	static function () {
		$screen = get_current_screen();
		if ( ! $screen || ( false === strpos( (string) $screen->id, 'gsf' ) && false === strpos( (string) $screen->post_type, 'gsf_' ) ) ) {
			return;
		}
		wp_enqueue_style( 'gsf-admin', GSF_URL . 'assets/css/admin.css', array(), gsf_asset_version( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'gsf-admin', GSF_URL . 'assets/js/admin.js', array(), gsf_asset_version( 'assets/js/admin.js' ), true );
	}
);

/**
 * The match title field is hidden (generated): show a hint instead.
 */
add_action(
	'edit_form_after_title',
	static function ( $post ) {
		if ( 'gsf_match' === $post->post_type ) {
			echo '<h1 class="gsf-admin-match-title">' . esc_html( $post->post_title && 'auto-draft' !== $post->post_status ? $post->post_title : __( 'Nouveau match', 'galsenfoot-league' ) ) . '</h1>';
		}
	}
);

/**
 * Matches use the classic editor screen: the match sheet is the main content.
 */
add_filter(
	'use_block_editor_for_post_type',
	static function ( $use, $post_type ) {
		return in_array( $post_type, array( 'gsf_match', 'gsf_club', 'gsf_player' ), true ) ? false : $use;
	},
	10,
	2
);
