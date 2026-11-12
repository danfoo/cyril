=== Écurie Prestige ===
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later

Présentez les chevaux de votre écurie de courses avec des widgets Elementor haut de gamme.

== Description ==

* Type de contenu « Cheval » (menu Écurie) : photo, biographie, identité, pedigree, entourage, performances, palmarès, galerie, vidéo.
* Taxonomie « Disciplines » (Plat, Obstacle, Trot…) avec filtres automatiques.
* 4 widgets Elementor (catégorie « Écurie Prestige ») : Chevaux — Grille (grille ou mosaïque), Chevaux — Carrousel cinéma (cheval central en majesté, défilement automatique, boucle infinie, glisser au doigt), Chevaux — Carrousel, Cheval vedette.
* Fiche détaillée en fenêtre (statistiques animées, caractéristiques, pedigree, palmarès, galerie) et fiche technique ajoutée automatiquement à la page de chaque cheval.
* Thème sombre « noir & or » ou clair « ivoire & or », couleur d’accent, polices, colonnes, format des photos et arrondis réglables dans l’onglet Style.
* Shortcodes [ecurie_chevaux] et [ecurie_cheval] pour une utilisation sans Elementor.
* Aucune dépendance externe : pas de jQuery côté public, police servie localement (pas d’appel à Google Fonts).

== Installation ==

1. Extensions → Ajouter → Téléverser une extension, choisissez le fichier .zip puis activez.
2. Écurie → Guide de démarrage : créez vos chevaux (ou générez 6 chevaux de démonstration).
3. Dans Elementor, recherchez « Écurie » et déposez un widget dans votre page.
4. Si les pages des chevaux affichent une erreur 404 : Réglages → Permaliens → Enregistrer.

== Personnalisation pour développeurs ==

La page d’un cheval utilise le gabarit du plugin (templates/single-cheval.php). Pour le remplacer, créez single-ep_cheval.php dans votre thème, ou désactivez-le : add_filter( 'ecurie_prestige_single_template', '__return_false' ). Un modèle « Single » d’Elementor Pro reste prioritaire.

Filtres : ecurie_prestige_query_args, ecurie_prestige_archive_url, ecurie_prestige_data, ecurie_prestige_single_fiche (false pour désactiver la fiche automatique sur la page d’un cheval), ecurie_prestige_single_theme ('light' ou 'dark').
Variables CSS : --ep-accent, --ep-bg, --ep-surface, --ep-text, --ep-radius, --ep-gap, --ep-ratio, --ep-cols, --ep-font-display, --ep-font-body.

== Crédits ==

Police Cormorant Garamond (Christian Thalmann, Catharsis Fonts) — SIL Open Font License 1.1.

== Changelog ==

= 1.3.0 =
* Page « L’élevage » : 5 widgets Elementor « Page — … » (bandeau titre, mot de la maison, piliers, infrastructures, appel à contact), création de la page en un clic (Écurie → Guide de démarrage), modèle Elementor à importer (templates/elementor/elevage.json) et shortcode [ecurie_elevage].

= 1.2.1 =
* Gains affichés en FCFA (devise modifiable avec le filtre ecurie_prestige_currency).

= 1.2.0 =
* Nouvelle page d’un cheval : bandeau photo pleine largeur, portrait, palmarès, galerie, fiche technique en colonne, navigation précédent / suivant.
* Balisage insensible aux styles de listes des thèmes (libellés, cadres et débordements corrigés).

= 1.1.0 =
* Nouveau widget « Chevaux — Carrousel cinéma » et shortcode [ecurie_chevaux mise_en_page="cinema"].

= 1.0.0 =
* Première version.
