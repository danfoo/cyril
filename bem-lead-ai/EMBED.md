# Embarquement sur un site non-WordPress

Le widget de chat et la capture de formulaires peuvent fonctionner sur
**n'importe quel site** (WordPress ou non : Wix, Squarespace, site PHP/custom,
React/Next.js, HTML statique…). Le « cerveau » reste une installation
`bem-lead-ai` que **vous** hébergez (le *backend*) ; le site client ne fait
qu'afficher le widget via un petit script.

## 1. Récupérer le code à coller

Dans le backend : **Réglages School IA → Embarquement sur un site non-WordPress**.
Vous y trouvez :

- la **clé de site** (identifie le tenant) ;
- le champ **origines autorisées** (le ou les domaines des sites clients) ;
- le **code à coller**, déjà prérempli.

## 2. Coller le snippet sur le site client

Avant `</body>` :

```html
<script src="https://VOTRE-BACKEND/wp-content/plugins/bem-lead-ai/assets/js/embed.js"
        data-backend="https://VOTRE-BACKEND/wp-json/bem-lead-ai/v1"
        data-key="VOTRE_CLE_DE_SITE" defer></script>
```

Le script :

1. récupère la configuration du widget (design, textes) auprès du backend ;
2. affiche le **widget de chat** (identique à la version WordPress) ;
3. écoute les **soumissions de formulaires** de la page et les transmet au CRM.

## 3. Sécurité (CORS)

Renseignez les **origines autorisées** (une par ligne, ex. `https://ecole.com`)
dans les réglages. Seuls ces domaines pourront appeler l'API. Laissez vide
**uniquement en test** (toutes origines autorisées).

## Capture de formulaires — détails

- La détection des champs est automatique (e-mail, téléphone, nom, formation)
  par type de champ et libellé — aucune configuration par formulaire.
- Pour **ignorer** un formulaire : `<form data-sia-ignore>`.
- Pour **nommer** la source d'un formulaire : `<form data-sia-form="Candidature Master">`.
- Le lead issu d'un formulaire est **rattaché à la conversation de chat** en
  cours (même visiteur) grâce au `session_id` partagé — pas de doublon.

## Limites connues (prototype)

- Le widget authentifie ses appels par la clé de site publique + CORS ; pour un
  déploiement multi-écoles à grande échelle, prévoir une gestion de clés par
  tenant et un durcissement (rate-limit par origine, rotation de clé).
- La capture repose sur des heuristiques de libellés ; un formulaire très
  atypique peut nécessiter les attributs `data-sia-*`.
