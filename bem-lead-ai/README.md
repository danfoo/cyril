# BEM Lead AI — Conseiller IA & Lead Scoring Comportemental

Plugin WordPress qui transforme le site de **BEM Dakar** en conseiller d'orientation actif : chatbot IA ancré dans le contenu réel des formations (catalogue injecté en contexte et mis en cache LLM), scoring de lead comportemental + intentionnel, triggers CRM automatiques, escalade humaine en temps réel, passerelle WhatsApp click-to-chat, veille concurrentielle et relances auto-apprenantes. Modèles Claude et design du widget entièrement configurables depuis l'admin.

## Ce que fait le plugin

| Capacité | Module |
|---|---|
| Chatbot ancré dans le **catalogue réel** (contenu injecté dans le prompt + mis en cache LLM) | `Chat\ChatOrchestrator`, `Knowledge\KnowledgeBaseBuilder` |
| Tracking comportemental anonyme, rattaché dès qu'un email/téléphone est capturé | `assets/js/widget.js`, `Chat\ChannelAdapter` |
| Score comportemental (règles pondérées + décroissance temporelle + vélocité) | `Scoring\ScoringEngine` |
| Score d'intention multi-signaux — **un seul appel LLM** remonte intention, urgence, concurrent, prix | `Ai\SignalClassifier` |
| Moteur de triggers configurable → actions | `Triggers\TriggerEngine`, `Triggers\ActionRunner` |
| Détection de désengagement (chute d'activité après un pic) | `Scoring\DisengagementDetector` |
| Escalade humaine temps réel + inbox conseiller | `Handoff\HandoffManager`, `Admin\InboxPage` |
| Résumé automatique de conversation + approche recommandée, poussé au CRM | `Ai\Summarizer` |
| Simulateur de financement (déclenché sur sensibilité prix) | `Financing\FinancingSimulator` |
| Veille concurrentielle passive (sans appel LLM supplémentaire) | `Ai\SignalClassifier`, `Admin\CompetitorsPage` |
| Apprentissage des messages de relance (bandit epsilon-greedy) | `Learning\VariantBandit` |
| **Passerelle WhatsApp click-to-chat** (nos numéros, lien wa.me pré-rempli) | `Channels\WhatsAppHandoff` |
| **Design du widget personnalisable** (couleurs, avatar/logo, position) | `Admin\SettingsPage`, `Core\Plugin`, `assets/*` |
| **Sélection des modèles Claude** + lien vers la console Anthropic | `Admin\SettingsPage`, `Core\Options` |
| Extension post-inscription (bascule base de connaissance onboarding) | webhook `/crm-status-webhook` |
| Connecteurs CRM : Perfex (prioritaire), HubSpot (optionnel) | `Crm\*` |
| **Assistant de configuration** en 4 étapes au premier lancement (réexécutable) | `Admin\SetupWizard` |
| **Export des leads** en CSV (Excel-compatible, BOM UTF-8) et **XLSX natif**, filtres conservés | `Admin\LeadExporter` |
| **Licence & mises à jour automatiques** (abonnement annuel, serveur maison, dégradation douce) | `License\LicenseClient`, `License\Updater`, `License\LicensePage` |
| **Notifications e-mail** HTML brandées (lead chaud, escalade, rappels de tâches) + **e-mail de test** + interrupteurs par événement + Slack | `Notifications\Mailer` |

## Architecture (résumé)

```
Visiteur web
   │ tracking / messages
   ▼
Plugin « BEM Lead AI »
   ├─ Behavior Tracker (REST /track)
   ├─ Chat Orchestrator (catalogue en contexte + cache LLM + handoff humain)
   │        └─ Knowledge Base Builder (catalogue figé, reconstruit sur save_post)
   ├─ Lead Scoring & Signal Engine (règles + 1 appel LLM multi-signaux)
   ├─ Trigger / Event Engine ── file asynchrone (Action Scheduler)
   │     └─ Perfex · HubSpot · Email/Slack · Escalade · Résumé · Simulateur · Bandit
   ├─ WhatsApp Handoff (bouton → wa.me vers nos numéros)
   └─ Veille concurrentielle (table dédiée + rapport admin)
              │
              ▼
        Claude API (chat + classification + résumés)
```

### Pourquoi un catalogue caché plutôt que du RAG ?

À l'échelle de BEM (catalogue de quelques dizaines de formations, largement sous la
fenêtre de 1M tokens de Claude Sonnet), on injecte **tout le contenu officiel** dans
le prompt système et on le met en cache côté LLM (**prompt caching** : écriture 1,25–2×,
lecture ~0,1× le prix input). Résultat : **aucune infra externe** (ni embeddings, ni
vector store), contenu **toujours à jour** (reconstruit à chaque modification de page),
**zéro risque de "chunk manqué"**, et réponses plus cohérentes. Le RAG reste la voie de
montée en charge (multilingue / multi-campus) — voir « À revisiter en cas de montée en charge ».

## Logique marketing encodée par défaut

- **Pondération = intensité du signal d'achat** : consulter les frais (8) ou cliquer « candidater » (12) pèse bien plus qu'une page vue (1).
- **Récence** : décroissance exponentielle (demi-vie 7 j) — un pic ancien vaut moins qu'une activité récente.
- **L'intention prime sur le comportement** dans le score final (poids 0,55) : « comment candidater ? » est plus fort que 10 pages vues.
- **Anti-harcèlement** : chaque trigger a un cooldown par lead.
- **Le désengagement est une opportunité** : on relance au moment exact où le lead décroche, avant qu'il ne parte à la concurrence.
- **Le prix est traité comme un frein, pas un tabou** : la sensibilité prix déclenche une proposition de solutions (paiement échelonné, bourses).

## Installation

1. Copier le dossier `bem-lead-ai/` dans `wp-content/plugins/` et activer le plugin (création automatique des tables + valeurs par défaut + première construction du catalogue).
2. **Assistant de configuration** : à la première activation, le plugin ouvre automatiquement un assistant en 4 étapes (établissement + clé IA → WhatsApp → widget → catalogue) pour être opérationnel en quelques minutes. Réexécutable à tout moment via le menu **School IA → Assistant de configuration**. L'assistant ne dispense pas de la page de réglages complète ci-dessous.
3. **Réglages → School IA** : renseigner la clé API Anthropic (lien direct vers `console.anthropic.com`), choisir les **modèles Claude** (conseiller / classification), l'email admissions.
3. Le catalogue se reconstruit tout seul à chaque modification de page ; bouton **Reconstruire le catalogue maintenant** disponible au besoin.
4. **WhatsApp** : activer la passerelle et lister vos numéros (`Label|indicatif+numéro|formation`). Aucune API WhatsApp Business requise.
5. **Design** : couleurs, avatar/logo (sélecteur média WordPress), position du widget.
6. (Optionnel) Configurer Perfex / HubSpot — voir ci-dessous.
7. Recommandé : installer le plugin **Action Scheduler** pour une file asynchrone robuste (le plugin retombe sinon sur WP-Cron).

## Endpoints REST (`bem-lead-ai/v1`)

| Endpoint | Rôle |
|---|---|
| `POST /chat` | Message web, réponse du conseiller IA |
| `GET /messages` | Polling widget (relances proactives, réponses conseiller) |
| `POST /track` | Événement comportemental (soumis au consentement) |
| `POST /whatsapp-link` | Lien wa.me pré-rempli vers un de nos numéros (trace le clic = forte intention) |
| `GET /lead/{session_id}` | Profil et scores (admin) |
| `POST /rebuild-kb` | Reconstruction du catalogue en contexte (admin) |
| `POST /handoff/{lead_id}/reply` | Réponse conseiller depuis l'inbox |
| `POST /crm-status-webhook` | Retour CRM « inscrit » → bascule onboarding |
| `GET /financing/options`, `POST /financing/simulate` | Simulateur de financement |

## Sécurité & conformité (loi n°2008-12, CDP Sénégal)

- Consentement explicite avant tout tracking comportemental (bandeau).
- Clés API chiffrées au repos (libsodium, clé dérivée des salts WP) — jamais en clair, jamais renvoyées au navigateur.
- Droit à l'oubli : suppression d'un lead + tout son historique (web + WhatsApp), branché sur l'outil natif WordPress et disponible depuis la fiche lead.
- Signature vérifiée sur le webhook retour CRM (secret partagé `X-Bem-Secret`).
- Rate limiting sur `/chat`, `/track`, `/whatsapp-link`.

## Stack

PHP 8+ · Action Scheduler (file) · Claude API (modèles sélectionnables : Sonnet/Opus pour le chat, Haiku pour la classification) · **prompt caching** pour le catalogue en contexte (aucun vector store) · WhatsApp click-to-chat (wa.me) · JS vanilla (widget, design personnalisable).

## Notes d'exploitation

- **WhatsApp** : passerelle « click-to-chat » — le prospect est redirigé vers un de vos numéros via un lien wa.me pré-rempli, aucune validation Meta ni API Business requise. Un conseiller doit répondre sur le(s) numéro(s) configuré(s).
- **Escalade humaine** : suppose une inbox conseiller surveillée activement par l'équipe admissions (engagement opérationnel autant que technique).
- **Bandit** : tourne en mode exploration tant que le volume est faible ; passage à Thompson sampling à envisager avec du volume.
- **Montée en charge** : déporter chat + scoring vers un microservice si le volume l'exige ; cache des réponses fréquentes pour réduire les coûts LLM.
