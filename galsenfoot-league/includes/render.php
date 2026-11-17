<?php
/**
 * HTML rendering of every front-end component. Shared by shortcodes,
 * Elementor widgets, theme templates and the single views.
 *
 * @package GalsenFootLeague
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalise common arguments.
 *
 * @param array $args     Raw args.
 * @param array $defaults Defaults.
 * @return array
 */
function gsf_args( $args, $defaults ) {
	$args                = shortcode_atts( array_merge( array( 'competition' => '', 'season' => '', 'title' => '' ), $defaults ), (array) $args );
	$args['competition'] = gsf_resolve_term( $args['competition'], 'gsf_competition' );
	$args['season']      = gsf_resolve_term( $args['season'], 'gsf_season' );
	wp_enqueue_script( 'gsf' );
	return $args;
}

/**
 * Optional block heading.
 *
 * @param string $title Title.
 * @return string
 */
function gsf_block_title( $title ) {
	return '' !== (string) $title ? '<h2 class="gsf-block-title"><span>' . esc_html( $title ) . '</span></h2>' : '';
}

/**
 * Empty state.
 *
 * @param string $message Message.
 * @return string
 */
function gsf_empty( $message ) {
	return '<p class="gsf-empty">' . esc_html( $message ) . '</p>';
}

/**
 * Player photo or neutral silhouette.
 *
 * @param int    $player_id Player ID.
 * @param string $size      Image size.
 * @return string
 */
function gsf_player_photo( $player_id, $size = 'thumbnail' ) {
	$alt = get_the_title( $player_id );
	if ( has_post_thumbnail( $player_id ) ) {
		return get_the_post_thumbnail(
			$player_id,
			$size,
			array(
				'class'   => 'gsf-photo',
				'alt'     => $alt,
				'loading' => 'lazy',
			)
		);
	}
	$url = get_post_meta( $player_id, '_gsf_photo_url', true );
	if ( $url ) {
		return sprintf( '<img class="gsf-photo" src="%s" alt="%s" width="150" height="150" loading="lazy" decoding="async">', esc_url( $url ), esc_attr( $alt ) );
	}
	return '<svg class="gsf-photo gsf-photo--blank" viewBox="0 0 64 64" role="img" aria-label="' . esc_attr( $alt ) . '"><rect width="64" height="64" fill="currentColor" opacity=".08"/><circle cx="32" cy="24" r="11" fill="currentColor" opacity=".35"/><path d="M10 64c1-15 10-23 22-23s21 8 22 23z" fill="currentColor" opacity=".35"/></svg>';
}

/**
 * League table.
 *
 * @param array $args Arguments.
 * @return string
 */
function gsf_render_standings( $args = array() ) {
	$args = gsf_args(
		$args,
		array(
			'limit'     => 0,
			'compact'   => 0,
			'form'      => 1,
			'highlight' => 0,
			'link'      => '',
		)
	);
	$rows = gsf_get_standings( $args['competition'], $args['season'] );
	if ( ! $rows ) {
		return gsf_block_title( $args['title'] ) . gsf_empty( __( 'Le classement sera disponible dès les premiers matchs.', 'galsenfoot-league' ) );
	}
	$total   = count( $rows );
	$top     = (int) gsf_settings( 'zone_top' );
	$top2    = (int) gsf_settings( 'zone_top2' );
	$bottom  = (int) gsf_settings( 'zone_bottom' );
	$compact = ! empty( $args['compact'] ) && 'no' !== $args['compact'];
	$form    = ! empty( $args['form'] ) && 'no' !== $args['form'] && ! $compact;
	if ( (int) $args['limit'] > 0 ) {
		$rows = array_slice( $rows, 0, (int) $args['limit'] );
	}
	$caption = trim( ( get_term( $args['competition'] )->name ?? '' ) . ' ' . ( get_term( $args['season'] )->name ?? '' ) );

	ob_start();
	echo gsf_block_title( $args['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput
	?>
	<div class="gsf-table-wrap<?php echo $compact ? ' is-compact' : ''; ?>">
		<table class="gsf-table gsf-standings">
			<caption class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %s competition */ __( 'Classement %s', 'galsenfoot-league' ), $caption ) ); ?></caption>
			<thead>
				<tr>
					<th scope="col" class="gsf-col-rank">#</th>
					<th scope="col" class="gsf-col-club"><?php esc_html_e( 'Club', 'galsenfoot-league' ); ?></th>
					<th scope="col" class="gsf-col-pts"><abbr title="<?php esc_attr_e( 'Points', 'galsenfoot-league' ); ?>">Pts</abbr></th>
					<th scope="col"><abbr title="<?php esc_attr_e( 'Matchs joués', 'galsenfoot-league' ); ?>">J</abbr></th>
					<?php if ( ! $compact ) : ?>
						<th scope="col"><abbr title="<?php esc_attr_e( 'Victoires', 'galsenfoot-league' ); ?>">G</abbr></th>
						<th scope="col"><abbr title="<?php esc_attr_e( 'Nuls', 'galsenfoot-league' ); ?>">N</abbr></th>
						<th scope="col"><abbr title="<?php esc_attr_e( 'Défaites', 'galsenfoot-league' ); ?>">P</abbr></th>
						<th scope="col" class="gsf-hide-sm"><abbr title="<?php esc_attr_e( 'Buts pour', 'galsenfoot-league' ); ?>">BP</abbr></th>
						<th scope="col" class="gsf-hide-sm"><abbr title="<?php esc_attr_e( 'Buts contre', 'galsenfoot-league' ); ?>">BC</abbr></th>
					<?php endif; ?>
					<th scope="col"><abbr title="<?php esc_attr_e( 'Différence de buts', 'galsenfoot-league' ); ?>">Diff</abbr></th>
					<?php if ( $form ) : ?>
						<th scope="col" class="gsf-col-form gsf-hide-sm"><?php esc_html_e( 'Forme', 'galsenfoot-league' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $rows as $row ) :
				$club = gsf_club( $row['club'] );
				$zone = '';
				if ( $row['rank'] <= $top ) {
					$zone = 'zone-top';
				} elseif ( $row['rank'] <= $top + $top2 ) {
					$zone = 'zone-top2';
				} elseif ( $bottom && $row['rank'] > $total - $bottom ) {
					$zone = 'zone-bottom';
				}
				$classes = trim( $zone . ( (int) $args['highlight'] === $club['id'] ? ' is-highlight' : '' ) );
				?>
				<tr class="<?php echo esc_attr( $classes ); ?>">
					<td class="gsf-col-rank"><span class="gsf-rank"><?php echo (int) $row['rank']; ?></span></td>
					<th scope="row" class="gsf-col-club">
						<a href="<?php echo esc_url( $club['url'] ); ?>" class="gsf-club-link">
							<?php echo gsf_crest( $club['id'], 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<span class="gsf-club-name"><?php echo esc_html( $club['name'] ); ?></span>
						</a>
					</th>
					<td class="gsf-col-pts"><strong><?php echo (int) $row['points']; ?></strong></td>
					<td><?php echo (int) $row['played']; ?></td>
					<?php if ( ! $compact ) : ?>
						<td><?php echo (int) $row['won']; ?></td>
						<td><?php echo (int) $row['drawn']; ?></td>
						<td><?php echo (int) $row['lost']; ?></td>
						<td class="gsf-hide-sm"><?php echo (int) $row['gf']; ?></td>
						<td class="gsf-hide-sm"><?php echo (int) $row['ga']; ?></td>
					<?php endif; ?>
					<td><?php echo esc_html( ( $row['gd'] > 0 ? '+' : '' ) . $row['gd'] ); ?></td>
					<?php if ( $form ) : ?>
						<td class="gsf-col-form gsf-hide-sm">
							<span class="gsf-form">
							<?php
							$labels = array(
								'W' => array( 'V', __( 'Victoire', 'galsenfoot-league' ) ),
								'D' => array( 'N', __( 'Nul', 'galsenfoot-league' ) ),
								'L' => array( 'D', __( 'Défaite', 'galsenfoot-league' ) ),
							);
							foreach ( $row['form'] as $result ) {
								printf( '<span class="gsf-form__i is-%s" title="%s">%s</span>', esc_attr( strtolower( $result ) ), esc_attr( $labels[ $result ][1] ), esc_html( $labels[ $result ][0] ) );
							}
							?>
							</span>
						</td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php if ( ! $compact ) : ?>
		<ul class="gsf-legend">
			<?php if ( $top ) : ?><li class="zone-top"><?php esc_html_e( 'Champion / Ligue des champions CAF', 'galsenfoot-league' ); ?></li><?php endif; ?>
			<?php if ( $top2 ) : ?><li class="zone-top2"><?php esc_html_e( 'Coupe de la Confédération', 'galsenfoot-league' ); ?></li><?php endif; ?>
			<?php if ( $bottom ) : ?><li class="zone-bottom"><?php esc_html_e( 'Relégation', 'galsenfoot-league' ); ?></li><?php endif; ?>
		</ul>
	<?php endif; ?>
	<?php
	if ( $args['link'] ) {
		printf( '<p class="gsf-more"><a href="%s">%s <span aria-hidden="true">→</span></a></p>', esc_url( $args['link'] ), esc_html__( 'Classement complet', 'galsenfoot-league' ) );
	}
	return ob_get_clean();
}

/**
 * Centre block of a match: score, kick-off time or status.
 *
 * @param array $m Match data.
 * @return string
 */
function gsf_match_center( $m ) {
	if ( $m['has_score'] ) {
		$live = 'live' === $m['status'];
		return sprintf(
			'<span class="gsf-score%s"><b>%d</b><i aria-hidden="true">–</i><span class="screen-reader-text"> %s </span><b>%d</b></span>%s',
			$live ? ' is-live' : '',
			$m['home_score'],
			esc_html__( 'à', 'galsenfoot-league' ),
			$m['away_score'],
			$live ? '<span class="gsf-live"><span class="gsf-live__dot"></span>' . ( $m['minute'] ? (int) $m['minute'] . '’' : esc_html__( 'Direct', 'galsenfoot-league' ) ) . '</span>' : ''
		);
	}
	if ( in_array( $m['status'], array( 'postponed', 'cancelled' ), true ) ) {
		return '<span class="gsf-status-tag">' . esc_html( gsf_statuses()[ $m['status'] ] ) . '</span>';
	}
	return $m['ts'] ? '<time class="gsf-kickoff" datetime="' . esc_attr( wp_date( 'c', $m['ts'] ) ) . '">' . esc_html( gsf_date( $m['ts'], 'H:i' ) ) . '</time>' : '<span class="gsf-kickoff">–:–</span>';
}

/**
 * One match line.
 *
 * @param int $match_id Match ID.
 * @return string
 */
function gsf_match_row( $match_id ) {
	$m    = gsf_match( $match_id );
	$home = gsf_club( $m['home'] );
	$away = gsf_club( $m['away'] );
	$win  = '';
	if ( 'ft' === $m['status'] && $m['has_score'] ) {
		$win = $m['home_score'] > $m['away_score'] ? 'home' : ( $m['away_score'] > $m['home_score'] ? 'away' : '' );
	}
	// Full name on wide screens, abbreviation on phones.
	$name = static function ( $club ) {
		return '<span class="gsf-full">' . esc_html( $club['name'] ) . '</span><abbr class="gsf-short" title="' . esc_attr( $club['name'] ) . '">' . esc_html( $club['short'] ) . '</abbr>';
	};
	return sprintf(
		'<a class="gsf-match is-%1$s" href="%2$s" data-match="%3$d"><span class="gsf-match__team gsf-match__team--home%8$s"><span class="gsf-match__name">%4$s</span>%5$s</span><span class="gsf-match__center">%6$s</span><span class="gsf-match__team gsf-match__team--away%9$s">%7$s<span class="gsf-match__name">%10$s</span></span></a>',
		esc_attr( $m['status'] ),
		esc_url( $m['url'] ),
		$m['id'],
		$name( $home ),
		gsf_crest( $home['id'], 32 ),
		gsf_match_center( $m ),
		gsf_crest( $away['id'], 32 ),
		'home' === $win ? ' is-winner' : '',
		'away' === $win ? ' is-winner' : '',
		$name( $away )
	);
}

/**
 * Match IDs for a list type.
 *
 * @param array $args Normalised args with type, limit, club, matchday.
 * @return int[]
 */
function gsf_matches_for( $args ) {
	$base = array(
		'competition' => $args['competition'],
		'season'      => $args['season'],
		'club'        => (int) ( $args['club'] ?? 0 ),
		'limit'       => max( 1, (int) $args['limit'] ),
		'matchday'    => (int) ( $args['matchday'] ?? 0 ),
		'any_term'    => ! empty( $args['club'] ) && empty( $args['strict'] ),
	);
	switch ( $args['type'] ) {
		case 'results':
			return gsf_query_matches( $base + array( 'status' => 'ft', 'order' => 'DESC' ) );
		case 'live':
			return gsf_query_matches( $base + array( 'status' => 'live' ) );
		case 'all':
			return gsf_query_matches( $base );
		case 'fixtures':
		default:
			return gsf_query_matches( $base + array( 'status' => array( 'scheduled', 'live', 'postponed' ), 'from' => time() - 3 * HOUR_IN_SECONDS ) );
	}
}

/**
 * List of matches grouped by day.
 *
 * @param array $args Arguments.
 * @return string
 */
function gsf_render_matches( $args = array() ) {
	$args = gsf_args(
		$args,
		array(
			'type'     => 'fixtures',
			'limit'    => 8,
			'club'     => 0,
			'matchday' => 0,
			'link'     => '',
		)
	);
	$ids  = gsf_matches_for( $args );
	if ( ! $ids ) {
		$message = 'results' === $args['type'] ? __( 'Aucun résultat pour le moment.', 'galsenfoot-league' ) : __( 'Aucun match programmé pour le moment.', 'galsenfoot-league' );
		return gsf_block_title( $args['title'] ) . gsf_empty( $message );
	}
	$has_live = false;
	$html     = '';
	$day      = '';
	foreach ( $ids as $id ) {
		$ts       = (int) get_post_meta( $id, '_gsf_ts', true );
		$this_day = $ts ? gsf_date( $ts, 'Y-m-d' ) : '';
		if ( $this_day !== $day ) {
			$html .= ( '' !== $day || '' !== $html ? '</div>' : '' );
			$md    = (int) get_post_meta( $id, '_gsf_matchday', true );
			$html .= '<div class="gsf-match-group"><h3 class="gsf-match-day"><span>' . esc_html( $ts ? ucfirst( gsf_date( $ts, 'l j F' ) ) : __( 'Date à confirmer', 'galsenfoot-league' ) ) . '</span>' . ( $md ? '<small>' . esc_html( sprintf( /* translators: %d matchday */ __( 'J%d', 'galsenfoot-league' ), $md ) ) . '</small>' : '' ) . '</h3>';
			$day   = $this_day;
		}
		$html    .= gsf_match_row( $id );
		$has_live = $has_live || 'live' === get_post_meta( $id, '_gsf_status', true );
	}
	$html .= '</div>';
	$more  = $args['link'] ? sprintf( '<p class="gsf-more"><a href="%s">%s <span aria-hidden="true">→</span></a></p>', esc_url( $args['link'] ), esc_html__( 'Tous les matchs', 'galsenfoot-league' ) ) : '';
	return gsf_block_title( $args['title'] ) . '<div class="gsf-matches"' . ( $has_live ? ' data-gsf-live="1"' : '' ) . '>' . $html . '</div>' . $more;
}

/**
 * Horizontal score strip (latest results then next fixtures).
 *
 * @param array $args Arguments.
 * @return string
 */
function gsf_render_ticker( $args = array() ) {
	$args    = gsf_args( $args, array( 'limit' => 12 ) );
	$half    = max( 2, (int) ceil( (int) $args['limit'] / 2 ) );
	$results = array_reverse( gsf_matches_for( array_merge( $args, array( 'type' => 'results', 'limit' => $half ) ) ) );
	$next    = gsf_matches_for( array_merge( $args, array( 'type' => 'fixtures', 'limit' => $half ) ) );
	$ids     = array_merge( $results, $next );
	if ( ! $ids ) {
		return '';
	}
	$has_live = false;
	$items    = '';
	foreach ( $ids as $id ) {
		$m        = gsf_match( $id );
		$home     = gsf_club( $m['home'] );
		$away     = gsf_club( $m['away'] );
		$has_live = $has_live || 'live' === $m['status'];
		if ( 'live' === $m['status'] ) {
			$label = '<span class="gsf-live"><span class="gsf-live__dot"></span>' . ( $m['minute'] ? (int) $m['minute'] . '’' : esc_html__( 'Direct', 'galsenfoot-league' ) ) . '</span>';
		} elseif ( 'ft' === $m['status'] ) {
			$label = esc_html( gsf_date( $m['ts'], 'j M' ) ) . ' · ' . esc_html__( 'Terminé', 'galsenfoot-league' );
		} elseif ( 'scheduled' === $m['status'] ) {
			$label = esc_html( gsf_date( $m['ts'], 'D j M · H:i' ) );
		} else {
			$label = esc_html( gsf_statuses()[ $m['status'] ] );
		}
		$items .= sprintf(
			'<li class="gsf-ticker__item is-%1$s"><a href="%2$s" data-match="%9$d"><span class="gsf-ticker__meta">%3$s</span><span class="gsf-ticker__row">%4$s<span class="gsf-ticker__team">%5$s</span><b>%6$s</b></span><span class="gsf-ticker__row">%7$s<span class="gsf-ticker__team">%8$s</span><b>%10$s</b></span></a></li>',
			esc_attr( $m['status'] ),
			esc_url( $m['url'] ),
			$label,
			gsf_crest( $home['id'], 20 ),
			esc_html( $home['short'] ),
			$m['has_score'] ? (int) $m['home_score'] : '',
			gsf_crest( $away['id'], 20 ),
			esc_html( $away['short'] ),
			$m['id'],
			$m['has_score'] ? (int) $m['away_score'] : ''
		);
	}
	return '<div class="gsf-ticker"' . ( $has_live ? ' data-gsf-live="1"' : '' ) . ' role="region" aria-label="' . esc_attr__( 'Scores et prochains matchs', 'galsenfoot-league' ) . '"><ul class="gsf-ticker__track" tabindex="0">' . $items . '</ul></div>';
}

/**
 * Next match hero with countdown.
 *
 * @param array $args Arguments.
 * @return string
 */
function gsf_render_next_match( $args = array() ) {
	$args = gsf_args( $args, array( 'club' => 0, 'match' => 0 ) );
	$id   = (int) $args['match'];
	if ( ! $id ) {
		$live = gsf_matches_for( array_merge( $args, array( 'type' => 'live', 'limit' => 1 ) ) );
		$ids  = $live ? $live : gsf_matches_for( array_merge( $args, array( 'type' => 'fixtures', 'limit' => 1 ) ) );
		if ( ! $ids ) {
			$ids = gsf_matches_for( array_merge( $args, array( 'type' => 'results', 'limit' => 1 ) ) );
		}
		$id = $ids ? $ids[0] : 0;
	}
	if ( ! $id || 'gsf_match' !== get_post_type( $id ) ) {
		return gsf_empty( __( 'Aucun match programmé pour le moment.', 'galsenfoot-league' ) );
	}
	return gsf_block_title( $args['title'] ) . gsf_match_hero( $id, true );
}

/**
 * Large score board used for the "next match" block and the match page.
 *
 * @param int  $match_id Match ID.
 * @param bool $link     Wrap team names with links and add a CTA.
 * @return string
 */
function gsf_match_hero( $match_id, $link = false ) {
	$m     = gsf_match( $match_id );
	$home  = gsf_club( $m['home'] );
	$away  = gsf_club( $m['away'] );
	$comp  = get_the_terms( $match_id, 'gsf_competition' );
	$label = ( $comp && ! is_wp_error( $comp ) ) ? $comp[0]->name : '';
	if ( $m['matchday'] ) {
		$label .= ( $label ? ' · ' : '' ) . sprintf( /* translators: %d matchday */ __( 'Journée %d', 'galsenfoot-league' ), $m['matchday'] );
	}
	$team = static function ( $club, $side ) {
		return sprintf(
			'<div class="gsf-hero__team gsf-hero__team--%s"><a href="%s">%s<span class="gsf-hero__name">%s</span></a></div>',
			$side,
			esc_url( $club['url'] ),
			gsf_crest( $club['id'], 96 ),
			esc_html( $club['name'] )
		);
	};
	$center = gsf_match_center( $m );
	if ( 'scheduled' === $m['status'] && $m['ts'] > time() ) {
		$center .= '<div class="gsf-countdown" data-gsf-countdown="' . (int) $m['ts'] . '" aria-hidden="true"></div>';
	} elseif ( 'ft' === $m['status'] ) {
		$center .= '<span class="gsf-status-tag">' . esc_html__( 'Terminé', 'galsenfoot-league' ) . '</span>';
	}
	$meta = array();
	if ( $m['ts'] ) {
		$meta[] = '<time datetime="' . esc_attr( wp_date( 'c', $m['ts'] ) ) . '">' . esc_html( ucfirst( gsf_date( $m['ts'], 'l j F Y · H:i' ) ) ) . '</time>';
	}
	if ( $m['venue'] ) {
		$meta[] = '<span>' . esc_html( $m['venue'] ) . '</span>';
	}
	return sprintf(
		'<div class="gsf-hero is-%1$s" style="--gsf-home:%2$s;--gsf-away:%3$s"%9$s data-match-hero="%10$d"><p class="gsf-hero__label">%4$s</p><div class="gsf-hero__board">%5$s<div class="gsf-hero__center">%6$s</div>%7$s</div><p class="gsf-hero__meta">%8$s</p>%11$s</div>',
		esc_attr( $m['status'] ),
		esc_attr( $home['color'] ),
		esc_attr( $away['color'] ),
		esc_html( $label ),
		$team( $home, 'home' ),
		$center,
		$team( $away, 'away' ),
		implode( '<i aria-hidden="true"> • </i>', $meta ),
		'live' === $m['status'] ? ' data-gsf-live="1"' : '',
		$m['id'],
		$link ? '<p class="gsf-hero__cta"><a class="gsf-btn" href="' . esc_url( $m['url'] ) . '">' . esc_html__( 'Fiche du match', 'galsenfoot-league' ) . '</a></p>' : ''
	);
}

/**
 * Player ranking (top scorers, assists, cards…).
 *
 * @param array $args Arguments.
 * @return string
 */
function gsf_render_leaders( $args = array() ) {
	$args  = gsf_args(
		$args,
		array(
			'stat'  => 'goals',
			'limit' => 10,
			'club'  => 0,
		)
	);
	$rows  = gsf_get_leaders( $args['stat'], $args['competition'], $args['season'], (int) $args['limit'], (int) $args['club'] );
	$units = array(
		'goals'   => __( 'buts', 'galsenfoot-league' ),
		'assists' => __( 'passes D.', 'galsenfoot-league' ),
		'yellow'  => __( 'jaunes', 'galsenfoot-league' ),
		'red'     => __( 'rouges', 'galsenfoot-league' ),
		'apps'    => __( 'matchs', 'galsenfoot-league' ),
	);
	$unit  = $units[ $args['stat'] ] ?? $units['goals'];
	if ( ! $rows ) {
		return gsf_block_title( $args['title'] ) . gsf_empty( __( 'Les statistiques seront disponibles après les premiers matchs.', 'galsenfoot-league' ) );
	}
	$max  = max( 1, $rows[0]['value'] );
	$html = '<ol class="gsf-leaders gsf-leaders--' . esc_attr( $args['stat'] ) . '">';
	foreach ( $rows as $i => $row ) {
		$club  = gsf_club( $row['club'] );
		$html .= sprintf(
			'<li class="gsf-leader%1$s"><span class="gsf-leader__rank">%2$d</span><a class="gsf-leader__photo" href="%3$s" tabindex="-1" aria-hidden="true">%4$s</a><span class="gsf-leader__body"><a class="gsf-leader__name" href="%3$s">%5$s</a><span class="gsf-leader__club">%6$s %7$s</span><span class="gsf-leader__bar" aria-hidden="true"><i style="width:%8$d%%"></i></span></span><span class="gsf-leader__value"><b data-gsf-count="%9$d">%9$d</b> <small>%10$s</small></span></li>',
			0 === $i ? ' is-first' : '',
			$i + 1,
			esc_url( get_permalink( $row['player'] ) ),
			gsf_player_photo( $row['player'] ),
			esc_html( $row['name'] ),
			gsf_crest( $club['id'], 18 ),
			esc_html( $club['id'] ? $club['name'] : '' ),
			(int) round( $row['value'] / $max * 100 ),
			$row['value'],
			esc_html( $unit )
		);
	}
	return gsf_block_title( $args['title'] ) . $html . '</ol>';
}

/**
 * Grid of clubs.
 *
 * @param array $args Arguments.
 * @return string
 */
function gsf_render_clubs( $args = array() ) {
	$args  = gsf_args( $args, array( 'scope' => 'competition', 'layout' => 'grid' ) );
	$clubs = array();
	if ( 'all' !== $args['scope'] ) {
		$clubs = wp_list_pluck( gsf_get_standings( $args['competition'], $args['season'] ), 'club' );
		usort(
			$clubs,
			static function ( $a, $b ) {
				return strcasecmp( get_the_title( $a ), get_the_title( $b ) );
			}
		);
	}
	if ( ! $clubs ) {
		$clubs = get_posts(
			array(
				'post_type'      => 'gsf_club',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
	}
	if ( ! $clubs ) {
		return gsf_block_title( $args['title'] ) . gsf_empty( __( 'Aucun club pour le moment.', 'galsenfoot-league' ) );
	}
	$html = '<ul class="gsf-clubs gsf-clubs--' . esc_attr( 'strip' === $args['layout'] ? 'strip' : 'grid' ) . '">';
	foreach ( $clubs as $club_id ) {
		$club  = gsf_club( $club_id );
		$city  = get_post_meta( $club_id, '_gsf_city', true );
		$html .= sprintf(
			'<li><a class="gsf-club-card" href="%s" style="--gsf-club:%s">%s<span class="gsf-club-card__name">%s</span>%s</a></li>',
			esc_url( $club['url'] ),
			esc_attr( $club['color'] ),
			gsf_crest( $club_id, 64 ),
			esc_html( $club['name'] ),
			$city ? '<span class="gsf-club-card__city">' . esc_html( $city ) . '</span>' : ''
		);
	}
	return gsf_block_title( $args['title'] ) . $html . '</ul>';
}

/**
 * Squad of a club grouped by position.
 *
 * @param array $args Arguments (club).
 * @return string
 */
function gsf_render_squad( $args = array() ) {
	$args    = gsf_args( $args, array( 'club' => 0 ) );
	$club_id = (int) $args['club'] ?: ( is_singular( 'gsf_club' ) ? get_the_ID() : 0 );
	$players = $club_id ? gsf_club_players( $club_id ) : array();
	if ( ! $players ) {
		return gsf_block_title( $args['title'] ) . gsf_empty( __( 'Effectif non renseigné.', 'galsenfoot-league' ) );
	}
	$stats     = gsf_get_player_stats_table( $args['competition'], $args['season'] );
	$positions = gsf_positions();
	$plural    = array(
		'GK' => __( 'Gardiens', 'galsenfoot-league' ),
		'DF' => __( 'Défenseurs', 'galsenfoot-league' ),
		'MF' => __( 'Milieux', 'galsenfoot-league' ),
		'FW' => __( 'Attaquants', 'galsenfoot-league' ),
		''   => __( 'Autres', 'galsenfoot-league' ),
	);
	$groups    = array();
	foreach ( $players as $player ) {
		$pos              = get_post_meta( $player->ID, '_gsf_position', true );
		$pos              = isset( $positions[ $pos ] ) ? $pos : '';
		$groups[ $pos ][] = $player;
	}
	$html = '<div class="gsf-squad">';
	foreach ( $groups as $pos => $group ) {
		$html .= '<h3 class="gsf-squad__pos">' . esc_html( $plural[ $pos ] ) . '</h3><ul class="gsf-squad__list">';
		foreach ( $group as $player ) {
			$s      = $stats[ $player->ID ] ?? array( 'apps' => 0, 'goals' => 0 );
			$number = get_post_meta( $player->ID, '_gsf_number', true );
			$html  .= sprintf(
				'<li><a class="gsf-player-card" href="%s"><span class="gsf-player-card__num">%s</span>%s<span class="gsf-player-card__name">%s</span><span class="gsf-player-card__stats">%s</span></a></li>',
				esc_url( get_permalink( $player ) ),
				'' !== $number ? (int) $number : '–',
				gsf_player_photo( $player->ID ),
				esc_html( get_the_title( $player ) ),
				esc_html( sprintf( /* translators: 1: appearances 2: goals */ __( '%1$d matchs · %2$d buts', 'galsenfoot-league' ), $s['apps'], $s['goals'] ) )
			);
		}
		$html .= '</ul>';
	}
	return gsf_block_title( $args['title'] ) . $html . '</div>';
}

/**
 * Definition list of facts.
 *
 * @param array $facts label => value.
 * @return string
 */
function gsf_facts( $facts ) {
	$html = '';
	foreach ( array_filter( $facts, 'strlen' ) as $label => $value ) {
		$html .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . wp_kses_post( $value ) . '</dd></div>';
	}
	return $html ? '<dl class="gsf-facts">' . $html . '</dl>' : '';
}

/**
 * Club page body.
 *
 * @param int $club_id Club ID.
 * @return string
 */
function gsf_render_club_profile( $club_id ) {
	$capacity = (int) get_post_meta( $club_id, '_gsf_capacity', true );
	$website  = get_post_meta( $club_id, '_gsf_website', true );
	$facts    = gsf_facts(
		array(
			__( 'Ville', 'galsenfoot-league' )      => esc_html( get_post_meta( $club_id, '_gsf_city', true ) ),
			__( 'Fondation', 'galsenfoot-league' )  => esc_html( get_post_meta( $club_id, '_gsf_founded', true ) ),
			__( 'Stade', 'galsenfoot-league' )      => esc_html( get_post_meta( $club_id, '_gsf_stadium', true ) ) . ( $capacity ? ' <small>(' . esc_html( number_format_i18n( $capacity ) ) . ' ' . esc_html__( 'places', 'galsenfoot-league' ) . ')</small>' : '' ),
			__( 'Entraîneur', 'galsenfoot-league' ) => esc_html( get_post_meta( $club_id, '_gsf_coach', true ) ),
			__( 'Président', 'galsenfoot-league' )  => esc_html( get_post_meta( $club_id, '_gsf_president', true ) ),
			__( 'Site web', 'galsenfoot-league' )   => $website ? '<a href="' . esc_url( $website ) . '" rel="noopener nofollow" target="_blank">' . esc_html( wp_parse_url( $website, PHP_URL_HOST ) ) . '</a>' : '',
		)
	);
	$common = array( 'club' => $club_id );
	$html   = $facts;
	$html  .= '<div class="gsf-cols">';
	$html  .= '<section>' . gsf_render_matches( $common + array( 'type' => 'fixtures', 'limit' => 5, 'title' => __( 'Prochains matchs', 'galsenfoot-league' ) ) ) . '</section>';
	$html  .= '<section>' . gsf_render_matches( $common + array( 'type' => 'results', 'limit' => 5, 'title' => __( 'Derniers résultats', 'galsenfoot-league' ) ) ) . '</section>';
	$html  .= '</div>';
	$html  .= '<section>' . gsf_render_standings( array( 'highlight' => $club_id, 'title' => __( 'Classement', 'galsenfoot-league' ) ) ) . '</section>';
	$html  .= '<section>' . gsf_render_squad( $common + array( 'title' => __( 'Effectif', 'galsenfoot-league' ) ) ) . '</section>';
	$html  .= '<section>' . gsf_render_leaders( $common + array( 'limit' => 5, 'title' => __( 'Meilleurs buteurs du club', 'galsenfoot-league' ) ) ) . '</section>';
	return $html;
}

/**
 * Player page body.
 *
 * @param int $player_id Player ID.
 * @return string
 */
function gsf_render_player_profile( $player_id ) {
	$club_id   = (int) get_post_meta( $player_id, '_gsf_club', true );
	$club      = gsf_club( $club_id );
	$positions = gsf_positions();
	$pos       = get_post_meta( $player_id, '_gsf_position', true );
	$birth     = get_post_meta( $player_id, '_gsf_birth', true );
	$age       = gsf_age( $birth );
	$height    = (int) get_post_meta( $player_id, '_gsf_height', true );
	$feet      = array(
		'R' => __( 'Droit', 'galsenfoot-league' ),
		'L' => __( 'Gauche', 'galsenfoot-league' ),
		'B' => __( 'Les deux', 'galsenfoot-league' ),
	);
	$foot      = get_post_meta( $player_id, '_gsf_foot', true );
	$number    = get_post_meta( $player_id, '_gsf_number', true );
	$season    = gsf_resolve_term( '', 'gsf_season' );
	$comp      = gsf_resolve_term( '', 'gsf_competition' );
	$s         = gsf_get_player_stats( $player_id, 'api' === ( gsf_get_player_stats( $player_id, 0, $season )['source'] ?? '' ) ? 0 : $comp, $season );

	$facts = gsf_facts(
		array(
			__( 'Club', 'galsenfoot-league' )              => $club['id'] ? '<a href="' . esc_url( $club['url'] ) . '">' . esc_html( $club['name'] ) . '</a>' : '',
			__( 'Poste', 'galsenfoot-league' )             => esc_html( $positions[ $pos ] ?? '' ),
			__( 'Numéro', 'galsenfoot-league' )            => '' !== $number ? (string) (int) $number : '',
			__( 'Date de naissance', 'galsenfoot-league' ) => $birth ? esc_html( wp_date( get_option( 'date_format' ), strtotime( $birth . ' 12:00:00' ) ) ) . ( null !== $age ? ' <small>(' . esc_html( sprintf( /* translators: %d age */ __( '%d ans', 'galsenfoot-league' ), $age ) ) . ')</small>' : '' ) : '',
			__( 'Nationalité', 'galsenfoot-league' )       => esc_html( get_post_meta( $player_id, '_gsf_nationality', true ) ),
			__( 'Taille', 'galsenfoot-league' )            => $height ? esc_html( number_format_i18n( $height / 100, 2 ) . ' m' ) : '',
			__( 'Pied fort', 'galsenfoot-league' )         => esc_html( $feet[ $foot ] ?? '' ),
		)
	);

	$tiles = array(
		array( $s['apps'], __( 'Matchs', 'galsenfoot-league' ) ),
		array( $s['goals'], __( 'Buts', 'galsenfoot-league' ) ),
		array( $s['assists'], __( 'Passes déc.', 'galsenfoot-league' ) ),
		array( $s['yellow'], __( 'Cartons jaunes', 'galsenfoot-league' ) ),
		array( $s['red'], __( 'Cartons rouges', 'galsenfoot-league' ) ),
	);
	if ( $s['minutes'] > 0 ) {
		$tiles[] = array( $s['minutes'], __( 'Minutes', 'galsenfoot-league' ) );
	}
	$season_name = $season ? get_term( $season )->name : '';
	$html        = $facts . gsf_block_title( trim( __( 'Performances', 'galsenfoot-league' ) . ' ' . $season_name ) ) . '<ul class="gsf-tiles">';
	foreach ( $tiles as $tile ) {
		$html .= sprintf( '<li><b data-gsf-count="%1$d">%1$d</b><span>%2$s</span></li>', (int) $tile[0], esc_html( $tile[1] ) );
	}
	$html .= '</ul>';
	if ( $s['rating'] > 0 ) {
		$html .= '<p class="gsf-rating">' . esc_html__( 'Note moyenne', 'galsenfoot-league' ) . ' <b>' . esc_html( number_format_i18n( $s['rating'], 2 ) ) . '</b></p>';
	}
	if ( $s['apps'] > 0 ) {
		$html .= '<p class="gsf-ratio">' . esc_html( sprintf( /* translators: %s ratio */ __( 'Ratio : %s but par match', 'galsenfoot-league' ), number_format_i18n( $s['goals'] / $s['apps'], 2 ) ) ) . '</p>';
	}

	// Goals match by match.
	$log = '';
	foreach ( gsf_query_matches( array( 'season' => $season, 'club' => $club_id, 'status' => 'ft', 'order' => 'DESC', 'limit' => 60 ) ) as $match_id ) {
		$mine = array();
		foreach ( (array) get_post_meta( $match_id, '_gsf_events', true ) as $event ) {
			if ( (int) ( $event['player'] ?? 0 ) === (int) $player_id && in_array( $event['type'], array( 'goal', 'penalty' ), true ) ) {
				$mine[] = (int) $event['minute'] . '’' . ( 'penalty' === $event['type'] ? ' (pen.)' : '' );
			}
		}
		if ( $mine ) {
			$log .= '<li><a href="' . esc_url( get_permalink( $match_id ) ) . '">' . esc_html( get_the_title( $match_id ) ) . '</a> <span>⚽ ' . esc_html( implode( ', ', $mine ) ) . '</span></li>';
		}
	}
	if ( $log ) {
		$html .= gsf_block_title( __( 'Ses buts cette saison', 'galsenfoot-league' ) ) . '<ul class="gsf-goal-log">' . $log . '</ul>';
	}
	return $html;
}

/**
 * Match page body: score board and timeline.
 *
 * @param int $match_id Match ID.
 * @return string
 */
function gsf_render_match_detail( $match_id ) {
	wp_enqueue_script( 'gsf' );
	$m     = gsf_match( $match_id );
	$html  = gsf_match_hero( $match_id );
	$icons = array(
		'goal'     => '⚽',
		'penalty'  => '⚽',
		'own_goal' => '⚽',
		'yellow'   => '<i class="gsf-card gsf-card--y"></i>',
		'red'      => '<i class="gsf-card gsf-card--r"></i>',
	);
	$types = gsf_event_types();
	if ( $m['events'] ) {
		$events = $m['events'];
		usort(
			$events,
			static function ( $a, $b ) {
				return (int) $a['minute'] <=> (int) $b['minute'];
			}
		);
		$html .= gsf_block_title( __( 'Temps forts', 'galsenfoot-league' ) ) . '<ol class="gsf-timeline">';
		foreach ( $events as $event ) {
			$type = $event['type'] ?? '';
			if ( ! isset( $types[ $type ] ) ) {
				continue;
			}
			$name   = ! empty( $event['player'] ) && get_post( (int) $event['player'] ) ? '<a href="' . esc_url( get_permalink( (int) $event['player'] ) ) . '">' . esc_html( get_the_title( (int) $event['player'] ) ) . '</a>' : esc_html( $event['player_name'] ?? '' );
			$assist = '';
			if ( in_array( $type, array( 'goal', 'penalty' ), true ) ) {
				$assist_name = ! empty( $event['assist'] ) ? get_the_title( (int) $event['assist'] ) : ( $event['assist_name'] ?? '' );
				$assist      = $assist_name ? ' <small>(' . esc_html( sprintf( /* translators: %s player */ __( 'passe de %s', 'galsenfoot-league' ), $assist_name ) ) . ')</small>' : '';
			}
			$note  = 'penalty' === $type ? ' <small>(' . esc_html__( 'pen.', 'galsenfoot-league' ) . ')</small>' : ( 'own_goal' === $type ? ' <small>(' . esc_html__( 'c.s.c.', 'galsenfoot-league' ) . ')</small>' : '' );
			$html .= sprintf(
				'<li class="gsf-timeline__item is-%1$s is-%2$s"><span class="gsf-timeline__min">%3$d’</span><span class="gsf-timeline__icon" title="%4$s">%5$s</span><span class="gsf-timeline__text">%6$s%7$s%8$s</span></li>',
				'away' === ( $event['side'] ?? 'home' ) ? 'away' : 'home',
				esc_attr( $type ),
				(int) $event['minute'],
				esc_attr( $types[ $type ] ),
				$icons[ $type ],
				$name ? $name : esc_html( $types[ $type ] ),
				$note,
				$assist
			);
		}
		$html .= '</ol>';
	}

	// Line-ups.
	$lineups = '';
	foreach ( array( 'home', 'away' ) as $side ) {
		$ids = array_filter( array_map( 'intval', (array) get_post_meta( $match_id, '_gsf_lineup_' . $side, true ) ) );
		if ( ! $ids ) {
			continue;
		}
		$club     = gsf_club( $m[ $side ] );
		$lineups .= '<div class="gsf-lineup"><h3>' . gsf_crest( $club['id'], 24 ) . ' ' . esc_html( $club['name'] ) . '</h3><ul>';
		foreach ( $ids as $player_id ) {
			if ( 'gsf_player' === get_post_type( $player_id ) ) {
				$number   = get_post_meta( $player_id, '_gsf_number', true );
				$lineups .= '<li><span>' . ( '' !== $number ? (int) $number : '' ) . '</span><a href="' . esc_url( get_permalink( $player_id ) ) . '">' . esc_html( get_the_title( $player_id ) ) . '</a></li>';
			}
		}
		$lineups .= '</ul></div>';
	}
	if ( $lineups ) {
		$html .= gsf_block_title( __( 'Compositions', 'galsenfoot-league' ) ) . '<div class="gsf-cols">' . $lineups . '</div>';
	}
	return $html;
}

/**
 * Inject the profile blocks into the content of single clubs / players / matches,
 * so the plugin works with any theme.
 */
add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! is_singular( array( 'gsf_club', 'gsf_player', 'gsf_match' ) ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		static $running = false;
		if ( $running ) {
			return $content;
		}
		$running = true;
		$id      = get_the_ID();
		switch ( get_post_type( $id ) ) {
			case 'gsf_club':
				$content = '<div class="gsf-about">' . $content . '</div>' . gsf_render_club_profile( $id );
				break;
			case 'gsf_player':
				$content = gsf_render_player_profile( $id ) . ( trim( $content ) ? gsf_block_title( __( 'Biographie', 'galsenfoot-league' ) ) . '<div class="gsf-about">' . $content . '</div>' : '' );
				break;
			case 'gsf_match':
				$content = gsf_render_match_detail( $id ) . ( trim( $content ) ? gsf_block_title( __( 'Compte rendu', 'galsenfoot-league' ) ) . '<div class="gsf-about">' . $content . '</div>' : '' );
				break;
		}
		$running = false;
		return '<div class="gsf-single">' . $content . '</div>';
	},
	20
);
