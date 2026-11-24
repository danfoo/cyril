# Éternelle + Wedding RSVP Pass

Un thème WordPress de mariage animé et un plugin de gestion des invités avec cartons d'invitation à QR code et scanner hors connexion pour le jour J.

```
themes/eternelle/            → le thème
plugins/wedding-rsvp-pass/   → le plugin
dist/*.zip                   → archives prêtes à téléverser (régénérer avec ./build.sh)
```

## Installation

1. **Apparence → Thèmes → Ajouter → Téléverser** : `dist/eternelle.zip`, puis Activer.
2. **Extensions → Ajouter → Téléverser** : `dist/wedding-rsvp-pass.zip`, puis Activer.
3. **Réglages → Permaliens** : choisir « Titre de la publication » puis enregistrer. Les liens `/rsvp/…`, `/invitation/…` et `/wrp-scanner/` en ont besoin.
4. **Utilisateurs → Ajouter** : créer un compte pour chacun des mariés avec le rôle **Marié(e)**.
5. **Invités → Messages & réglages** : associer chaque compte à un prénom, régler la date limite de réponse, les messages et les couleurs du carton.
6. **Apparence → Personnaliser → 💍 Notre mariage** : prénoms, photos, date, lieu, histoire, programme, galerie, musique et effets.
   Les prénoms, la date et le lieu saisis ici servent aussi de valeurs par défaut au plugin, donc pas besoin de les saisir deux fois.

> Le site doit être en **HTTPS** : la caméra du scanner et le mode hors ligne (service worker) l'exigent.

## Le thème « Éternelle »

- **Intro « enveloppe »** avec sceau de cire aux initiales : au clic, le rabat s'ouvre, la carte « Save the date » sort de l'enveloppe et la musique démarre. Elle n'est montrée qu'une fois par visite.
- **Hero animé** :
  - pétales de rose dessinés sur canvas, poussière d'or scintillante et halos de lumière qui suivent la souris ;
  - monogramme doré qui se dessine, prénoms qui apparaissent lettre par lettre, date au reflet doré ;
  - compte à rebours en verre dépoli ;
  - effet Ken Burns et parallaxe sur la photo de couverture.
- Sections : les mariés, notre histoire (frise), programme, lieu (carte + itinéraire), galerie (visionneuse au clavier et au swipe), RSVP (« recevoir à nouveau mon lien »), liste de mariage, pied de page avec cœurs qui montent.
- Petits effets : cœurs au clic, traînée d'étincelles au curseur, lecteur de musique façon vinyle. Chaque effet peut être désactivé, et `prefers-reduced-motion` est respecté.
- Le menu se crée automatiquement à partir des sections. Un menu WordPress classique peut aussi être assigné.

## Le plugin « Wedding RSVP Pass »

### Chaque marié·e gère sa liste
- Le rôle **Marié(e)** ne voit et ne modifie que **ses** invités. L'administrateur (ou le rôle *Organisateur·rice*) voit tout, avec des onglets par liste.
- Tableau de bord : invitations, personnes présentes, en attente, absents, arrivés le jour J, taux de réponse.
- Pour chaque invité : places, groupe, table, régime alimentaire, notes privées. Import et export CSV (compatible Excel).

### Envoi des invitations
- **E-mail** HTML élégant, à l'unité ou en masse.
- **WhatsApp** (lien `wa.me` avec message prérempli et indicatif pays automatique), **SMS** ou **copie du lien**. L'invité est automatiquement marqué « invité ».
- Messages modifiables avec des variables : `{prenom} {maries} {date} {lieu} {places} {date_limite} {lien} {carton} {code} {table}`…

### La page de réponse de l'invité (`/rsvp/{lien-personnel}`)
- Page « papier » avec pétales qui tombent et choix « Avec joie ! » / « À regret ».
- L'invité indique le nombre de personnes, le nom des accompagnants, ses allergies et un petit mot pour les mariés.
- Il peut modifier sa réponse jusqu'à la date limite (option désactivable).

### Le carton d'invitation (`/invitation/{lien-personnel}`)
- Généré dès la confirmation, avec une animation de confettis et de cœurs. Il est aussi envoyé par e-mail.
- Carton nominatif : prénoms des mariés, date, lieu, nom de l'invité et des accompagnants, nombre de places, table, **QR code** et code lisible (ex. `7SAS NCCE`).
- Boutons : **Télécharger en image PNG** (dessinée en haute définition), **Imprimer / PDF** (format A5), **Ajouter au calendrier** (.ics), **Itinéraire**.

### Le jour J : scanner hors connexion (`/wrp-scanner/`)
1. Dans **Invités → Jour J – Scanner**, scannez le premier QR code avec le téléphone d'accueil, puis « Ajouter à l'écran d'accueil ».
2. Activez l'application en scannant le **QR d'activation** (ou en tapant la clé). La liste est alors téléchargée dans le téléphone.
3. Ensuite, **tout fonctionne sans réseau**, même en mode avion :
   - 🟢 **Bienvenue** : nom, accompagnants, nombre de places, table, régime alimentaire ;
   - 🟠 **Déjà validé** : heure du passage, et si c'était ce téléphone ou un autre ;
   - 🔴 **Carton inconnu** ou invité ayant décliné.
4. Un bip et une vibration accompagnent chaque résultat. Le scanner propose aussi une **recherche par nom** (pour l'invité sans téléphone), un historique et un compteur de personnes arrivées.
5. Dès que le réseau revient, les validations sont envoyées au serveur. L'envoi peut être répété sans risque de doublon, et les doubles passages entre plusieurs téléphones sont signalés. Les arrivées s'affichent en direct dans l'admin.

**Sécurité**
- Le téléphone ne stocke que des empreintes SHA-256 des codes, jamais les codes eux-mêmes.
- L'API est protégée par la clé du scanner, avec une limite contre les essais en série. La clé peut être régénérée.
- Les liens personnels sont aléatoires (128 bits) et exclus des moteurs de recherche.

## Livre d'or

- **Page « Livre d'or »** : elle est créée automatiquement (`/livre-d-or/`) et ajoutée au menu automatique.
  - Les invités y signent avec leur nom, leur lien (famille, ami·e…) et leur message.
  - Les vœux validés s'affichent en mur de cartes, avec un bouton « Voir plus ».
- **Carrousel des derniers vœux en bas de l'accueil** :
  - il apparaît dans tous les modes de page d'accueil, Elementor compris ;
  - défilement automatique, flèches, points et glisser au doigt sur mobile ;
  - réglages dans Personnaliser → Notre mariage → « Réponses, livre d'or & liste » (titre, nombre de vœux, afficher ou masquer).
- **Petits mots de la réponse RSVP** : ils sont ajoutés automatiquement au livre d'or (option désactivable).
- **Validation** : par défaut, un message n'apparaît qu'après la validation des mariés. On valide depuis l'application (accueil → « Livre d'or », avec un badge du nombre de messages en attente) ou depuis Invités → Livre d'or dans l'administration. On peut aussi publier directement : Messages & réglages → Livre d'or.
- **Anti-spam** : champ piège invisible pour les robots, délai minimum de saisie, et 5 messages au maximum par heure et par connexion.
- **Shortcodes** : `[wrp_guestbook]` et `[wrp_wishes_carousel count="12"]`.
- **Widgets Elementor** : « Carrousel des vœux (livre d'or) » et « Livre d'or (formulaire + messages) ».

## Application « Espace mariés » (installable sur téléphone)

L'application s'ouvre à l'adresse **`votresite.com/espace-maries/`**. C'est une PWA : elle s'installe sur l'écran d'accueil depuis le navigateur, sans passer par un store.

- **Page de connexion** : avec l'e-mail ou l'identifiant WordPress. On reste connecté sur le téléphone.
- **Mariés** (rôle « Marié(e) »), et aussi l'organisateur·rice et l'administrateur :
  - tableau de bord : compte à rebours, présents, en attente, absents, taux de réponse, derniers petits mots ;
  - liste des invités : recherche, filtres (à inviter, sans réponse, présents, absents, arrivés), onglets par liste pour l'administrateur ;
  - fiche invité : WhatsApp, SMS, e-mail, partager, copier le lien, voir le carton, modifier, supprimer, annuler un passage ;
  - ajout et modification d'invités ;
  - onglet **Équipe** : création d'un accès « Agent d'accueil » en un clic. L'identifiant et le mot de passe s'affichent une fois, avec un message prêt à envoyer par WhatsApp.
- **Équipe d'accueil** (rôle « Agent d'accueil (scan) ») :
  - après connexion, la personne arrive directement sur le scanner, **déjà configuré** (aucune clé à saisir) ;
  - elle n'a aucun accès à la liste des invités ni à l'administration WordPress.
- **Installation** :
  - Android / Chrome : bouton « Installer l'application » ;
  - iPhone : Safari → Partager → « Sur l'écran d'accueil » (instructions dans l'application).
- **Hors connexion** : l'application s'ouvre avec la dernière liste enregistrée, en lecture seule, et le scanner fonctionne entièrement.
  - Conseil : ouvrez le scanner une fois avec du réseau avant le jour J pour télécharger la liste.
- L'ancienne adresse du scanner (`/wrp-scanner/`) redirige vers `/espace-maries/scanner/`.

## Elementor

Le thème est compatible avec Elementor (gratuit) et Elementor Pro.

- **Pages faites avec Elementor** : elles s'affichent en pleine largeur, sans le titre du thème. Elementor contrôle toute la mise en page. Les modèles « Elementor Canvas » et « Elementor pleine largeur » fonctionnent aussi.
- **Elementor Pro, Theme Builder** : les emplacements En-tête, Pied de page, Article et Archive sont déclarés. Un en-tête ou un pied de page créé dans Elementor remplace celui du thème.
- **Page d'accueil** (Personnaliser → Notre mariage → Couleurs & sections → « Page d'accueil ») :
  - *Automatique* : contenu Elementor si la page d'accueil est faite avec Elementor, sinon les sections du thème ;
  - *Hero animé du thème + contenu Elementor* ;
  - *Sections du thème + contenu Elementor en dessous* ;
  - *Sections du thème uniquement* ou *Elementor uniquement*.
- **Widgets « Éternelle – Mariage »** dans le panneau Elementor :
  - Hero animé (pétales, poussière d'or, monogramme, prénoms animés, compte à rebours) ;
  - Compte à rebours (style clair ou verre) ;
  - Titre décoré (police manuscrite + ornement doré) ;
  - Les mariés, Notre histoire, Programme, Lieu, Galerie, RSVP, Liste de mariage ;
  - Retrouver mon invitation (plugin RSVP).

  Leur contenu vient du Customizer : les prénoms, la date et le lieu sont donc les mêmes partout (site, cartons, e-mails).
- **Couleurs et polices** : le thème désactive les couleurs et polices par défaut d'Elementor (bleu, Roboto) pour que les widgets standard prennent celles du thème. Vous pouvez les réactiver dans Elementor → Réglages.
- **Plugin RSVP** : la page de réponse, le carton et le scanner sont des pages autonomes. Elles fonctionnent pareil avec ou sans Elementor.

## Personnalisation avancée
- Pour remplacer les gabarits du plugin, copiez `templates/rsvp.php`, `invitation.php` ou `scanner.php` dans `votre-theme/wedding-rsvp-pass/`.
- Filtres disponibles : `wrp_settings_defaults`, `wrp_slugs` (changer `/rsvp/`, `/invitation/`…), `wrp_fonts_url`, `eternelle_fonts_url`, `eternelle_sections`.
- Shortcodes : `[wrp_find_invitation]`, `[wrp_countdown]`.

## Bibliothèques incluses
- [qrcode-generator](https://github.com/kazuhikoarase/qrcode-generator) (MIT) : génération des QR codes.
- [jsQR](https://github.com/cozmo/jsQR) (Apache-2.0) : lecture des QR codes quand le navigateur ne propose pas `BarcodeDetector`.
