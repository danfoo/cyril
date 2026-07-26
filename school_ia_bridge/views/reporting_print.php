<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Version imprimable / PDF du reporting — document AUTONOME (pas d'init_head/
 * init_tail : aucun menu, barre latérale ni topbar de la plateforme). Ouverte
 * dans un nouvel onglet depuis le bouton « Imprimer / PDF », elle déclenche
 * automatiquement la boîte de dialogue d'impression du navigateur.
 */

$money = function ($v) use ($currency) { return number_format((float) $v, 0, ',', ' ') . ' ' . $currency; };

/** Variation vs période précédente, en texte sobre (pas de fond coloré ici). */
$delta = function ($cur, $prev): string {
    $c = (float) $cur; $p = (float) $prev;
    if ($p == 0.0) { return $c == 0.0 ? '— vs préc.' : '▲ nouveau vs préc.'; }
    $pct = (int) round(($c - $p) / $p * 100);
    if ($pct === 0) { return '→ 0 % vs préc.'; }
    return ($pct > 0 ? '▲ +' : '▼ ') . $pct . ' % vs préc.';
};

/** Carte KPI imprimable : accent coloré, gros chiffre, libellé, delta discret. */
$kpi = function (string $label, $value, string $accent, string $sub = '') {
    ob_start(); ?>
    <div class="pkpi" style="border-left-color:<?php echo $accent; ?>;">
      <div class="pkpi-val"><?php echo $value; ?></div>
      <div class="pkpi-lbl"><?php echo htmlspecialchars($label, ENT_QUOTES); ?></div>
      <?php if ($sub !== '') { ?><div class="pkpi-sub"><?php echo $sub; ?></div><?php } ?>
    </div>
    <?php return ob_get_clean();
};

/** Petit histogramme SVG à largeur FIXE (jamais d'overflow hors page à l'impression). */
$printBars = function (array $series, string $accent) {
    if (empty($series)) { return '<p class="muted">Aucune donnée sur cette période.</p>'; }
    $W = 900; $H = 130; $padTop = 16; $padBot = 22;
    $max = 1; foreach ($series as $s) { $max = max($max, (int) $s['value']); }
    $n = count($series); $slot = $W / max(1, $n);
    $barW = max(2, min(26, $slot * 0.55));
    $labelEvery = (int) max(1, ceil($n / 14));
    $chartH = $H - $padTop - $padBot;
    $svg = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" width="100%" height="' . $H . '" preserveAspectRatio="none" style="display:block;">';
    foreach ($series as $i => $s) {
        $val = (int) $s['value'];
        $h = $val > 0 ? max(2, round($val / $max * $chartH)) : 0;
        $x = $i * $slot + ($slot - $barW) / 2;
        $y = $padTop + ($chartH - $h);
        if ($h > 0) {
            $svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($barW, 1) . '" height="' . $h . '" rx="2" fill="' . $accent . '"/>';
            if ($n <= 20) {
                $svg .= '<text x="' . round($x + $barW / 2, 1) . '" y="' . round($y - 4, 1) . '" text-anchor="middle" font-size="9" font-weight="700" fill="#334155">' . $val . '</text>';
            }
        }
        if ($i % $labelEvery === 0) {
            $svg .= '<text x="' . round($x + $barW / 2, 1) . '" y="' . ($H - 6) . '" text-anchor="middle" font-size="9" fill="#94a3b8">' . htmlspecialchars((string) $s['label'], ENT_QUOTES) . '</text>';
        }
    }
    $svg .= '</svg>';
    return $svg;
};
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Rapport — <?php echo htmlspecialchars((string) $label, ENT_QUOTES); ?> — <?php echo htmlspecialchars((string) $schoolName, ENT_QUOTES); ?></title>
<style>
  @page { size: A4 landscape; margin: 12mm 12mm 16mm; }
  * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
  html, body { margin: 0; padding: 0; background: #fff; color: #1f2430;
    font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 11.5px; }
  h1, h2, h3 { margin: 0; }
  p { margin: 0; }
  table { border-collapse: collapse; width: 100%; }
  .muted { color: #7c8aa5; }
  .wrap { max-width: 1100px; margin: 0 auto; padding: 0 4mm; }

  /* En-tête */
  .phead { display: flex; align-items: center; gap: 18px; padding: 6mm 0 5mm; border-bottom: 3px solid <?php echo $brandColor; ?>; }
  .phead img { height: 44px; width: auto; display: block; }
  .phead .word { font-size: 24px; font-weight: 800; letter-spacing: -.01em; color: #0f172a; }
  .phead .word b { color: <?php echo $brandColor; ?>; }
  .phead .meta { margin-left: auto; text-align: right; }
  .phead .meta h1 { font-size: 18px; font-weight: 800; color: #0f172a; }
  .phead .meta .period { color: #475569; font-size: 12.5px; margin-top: 2px; }
  .phead .meta .gen { color: #94a3b8; font-size: 10.5px; margin-top: 4px; }

  /* Grilles KPI */
  .prow { display: grid; grid-template-columns: repeat(4, 1fr); gap: 7px; margin: 3.5mm 0; }
  .prow.c3 { grid-template-columns: repeat(3, 1fr); }
  .pkpi { border: 1px solid #e6e9f0; border-left: 4px solid #999; border-radius: 7px; padding: 7px 11px; background: #fbfcfe; break-inside: avoid; }
  .pkpi-val { font-size: 17px; font-weight: 800; color: #0f172a; line-height: 1.1; letter-spacing: -.01em; }
  .pkpi-lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-top: 3px; }
  .pkpi-sub { font-size: 10px; color: #94a3b8; margin-top: 3px; }

  /* Panneaux de section */
  .psection { display: grid; grid-template-columns: 1fr 1fr; gap: 7px; margin-bottom: 6px; }
  .psection.full { grid-template-columns: 1fr; }
  .pcard { border: 1px solid #e6e9f0; border-radius: 8px; padding: 8px 12px 10px; break-inside: avoid; page-break-inside: avoid; }
  .pcard h3 { font-size: 12px; font-weight: 800; color: #0f172a; padding-bottom: 5px; margin-bottom: 6px; border-bottom: 1px solid #eef1f6;
    display: flex; align-items: center; gap: 7px; }
  .pcard h3::before { content: ""; width: 8px; height: 8px; border-radius: 2px; background: <?php echo $brandColor; ?>; flex: 0 0 auto; }
  .pcard .note { font-size: 10px; color: #94a3b8; margin-top: 8px; }

  .plist li { display: flex; justify-content: space-between; gap: 10px; padding: 2.5px 0; border-bottom: 1px dashed #eef1f6; font-size: 11px; }
  .plist { list-style: none; margin: 0; padding: 0; }
  .plist li:last-child { border-bottom: 0; }
  .plist b { white-space: nowrap; }

  .pbar-row { margin-bottom: 6px; }
  .pbar-row .top { display: flex; justify-content: space-between; font-size: 11.5px; margin-bottom: 3px; }
  .pbar-row .track { height: 7px; border-radius: 4px; background: #eef1f6; overflow: hidden; }
  .pbar-row .fill { height: 7px; border-radius: 4px; }

  table.ptable th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; color: #94a3b8;
    padding: 5px 8px; border-bottom: 1px solid #e6e9f0; }
  table.ptable td { padding: 4.5px 8px; border-bottom: 1px solid #f1f4f9; font-size: 11px; }
  table.ptable td.r, table.ptable th.r { text-align: right; }

  .chips span { display: inline-block; background: #f1f4f9; color: #475569; border-radius: 999px; padding: 3px 10px; font-size: 10.5px; margin: 2px 4px 0 0; }

  .ai-content { font-size: 12px; line-height: 1.6; color: #1f2430; }
  .ai-content :is(h1,h2,h3,h4) { font-size: 12.5px; }

  /* Pied de page en fin de document (flux normal, pas position:fixed — Chrome
     introduit une page blanche parasite en fin d'impression avec un footer
     fixe répété). */
  .pfoot { margin-top: 4mm; padding-top: 2.5mm; font-size: 9.5px; color: #94a3b8;
    border-top: 1px solid #eef1f6; display: flex; justify-content: space-between; break-inside: avoid; }
  .pfoot b { color: <?php echo $brandColor; ?>; }

  .screen-actions { position: sticky; top: 0; z-index: 5; background: #0f172a; color: #fff; padding: 10px 16px;
    display: flex; gap: 10px; align-items: center; }
  .screen-actions button, .screen-actions a { background: <?php echo $brandColor; ?>; color: #fff; border: 0; border-radius: 7px;
    padding: 8px 16px; font-size: 13px; font-weight: 650; cursor: pointer; text-decoration: none; }
  .screen-actions a.ghost { background: transparent; border: 1px solid rgba(255,255,255,.35); }
  .screen-actions .hint { margin-left: auto; font-size: 12px; opacity: .75; }

  @media print { .no-print { display: none !important; } }
</style>
</head>
<body>

  <div class="screen-actions no-print">
    <button onclick="window.print()">🖨 Imprimer / Enregistrer en PDF</button>
    <a href="javascript:window.close();" class="ghost">Fermer</a>
    <span class="hint">Choisissez « Enregistrer en PDF » comme destination dans la boîte de dialogue d'impression.</span>
  </div>

  <div class="wrap">

    <div class="phead">
      <?php if (!empty($reportLogo)) { ?>
        <img src="<?php echo htmlspecialchars($reportLogo, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($schoolName, ENT_QUOTES); ?>">
      <?php } else { ?>
        <div class="word"><?php echo htmlspecialchars($schoolName, ENT_QUOTES); ?></div>
      <?php } ?>
      <div class="meta">
        <h1>Rapport d'admissions — <?php echo htmlspecialchars((string) $label, ENT_QUOTES); ?></h1>
        <div class="period">Du <?php echo date('d/m/Y', strtotime($from)); ?> au <?php echo date('d/m/Y', strtotime($to)); ?> · comparé à <?php echo htmlspecialchars((string) $prevLabel, ENT_QUOTES); ?></div>
        <div class="gen">Généré le <?php echo date('d/m/Y à H:i'); ?> · School AI</div>
      </div>
    </div>

    <!-- KPIs principaux -->
    <div class="prow">
      <?php
      echo $kpi('Nouveaux leads', (int) $agg['leads_total'], '#4f46e5', $delta($agg['leads_total'], $prevAgg['leads_total']));
      echo $kpi('Inscrits', (int) $agg['inscrits'], '#059669', $delta($agg['inscrits'], $prevAgg['inscrits']));
      echo $kpi('Taux de conversion', $agg['conversion'] . ' %', '#0d9488', $delta($agg['conversion'], $prevAgg['conversion']));
      echo $kpi('E-mails envoyés', (int) $agg['email_sent'], '#2563eb', $delta($agg['email_sent'], $prevAgg['email_sent']));
      ?>
    </div>
    <div class="prow c3">
      <?php
      $frh = $agg['first_response_hours'] ?? null;
      $frVal = $frh === null ? '—' : ((float) $frh < 24 ? number_format((float) $frh, 1, ',', ' ') . ' h' : number_format((float) $frh / 24, 1, ',', ' ') . ' j');
      $cvd = $agg['conversion_days'] ?? null;
      $cvVal = $cvd === null ? '—' : number_format((float) $cvd, 1, ',', ' ') . ' j';
      echo $kpi('Délai moyen 1re réponse', $frVal, '#d97706', 'réception → 1re action');
      echo $kpi('Délai moyen de conversion', $cvVal, '#7c3aed', 'réception → inscription');
      echo $kpi('SMS envoyés', (int) $agg['sms_sent'], '#0d9488', $delta($agg['sms_sent'], $prevAgg['sms_sent']));
      ?>
    </div>

    <?php if (!empty($agg['has_fees'])) { ?>
    <div class="prow c3">
      <?php
      echo $kpi('CA réalisé (période)', $money($agg['finance_realized']), '#059669', $delta($agg['finance_realized'], $prevAgg['finance_realized']) . ' · basé sur la date de conversion');
      echo $kpi('Valeur ajoutée au pipeline', $money($agg['finance_pipeline']), '#8a63d2', $delta($agg['finance_pipeline'], $prevAgg['finance_pipeline']) . ' · leads reçus cette période');
      $fc = $financeForecast ?? ['has_fees' => false];
      echo $kpi('Revenu prévisionnel', !empty($fc['has_fees']) ? $money($fc['projected']) : '—', '#d97706',
          !empty($fc['has_fees']) ? 'taux de conversion historique : ' . $fc['conversion_rate'] . ' %' : '');
      ?>
    </div>
    <?php } ?>

    <!-- Tendance -->
    <div class="pcard" style="margin-bottom:8px;">
      <h3>Évolution des nouveaux leads</h3>
      <?php echo $printBars($series, $brandColor); ?>
    </div>

    <!-- Entonnoir + formations -->
    <div class="psection">
      <div class="pcard">
        <h3>Entonnoir par étape</h3>
        <ul class="plist">
          <?php foreach ($model->stages() as $slug => $conf) {
              $n = (int) ($agg['by_stage'][$conf[0]] ?? 0);
              $pct = $agg['leads_total'] > 0 ? round($n * 100 / $agg['leads_total']) : 0; ?>
            <li><span><?php echo htmlspecialchars($conf[0], ENT_QUOTES); ?></span><b><?php echo $n; ?> · <?php echo $pct; ?> %</b></li>
          <?php } ?>
        </ul>
      </div>
      <div class="pcard">
        <h3>Par formation</h3>
        <ul class="plist">
          <?php foreach ($agg['top_formations'] as $f => $n) {
              $pct = $agg['leads_total'] > 0 ? round($n * 100 / $agg['leads_total']) : 0; ?>
            <li><span><?php echo htmlspecialchars((string) $f, ENT_QUOTES); ?></span><b><?php echo $n; ?> · <?php echo $pct; ?> %</b></li>
          <?php } ?>
        </ul>
      </div>
    </div>

    <?php if (!empty($agg['has_fees']) && !empty($agg['revenue_by_formation'])) {
        $revMax = max($agg['revenue_by_formation']); ?>
    <!-- Revenu par formation -->
    <div class="psection full">
      <div class="pcard">
        <h3>Revenu par formation</h3>
        <?php foreach ($agg['revenue_by_formation'] as $f => $val) {
            $w = $revMax > 0 ? max(4, round($val * 100 / $revMax)) : 0; ?>
          <div class="pbar-row">
            <div class="top"><span><?php echo htmlspecialchars((string) $f, ENT_QUOTES); ?></span><b><?php echo number_format((float) $val, 0, ',', ' ') . ' ' . htmlspecialchars((string) $currency, ENT_QUOTES); ?></b></div>
            <div class="track"><div class="fill" style="width:<?php echo $w; ?>%;background:<?php echo $brandColor; ?>;"></div></div>
          </div>
        <?php } ?>
      </div>
    </div>
    <?php } ?>

    <!-- Acquisition + motifs de perte -->
    <div class="psection">
      <div class="pcard">
        <h3>Sources d'acquisition (canal)</h3>
        <ul class="plist">
          <?php foreach (($agg['by_channel'] ?? []) as $ch => $n) {
              $pct = $agg['leads_total'] > 0 ? round($n * 100 / $agg['leads_total']) : 0; ?>
            <li><span><?php echo htmlspecialchars((string) $ch, ENT_QUOTES); ?></span><b><?php echo $n; ?> · <?php echo $pct; ?> %</b></li>
          <?php } ?>
        </ul>
        <?php if (!empty($agg['by_campaign'])) { ?>
          <div class="note" style="margin-top:10px;font-weight:700;color:#64748b;">Campagnes</div>
          <ul class="plist">
            <?php foreach ($agg['by_campaign'] as $camp => $n) { ?>
              <li><span><?php echo htmlspecialchars((string) $camp, ENT_QUOTES); ?></span><b><?php echo $n; ?></b></li>
            <?php } ?>
          </ul>
        <?php } ?>
      </div>
      <div class="pcard">
        <h3>Motifs de perte</h3>
        <?php if (empty($agg['loss_reasons'])) { ?>
          <p class="muted">Aucune donnée sur cette période.</p>
        <?php } else { ?>
          <ul class="plist">
            <?php foreach ($agg['loss_reasons'] as $r => $n) { ?>
              <li><span><?php echo htmlspecialchars((string) $r, ENT_QUOTES); ?></span><b><?php echo $n; ?></b></li>
            <?php } ?>
          </ul>
        <?php } ?>
      </div>
    </div>

    <!-- Conseillers + engagement -->
    <div class="psection">
      <div class="pcard">
        <h3>Performance par conseiller</h3>
        <table class="ptable">
          <thead><tr><th>Conseiller</th><th class="r">Leads</th><th class="r">Inscrits</th><th class="r">Conv.</th></tr></thead>
          <tbody>
            <?php if (empty($byStaff)) { ?>
              <tr><td colspan="4" class="muted">Aucun lead assigné à un conseiller sur cette période.</td></tr>
            <?php } else {
                foreach ($byStaff as $st) {
                    $tx = $st->total > 0 ? round($st->inscrits * 100 / $st->total, 1) : 0; ?>
              <tr>
                <td><?php echo htmlspecialchars((string) $st->name, ENT_QUOTES); ?></td>
                <td class="r"><?php echo (int) $st->total; ?></td>
                <td class="r"><?php echo (int) $st->inscrits; ?></td>
                <td class="r"><?php echo $tx; ?> %</td>
              </tr>
            <?php }
            } ?>
          </tbody>
        </table>
      </div>
      <div class="pcard">
        <h3>Canaux d'engagement</h3>
        <div class="prow" style="grid-template-columns:repeat(3,1fr);margin:0 0 8px;">
          <?php
          echo $kpi('E-mails envoyés', (int) $agg['email_sent'], '#2563eb');
          echo $kpi('Ouverts', (int) $agg['email_opened'] . ' (' . $agg['open_rate'] . ' %)', '#16a34a');
          echo $kpi('Cliqués', (int) $agg['email_clicked'] . ' (' . $agg['click_rate'] . ' %)', '#8a63d2');
          ?>
        </div>
        <div class="prow" style="grid-template-columns:repeat(3,1fr);margin:0;">
          <?php
          echo $kpi('SMS envoyés', (int) $agg['sms_sent'], '#0d9488');
          echo $kpi('SMS en échec', (int) $agg['sms_failed'], '#dc2626');
          echo $kpi('Interactions', array_sum($activityBreakdown ?? []), '#d11349');
          ?>
        </div>
        <?php if (!empty($activityBreakdown)) {
            $actLabels = ['note' => 'Notes', 'email' => 'E-mails', 'sms' => 'SMS', 'stage_change' => 'Changements d\'étape', 'task' => 'Tâches', 'assignment' => 'Assignations']; ?>
          <div class="chips" style="margin-top:10px;">
            <?php foreach ($activityBreakdown as $type => $n) { ?>
              <span><?php echo htmlspecialchars($actLabels[$type] ?? $type, ENT_QUOTES); ?> : <?php echo (int) $n; ?></span>
            <?php } ?>
          </div>
        <?php } ?>
      </div>
    </div>

    <?php if ($report) { ?>
    <!-- Synthèse IA -->
    <div class="psection full">
      <div class="pcard">
        <h3>Synthèse rédigée par l'IA — <?php echo htmlspecialchars((string) $report->label, ENT_QUOTES); ?></h3>
        <div class="ai-content"><?php echo $report->content; /* HTML produit par l'appel IA */ ?></div>
      </div>
    </div>
    <?php } ?>

    <div class="pfoot">
      <span><b>School AI</b> · <?php echo htmlspecialchars($schoolName, ENT_QUOTES); ?> — Rapport confidentiel généré automatiquement</span>
      <span><?php echo date('d/m/Y H:i'); ?></span>
    </div>

  </div>

<script>
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 250); });
</script>
</body>
</html>
