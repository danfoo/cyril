# School IA — Serveur de licences

Autorité de licences **maison** (éditeur : Maestro Dan) pour les produits School IA.
À installer **sur votre site** (ex. `maestrodan.art`), **jamais** sur le site d'un client.

C'est le « coffre-fort » qui crée, valide, révoque les clés et livre les mises à jour.
Le plugin School IA installé chez le client dialogue avec ce serveur via une API REST.

## Ce qu'il fait

- **Génère des clés** lisibles et uniques (`SIA-XXXX-XXXX-XXXX-XXXX`).
- **Abonnement annuel** : chaque clé porte une date d'expiration (1 an, 2 ans, ou à vie).
- **Verrouillage par domaine** : une clé n'est valable que sur le(s) site(s) autorisé(s)
  (limite d'activations configurable ; ex. 1 clé = 1 site).
- **Révocation** immédiate (ex. remboursement, impayé) et **renouvellement** en un clic (+1 an).
- **Livraison des mises à jour** : vous publiez un `.zip`, les sites sous licence active
  voient « Mettre à jour » dans leur tableau de bord WordPress.

## Installation

1. Installer et activer ce plugin sur votre site éditeur (création des tables).
2. Menu **Licences** dans l'administration.
3. Vérifier que la constante `BEM_LEAD_AI_LICENSE_SERVER` du plugin School IA pointe
   bien vers l'URL de ce site (par défaut `https://maestrodan.art`).

## Vendre une licence (flux type)

1. Le client paie (mobile money, virement, en ligne… peu importe : **vous** encaissez).
2. Dans **Licences → Créer une licence** : nom/email du client, durée (1 an), nombre de
   sites, puis **Générer la clé**.
3. Vous communiquez la clé au client.
4. Le client la saisit dans **School IA → Licence → Activer**. Sa clé se verrouille sur
   son domaine et il reçoit les mises à jour tant que l'abonnement est valide.

## Publier une mise à jour

1. Construire le `.zip` de la nouvelle version de School IA (dossier `bem-lead-ai/`).
2. **Licences → Publier une nouvelle version** : numéro de version (ex. `2.12.0`),
   fichier `.zip`, notes de version.
3. Les sites sous licence active sont notifiés automatiquement (vérification toutes les
   ~6 h côté client, ou « Vérifier à nouveau » sur leur page Extensions).

## API REST (`sia-license/v1`)

| Endpoint | Rôle |
|---|---|
| `POST /activate` | Active une clé sur un domaine (verrouillage + limite) |
| `POST /validate` | Revalide (statut, expiration, révocation) — auto-cicatrisant |
| `POST /deactivate` | Libère l'emplacement d'un domaine |
| `POST /update-check` | Dernière version + lien de paquet si sous licence |
| `GET /download` | Sert le `.zip` si la licence autorise ce domaine |

## Sécurité & principe

- Les clés sont l'unique secret ; les endpoints valident existence → produit → statut →
  expiration → limite d'activations.
- Le paquet de mise à jour n'est servi qu'aux domaines **activés et sous licence valide**.
- Le dossier de stockage des `.zip` (`uploads/sia-licenses/`) est protégé du listing.
- **Côté client**, la dégradation est douce : une licence absente/expirée coupe les
  mises à jour mais ne casse jamais le site et n'efface **jamais** les données.

## Tables créées

- `wp_sia_licenses` — clés, client, statut, expiration, limite d'activations.
- `wp_sia_activations` — couples (licence, domaine) + dernière vérification.
- `wp_sia_releases` — versions publiées (fichier, changelog, compatibilités).
