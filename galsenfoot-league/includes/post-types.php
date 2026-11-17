<?php
/**
 * Post types and taxonomies.
 *
 * @package GalsenFootLeague
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register clubs, players, matches, competitions and seasons.
 */
function gsf_register_content_types() {
	$common = array(
		'public'       => true,
		'show_in_rest' => true,
		'show_in_menu' => 'gsf',
		'has_archive'  => true,
		'map_meta_cap' => true,
	);

	register_post_type(
		'gsf_club',
		array_merge(
			$common,
			array(
				'labels'    => array(
					'name'               => __( 'Clubs', 'galsenfoot-league' ),
					'singular_name'      => __( 'Club', 'galsenfoot-league' ),
					'add_new_item'       => __( 'Ajouter un club', 'galsenfoot-league' ),
					'edit_item'          => __( 'Modifier le club', 'galsenfoot-league' ),
					'new_item'           => __( 'Nouveau club', 'galsenfoot-league' ),
					'view_item'          => __( 'Voir le club', 'galsenfoot-league' ),
					'search_items'       => __( 'Rechercher un club', 'galsenfoot-league' ),
					'not_found'          => __( 'Aucun club', 'galsenfoot-league' ),
					'all_items'          => __( 'Clubs', 'galsenfoot-league' ),
					'featured_image'     => __( 'Logo du club', 'galsenfoot-league' ),
					'set_featured_image' => __( 'Définir le logo', 'galsenfoot-league' ),
				),
				'supports'  => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'rewrite'   => array(
					'slug'       => 'clubs',
					'with_front' => false,
				),
				'menu_icon' => 'dashicons-shield',
			)
		)
	);

	register_post_type(
		'gsf_player',
		array_merge(
			$common,
			array(
				'labels'  => array(
					'name'               => __( 'Joueurs', 'galsenfoot-league' ),
					'singular_name'      => __( 'Joueur', 'galsenfoot-league' ),
					'add_new_item'       => __( 'Ajouter un joueur', 'galsenfoot-league' ),
					'edit_item'          => __( 'Modifier le joueur', 'galsenfoot-league' ),
					'new_item'           => __( 'Nouveau joueur', 'galsenfoot-league' ),
					'view_item'          => __( 'Voir le joueur', 'galsenfoot-league' ),
					'search_items'       => __( 'Rechercher un joueur', 'galsenfoot-league' ),
					'not_found'          => __( 'Aucun joueur', 'galsenfoot-league' ),
					'all_items'          => __( 'Joueurs', 'galsenfoot-league' ),
					'featured_image'     => __( 'Photo du joueur', 'galsenfoot-league' ),
					'set_featured_image' => __( 'Définir la photo', 'galsenfoot-league' ),
				),
				'supports' => array( 'title', 'editor', 'thumbnail' ),
				'rewrite'  => array(
					'slug'       => 'joueurs',
					'with_front' => false,
				),
			)
		)
	);

	register_post_type(
		'gsf_match',
		array_merge(
			$common,
			array(
				'labels'   => array(
					'name'          => __( 'Matchs', 'galsenfoot-league' ),
					'singular_name' => __( 'Match', 'galsenfoot-league' ),
					'add_new_item'  => __( 'Ajouter un match', 'galsenfoot-league' ),
					'edit_item'     => __( 'Modifier le match', 'galsenfoot-league' ),
					'new_item'      => __( 'Nouveau match', 'galsenfoot-league' ),
					'view_item'     => __( 'Voir le match', 'galsenfoot-league' ),
					'search_items'  => __( 'Rechercher un match', 'galsenfoot-league' ),
					'not_found'     => __( 'Aucun match', 'galsenfoot-league' ),
					'all_items'     => __( 'Matchs', 'galsenfoot-league' ),
				),
				// The title is generated from the two clubs; the editor holds the match report.
				'supports' => array( 'editor', 'thumbnail', 'comments' ),
				'rewrite'  => array(
					'slug'       => 'matchs',
					'with_front' => false,
				),
			)
		)
	);

	$tax_common = array(
		'public'            => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'hierarchical'      => true,
	);
	register_taxonomy(
		'gsf_competition',
		array( 'gsf_match' ),
		array_merge(
			$tax_common,
			array(
				'labels'  => array(
					'name'          => __( 'Compétitions', 'galsenfoot-league' ),
					'singular_name' => __( 'Compétition', 'galsenfoot-league' ),
					'add_new_item'  => __( 'Ajouter une compétition', 'galsenfoot-league' ),
					'edit_item'     => __( 'Modifier la compétition', 'galsenfoot-league' ),
					'search_items'  => __( 'Rechercher', 'galsenfoot-league' ),
					'all_items'     => __( 'Toutes les compétitions', 'galsenfoot-league' ),
				),
				'rewrite' => array(
					'slug'       => 'competition',
					'with_front' => false,
				),
			)
		)
	);
	register_taxonomy(
		'gsf_season',
		array( 'gsf_match' ),
		array_merge(
			$tax_common,
			array(
				'labels'  => array(
					'name'          => __( 'Saisons', 'galsenfoot-league' ),
					'singular_name' => __( 'Saison', 'galsenfoot-league' ),
					'add_new_item'  => __( 'Ajouter une saison', 'galsenfoot-league' ),
					'edit_item'     => __( 'Modifier la saison', 'galsenfoot-league' ),
					'search_items'  => __( 'Rechercher', 'galsenfoot-league' ),
					'all_items'     => __( 'Toutes les saisons', 'galsenfoot-league' ),
				),
				'rewrite' => array(
					'slug'       => 'saison',
					'with_front' => false,
				),
			)
		)
	);
}
add_action( 'init', 'gsf_register_content_types', 5 );

/**
 * Flush rewrite rules once after an update that changes them.
 */
add_action(
	'init',
	static function () {
		if ( get_option( 'gsf_rewrite_v' ) !== GSF_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'gsf_rewrite_v', GSF_VERSION, true );
		}
	},
	99
);

/**
 * Recompute statistics whenever match / player data changes.
 */
add_action(
	'save_post',
	static function ( $post_id, $post ) {
		if ( in_array( $post->post_type, array( 'gsf_match', 'gsf_player', 'gsf_club' ), true ) ) {
			gsf_flush_cache();
		}
	},
	50,
	2
);
add_action(
	'deleted_post',
	static function () {
		gsf_flush_cache();
	}
);
add_action(
	'set_object_terms',
	static function ( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( in_array( $taxonomy, array( 'gsf_competition', 'gsf_season' ), true ) ) {
			gsf_flush_cache();
		}
	},
	10,
	4
);

/**
 * Match archives: chronological order, most recent first.
 */
add_action(
	'pre_get_posts',
	static function ( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->is_post_type_archive( 'gsf_match' ) || $query->is_tax( array( 'gsf_competition', 'gsf_season' ) ) ) {
			$query->set( 'meta_key', '_gsf_ts' );
			$query->set( 'orderby', 'meta_value_num' );
			$query->set( 'order', 'DESC' );
			$query->set( 'posts_per_page', 30 );
		}
		if ( $query->is_post_type_archive( array( 'gsf_club', 'gsf_player' ) ) ) {
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'ASC' );
			$query->set( 'posts_per_page', 48 );
		}
	}
);

/**
 * Build the match title from its clubs ("Jaraaf – Casa Sports").
 *
 * @param int $match_id Match ID.
 * @return string
 */
function gsf_build_match_title( $match_id ) {
	$home = gsf_club( (int) get_post_meta( $match_id, '_gsf_home', true ) );
	$away = gsf_club( (int) get_post_meta( $match_id, '_gsf_away', true ) );
	return $home['name'] . ' – ' . $away['name'];
}

/**
 * Persist the generated title and slug of a match.
 *
 * @param int $match_id Match ID.
 */
function gsf_sync_match_title( $match_id ) {
	$post = get_post( $match_id );
	if ( ! $post || 'gsf_match' !== $post->post_type ) {
		return;
	}
	$title = gsf_build_match_title( $match_id );
	if ( $post->post_title === $title ) {
		return;
	}
	$ts   = (int) get_post_meta( $match_id, '_gsf_ts', true );
	$slug = sanitize_title( $title . ( $ts ? '-' . gmdate( 'Y-m-d', $ts ) : '' ) );
	global $wpdb;
	// Direct update: avoids re-triggering save_post handlers recursively.
	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->posts,
		array(
			'post_title' => $title,
			'post_name'  => wp_unique_post_slug( $slug, $match_id, $post->post_status, 'gsf_match', 0 ),
		),
		array( 'ID' => $match_id )
	);
	clean_post_cache( $match_id );
}
