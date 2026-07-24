# Tests

Suite de tests automatisés du plugin (PHPUnit).

## Lancer les tests en local

```bash
cd bem-lead-ai
composer install      # installe PHPUnit (dépendance de dev)
composer test         # ou : vendor/bin/phpunit
```

## Approche

Le plugin dépend de WordPress, indisponible en ligne de commande.
`tests/bootstrap.php` fournit donc :

- des **stubs** des fonctions WordPress utilisées (`is_email`, `sanitize_text_field`…) ;
- de **faux collaborateurs** (`LeadRepository`, `ChannelAdapter`, `Options`…) partageant
  les namespaces du code réel, adossés à un store de leads en mémoire (`FakeLeadStore`) ;
- le chargement des **vraies** classes testées.

Les tests exercent ainsi le code de production réel (pas une réimplémentation),
sans nécessiter une installation WordPress complète.

## Couverture actuelle

| Fichier de test | Ce qui est vérifié |
|---|---|
| `PhoneNumberTest` | Extraction déterministe et normalisation des numéros (indicatif par défaut, formats internationaux, rejet des montants monétaires). |
| `FormCaptureDedupTest` | Résolution d'identité multi-canal (chat / formulaire) et capture externe (sites non-WordPress) : une même personne ne crée jamais deux fiches. |
| `ScoringTest` | Score comportemental (règles pondérées × décroissance de récence, plafond), classification en bandes, et mélange avec l'intention conversationnelle. |
| `DisengagementTest` | Détection de désengagement : calcul du pic d'activité (fenêtre glissante 72 h), décision d'émission sur chute nette, et non-répétition d'une relance récente. |
| `PayloadCodecTest` | Transport vers Perfex : compression gzip + base64url (préfixe `SIAZ1:`), round-trip, sûreté pare-feu, et contrat d'interop avec le décodeur Perfex. |
| `CronHealthTest` | Surveillance des tâches planifiées : détection des retards, période de grâce après installation, formatage, et état persistant. |
| `WidgetStyleTest` | CSS de design du widget (couleurs dérivées, replis), partagé par l'affichage WordPress et l'embarquement inter-sites. |

Ces tests couvrent les corrections de capture les plus sensibles, le cœur du
scoring, la détection de désengagement, le transport CRM et la supervision des
tâches planifiées. À étendre au reste de la synchro Perfex au fil des évolutions.
