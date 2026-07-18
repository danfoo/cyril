# BEM Lead AI — Conseiller IA & Lead Scoring Comportemental

Plugin WordPress qui transforme le site de **BEM Dakar** en conseiller d'orientation actif, sur le web **et** sur WhatsApp : chatbot IA ancré dans le contenu réel des formations (RAG), scoring de lead comportemental + intentionnel, triggers CRM automatiques, escalade humaine en temps réel, veille concurrentielle et relances auto-apprenantes.

## Ce que fait le plugin

| Capacité | Module |
|---|---|
| Chatbot RAG (web + WhatsApp), réponses ancrées dans le contenu officiel | `Chat\ChatOrchestrator`, `Rag\*` |
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
| Extension post-inscription (bascule base de connaissance onboarding) | webhook `/crm-status-webhook` |
| Connecteurs CRM : Perfex (prioritaire), HubSpot (optionnel) | `Crm\*` |

## Architecture (résumé)

```
Visiteur (Web ou WhatsApp)
   │ tracking / messages
   ▼
Plugin « BEM Lead AI »
   ├─ Behavior Tracker (REST /track)
   ├─ Channel Adapter (unifie web + WhatsApp → session_id unique)
   ├─ Chat Orchestrator (RAG + handoff humain)  ── Vector Store (Qdrant)
   ├─ Lead Scoring & Signal Engine (règles + 1 appel LLM multi-signaux)
   ├─ Trigger / Event Engine ── file asynchrone (Action Scheduler)
   │     └─ Perfex · HubSpot · Email/Slack · Escalade · Résumé · Simulateur · Bandit
   └─ Veille concurrentielle (table dédiée + rapport admin)
              │
              ▼
        Claude API (chat + classification + résumés)
```

## Logique marketing encodée par défaut

- **Pondération = intensité du signal d'achat** : consulter les frais (8) ou cliquer « candidater » (12) pèse bien plus qu'une page vue (1).
- **Récence** : décroissance exponentielle (demi-vie 7 j) — un pic ancien vaut moins qu'une activité récente.
- **L'intention prime sur le comportement** dans le score final (poids 0,55) : « comment candidater ? » est plus fort que 10 pages vues.
- **Anti-harcèlement** : chaque trigger a un cooldown par lead.
- **Le désengagement est une opportunité** : on relance au moment exact où le lead décroche, avant qu'il ne parte à la concurrence.
- **Le prix est traité comme un frein, pas un tabou** : la sensibilité prix déclenche une proposition de solutions (paiement échelonné, bourses).

## Installation

1. Copier le dossier `bem-lead-ai/` dans `wp-content/plugins/` et activer le plugin (création automatique des tables + valeurs par défaut).
2. **Réglages → BEM Lead AI** : renseigner la clé API Anthropic, les embeddings, l'URL/clé Qdrant, l'email admissions.
3. Cliquer **Réindexer maintenant** pour indexer les formations dans le vector store.
4. (Optionnel) Configurer WhatsApp Business et Perfex — voir ci-dessous.
5. Recommandé : installer le plugin **Action Scheduler** pour une file asynchrone robuste (le plugin retombe sinon sur WP-Cron).

## Endpoints REST (`bem-lead-ai/v1`)

| Endpoint | Rôle |
|---|---|
| `POST /chat` | Message web, réponse du conseiller IA |
| `GET /messages` | Polling widget (relances proactives, réponses conseiller) |
| `POST /track` | Événement comportemental (soumis au consentement) |
| `GET|POST /whatsapp-webhook` | Vérification + réception des messages WhatsApp |
| `GET /lead/{session_id}` | Profil et scores (admin) |
| `POST /reindex` | Réindexation du contenu (admin) |
| `POST /handoff/{lead_id}/reply` | Réponse conseiller depuis l'inbox |
| `POST /crm-status-webhook` | Retour CRM « inscrit » → bascule onboarding |
| `GET /financing/options`, `POST /financing/simulate` | Simulateur de financement |

## Sécurité & conformité (loi n°2008-12, CDP Sénégal)

- Consentement explicite avant tout tracking comportemental (bandeau).
- Clés API chiffrées au repos (libsodium, clé dérivée des salts WP) — jamais en clair, jamais renvoyées au navigateur.
- Droit à l'oubli : suppression d'un lead + tout son historique (web + WhatsApp), branché sur l'outil natif WordPress et disponible depuis la fiche lead.
- Signature vérifiée sur les webhooks WhatsApp (X-Hub-Signature-256) et CRM (secret partagé).
- Rate limiting sur `/chat`, `/track`, `/whatsapp-webhook`.

## Stack

PHP 8+ · Action Scheduler (file) · Claude API (Sonnet chat/résumés, Haiku classification) · Qdrant Cloud (vector store) · Voyage/OpenAI (embeddings) · Meta WhatsApp Cloud API · JS vanilla (widget).

## Notes d'exploitation

- **WhatsApp** : l'activation nécessite une validation Meta (vérification d'entreprise + templates) pouvant prendre plusieurs semaines — à lancer tôt.
- **Escalade humaine** : suppose une inbox conseiller surveillée activement par l'équipe admissions (engagement opérationnel autant que technique).
- **Bandit** : tourne en mode exploration tant que le volume est faible ; passage à Thompson sampling à envisager avec du volume.
- **Montée en charge** : déporter chat + scoring vers un microservice si le volume l'exige ; cache des réponses fréquentes pour réduire les coûts LLM.
