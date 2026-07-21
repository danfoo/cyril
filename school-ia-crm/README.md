# School IA CRM — Application web (SaaS)

Plateforme CRM & marketing d'admission pour les écoles, éditée par **Maestro Dan**.
Successeur applicatif du plugin WordPress *School IA* : le plugin devient le **capteur**
(chatbot IA + capture de leads sur le site de l'école), cette application devient le
**cerveau** (CRM, campagnes, facturation).

> État : **prototype cliquable** (design / ergonomie). Le moteur applicatif n'est pas
> encore développé — c'est la prochaine étape une fois l'ergonomie validée.

## Deux espaces

**Espace Admin (Maestro Dan)**
- Créer et gérer les comptes clients (écoles) — un **utilisateur administrateur** est créé avec chaque compte
- Gérer les **utilisateurs** de chaque entité (per-seat)
- Générer et gérer les licences du plugin School IA
- Définir les tarifs et formules d'abonnement (**quota d'utilisateurs inclus**)
- Suivre les paiements et la facturation (PayPal, Wave, Stripe, Orange Money)

**Espace Client (école)**
- Suivre les leads (pipeline d'admission)
- Planifier des campagnes marketing (e-mail, SMS, WhatsApp)
- Préconfigurer des modèles d'e-mails et de SMS, puis les envoyer
- Gérer son **équipe / utilisateurs** (inviter des agents d'admission)
- Uploader et partager des documents (brochures, dossiers…)
- Connecter les réseaux sociaux pour publier et suivre les campagnes

### Facturation à l'utilisateur (per-seat)

Chaque formule inclut un **quota d'utilisateurs**. Au-delà, chaque utilisateur
supplémentaire est **facturé automatiquement** (ex. +15 000 XOF/mois). Reflété
dans l'espace admin (facturation) et dans l'espace client (bandeau quota + coût).

### Design

Interface **claire, inspiration HubSpot, palette bleue**, conçue **mobile-first**
(barre latérale escamotable, mises en page qui s'empilent, tableaux/kanban à
défilement horizontal). Un seul thème (clair), volontairement.

## Prototype

`prototype/index.html` — fichier **autonome** (HTML/CSS/JS, sans dépendance).
S'ouvre dans n'importe quel navigateur et se déploie tel quel sur tout hébergement
(dont **PlanetHoster**). Écran de connexion → choisir « Espace Admin » ou « Espace Client ».

## Stack cible (proposition, à valider)

Pensé pour un hébergement **PlanetHoster** (cPanel / N0C, PHP & Node) :

- **Option A — Laravel (PHP 8) + Inertia + React** : la plus proche de PlanetHoster et
  de l'univers WordPress ; auth, multi-tenant, facturation (Cashier) matures.
- **Option B — Node.js (Next.js) + PostgreSQL + Prisma** sur N0C.

Dans les deux cas :
- **PostgreSQL/MySQL**, multi-tenant strict (`tenant_id` sur chaque table)
- **RBAC** (admin / client / agent), 2FA, chiffrement des secrets, journal d'audit
- Conformité RGPD + loi n°2008-12 (export/suppression, consentement)

## Briques externes (à ne pas réinventer)

| Besoin | Service |
|---|---|
| Paiement | Stripe + PayPal + Wave + Orange Money (via agrégateur type CinetPay / Paydunya) |
| E-mail | Resend / Brevo / Amazon SES |
| SMS | Twilio / Africa's Talking / Orange SMS API |
| Réseaux sociaux | Ayrshare / Phyllo (multi-plateformes en une API) |
| Fichiers | S3 / Cloudflare R2 (URLs signées) |

## Feuille de route

0. **Socle** — auth, multi-tenant, espace admin (clients, licences, tarifs, paiement)
1. **Leads** — ingestion depuis le plugin, pipeline, fiche lead
2. **E-mail** — modèles + campagnes + envoi
3. **SMS + Documents**
4. **Réseaux sociaux**
