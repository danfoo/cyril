<?php
/**
 * Administration : styles de la fiche, sélecteur de galerie, page d’aide et chevaux de démonstration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EP_Admin {

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_ep_import_demo', array( __CLASS__, 'import_demo' ) );
	}

	public static function assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || EP_CPT::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'ecurie-prestige-admin', EP_URL . 'assets/css/admin.css', array(), EP_VERSION );
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'ecurie-prestige-admin', EP_URL . 'assets/js/admin.js', array( 'jquery' ), EP_VERSION, true );
			wp_localize_script(
				'ecurie-prestige-admin',
				'epAdmin',
				array(
					'title'  => __( 'Galerie du cheval', 'ecurie-prestige' ),
					'button' => __( 'Utiliser ces photos', 'ecurie-prestige' ),
				)
			);
		}
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . EP_CPT::POST_TYPE,
			__( 'Guide de démarrage', 'ecurie-prestige' ),
			__( 'Guide de démarrage', 'ecurie-prestige' ),
			'edit_posts',
			'ep-guide',
			array( __CLASS__, 'page' )
		);
	}

	public static function page() {
		$count = wp_count_posts( EP_CPT::POST_TYPE );
		$total = isset( $count->publish ) ? (int) $count->publish : 0;
		?>
		<div class="wrap ep-guide">
			<h1><?php esc_html_e( 'Écurie Prestige — Guide de démarrage', 'ecurie-prestige' ); ?></h1>
			<?php if ( isset( $_GET['ep_imported'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success"><p><?php echo esc_html( sprintf( /* translators: %d : nombre de chevaux créés */ __( '%d chevaux de démonstration ont été créés. Ajoutez-leur une photo principale pour un rendu optimal.', 'ecurie-prestige' ), absint( $_GET['ep_imported'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification ?></p></div>
			<?php endif; ?>

			<div class="ep-guide__cols">
				<div class="card">
					<h2>1. <?php esc_html_e( 'Créez vos chevaux', 'ecurie-prestige' ); ?></h2>
					<p><?php esc_html_e( 'Dans Écurie → Ajouter : nom, photo principale (portrait de préférence, 1 200 × 1 600 px minimum), biographie, puis la « Fiche du cheval » (identité, pedigree, entourage, performances, palmarès, galerie).', 'ecurie-prestige' ); ?></p>
					<p><?php esc_html_e( 'Classez-les par discipline (Plat, Obstacle, Trot…) : les filtres s’affichent automatiquement. L’ordre d’affichage se règle avec le champ « Ordre » (Attributs de page).', 'ecurie-prestige' ); ?></p>
					<p>
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . EP_CPT::POST_TYPE ) ); ?>"><?php esc_html_e( 'Ajouter un cheval', 'ecurie-prestige' ); ?></a>
					</p>
					<?php if ( current_user_can( 'publish_posts' ) ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="ep_import_demo">
							<?php wp_nonce_field( 'ep_import_demo' ); ?>
							<p>
								<button class="button"><?php esc_html_e( 'Créer 6 chevaux de démonstration', 'ecurie-prestige' ); ?></button>
								<span class="description"><?php echo esc_html( sprintf( /* translators: %d : nombre de chevaux publiés */ __( '(%d cheval(aux) publié(s) actuellement)', 'ecurie-prestige' ), $total ) ); ?></span>
							</p>
						</form>
					<?php endif; ?>
				</div>

				<div class="card">
					<h2>2. <?php esc_html_e( 'Avec Elementor', 'ecurie-prestige' ); ?></h2>
					<p><?php esc_html_e( 'Dans l’éditeur Elementor, recherchez « Écurie » : quatre widgets sont disponibles dans la catégorie « Écurie Prestige ».', 'ecurie-prestige' ); ?></p>
					<ul class="ul-disc">
						<li><strong><?php esc_html_e( 'Chevaux — Grille', 'ecurie-prestige' ); ?></strong> : <?php esc_html_e( 'grille ou mosaïque, filtres par discipline, fiche en fenêtre.', 'ecurie-prestige' ); ?></li>
						<li><strong><?php esc_html_e( 'Chevaux — Carrousel cinéma', 'ecurie-prestige' ); ?></strong> : <?php esc_html_e( 'le grand spectacle : cheval central en majesté, défilement automatique, boucle infinie.', 'ecurie-prestige' ); ?></li>
						<li><strong><?php esc_html_e( 'Chevaux — Carrousel', 'ecurie-prestige' ); ?></strong> : <?php esc_html_e( 'défilement horizontal fluide, idéal en page d’accueil.', 'ecurie-prestige' ); ?></li>
						<li><strong><?php esc_html_e( 'Cheval vedette', 'ecurie-prestige' ); ?></strong> : <?php esc_html_e( 'grande mise en avant d’un champion avec ses statistiques.', 'ecurie-prestige' ); ?></li>
					</ul>
					<p><?php esc_html_e( 'Couleur d’accent, thème sombre ou clair, typographies, colonnes, format des photos : tout se règle dans l’onglet Style.', 'ecurie-prestige' ); ?></p>
				</div>

				<div class="card">
					<h2>3. <?php esc_html_e( 'Sans Elementor : les shortcodes', 'ecurie-prestige' ); ?></h2>
					<p><code>[ecurie_chevaux]</code></p>
					<p><code>[ecurie_chevaux mise_en_page="mosaique" theme="clair" colonnes="3" titre="Nos champions" surtitre="L’écurie"]</code></p>
					<p><code>[ecurie_chevaux mise_en_page="cinema" duree="6"]</code></p>
					<p><code>[ecurie_chevaux mise_en_page="carrousel" discipline="plat" tri="victoires"]</code></p>
					<p><code>[ecurie_cheval id="123" clic="fiche"]</code></p>
					<p class="description"><?php esc_html_e( 'Options : mise_en_page (grille, mosaique, carrousel, cinema), defilement_auto, duree, style (prestige, editorial), theme (sombre, clair), fond, colonnes, nombre, discipline, vedettes, ids, filtres, stats, tri (ordre, nom, date, victoires, gains, aleatoire), clic (fiche, page, aucun), surtitre, titre, intro, alignement.', 'ecurie-prestige' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	public static function import_demo() {
		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'ecurie-prestige' ) );
		}
		check_admin_referer( 'ep_import_demo' );

		$year  = (int) wp_date( 'Y' );
		$demo  = array(
			array( 'Aube Impériale', 'Plat', 'Portée par un changement de vitesse foudroyant, Aube Impériale s’est imposée comme la tête d’affiche de l’écurie sur les distances classiques. Une jument généreuse, d’un calme olympien au rond de présentation et intraitable dans la ligne droite.', array( 'surnom' => 'La reine de la ligne droite', 'sexe' => 'femelle', 'robe' => 'Bai brun', 'naissance' => $year - 4, 'race' => 'Pur-sang', 'aptitude' => '2 000 – 2 400 m, terrain bon à souple', 'statut' => 'competition', 'vedette' => '1', 'pere' => 'Soleil d’Orient', 'mere' => 'Belle Aurore', 'pere_mere' => 'Grand Vizir', 'entraineur' => 'A. de Montfort', 'jockey' => 'L. Castel', 'proprietaire' => 'Écurie Prestige', 'eleveur' => 'Haras des Tilleuls', 'courses' => 14, 'victoires' => 8, 'places' => 4, 'gains' => 25000000, 'palmares' => ( $year - 1 ) . " | Grand Prix de l’Écurie | 1re | Chantilly\n" . ( $year - 1 ) . " | Prix des Lys | 1re | ParisLongchamp\n" . ( $year - 1 ) . " | Prix de la Forêt Royale | 2e | Deauville\n" . ( $year - 2 ) . ' | Critérium des Pouliches | 1re | Saint-Cloud' ) ),
			array( 'Vent du Large', 'Plat', 'Sprinter né, Vent du Large jaillit des stalles comme personne. Son courage dans les arrivées serrées en fait un favori du public.', array( 'surnom' => 'L’éclair normand', 'sexe' => 'male', 'robe' => 'Alezan', 'naissance' => $year - 5, 'race' => 'Pur-sang', 'aptitude' => '1 000 – 1 400 m', 'statut' => 'competition', 'pere' => 'Cap Horn', 'mere' => 'Marée Haute', 'pere_mere' => 'Neptune', 'entraineur' => 'A. de Montfort', 'jockey' => 'M. Ferrand', 'proprietaire' => 'Écurie Prestige', 'courses' => 22, 'victoires' => 9, 'places' => 7, 'gains' => 9700000, 'palmares' => ( $year - 1 ) . " | Prix du Petit Couvert | 1er | ParisLongchamp\n" . ( $year - 2 ) . ' | Prix de Meautry | 3e | Deauville' ) ),
			array( 'Roc d’Ébène', 'Obstacle', 'Sauteur d’exception, Roc d’Ébène conjugue puissance et précision. À l’aise sur les parcours les plus exigeants, il n’a jamais chuté en compétition.', array( 'surnom' => 'Le maître d’Auteuil', 'sexe' => 'hongre', 'robe' => 'Noir pangaré', 'naissance' => $year - 8, 'race' => 'AQPS', 'aptitude' => '3 600 – 4 400 m, steeple-chase', 'statut' => 'competition', 'vedette' => '1', 'pere' => 'Roc Royal', 'mere' => 'Nuit d’Ébène', 'pere_mere' => 'Sultan Noir', 'entraineur' => 'G. Lemarchand', 'jockey' => 'T. Beaumont', 'proprietaire' => 'Écurie Prestige', 'courses' => 31, 'victoires' => 12, 'places' => 11, 'gains' => 18250000, 'palmares' => ( $year - 1 ) . " | Grand Steeple des Haies Vives | 1er | Auteuil\n" . ( $year - 2 ) . " | Prix du Président | 2e | Auteuil\n" . ( $year - 3 ) . ' | Prix des Drags d’Or | 1er | Auteuil' ) ),
			array( 'Étoile de Jade', 'Obstacle', 'Une jument de haies légère et rapide, révélée la saison dernière. Sa marge de progression laisse entrevoir de très belles échéances.', array( 'surnom' => 'La grâce en mouvement', 'sexe' => 'femelle', 'robe' => 'Gris pommelé', 'naissance' => $year - 5, 'race' => 'AQPS', 'aptitude' => '3 500 – 3 900 m, haies', 'statut' => 'entrainement', 'pere' => 'Jade Empire', 'mere' => 'Étoile du Soir', 'pere_mere' => 'Sirius', 'entraineur' => 'G. Lemarchand', 'jockey' => 'C. Roussel', 'proprietaire' => 'Écurie Prestige', 'courses' => 9, 'victoires' => 3, 'places' => 4, 'gains' => 2950000, 'palmares' => ( $year - 1 ) . ' | Prix des Belles Haies | 1re | Compiègne' ) ),
			array( 'Fakir du Bocage', 'Trot', 'Trotteur régulier et dur à l’effort, Fakir du Bocage brille aussi bien à l’attelé qu’au monté. Un métronome sur la cendrée.', array( 'surnom' => 'Le métronome', 'sexe' => 'male', 'robe' => 'Bai', 'naissance' => $year - 7, 'race' => 'Trotteur Français', 'aptitude' => '2 100 – 2 850 m, attelé', 'statut' => 'competition', 'pere' => 'Prince du Bocage', 'mere' => 'Fée des Champs', 'pere_mere' => 'Orage', 'entraineur' => 'P. Duvallon', 'jockey' => 'P. Duvallon', 'proprietaire' => 'Écurie Prestige', 'courses' => 48, 'victoires' => 15, 'places' => 19, 'gains' => 12500000, 'palmares' => ( $year - 1 ) . " | Prix de la Cendrée | 1er | Vincennes\n" . ( $year - 1 ) . ' | Grand Prix du Bocage | 2e | Caen' ) ),
			array( 'Premier Matin', 'Plat', 'Le benjamin de l’écurie. Un poulain au modèle remarquable et à l’action déliée, qui fera ses débuts cette saison.', array( 'surnom' => 'L’avenir en marche', 'sexe' => 'poulain', 'robe' => 'Alezan brûlé', 'naissance' => $year - 2, 'race' => 'Pur-sang', 'aptitude' => '1 600 m et plus', 'statut' => 'espoir', 'pere' => 'Soleil d’Orient', 'mere' => 'Douce Matinée', 'pere_mere' => 'Horizon', 'entraineur' => 'A. de Montfort', 'proprietaire' => 'Écurie Prestige', 'eleveur' => 'Haras des Tilleuls' ) ),
		);

		$created = 0;
		foreach ( $demo as $i => $horse ) {
			list( $name, $discipline, $bio, $fields ) = $horse;
			$post_id = wp_insert_post(
				array(
					'post_type'    => EP_CPT::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $name,
					'post_content' => $bio,
					'menu_order'   => $i,
				)
			);
			if ( ! $post_id || is_wp_error( $post_id ) ) {
				continue;
			}
			EP_CPT::update_fields( $post_id, $fields );
			wp_set_object_terms( $post_id, $discipline, EP_CPT::TAXONOMY );
			$created++;
		}

		wp_safe_redirect( add_query_arg( array( 'post_type' => EP_CPT::POST_TYPE, 'page' => 'ep-guide', 'ep_imported' => $created ), admin_url( 'edit.php' ) ) );
		exit;
	}
}
