<?php
/**
 * Type de contenu « Cheval », taxonomie « Discipline » et champs de la fiche.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EP_CPT {

	const POST_TYPE = 'ep_cheval';
	const TAXONOMY  = 'ep_discipline';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'                  => __( 'Chevaux', 'ecurie-prestige' ),
					'singular_name'         => __( 'Cheval', 'ecurie-prestige' ),
					'menu_name'             => __( 'Écurie', 'ecurie-prestige' ),
					'all_items'             => __( 'Tous les chevaux', 'ecurie-prestige' ),
					'add_new'               => __( 'Ajouter', 'ecurie-prestige' ),
					'add_new_item'          => __( 'Ajouter un cheval', 'ecurie-prestige' ),
					'edit_item'             => __( 'Modifier le cheval', 'ecurie-prestige' ),
					'new_item'              => __( 'Nouveau cheval', 'ecurie-prestige' ),
					'view_item'             => __( 'Voir le cheval', 'ecurie-prestige' ),
					'search_items'          => __( 'Rechercher un cheval', 'ecurie-prestige' ),
					'not_found'             => __( 'Aucun cheval trouvé', 'ecurie-prestige' ),
					'not_found_in_trash'    => __( 'Aucun cheval dans la corbeille', 'ecurie-prestige' ),
					'featured_image'        => __( 'Photo principale', 'ecurie-prestige' ),
					'set_featured_image'    => __( 'Définir la photo principale', 'ecurie-prestige' ),
					'remove_featured_image' => __( 'Retirer la photo principale', 'ecurie-prestige' ),
					'use_featured_image'    => __( 'Utiliser comme photo principale', 'ecurie-prestige' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-awards',
				'menu_position' => 21,
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'chevaux' ),
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Disciplines', 'ecurie-prestige' ),
					'singular_name' => __( 'Discipline', 'ecurie-prestige' ),
					'all_items'     => __( 'Toutes les disciplines', 'ecurie-prestige' ),
					'edit_item'     => __( 'Modifier la discipline', 'ecurie-prestige' ),
					'add_new_item'  => __( 'Ajouter une discipline', 'ecurie-prestige' ),
					'search_items'  => __( 'Rechercher une discipline', 'ecurie-prestige' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'discipline' ),
			)
		);
	}

	public static function sexes() {
		return array(
			''        => '—',
			'male'    => __( 'Mâle', 'ecurie-prestige' ),
			'hongre'  => __( 'Hongre', 'ecurie-prestige' ),
			'femelle' => __( 'Femelle', 'ecurie-prestige' ),
			'poulain' => __( 'Poulain', 'ecurie-prestige' ),
			'pouliche' => __( 'Pouliche', 'ecurie-prestige' ),
		);
	}

	public static function statuts() {
		return array(
			''             => '—',
			'competition'  => __( 'En compétition', 'ecurie-prestige' ),
			'entrainement' => __( 'À l’entraînement', 'ecurie-prestige' ),
			'espoir'       => __( 'Jeune espoir', 'ecurie-prestige' ),
			'repos'        => __( 'Au repos', 'ecurie-prestige' ),
			'retraite'     => __( 'Retraité', 'ecurie-prestige' ),
			'etalon'       => __( 'Étalon', 'ecurie-prestige' ),
			'pouliniere'   => __( 'Poulinière', 'ecurie-prestige' ),
		);
	}

	/**
	 * Définition des champs, regroupés par section.
	 */
	public static function fields() {
		return array(
			'identite'     => array(
				'label'  => __( 'Identité', 'ecurie-prestige' ),
				'fields' => array(
					'surnom'    => array( 'label' => __( 'Signature / devise', 'ecurie-prestige' ), 'type' => 'text', 'wide' => true, 'placeholder' => __( 'Ex. : Le prince de Longchamp', 'ecurie-prestige' ) ),
					'sexe'      => array( 'label' => __( 'Sexe', 'ecurie-prestige' ), 'type' => 'select', 'options' => self::sexes() ),
					'robe'      => array( 'label' => __( 'Robe', 'ecurie-prestige' ), 'type' => 'text', 'placeholder' => __( 'Bai, alezan, gris…', 'ecurie-prestige' ) ),
					'naissance' => array( 'label' => __( 'Année de naissance', 'ecurie-prestige' ), 'type' => 'number', 'placeholder' => '2021' ),
					'race'      => array( 'label' => __( 'Race', 'ecurie-prestige' ), 'type' => 'text', 'placeholder' => __( 'Pur-sang, AQPS, Trotteur Français…', 'ecurie-prestige' ) ),
					'aptitude'  => array( 'label' => __( 'Aptitude / distance', 'ecurie-prestige' ), 'type' => 'text', 'placeholder' => __( '2 000 – 2 400 m, terrain souple', 'ecurie-prestige' ) ),
					'statut'    => array( 'label' => __( 'Statut', 'ecurie-prestige' ), 'type' => 'select', 'options' => self::statuts() ),
					'vedette'   => array( 'label' => __( 'Cheval vedette', 'ecurie-prestige' ), 'type' => 'checkbox', 'help' => __( 'Mettre ce cheval en avant', 'ecurie-prestige' ) ),
				),
			),
			'pedigree'     => array(
				'label'  => __( 'Pedigree', 'ecurie-prestige' ),
				'fields' => array(
					'pere'      => array( 'label' => __( 'Père', 'ecurie-prestige' ), 'type' => 'text' ),
					'mere'      => array( 'label' => __( 'Mère', 'ecurie-prestige' ), 'type' => 'text' ),
					'pere_mere' => array( 'label' => __( 'Père de mère', 'ecurie-prestige' ), 'type' => 'text' ),
				),
			),
			'entourage'    => array(
				'label'  => __( 'Entourage', 'ecurie-prestige' ),
				'fields' => array(
					'entraineur'   => array( 'label' => __( 'Entraîneur', 'ecurie-prestige' ), 'type' => 'text' ),
					'jockey'       => array( 'label' => __( 'Jockey / driver', 'ecurie-prestige' ), 'type' => 'text' ),
					'proprietaire' => array( 'label' => __( 'Propriétaire', 'ecurie-prestige' ), 'type' => 'text' ),
					'eleveur'      => array( 'label' => __( 'Éleveur', 'ecurie-prestige' ), 'type' => 'text' ),
				),
			),
			'performances' => array(
				'label'  => __( 'Performances', 'ecurie-prestige' ),
				'fields' => array(
					'courses'   => array( 'label' => __( 'Courses courues', 'ecurie-prestige' ), 'type' => 'number' ),
					'victoires' => array( 'label' => __( 'Victoires', 'ecurie-prestige' ), 'type' => 'number' ),
					'places'    => array( 'label' => __( 'Places (podiums)', 'ecurie-prestige' ), 'type' => 'number' ),
					'gains'     => array( 'label' => __( 'Gains (FCFA)', 'ecurie-prestige' ), 'type' => 'number' ),
					'palmares'  => array(
						'label'       => __( 'Palmarès', 'ecurie-prestige' ),
						'type'        => 'textarea',
						'wide'        => true,
						'placeholder' => "2025 | Prix de l’Arc de Triomphe | 1er | ParisLongchamp\n2024 | Grand Prix de Deauville | 2e | Deauville",
						'help'        => __( 'Une ligne par résultat : Année | Course | Classement | Hippodrome (facultatif)', 'ecurie-prestige' ),
					),
				),
			),
			'medias'       => array(
				'label'  => __( 'Médias', 'ecurie-prestige' ),
				'fields' => array(
					'galerie' => array( 'label' => __( 'Galerie photos', 'ecurie-prestige' ), 'type' => 'gallery', 'wide' => true ),
					'video'   => array( 'label' => __( 'Lien vidéo (YouTube, Vimeo, replay…)', 'ecurie-prestige' ), 'type' => 'url', 'wide' => true ),
				),
			),
		);
	}

	public static function flat_fields() {
		$flat = array();
		foreach ( self::fields() as $group ) {
			$flat += $group['fields'];
		}
		return $flat;
	}

	public static function meta( $post_id, $key ) {
		return get_post_meta( $post_id, '_ep_' . $key, true );
	}

	public static function sanitize( $type, $value ) {
		switch ( $type ) {
			case 'number':
				return '' === $value || null === $value ? '' : absint( $value );
			case 'checkbox':
				return $value ? '1' : '';
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'url':
				return esc_url_raw( $value );
			case 'gallery':
				return implode( ',', array_filter( array_map( 'absint', explode( ',', (string) $value ) ) ) );
			default:
				return sanitize_text_field( $value );
		}
	}

	/** Enregistre un tableau clé => valeur de champs (utilisé aussi par l’import de démonstration). */
	public static function update_fields( $post_id, array $values ) {
		foreach ( self::flat_fields() as $key => $field ) {
			$raw   = isset( $values[ $key ] ) ? $values[ $key ] : '';
			$value = self::sanitize( $field['type'], is_string( $raw ) ? wp_unslash( $raw ) : $raw );
			// Les compteurs sont toujours enregistrés pour permettre le tri.
			if ( '' === $value && in_array( $key, array( 'victoires', 'gains', 'courses', 'places' ), true ) ) {
				$value = 0;
			}
			if ( '' === $value ) {
				delete_post_meta( $post_id, '_ep_' . $key );
			} else {
				update_post_meta( $post_id, '_ep_' . $key, $value );
			}
		}
	}

	public static function add_meta_box() {
		add_meta_box( 'ep-fiche', __( 'Fiche du cheval', 'ecurie-prestige' ), array( __CLASS__, 'render_meta_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'ep_save_fiche', 'ep_fiche_nonce' );
		echo '<div class="ep-admin">';
		foreach ( self::fields() as $group ) {
			echo '<fieldset class="ep-admin__group"><legend>' . esc_html( $group['label'] ) . '</legend><div class="ep-admin__grid">';
			foreach ( $group['fields'] as $key => $field ) {
				$id    = 'ep_' . $key;
				$name  = 'ep[' . $key . ']';
				$value = self::meta( $post->ID, $key );
				$ph    = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
				echo '<div class="ep-admin__field' . ( empty( $field['wide'] ) ? '' : ' is-wide' ) . '">';
				if ( 'checkbox' !== $field['type'] ) {
					echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>';
				}
				switch ( $field['type'] ) {
					case 'select':
						echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
						foreach ( $field['options'] as $opt => $label ) {
							echo '<option value="' . esc_attr( $opt ) . '"' . selected( $value, $opt, false ) . '>' . esc_html( $label ) . '</option>';
						}
						echo '</select>';
						break;
					case 'checkbox':
						echo '<label class="ep-admin__check"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( $value, '1', false ) . '> <strong>' . esc_html( $field['label'] ) . '</strong></label>';
						break;
					case 'textarea':
						echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="6" placeholder="' . esc_attr( $ph ) . '">' . esc_textarea( $value ) . '</textarea>';
						break;
					case 'gallery':
						echo '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="ep-gallery-input">';
						echo '<div class="ep-gallery-preview">';
						foreach ( array_filter( explode( ',', (string) $value ) ) as $att ) {
							echo wp_get_attachment_image( (int) $att, 'thumbnail' );
						}
						echo '</div>';
						echo '<button type="button" class="button ep-gallery-pick">' . esc_html__( 'Choisir les photos', 'ecurie-prestige' ) . '</button> ';
						echo '<button type="button" class="button-link ep-gallery-clear">' . esc_html__( 'Vider', 'ecurie-prestige' ) . '</button>';
						break;
					default:
						$type = 'number' === $field['type'] ? 'number' : ( 'url' === $field['type'] ? 'url' : 'text' );
						echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $ph ) . '"' . ( 'number' === $type ? ' min="0" step="1"' : '' ) . '>';
				}
				if ( ! empty( $field['help'] ) && 'checkbox' !== $field['type'] ) {
					echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
				}
				echo '</div>';
			}
			echo '</div></fieldset>';
		}
		echo '</div>';
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST['ep_fiche_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ep_fiche_nonce'] ), 'ep_save_fiche' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- chaque champ est nettoyé selon son type dans update_fields().
		$values = isset( $_POST['ep'] ) && is_array( $_POST['ep'] ) ? $_POST['ep'] : array();
		self::update_fields( $post_id, $values );
	}

	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new['ep_photo'] = __( 'Photo', 'ecurie-prestige' );
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['ep_statut']    = __( 'Statut', 'ecurie-prestige' );
				$new['ep_victoires'] = __( 'Victoires', 'ecurie-prestige' );
			}
		}
		return $new;
	}

	public static function column( $column, $post_id ) {
		if ( 'ep_photo' === $column ) {
			echo get_the_post_thumbnail( $post_id, array( 48, 48 ) );
		} elseif ( 'ep_statut' === $column ) {
			$statuts = self::statuts();
			$statut  = self::meta( $post_id, 'statut' );
			echo esc_html( isset( $statuts[ $statut ] ) ? $statuts[ $statut ] : '—' );
			if ( self::meta( $post_id, 'vedette' ) ) {
				echo ' <span title="' . esc_attr__( 'Cheval vedette', 'ecurie-prestige' ) . '">★</span>';
			}
		} elseif ( 'ep_victoires' === $column ) {
			echo esc_html( (int) self::meta( $post_id, 'victoires' ) . ' / ' . (int) self::meta( $post_id, 'courses' ) );
		}
	}

	public static function title_placeholder( $text, $post ) {
		return self::POST_TYPE === $post->post_type ? __( 'Nom du cheval', 'ecurie-prestige' ) : $text;
	}
}
