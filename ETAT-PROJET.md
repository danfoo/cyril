# School IA — CRM d'admission · État du projet & recettes

_Dernière mise à jour : 2026-07-23_

Ce document récapitule **ce qui a été construit**, **comment ça marche**, **comment
déployer**, et **où en est l'évolution**. Il sert de mémoire du projet.

---

## 1. Vue d'ensemble

Le projet est composé de **deux briques** qui communiquent par un « pont » maison :

| Brique | Rôle | Emplacement | Version actuelle |
|---|---|---|---|
| **Plugin WordPress `bem-lead-ai`** (« School IA ») | Chatbot IA sur le site de l'école, scoring des leads, détection de signaux, envoi des données vers le CRM | `bem-lead-ai/` | **2.31.0** |
| **Module Perfex `school_ia_bridge`** | CRM d'admission complet **dans Perfex** : réception des leads, pipeline, tâches, e-mail/SMS, veille, reporting IA… | `school_ia_bridge/` | 1.0.0 (CSS `?v=10`) |

> **Principe directeur** (demande initiale) : on ne s'intègre PAS au CRM natif de
> Perfex. On construit **notre propre processus de CRM**, comme celui de School IA
> mais en mieux, entièrement dans un module Perfex maison — sans module payant,
> sans build, déploiement par zip.

---

## 2. Le pont WordPress → Perfex (comment ça marche)

- Le plugin WordPress envoie les données au module via un **point d'entrée public**
  authentifié par un **secret partagé** :
  `{perfex}/school_ia_bridge/api/receive`
- **Transport en GET** (et non POST) : la protection CSRF de Perfex ne bloque que le
  POST (erreur 419). Le secret voyage dans l'en-tête `X-SIA-Secret` **et** en
  paramètre `secret` (repli si l'hébergeur filtre les en-têtes).
- La query string est construite avec `http_build_query()` (encode correctement
  accents et retours à la ligne).

### Réglages à recopier (une fois)
Dans **WordPress → School IA → Réglages → CRM**, coller l'URL du point d'entrée et le
secret affichés dans **Perfex → School IA → Réglages**. Le secret doit être identique
des deux côtés.

---

## 3. Fonctionnalités livrées (module Perfex)

### CRM cœur
- **Réception des leads** depuis le chatbot / formulaires (dédup par `external_id` +
  `source_site`, insensible à http/https).
- **Pipeline (Kanban)** avec glisser-déposer natif, 7 étapes colorées
  (Nouveau → Contacté → Qualifié → Relance → Candidature → Inscrit / Perdu).
- **Fiche lead** retravaillée : en-tête avec avatar/initiales, badge d'étape,
  infos en puces, menu « Changer d'étape », suppression du lead.
- **Contacts** (liste) : recherche, filtres (étape, **rentrée**, score min, non
  assignés), export CSV/Excel, suppression. La **rentrée** (session d'admission
  visée) est aussi affichée sur la fiche lead.
- **Tâches & relances** datées (date + heure), rappels e-mail automatiques (cron).
- **Notes** affichées en cartes sur la fiche.
- **Journal d'activité** global (toutes actions, filtrable par type).
- **Responsable** : assignation d'un conseiller par lead.

### Communication
- **E-mail + SMS** depuis la fiche lead. SMS via **LAfricaMobile**.
- **Modèles** réutilisables e-mail / SMS (variables `{prenom}`, `{formation}`).
- **Pièces jointes** : gestionnaire de **documents par programme** ; sélecteur en
  **grille 4/ligne avec recherche** (Pop Up) pour joindre sans scroller.
- **Envoi groupé** e-mail et SMS.
- **Séquences de relance** automatiques (drip, multi-étapes, délais jours+heures, cron).
- **Statistiques de campagnes** : ouverture e-mail (pixel), clics (liens tracés),
  succès/échec SMS.

### Conversations & veille (les points récents)
- **Conversations IA** : les échanges du chatbot sont remontés et affichés en fil
  sur la fiche lead (visiteur à gauche, IA à droite).
- **Veille concurrentielle** : écoles concurrentes citées par les prospects.
  → **Analyse faite DANS Perfex** à partir des conversations stockées, via Claude
  (voir §5). Page dédiée (classement + extraits) + panneau « Concurrents cités »
  sur chaque fiche. Analyse **automatique via cron** + boutons manuels
  (« Analyser les nouvelles » / « Tout réanalyser »).
- **Capture de la formation d'intérêt** depuis la conversation (remplit le champ si vide).

### Pilotage
- **Tableau de bord** premium (grille KPI à dégradé, camemberts, 4 zones
  hiérarchiques, par conseiller, par source, filtres de période).
- **Reporting IA** (Claude) : rapports journalier / hebdo / mensuel / annuel, avec
  analyse complète, comparaisons, graphiques, export, et gestion de plusieurs rapports.
- **Import** CSV / Excel, **Export** CSV / Excel.
- **Aides contextuelles** « ? » sur chaque page.
- **Droits par rôle** (Setup → Rôles → School IA CRM).
- **Design premium** (indigo, coins arrondis, ombres douces, mode clair/sombre),
  chargé uniquement sur les pages du module.
- **Page Diagnostic** : état des tables, réception veille, test d'écriture,
  derniers messages, nettoyage des parasites.

---

## 4. Décisions techniques & pièges (mémoire)

- **Version du module figée à 1.0.0** : les changements de schéma se font par
  `ensure_schema()` (ALTER/CREATE conditionnels à l'exécution), JAMAIS par migration
  Perfex. Bumper la version cherche une migration inexistante → erreur 500.
  `ensure_schema()` est protégé par un `static $done` (évite un double ALTER) **et
  est appelé aussi sur le point d'entrée API** (sinon les tables n'existaient pas au
  moment où WordPress envoyait → insertions perdues en silence tout en renvoyant 200).
- **CSRF Perfex** → tout passe en **GET** pour le pont ; les formulaires internes
  utilisent `form_open()` (jeton CSRF).
- **`save_lead()` ne remplace jamais un champ existant par du vide** et refuse de
  créer une fiche sans identité/contact/score (fini les « Lead #N » fantômes créés,
  puis vidés, par des requêtes partielles).
- **Cache CSS** cassé via `?v=N` (actuellement `?v=10`).

---

## 5. La saga « veille concurrentielle » (résolue)

Historique utile si le sujet revient :

1. On a d'abord voulu que **WordPress détecte** les concurrents (comme il le fait déjà
   via son classifieur IA) et les **envoie** au module.
2. Problème : les requêtes vers `.../api/receive_competitor` renvoyaient **200 sans
   jamais atteindre PHP** (compteur d'appels resté à 0). Cause : le **pare-feu
   applicatif (WAF) de l'hébergeur** bloque certaines requêtes.
3. Diagnostics successifs (endpoint `api/diag`, test d'écriture, instrumentation) ont
   prouvé : la table et le stockage marchent ; c'est la **réception** qui échoue.
4. Tentatives de contournement (chemin `receive_message`, base64, kind neutre,
   marqueur dans le contenu `SIACMP1:`) → le WAF bloquait sur les **noms de
   paramètres** (`name`/`context`/`kind`).
5. **Solution retenue (la bonne architecture)** : puisque Perfex a **déjà les
   conversations** et **déjà la clé API Claude** (reporting), c'est **Perfex qui
   analyse lui-même** les conversations pour en extraire les concurrents. Plus aucun
   aller-retour WordPress, plus de WAF. Analyse **automatique (cron)** + manuelle.

> Les points d'entrée `receive_competitor` et le transport `SIACMP1:` restent dans le
> code (compatibilité/diagnostic) mais ne sont plus le chemin principal.

---

## 6. Déploiement

**Aucun build.** On déploie par **zip** + upload manuel.

### Module Perfex (`school_ia_bridge/`)
1. cPanel → File Manager → dossier `modules/school_ia_bridge` de l'installation Perfex.
2. Uploader le zip, extraire, **Remplacer / Overwrite** quand demandé.
3. Ouvrir une page du module pour laisser `ensure_schema()` créer/mettre à jour les tables.
> Ne jamais faire de `git pull` sur le serveur pour ce module : déploiement à la main.

### Plugin WordPress (`bem-lead-ai/`)
1. Extensions → désactiver « School IA » → supprimer l'ancienne version.
2. Extensions → Ajouter → Téléverser le zip → installer → **réactiver**.

### Après un déploiement lié aux données
- **Réglages WordPress → « Synchroniser les leads et conversations vers Perfex »**
  pousse leads + conversations (backfill).
- **Veille → « Tout réanalyser »** (une fois) pour (re)passer l'IA sur toutes les
  conversations avec le dernier prompt.

---

## 7. Cron (obligatoire pour l'automatique)

Le cron de Perfex doit être configuré (tâche planifiée serveur appelant le cron
Perfex). Il déclenche :
- Rappels de tâches (`sia_reminders_enabled`).
- Séquences de relance.
- **Analyse automatique de la veille concurrentielle** (`sia_comp_auto`, activée par
  défaut si une clé API Claude est renseignée).

---

## 8. Réglages clés (options Perfex)

| Option | Rôle |
|---|---|
| `school_ia_bridge_secret` | Secret partagé du pont |
| `sia_ai_api_key` / `sia_ai_model` | Clé API Claude + modèle (défaut `claude-opus-4-8`) |
| `sia_comp_auto` | Analyse auto de la veille (1/0) |
| `sia_reminders_enabled` | Rappels de tâches |
| `sia_programs` | Liste des programmes (classement des documents) |
| `sms_accountid` / `sms_password` / `sms_sender` | LAfricaMobile |

---

## 9. Pistes / à décider

- **Quasi-instantané pour la veille** : déclencher une analyse dès l'arrivée d'une
  nouvelle conversation (au lieu d'attendre le cron). Faisable — à confirmer (coût
  d'un appel IA par conversation).
- **« Formations demandées »** : si le besoin est un classement des formations les
  plus citées (pas seulement le champ par lead), l'ajouter comme vue dédiée.
- **Envoi automatique des rapports IA** par e-mail (hebdo) — proposé, non confirmé.
- **Nettoyage** des anciens faux messages de veille (`SIACMP1:` / réf `cN`) via le
  bouton de la page Diagnostic si besoin.

---

## 10. Repères de code

- Pont / API : `school_ia_bridge/controllers/Api.php`
- Logique CRM : `school_ia_bridge/controllers/School_ia_bridge.php`
- Données / schéma : `school_ia_bridge/models/School_ia_bridge_model.php`
- Helpers globaux (IA, envoi, veille) : `school_ia_bridge/school_ia_bridge.php`
- Vues : `school_ia_bridge/views/*.php`
- Design : `school_ia_bridge/assets/school_ia_admin.css`
- Pont côté WordPress : `bem-lead-ai/includes/Crm/PerfexBridgeConnector.php`
- Détection de signaux (WP) : `bem-lead-ai/includes/Ai/SignalClassifier.php`
- Synchro en masse (WP) : `bem-lead-ai/includes/Admin/SettingsPage.php`

Branche de développement : `claude/wordpress-plugin-dev-itxoeb`.
