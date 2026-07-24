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
| `FormCaptureDedupTest` | Résolution d'identité multi-canal : une même personne ne crée jamais deux fiches selon l'ordre chat / formulaire. |
| `ScoringTest` | Score comportemental (règles pondérées × décroissance de récence, plafond), classification en bandes, et mélange avec l'intention conversationnelle. |

Ces tests couvrent les corrections de capture les plus sensibles et le cœur du
scoring. À étendre aux triggers et à la synchro CRM au fil des évolutions.
