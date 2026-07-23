<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: School IA Bridge
Description: Reçoit les leads envoyés par le plugin WordPress « School IA » et les affiche dans Perfex. Le traitement (conversion en lead Perfex, relances…) se fait ensuite.
Version: 1.0.0
Requires at least: 2.3.*
Author: Maestro Dan
Author URI: https://maestrodan.art
*/

define('SCHOOL_IA_BRIDGE_MODULE', 'school_ia_bridge');

/**
 * À l'activation : crée la table de stockage et un secret partagé par défaut.
 */
register_activation_hook(SCHOOL_IA_BRIDGE_MODULE, 'school_ia_bridge_activate');
function school_ia_bridge_activate()
{
    require_once __DIR__ . '/install.php';
}

/**
 * Rappels automatiques : à chaque passage du cron Perfex, on envoie un e-mail
 * au responsable pour chaque tâche due dont le rappel n'a pas encore été émis.
 */
hooks()->add_action('after_cron_run', 'school_ia_bridge_reminders_cron');
function school_ia_bridge_reminders_cron()
{
    if (get_option('sia_reminders_enabled') === '0') {
        return; // désactivé (activé par défaut)
    }
    $CI = &get_instance();
    $CI->load->model('school_ia_bridge/school_ia_bridge_model');
    $tasks = $CI->school_ia_bridge_model->due_reminders();
    if (!$tasks) {
        return;
    }

    $CI->load->library('email');
    foreach ($tasks as $t) {
        $staffId = (int) ($t->lead_owner ?: $t->staff_id);
        $CI->school_ia_bridge_model->mark_reminded((int) $t->id);
        if (!$staffId) {
            continue;
        }
        $staff = $CI->db->where('staffid', $staffId)->get(db_prefix() . 'staff')->row();
        if (!$staff || empty($staff->email)) {
            continue;
        }

        $link = admin_url('school_ia_bridge/lead/' . (int) $t->lead_id);
        $body = '<p>Bonjour ' . htmlspecialchars($staff->firstname) . ',</p>'
            . '<p>Rappel d\'une tâche à effectuer :</p>'
            . '<ul>'
            . '<li><strong>' . htmlspecialchars($t->title) . '</strong></li>'
            . '<li>Lead : ' . htmlspecialchars((string) ($t->lead_name ?: ('#' . $t->lead_id))) . '</li>'
            . '<li>Échéance : ' . htmlspecialchars((string) $t->due_at) . '</li>'
            . '</ul>'
            . '<p><a href="' . $link . '">Ouvrir la fiche du lead</a></p>';

        $CI->email->clear(true);
        $CI->email->from(get_option('smtp_email') ?: get_option('companyname'), get_option('companyname'));
        $CI->email->to($staff->email);
        $CI->email->subject('Rappel de tâche — ' . $t->title);
        $CI->email->message($body);
        $CI->email->set_mailtype('html');
        $CI->email->send(false);
    }
}

/**
 * Aide contextuelle : bouton « ? » + pop-up (modal Bootstrap) expliquant
 * la fonctionnalité et sa configuration. Utilisé dans les en-têtes de pages.
 */
function sia_help(string $key): string
{
    $help = [
        'dashboard' => ['Tableau de bord',
            '<p>Vue d\'ensemble de votre activité d\'admission.</p>
             <ul>
               <li><strong>Leads au total</strong> : tous les leads reçus (plugin, saisie, import).</li>
               <li><strong>Leads chauds</strong> : score ≥ 60 (prospects les plus engagés).</li>
               <li><strong>Inscrits</strong> et <strong>taux de conversion</strong> : leads en étape « Inscrit » / total.</li>
               <li><strong>Leads non assignés</strong> et <strong>délai moyen de 1ᵉʳ contact</strong> : suivi opérationnel de la prise en charge.</li>
               <li><strong>Valeur du pipeline</strong> et <strong>CA réalisé</strong> : basés sur les frais par formation (Réglages) ; masqués tant qu\'aucun frais n\'est configuré.</li>
               <li><strong>Objectif d\'inscrits</strong> : jauge comparant les inscrits de la période à l\'objectif défini dans Réglages.</li>
               <li><strong>Leads non assignés</strong> et <strong>derniers leads reçus</strong> : aperçus rapides pour agir vite.</li>
               <li><strong>Par conseiller</strong> : leads assignés, inscrits et conversion de chaque agent.</li>
               <li><strong>Par formation</strong> (camembert) : quelles filières attirent les candidats.</li>
               <li><strong>Maturité des leads</strong> (camembert) : froids (&lt; 40) / tièdes (40-59) / chauds (≥ 60).</li>
               <li><strong>Entonnoir</strong> (camembert) : répartition des leads par étape du pipeline.</li>
               <li><strong>Relances à faire</strong> : vos prochaines tâches dues.</li>
             </ul>
             <p><em>Période :</em> les boutons (Tout / 7 / 30 / 90 jours) ou une plage de dates personnalisée, combinables avec un filtre par <strong>rentrée</strong>, filtrent tous les indicateurs.</p>'],
        'pipeline' => ['Pipeline',
            '<p>Suivez la progression des leads par étape (Nouveau → … → Inscrit / Perdu).</p>
             <ul>
               <li><strong>Glisser-déposer</strong> : attrapez une carte et déposez-la dans une autre colonne pour changer son étape (enregistré automatiquement).</li>
               <li>Le menu <strong>« Déplacer »</strong> d\'une carte fait la même chose au clic.</li>
               <li>Cliquez le nom d\'un lead pour ouvrir sa <strong>fiche</strong>.</li>
             </ul>
             <p><em>Astuce :</em> passer un lead en « Inscrit » ou « Perdu » arrête ses séquences de relance automatiques.</p>'],
        'lead' => ['Fiche lead',
            '<p>Le centre de contrôle d\'un lead.</p>
             <ul>
               <li><strong>Étape</strong> : faites avancer le lead dans le pipeline.</li>
               <li><strong>Conversation avec l\'IA</strong> : les échanges du chatbot du site, remontés automatiquement pour donner le contexte avant de recontacter.</li>
               <li><strong>Contacter</strong> : envoyez un e-mail ou un SMS (onglets), avec modèles et pièces jointes.</li>
               <li><strong>Séquences</strong> : inscrivez le lead à une relance automatique.</li>
               <li><strong>Tâches</strong> : planifiez des relances datées (avec heure).</li>
               <li><strong>Responsable</strong> : assignez un conseiller.</li>
               <li><strong>Historique</strong> : tout ce qui a été fait sur le lead.</li>
             </ul>
             <p><em>Rien ne s\'affiche ?</em> Vérifiez que la version du plugin WordPress installée envoie bien les messages (mise à jour du pont) et que le secret partagé (Réglages) est identique des deux côtés.</p>'],
        'tasks' => ['Tâches & relances',
            '<p>Votre centre de pilotage des relances.</p>
             <ul>
               <li><strong>Création rapide</strong> (en haut) : intitulé, échéance, priorité, lead et responsable — le lead est facultatif.</li>
               <li><strong>Onglets</strong> : basculez entre À faire, En retard, Aujourd\'hui, À venir et Terminées (compteurs à jour).</li>
               <li><strong>Priorité</strong> (Haute / Moyenne / Basse) : filtrez-la et changez-la d\'un clic sur le badge.</li>
               <li>La vue « À faire » <strong>regroupe</strong> les tâches par échéance (En retard, Aujourd\'hui, Cette semaine, À venir).</li>
               <li><strong>Actions groupées</strong> : cochez plusieurs tâches puis terminez, reportez de 7 jours, réassignez ou supprimez en un clic.</li>
               <li>Chaque tâche est reliée à son <strong>lead</strong> et à son <strong>responsable</strong>.</li>
             </ul>
             <p><em>Rappels automatiques :</em> activez-les dans <strong>Réglages</strong>. Un e-mail est alors envoyé au responsable à l\'échéance (nécessite le cron Perfex).</p>'],
        'sequences' => ['Séquences de relance',
            '<p>Une suite d\'étapes envoyées automatiquement dans le temps.</p>
             <ol>
               <li>Créez une <strong>séquence</strong> (ex. « Relance admission »).</li>
               <li>Ajoutez des <strong>étapes</strong> : canal (e-mail/SMS), <strong>modèle</strong>, et <strong>délai</strong> (jours + heures) d\'attente avant l\'envoi.</li>
               <li>Inscrivez un lead à la séquence depuis sa <strong>fiche</strong>.</li>
             </ol>
             <p>Le <strong>cron Perfex</strong> envoie les étapes dues et fait avancer chaque inscription. La séquence s\'arrête si le lead devient « Inscrit » ou « Perdu ».</p>
             <p><em>Prérequis :</em> configurer le cron (Setup → Settings → Cron Job).</p>'],
        'templates' => ['Modèles e-mail / SMS',
            '<p>Des messages réutilisables pour gagner du temps.</p>
             <ul>
               <li>Choisissez le <strong>type</strong> (E-mail = objet + corps ; SMS = corps seul).</li>
               <li>Utilisez les variables <code>{prenom}</code> et <code>{formation}</code> : elles sont remplacées par les infos du lead à l\'envoi.</li>
             </ul>
             <p>Les modèles apparaissent ensuite dans les menus déroulants de la fiche lead, de l\'envoi groupé et des séquences.</p>'],
        'documents' => ['Documents',
            '<p>Votre bibliothèque de documents classés par <strong>programme</strong>.</p>
             <ol>
               <li>Définissez d\'abord vos <strong>programmes</strong> dans <strong>Réglages</strong>.</li>
               <li>Téléversez un fichier (PDF, Word, Excel, image… max 20 Mo) en choisissant son programme.</li>
               <li>Depuis la fiche lead (onglet E-mail) ou l\'envoi groupé, cochez un document pour le <strong>joindre</strong> à l\'e-mail.</li>
             </ol>'],
        'bulk' => ['Envoi groupé',
            '<p>Contactez plusieurs leads d\'un coup (e-mail ou SMS).</p>
             <ol>
               <li><strong>Cibler</strong> : filtrez par étape, programme, <strong>responsable</strong> (dont « Mes leads »), <strong>date de réception</strong> et score minimum. La <strong>pastille</strong> affiche en temps réel le nombre de destinataires.</li>
               <li><strong>Rédiger</strong> : choisissez un modèle prêt à l\'emploi ou écrivez ; <code>{prenom}</code>/<code>{formation}</code> sont personnalisés pour chaque destinataire.</li>
               <li><strong>Aperçu</strong> : vérifiez le rendu final (variables remplacées) avant l\'envoi.</li>
               <li>(E-mail) cochez des <strong>pièces jointes</strong> si besoin, puis envoyez.</li>
             </ol>
             <p>Seuls les leads ayant l\'e-mail (ou le téléphone pour les SMS) sont contactés. Chaque envoi est <strong>tracé dans la fiche du prospect</strong> et dans les <strong>statistiques des campagnes</strong>.</p>'],
        'import' => ['Import CSV / Excel',
            '<p>Importez une liste de leads existante.</p>
             <ul>
               <li>La <strong>1ʳᵉ ligne</strong> du fichier doit contenir les en-têtes ; les colonnes sont reconnues automatiquement (nom, e-mail, téléphone, formation, score, étape).</li>
               <li>Téléchargez le <strong>modèle CSV</strong> pour partir sur de bonnes bases.</li>
               <li>Un <strong>programme</strong> et une <strong>étape</strong> par défaut s\'appliquent aux lignes qui ne les précisent pas.</li>
               <li>Les e-mails déjà présents sont <strong>ignorés</strong> (pas de doublon).</li>
             </ul>'],
        'reporting' => ['Reporting IA',
            '<p>Génère un <strong>rapport d\'activité rédigé par l\'IA</strong> (Claude) pour la période choisie.</p>
             <ol>
               <li>Choisissez le <strong>type</strong> (journalier, hebdomadaire, mensuel, annuel) et une date de référence.</li>
               <li>Vérifiez l\'aperçu des chiffres, puis cliquez <strong>« Générer avec l\'IA »</strong>.</li>
               <li>L\'IA analyse les données (leads, conversions, sources, campagnes…) et produit une synthèse avec points forts, points de vigilance et <strong>recommandations concrètes</strong>.</li>
             </ol>
             <p>Chaque rapport est <strong>archivé</strong> et consultable à tout moment.</p>
             <p><em>Prérequis :</em> une clé API Anthropic dans <strong>Réglages → Rapports IA</strong>.</p>'],
        'campaigns' => ['Statistiques des campagnes',
            '<p>Mesurez l\'efficacité de vos envois.</p>
             <ul>
               <li><strong>Vue par type</strong> : basculez entre <em>Campagnes massives</em> (envois de masse) et <em>Séquences automatisées</em> (relances individuelles) pour ne pas fausser les taux — une campagne de masse ouvre naturellement moins qu\'une relance déclenchée.</li>
               <li><strong>Taux d\'ouverture / de clic</strong> (e-mail) : pixel invisible + liens tracés.</li>
               <li><strong>Conversions</strong> : combien de prospects <em>ayant cliqué</em> sont passés à l\'étape « Inscrit » — c\'est le vrai ROI de vos envois.</li>
               <li><strong>Tableau groupé</strong> : une ligne = une campagne/un lot (pas un individu). Cliquez une ligne pour voir <strong>qui a ouvert / cliqué</strong> et rappeler ces prospects.</li>
               <li><strong>SMS</strong> : nombre d\'envois réussis / échoués.</li>
             </ul>
             <p><em>Note :</em> certains logiciels de messagerie bloquent les images ; le taux d\'ouverture réel peut être légèrement supérieur à l\'affiché.</p>'],
        'competitors' => ['Veille concurrentielle',
            '<p>Un outil d\'aide à la vente : les écoles concurrentes citées spontanément par les prospects, et vos arguments pour les contrer.</p>
             <ul>
               <li><strong>Filtres</strong> : affinez par période, programme visé et statut du lead (gagné / perdu).</li>
               <li><strong>Tendance</strong> : sous « Mentions totales », la variation vs le mois précédent signale un concurrent de plus en plus agressif.</li>
               <li><strong>Classement analytique</strong> : mentions, prospects, programme le plus ciblé et <strong>taux de perte</strong> face à chaque école.</li>
               <li><strong>Argumentaire (battle card)</strong> : pastille verte « Prêt » / rouge « À rédiger ». Cliquez « Ajouter/Modifier » pour saisir vos arguments — ils s\'affichent ensuite dans l\'infobulle 💡 à côté de chaque citation.</li>
               <li><strong>Extraits orientés action</strong> : le score du prospect pour prioriser, et un badge <strong>À traiter / Objection contrée</strong> (cliquable) pour suivre le traitement de chaque objection.</li>
             </ul>
             <p><em>Analyse par l\'IA :</em> « Analyser les conversations » lit les échanges stockés et en extrait les concurrents cités. Nécessite la clé API Claude (Réglages → Rapports IA). Relancer ne traite que les conversations nouvelles.</p>'],
        'activity' => ['Journal d\'activité',
            '<p>Le flux central de <strong>tout ce qui se passe</strong> sur l\'ensemble des leads : notes, changements d\'étape, tâches, e-mails, SMS, assignations.</p>
             <ul>
               <li><strong>Filtres</strong> : par <strong>conseiller</strong>, par <strong>plage de dates</strong> (ou raccourcis Aujourd\'hui / 7 / 30 jours) et par <strong>type</strong> d\'activité.</li>
               <li><strong>Conseillers les plus actifs</strong> : le classement du haut montre qui a le plus agi sur la période ; cliquez un nom pour filtrer sur lui.</li>
               <li><strong>Changement d\'étape</strong> : l\'étape d\'origine et de destination sont affichées (ex. Nouveau → Qualifié).</li>
               <li><strong>Action rapide</strong> : « Terminer » coche une tâche directement depuis le journal, sans ouvrir la fiche.</li>
               <li>Chaque ligne indique <strong>qui</strong> a agi et <strong>quand</strong>, avec un lien vers le lead.</li>
             </ul>
             <p><em>Droits :</em> l\'accès aux fonctions du CRM se règle par rôle dans <strong>Setup → Rôles → School IA CRM</strong>.</p>'],
        'inbox' => ['Contacts',
            '<p>La liste de tous les leads reçus, avec gestion en masse.</p>
             <ul>
               <li><strong>Synthèse rapide</strong> (bandeau de chips) : chauds / tièdes / froids, non assignés et top formations — cliquez « chauds » ou « non assignés » pour filtrer d\'un coup.</li>
               <li><strong>Rechercher / filtrer</strong> par nom, e-mail, étape, rentrée, score ou non-assignés.</li>
               <li><strong>Colonne Conseiller</strong> : voyez d\'un coup d\'œil qui est responsable de chaque lead.</li>
               <li><strong>Sélection multiple</strong> (cases à cocher) puis <strong>actions groupées</strong> : assigner à un conseiller, changer d\'étape, ou supprimer plusieurs leads en un clic.</li>
               <li><strong>Ajouter</strong> un lead, <strong>Importer</strong> (CSV/Excel), <strong>Exporter</strong> la liste filtrée.</li>
               <li>Pour une <strong>campagne e-mail/SMS</strong> ciblée par filtres, utilisez la page <strong>Envoi groupé</strong>.</li>
             </ul>'],
        'new_lead' => ['Ajouter un lead',
            '<p>Saisie manuelle d\'un lead (salon, appel, recommandation…).</p>
             <p>Renseignez au moins un <strong>nom, e-mail ou téléphone</strong>. Le champ formation propose vos programmes. Le lead ouvre ensuite sa fiche, prêt pour tâches, e-mail/SMS et séquences.</p>'],
        'settings' => ['Réglages',
            '<p>La configuration du module.</p>
             <ul>
               <li><strong>Connexion du plugin</strong> : URL du point d\'entrée + secret à recopier dans le plugin School IA (WordPress → Réglages → CRM) pour recevoir les leads.</li>
               <li><strong>Rappels automatiques</strong> : e-mail au responsable des tâches dues (nécessite le cron).</li>
               <li><strong>Programmes</strong> : la liste servant à classer les documents.</li>
               <li><strong>SMS (LAfricaMobile)</strong> : Account ID, mot de passe API et expéditeur pour l\'envoi de SMS.</li>
             </ul>'],
    ];

    if (!isset($help[$key])) {
        return '';
    }
    $title = $help[$key][0];
    $body = $help[$key][1];
    $id = 'siaHelp_' . preg_replace('/[^a-z0-9_]/', '', $key);

    return '<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#' . $id . '" title="Aide"><i class="fa fa-question-circle"></i> Aide</button>'
        . '<div class="modal fade" id="' . $id . '" tabindex="-1" role="dialog"><div class="modal-dialog"><div class="modal-content">'
        . '<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button>'
        . '<h4 class="modal-title"><i class="fa fa-question-circle"></i> ' . $title . '</h4></div>'
        . '<div class="modal-body">' . $body . '</div>'
        . '<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button></div>'
        . '</div></div></div>';
}

/**
 * Rendu d'un « camembert » (donut SVG autonome, sans dépendance JavaScript) +
 * légende. Chaque tranche : ['label' => string, 'value' => number, 'color' => '#rrggbb'].
 * Les tranches nulles sont ignorées ; renvoie un message si tout est à zéro.
 */
function sia_pie_block(array $slices, int $size = 150): string
{
    $total = 0.0;
    foreach ($slices as $s) {
        $total += max(0.0, (float) $s['value']);
    }

    if ($total <= 0) {
        return '<p class="text-muted" style="margin:8px 0 0;">Aucune donnée sur cette période.</p>';
    }

    $thickness = 20;
    $r    = ($size - $thickness) / 2;
    $c    = $size / 2;
    $circ = 2 * M_PI * $r;

    // Styles inline volontaires : le camembert reste correct même si la feuille
    // de style du module est momentanément en cache côté navigateur.
    $svg  = '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '" role="img" style="display:block;">';
    $svg .= '<g transform="rotate(-90 ' . $c . ' ' . $c . ')">';
    $svg .= '<circle class="sia-donut-track" cx="' . $c . '" cy="' . $c . '" r="' . round($r, 2) . '" fill="none" stroke="#eef1f5" stroke-width="' . $thickness . '"/>';
    $offset = 0.0;
    foreach ($slices as $s) {
        $val = max(0.0, (float) $s['value']);
        if ($val <= 0) {
            continue;
        }
        $len = $circ * ($val / $total);
        $svg .= '<circle cx="' . $c . '" cy="' . $c . '" r="' . round($r, 2) . '" fill="none"'
            . ' stroke="' . htmlspecialchars((string) $s['color'], ENT_QUOTES) . '" stroke-width="' . $thickness . '"'
            . ' stroke-dasharray="' . round($len, 2) . ' ' . round($circ - $len, 2) . '"'
            . ' stroke-dashoffset="' . round(-$offset, 2) . '"/>';
        $offset += $len;
    }
    $svg .= '</g>';
    $svg .= '<text x="' . $c . '" y="' . $c . '" text-anchor="middle" dominant-baseline="central"'
        . ' style="font-size:24px;font-weight:800;fill:var(--sia-text,#0f172a);">' . (int) round($total) . '</text>';
    $svg .= '</svg>';

    $legend = '<ul style="list-style:none;margin:0;padding:0;flex:1 1 190px;min-width:170px;">';
    foreach ($slices as $s) {
        $val = max(0.0, (float) $s['value']);
        if ($val <= 0) {
            continue;
        }
        $pct   = round($val * 100 / $total);
        $label = htmlspecialchars((string) $s['label'], ENT_QUOTES);
        $color = htmlspecialchars((string) $s['color'], ENT_QUOTES);
        $legend .= '<li style="display:flex;align-items:center;gap:9px;padding:6px 0;border-bottom:1px solid var(--sia-border,#e6e9f0);font-size:13px;">'
            . '<span style="width:10px;height:10px;border-radius:3px;flex:0 0 auto;background:' . $color . ';"></span>'
            . '<span style="flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--sia-text,#0f172a);" title="' . $label . '">' . $label . '</span>'
            . '<span style="margin-left:auto;padding-left:10px;color:var(--sia-muted,#64748b);font-weight:600;flex:0 0 auto;white-space:nowrap;">' . (int) $val . ' · ' . $pct . ' %</span>'
            . '</li>';
    }
    $legend .= '</ul>';

    return '<div style="display:flex;align-items:center;gap:22px;flex-wrap:wrap;margin-top:6px;">'
        . '<div style="flex:0 0 auto;">' . $svg . '</div>' . $legend . '</div>';
}

/**
 * Histogramme SVG autonome (barres verticales à dégradé, sans dépendance JS).
 * $series : liste de ['label' => string, 'value' => number].
 */
function sia_bar_chart(array $series, int $height = 160): string
{
    if (empty($series)) {
        return '<p class="text-muted" style="margin:8px 0 0;">Aucune donnée sur cette période.</p>';
    }
    $max = 1;
    foreach ($series as $s) { $max = max($max, (int) $s['value']); }

    $n       = count($series);
    $slot    = 46;
    $barW    = 26;
    $padTop  = 18;
    $padBot  = 26;
    $chartH  = $height - $padTop - $padBot;
    $w       = $n * $slot;
    $labelEvery = (int) max(1, ceil($n / 16));

    $svg  = '<svg viewBox="0 0 ' . $w . ' ' . $height . '" width="100%" height="' . $height . '" preserveAspectRatio="xMinYMid meet" style="display:block;min-width:' . $w . 'px;">';
    $svg .= '<defs><linearGradient id="siaBarGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#6366f1"/><stop offset="1" stop-color="#4f46e5"/></linearGradient></defs>';
    foreach ($series as $i => $s) {
        $val = (int) $s['value'];
        $h   = $val > 0 ? (int) max(3, round($val / $max * $chartH)) : 0;
        $x   = $i * $slot + ($slot - $barW) / 2;
        $y   = $padTop + ($chartH - $h);
        if ($h > 0) {
            $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $barW . '" height="' . $h . '" rx="5" fill="url(#siaBarGrad)"/>';
            $svg .= '<text x="' . ($x + $barW / 2) . '" y="' . ($y - 5) . '" text-anchor="middle" style="font-size:10px;font-weight:700;fill:var(--sia-text,#0f172a);">' . $val . '</text>';
        } else {
            $svg .= '<rect x="' . $x . '" y="' . ($padTop + $chartH - 2) . '" width="' . $barW . '" height="2" rx="1" fill="#cbd5e1"/>';
        }
        if ($i % $labelEvery === 0) {
            $svg .= '<text x="' . ($x + $barW / 2) . '" y="' . ($height - 8) . '" text-anchor="middle" style="font-size:10px;fill:var(--sia-muted,#64748b);">' . htmlspecialchars((string) $s['label'], ENT_QUOTES) . '</text>';
        }
    }
    $svg .= '</svg>';
    return '<div style="overflow-x:auto;margin-top:6px;">' . $svg . '</div>';
}

/**
 * Badge de variation vs période précédente (▲ vert / ▼ rouge / — neutre).
 * Hausse considérée « positive » (adapté aux leads, inscrits, envois…).
 */
function sia_delta($current, $previous): string
{
    $cur = (float) $current;
    $prev = (float) $previous;
    $base = 'font-size:11.5px;font-weight:600;';
    if ($prev == 0.0) {
        if ($cur == 0.0) {
            return '<span style="' . $base . 'color:#94a3b8;">— vs préc.</span>';
        }
        return '<span style="' . $base . 'color:#16a34a;">▲ nouveau vs préc.</span>';
    }
    $pct = (int) round(($cur - $prev) / $prev * 100);
    if ($pct === 0) {
        return '<span style="' . $base . 'color:#94a3b8;">→ 0 % vs préc.</span>';
    }
    $up = $pct > 0;
    return '<span style="' . $base . 'color:' . ($up ? '#16a34a' : '#dc2626') . ';">'
        . ($up ? '▲ +' : '▼ ') . $pct . ' % vs préc.</span>';
}

/** Intervalle de la période PRÉCÉDENTE (même granularité). Renvoie [from,to,label]. */
function school_ia_prev_range(string $period, string $from): array
{
    $shift = ['day' => '-1 day', 'week' => '-1 week', 'month' => '-1 month', 'year' => '-1 year'][$period] ?? '-1 month';
    $prevRef = date('Y-m-d', strtotime($shift, strtotime($from)));
    return school_ia_period_range($period, $prevRef);
}

/** Formate un tableau associatif en "clé: valeur, clé: valeur". */
function school_ia_kv(array $arr): string
{
    $parts = [];
    foreach ($arr as $k => $v) {
        $parts[] = $k . ': ' . $v;
    }
    return implode(', ', $parts);
}

/**
 * Calcule l'intervalle de dates d'une période. Renvoie [from, to, label].
 */
function school_ia_period_range(string $period, string $date = ''): array
{
    $ref = $date !== '' ? strtotime($date) : time();
    switch ($period) {
        case 'day':
            $from = date('Y-m-d 00:00:00', $ref);
            $to   = date('Y-m-d 23:59:59', $ref);
            $label = 'Rapport journalier — ' . date('d/m/Y', $ref);
            break;
        case 'week':
            $mon = strtotime('monday this week', $ref);
            $sun = strtotime('sunday this week', $ref);
            $from = date('Y-m-d 00:00:00', $mon);
            $to   = date('Y-m-d 23:59:59', $sun);
            $label = 'Rapport hebdomadaire — semaine du ' . date('d/m/Y', $mon);
            break;
        case 'year':
            $from = date('Y-01-01 00:00:00', $ref);
            $to   = date('Y-12-31 23:59:59', $ref);
            $label = 'Rapport annuel — ' . date('Y', $ref);
            break;
        case 'month':
        default:
            $from = date('Y-m-01 00:00:00', $ref);
            $to   = date('Y-m-t 23:59:59', $ref);
            $label = 'Rapport mensuel — ' . date('m/Y', $ref);
            break;
    }
    return [$from, $to, $label];
}

/**
 * Appelle l'API Claude (Anthropic) pour rédiger le rapport. Renvoie [ok, texte].
 * Raw HTTPS via cURL (Perfex = PHP sans le SDK Anthropic).
 */
function school_ia_ai_generate(string $system, string $prompt): array
{
    $key = trim((string) get_option('sia_ai_api_key'));
    if ($key === '') {
        return [false, 'Clé API IA non configurée (Réglages → Rapports IA).'];
    }
    $model = trim((string) get_option('sia_ai_model')) ?: 'claude-opus-4-8';

    $payload = json_encode([
        'model'      => $model,
        'max_tokens' => 3000,
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTPHEADER     => [
            'content-type: application/json',
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return [false, 'Connexion à l\'IA échouée : ' . $cerr];
    }
    $data = json_decode((string) $resp, true);
    if ($code >= 400) {
        return [false, 'Erreur API (' . $code . ') : ' . ($data['error']['message'] ?? mb_substr((string) $resp, 0, 200))];
    }
    $text = '';
    foreach (($data['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') {
            $text .= $block['text'];
        }
    }
    if (trim($text) === '') {
        return [false, 'Réponse de l\'IA vide.'];
    }
    return [true, $text];
}

/**
 * Analyse les conversations stockées avec l'IA pour en extraire les écoles
 * concurrentes ET la formation d'intérêt. Utilisé par le bouton manuel ET le
 * cron automatique. Retourne ['scanned'=>int,'found'=>int,'error'=>?string].
 */
function school_ia_scan_competitors(bool $force = false): array
{
    if (trim((string) get_option('sia_ai_api_key')) === '') {
        return ['scanned' => 0, 'found' => 0, 'error' => 'Clé API Claude non configurée.'];
    }
    $CI = &get_instance();
    $CI->load->model('school_ia_bridge/school_ia_bridge_model');
    $model = $CI->school_ia_bridge_model;

    $school = trim((string) get_option('companyname')) ?: 'notre école';
    $system = "Tu analyses une conversation entre un prospect et le conseiller d'orientation de {$school}.\n"
        . "Ta mission : repérer TOUTES les AUTRES écoles / universités / instituts / centres de formation "
        . "(concurrents) que le PROSPECT mentionne, même en passant : où il pense aller, qu'il compare, où il a "
        . "déjà candidaté ou étudié, qu'un proche fréquente, etc. Sois EXHAUSTIF : liste chaque établissement cité, "
        . "même une seule fois. Corrige les fautes de frappe sur les noms connus.\n"
        . "N'inclus JAMAIS {$school} elle-même. N'inclus pas les simples noms de villes ni de formations.\n"
        . "Repère aussi la formation/le programme qui intéresse le prospect (ex. « Master Finance »).\n"
        . "Réponds STRICTEMENT en JSON, sans texte autour :\n"
        . "{\"competitors\":[{\"name\":\"Nom exact de l'école\",\"context\":\"courte citation du passage\"}],\"formation\":\"formation d'intérêt ou vide\"}";

    $scanned = 0;
    $found = 0;
    foreach ($model->conversations_for_scan() as $convo) {
        $leadId = (int) $convo['lead_id'];
        if (!$force && $convo['last_id'] <= $model->competitor_scan_marker($leadId)) {
            continue;
        }
        $scanned++;
        [$ok, $out] = school_ia_ai_generate($system, "Conversation :\n\n" . $convo['text']);
        if (!$ok) {
            return ['scanned' => $scanned - 1, 'found' => $found, 'error' => $out];
        }
        if (preg_match('/\{.*\}/s', $out, $m)) {
            $obj = json_decode($m[0], true);
            if (is_array($obj)) {
                foreach ((array) ($obj['competitors'] ?? []) as $c) {
                    $name = trim((string) ($c['name'] ?? ''));
                    if ($name === '') { continue; }
                    $ref = 'ai_' . md5($leadId . '|' . mb_strtolower($name));
                    $model->add_competitor_mention($leadId, $name, (string) ($c['context'] ?? ''), $ref);
                    $found++;
                }
                $formation = trim((string) ($obj['formation'] ?? ''));
                if ($formation !== '') {
                    $model->set_formation_if_empty($leadId, $formation);
                }
            }
        }
        $model->set_competitor_scan_marker($leadId, (int) $convo['last_id']);
    }
    return ['scanned' => $scanned, 'found' => $found, 'error' => null];
}

hooks()->add_action('after_cron_run', 'school_ia_bridge_competitors_cron');
function school_ia_bridge_competitors_cron()
{
    // Analyse automatique désactivable ; activée par défaut si une clé API existe.
    if (get_option('sia_comp_auto') === '0') {
        return;
    }
    if (trim((string) get_option('sia_ai_api_key')) === '') {
        return;
    }
    school_ia_scan_competitors(false);
}

// ---- Utilitaires d'envoi partagés (utilisés par le cron des séquences) ----

function school_ia_personalize(string $text, object $lead): string
{
    $prenom = trim(explode('#', (string) $lead->name)[0]);
    return strtr($text, [
        '{prenom}'    => $prenom !== '' ? $prenom : 'bonjour',
        '{formation}' => $lead->formation ?: 'votre formation',
    ]);
}

/**
 * Envoie un e-mail à un lead AVEC suivi : enregistre le message, insère un
 * pixel d'ouverture invisible et réécrit les liens pour tracer les clics.
 */
function school_ia_send_tracked_email(object $lead, string $subject, string $bodyText, string $campaign, array $attachPaths = [], ?int $campaignId = null): bool
{
    $CI = &get_instance();
    $CI->load->model('school_ia_bridge/school_ia_bridge_model');
    $token = $CI->school_ia_bridge_model->log_message([
        'lead_id'     => (int) $lead->id,
        'channel'     => 'email',
        'campaign'    => $campaign,
        'campaign_id' => $campaignId,
        'subject'     => $subject,
        'status'      => 'sent',
        'staff_id'    => function_exists('get_staff_user_id') ? (get_staff_user_id() ?: null) : null,
    ]);

    // Corps déjà en HTML (éditeur enrichi) → tel quel ; sinon on convertit les
    // sauts de ligne du texte brut en <br>.
    $html = (strip_tags($bodyText) !== $bodyText) ? $bodyText : nl2br($bodyText);
    // Réécrit les liens <a href="http..."> vers le traceur de clics.
    $click = site_url('school_ia_bridge/api/track_click/' . $token);
    $html = preg_replace_callback('/href="(https?:\/\/[^"]+)"/i', function ($m) use ($click) {
        return 'href="' . $click . '?u=' . rawurlencode($m[1]) . '"';
    }, $html);
    // Pixel d'ouverture.
    $html .= '<img src="' . site_url('school_ia_bridge/api/track_open/' . $token) . '" width="1" height="1" alt="" style="display:none">';

    $CI->load->library('email');
    $CI->email->clear(true);
    $CI->email->from(get_option('smtp_email') ?: get_option('companyname'), get_option('companyname'));
    $CI->email->to($lead->email);
    $CI->email->subject($subject);
    $CI->email->message($html);
    $CI->email->set_mailtype('html');
    foreach ($attachPaths as $p) {
        if (is_file($p)) {
            $CI->email->attach($p);
        }
    }
    return $CI->email->send(false);
}

/** Journalise un SMS envoyé (pour les statistiques). */
function school_ia_log_sms(int $leadId, bool $ok, string $campaign, ?int $campaignId = null): void
{
    $CI = &get_instance();
    $CI->load->model('school_ia_bridge/school_ia_bridge_model');
    $CI->school_ia_bridge_model->log_message([
        'lead_id'     => $leadId,
        'channel'     => 'sms',
        'campaign'    => $campaign,
        'campaign_id' => $campaignId,
        'status'      => $ok ? 'sent' : 'failed',
        'staff_id'    => function_exists('get_staff_user_id') ? (get_staff_user_id() ?: null) : null,
    ]);
}

function school_ia_send_email_raw(string $toEmail, string $subject, string $htmlMessage): bool
{
    $CI = &get_instance();
    $CI->load->library('email');
    $CI->email->clear(true);
    $CI->email->from(get_option('smtp_email') ?: get_option('companyname'), get_option('companyname'));
    $CI->email->to($toEmail);
    $CI->email->subject($subject);
    $CI->email->message($htmlMessage);
    $CI->email->set_mailtype('html');
    return $CI->email->send(false);
}

function school_ia_send_sms_raw(string $phone, string $text, int $leadId): bool
{
    $accountid = (string) get_option('sia_sms_accountid');
    $password  = (string) get_option('sia_sms_password');
    $sender    = (string) (get_option('sia_sms_sender') ?: 'SchoolIA');
    $num = preg_replace('/\D+/', '', $phone);
    if ($accountid === '' || $password === '' || $num === '') {
        return false;
    }
    $body = json_encode([
        'accountid' => $accountid, 'password' => $password, 'sender' => $sender,
        'ret_id' => 'sia_seq_' . $leadId . '_' . time(), 'priority' => '2',
        'text' => $text, 'to' => [['sia_' . $leadId => $num]],
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init('https://lamsms.lafricamobile.com/api');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $resp !== false && $code >= 200 && $code < 300;
}

/**
 * Cron des séquences de relance : envoie les étapes dues et fait avancer
 * chaque inscription. S'arrête si le lead est inscrit ou perdu.
 */
hooks()->add_action('after_cron_run', 'school_ia_bridge_sequences_cron');
function school_ia_bridge_sequences_cron()
{
    $CI = &get_instance();
    $CI->load->model('school_ia_bridge/school_ia_bridge_model');
    $m = $CI->school_ia_bridge_model;

    foreach ($m->due_enrollments() as $en) {
        $lead = $m->get_lead((int) $en->lead_id);
        if (!$lead) {
            $m->stop_enrollment((int) $en->id);
            continue;
        }
        if (in_array($lead->stage, ['inscrit', 'perdu'], true)) {
            $m->stop_enrollment((int) $en->id);
            continue;
        }

        $step = $m->step_by_order((int) $en->sequence_id, (int) $en->next_step_order);
        if (!$step) {
            $m->complete_enrollment((int) $en->id);
            continue;
        }

        $tpl = $step->template_id ? $m->get_template((int) $step->template_id) : null;
        if ($tpl) {
            if ($step->channel === 'email' && $lead->email) {
                $subject = school_ia_personalize((string) $tpl->subject, $lead);
                $bodyTxt = school_ia_personalize((string) $tpl->body, $lead);
                if (school_ia_send_tracked_email($lead, $subject, $bodyTxt, 'sequence')) {
                    $m->add_activity((int) $lead->id, 'email', 'Séquence : ' . $subject, null);
                }
            } elseif ($step->channel === 'sms' && $lead->phone) {
                $text = school_ia_personalize((string) $tpl->body, $lead);
                $okSms = school_ia_send_sms_raw((string) $lead->phone, $text, (int) $lead->id);
                school_ia_log_sms((int) $lead->id, $okSms, 'sequence');
                if ($okSms) {
                    $m->add_activity((int) $lead->id, 'sms', 'Séquence : ' . mb_substr($text, 0, 100), null);
                }
            }
        }

        $next = $m->step_by_order((int) $en->sequence_id, (int) $en->next_step_order + 1);
        if ($next) {
            $runAt = date('Y-m-d H:i:s', time() + $next->delay_days * 86400 + $next->delay_hours * 3600);
            $m->advance_enrollment((int) $en->id, (int) $next->step_order, $runAt);
        } else {
            $m->complete_enrollment((int) $en->id);
        }
    }
}

/**
 * Feuille de style premium — chargée UNIQUEMENT sur les pages du module
 * (aucun impact sur le reste de Perfex).
 */
hooks()->add_action('app_admin_head', 'school_ia_bridge_head_css');
function school_ia_bridge_head_css()
{
    if (strpos((string) ($_SERVER['REQUEST_URI'] ?? ''), 'school_ia_bridge') === false) {
        return;
    }
    // Version = date de modification du fichier : le cache du navigateur est
    // invalidé automatiquement à chaque mise à jour du CSS (plus de ?v=xx figé).
    $ver = @filemtime(module_dir_path(SCHOOL_IA_BRIDGE_MODULE, 'assets/school_ia_admin.css')) ?: time();
    echo '<link rel="stylesheet" href="' . module_dir_url(SCHOOL_IA_BRIDGE_MODULE, 'assets/school_ia_admin.css') . '?v=' . $ver . '">';
}

/**
 * Icônes Google Material pour les onglets du menu. Chargé sur TOUTES les pages
 * admin (la barre latérale est globale). Perfex rend l'icône comme
 * <i class="{classe}"></i> sans texte : on injecte donc le glyphe via ::before.
 */
hooks()->add_action('app_admin_head', 'school_ia_bridge_menu_icons');
function school_ia_bridge_menu_icons()
{
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">';
    echo '<style>'
        . '.sia-mi{font-family:"Material Icons";font-weight:normal;font-style:normal;font-size:19px;'
        . 'line-height:1;display:inline-block;vertical-align:middle;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;}'
        . '#sidebar .menu li a .sia-mi,#sidebar .sia-mi{width:22px;text-align:center;}'
        . '.sia-mi::before{display:inline-block;}'
        . '.sia-mi-dashboard::before{content:"\e871";}'
        . '.sia-mi-contacts::before{content:"\e7ef";}'
        . '.sia-mi-tasks::before{content:"\e862";}'
        . '.sia-mi-reports::before{content:"\e85c";}'
        . '.sia-mi-campaign::before{content:"\ef49";}'
        . '.sia-mi-veille::before{content:"\e8f4";}'
        . '.sia-mi-templates::before{content:"\e873";}'
        . '.sia-mi-documents::before{content:"\e2c7";}'
        . '.sia-mi-config::before{content:"\e8b8";}'
        . '</style>';
}

/**
 * Enregistre les permissions du module (Setup → Rôles) et construit le menu
 * en fonction des droits du membre connecté.
 */
hooks()->add_action('admin_init', 'school_ia_bridge_admin_init');
function school_ia_bridge_admin_init()
{
    // --- Permissions (visibles dans Setup → Rôles) ---
    register_staff_capabilities('school_ia_bridge', [
        'capabilities' => [
            'view'            => _l('Accéder au CRM School IA'),
            'manage_leads'    => _l('Gérer les leads (ajout, import, étapes, tâches, notes)'),
            'send'            => _l('Envoyer e-mails / SMS (individuels et groupés)'),
            'view_reports'    => _l('Consulter les rapports & le journal d\'activité'),
            'manage_settings' => _l('Configurer (modèles, documents, séquences)'),
            'manage_config'   => _l('Réglages & diagnostic (connexion, clé IA, SMS, objectifs)'),
        ],
    ], _l('School IA CRM'));

    school_ia_bridge_admin_menu();
}

/**
 * Menu latéral, filtré selon les permissions.
 */
function school_ia_bridge_admin_menu()
{
    $CI = &get_instance();
    if (!staff_can('view', 'school_ia_bridge')) {
        return;
    }

    $canSend   = staff_can('send', 'school_ia_bridge');
    $canManage  = staff_can('manage_settings', 'school_ia_bridge');
    $canConfig  = staff_can('manage_config', 'school_ia_bridge');
    $canReports = staff_can('view_reports', 'school_ia_bridge');

    // 1. Tableau de bord (onglet de premier plan)
    $CI->app_menu->add_sidebar_menu_item('sia_dashboard', [
        'name'     => 'Tableau de bord',
        'href'     => admin_url('school_ia_bridge/dashboard'),
        'icon'     => 'sia-mi sia-mi-dashboard',
        'position' => 30,
    ]);

    // 2. Contact
    $CI->app_menu->add_sidebar_menu_item('sia_contacts', [
        'name'     => 'Contact',
        'href'     => admin_url('school_ia_bridge'),
        'icon'     => 'sia-mi sia-mi-contacts',
        'position' => 31,
    ]);

    // 3. Tâches
    $CI->app_menu->add_sidebar_menu_item('sia_tasks', [
        'name'     => 'Tâches',
        'href'     => admin_url('school_ia_bridge/tasks'),
        'icon'     => 'sia-mi sia-mi-tasks',
        'position' => 32,
    ]);

    // 4. Rapports (groupe) : Reporting + Journal — permission dédiée et restreinte
    if ($canReports) {
        $CI->app_menu->add_sidebar_menu_item('sia_reports', [
            'name'     => 'Rapports',
            'icon'     => 'sia-mi sia-mi-reports',
            'position' => 33,
        ]);
        $CI->app_menu->add_sidebar_children_item('sia_reports', [
            'slug' => 'sia_reporting', 'name' => 'Reporting',
            'href' => admin_url('school_ia_bridge/reporting'), 'position' => 1,
        ]);
        $CI->app_menu->add_sidebar_children_item('sia_reports', [
            'slug' => 'sia_journal', 'name' => 'Journal',
            'href' => admin_url('school_ia_bridge/activity'), 'position' => 2,
        ]);
    }

    // 5. Campagne (groupe) : Les campagnes (séquences) + Nouvelle campagne (envoi groupé) + Statistiques
    $CI->app_menu->add_sidebar_menu_item('sia_campaign', [
        'name'     => 'Campagne',
        'icon'     => 'sia-mi sia-mi-campaign',
        'position' => 34,
    ]);
    if ($canSend) {
        $CI->app_menu->add_sidebar_children_item('sia_campaign', [
            'slug' => 'sia_bulk', 'name' => 'Nouvelle campagne',
            'href' => admin_url('school_ia_bridge/bulk'), 'position' => 1,
        ]);
    }
    $CI->app_menu->add_sidebar_children_item('sia_campaign', [
        'slug' => 'sia_campaigns_list', 'name' => 'Campagnes',
        'href' => admin_url('school_ia_bridge/campaigns_list'), 'position' => 2,
    ]);
    if ($canManage) {
        $CI->app_menu->add_sidebar_children_item('sia_campaign', [
            'slug' => 'sia_sequences', 'name' => 'Séquences de relance',
            'href' => admin_url('school_ia_bridge/sequences'), 'position' => 3,
        ]);
    }
    $CI->app_menu->add_sidebar_children_item('sia_campaign', [
        'slug' => 'sia_stats', 'name' => 'Statistiques',
        'href' => admin_url('school_ia_bridge/campaigns'), 'position' => 4,
    ]);

    // 6. Veille
    $CI->app_menu->add_sidebar_menu_item('sia_veille', [
        'name'     => 'Veille',
        'href'     => admin_url('school_ia_bridge/competitors'),
        'icon'     => 'sia-mi sia-mi-veille',
        'position' => 35,
    ]);

    if ($canManage) {
        // 7. Modèles
        $CI->app_menu->add_sidebar_menu_item('sia_templates', [
            'name'     => 'Modèles',
            'href'     => admin_url('school_ia_bridge/templates'),
            'icon'     => 'sia-mi sia-mi-templates',
            'position' => 36,
        ]);
        // 8. Documents
        $CI->app_menu->add_sidebar_menu_item('sia_documents', [
            'name'     => 'Documents',
            'href'     => admin_url('school_ia_bridge/documents'),
            'icon'     => 'sia-mi sia-mi-documents',
            'position' => 37,
        ]);
    }
    // 9. Configuration (groupe) : Réglages + Diagnostic — permission dédiée et restreinte
    if ($canConfig) {
        $CI->app_menu->add_sidebar_menu_item('sia_config', [
            'name'     => 'Configuration',
            'icon'     => 'sia-mi sia-mi-config',
            'position' => 38,
        ]);
        $CI->app_menu->add_sidebar_children_item('sia_config', [
            'slug' => 'sia_settings', 'name' => 'Réglages',
            'href' => admin_url('school_ia_bridge/settings'), 'position' => 1,
        ]);
        $CI->app_menu->add_sidebar_children_item('sia_config', [
            'slug' => 'sia_debug', 'name' => 'Diagnostic',
            'href' => admin_url('school_ia_bridge/debug'), 'position' => 2,
        ]);
    }
}
