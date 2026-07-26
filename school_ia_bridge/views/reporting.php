<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">

    <div class="clearfix" style="margin-bottom:15px;">
      <h4 class="no-margin pull-left"><i class="fa fa-file-text"></i> Reporting <?php echo sia_help('reporting'); ?></h4>
      <div class="pull-right sia-no-print">
        <a href="<?php echo admin_url('school_ia_bridge/reporting_export?period=' . urlencode($period) . ($date ? '&date=' . urlencode($date) : '')); ?>" class="btn btn-default">
          <i class="fa fa-download"></i> Exporter (CSV)
        </a>
        <a href="<?php echo admin_url('school_ia_bridge/reporting_print?period=' . urlencode($period) . ($date ? '&date=' . urlencode($date) : '') . ($report ? '&report=' . (int) $report->id : '')); ?>"
           target="_blank" rel="noopener" class="btn btn-default"><i class="fa fa-print"></i> Imprimer / PDF</a>
      </div>
    </div>

    <?php
    // Variation vs période précédente, rendue en pastille blanche lisible sur fond dégradé.
    $rdelta = function ($cur, $prev): string {
        $c = (float) $cur; $p = (float) $prev;
        if ($p == 0.0) {
            $txt = ($c == 0.0) ? '— vs préc.' : '▲ nouveau vs préc.';
        } else {
            $pct = (int) round(($c - $p) / $p * 100);
            $txt = $pct === 0 ? '→ 0 % vs préc.' : (($pct > 0 ? '▲ +' : '▼ ') . $pct . ' % vs préc.');
        }
        return '<span style="display:inline-block;background:rgba(255,255,255,.22);color:#fff;'
            . 'font-size:11px;font-weight:700;padding:2px 9px;border-radius:20px;">' . $txt . '</span>';
    };

    // Carte KPI en dégradé, même style que le tableau de bord.
    $rkpi = function (string $label, $value, string $grad, string $icon, string $delta = '', string $colClass = 'col-lg-3 col-sm-6') {
        ob_start(); ?>
        <div class="<?php echo $colClass; ?> sia-stat-col">
          <div class="sia-stat-card" style="position:relative;overflow:hidden;border-radius:16px;padding:18px 20px;min-height:104px;color:#fff;display:flex;flex-direction:column;justify-content:center;background:<?php echo $grad; ?>;box-shadow:0 6px 18px rgba(15,23,42,.14);">
            <div style="position:relative;z-index:1;font-size:26px;font-weight:800;line-height:1.08;letter-spacing:-.02em;"><?php echo $value; ?></div>
            <div style="position:relative;z-index:1;font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;opacity:.92;margin-top:5px;"><?php echo $label; ?></div>
            <?php if ($delta !== '') { ?><div style="position:relative;z-index:1;margin-top:8px;"><?php echo $delta; ?></div><?php } ?>
            <i class="fa <?php echo $icon; ?>" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:54px;opacity:.20;"></i>
          </div>
        </div>
        <?php return ob_get_clean();
    };
    ?>

    <!-- Sélecteur de période -->
    <div class="panel_s sia-no-print"><div class="panel-body">
      <?php echo form_open(admin_url('school_ia_bridge/reporting'), ['method' => 'get', 'class' => 'row']); ?>
        <div class="col-sm-3">
          <label class="control-label">Type de rapport</label>
          <select name="period" class="form-control">
            <?php foreach (['day' => 'Journalier', 'week' => 'Hebdomadaire', 'month' => 'Mensuel', 'year' => 'Annuel'] as $k => $lab) { ?>
              <option value="<?php echo $k; ?>" <?php echo $period === $k ? 'selected' : ''; ?>><?php echo $lab; ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="col-sm-3">
          <label class="control-label">Date de référence</label>
          <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date, ENT_QUOTES); ?>">
        </div>
        <div class="col-sm-2">
          <label class="control-label">&nbsp;</label>
          <button type="submit" class="btn btn-default btn-block">Aperçu</button>
        </div>
      <?php echo form_close(); ?>
      <p class="text-muted" style="margin-top:10px;"><strong><?php echo htmlspecialchars($label, ENT_QUOTES); ?></strong> · du <?php echo date('d/m/Y', strtotime($from)); ?> au <?php echo date('d/m/Y', strtotime($to)); ?> · comparé à : <?php echo htmlspecialchars($prevLabel, ENT_QUOTES); ?></p>
    </div></div>

    <!-- KPIs + comparaison période précédente -->
    <div class="row sia-kpi-grid">
      <?php
      echo $rkpi('Nouveaux leads', (int) $agg['leads_total'], 'linear-gradient(135deg,#6366f1,#4f46e5)', 'fa-users', $rdelta($agg['leads_total'], $prevAgg['leads_total']));
      echo $rkpi('Inscrits', (int) $agg['inscrits'], 'linear-gradient(135deg,#34d399,#059669)', 'fa-graduation-cap', $rdelta($agg['inscrits'], $prevAgg['inscrits']));
      echo $rkpi('Taux de conversion', $agg['conversion'] . ' %', 'linear-gradient(135deg,#2dd4bf,#0d9488)', 'fa-line-chart', $rdelta($agg['conversion'], $prevAgg['conversion']));
      echo $rkpi('E-mails envoyés', (int) $agg['email_sent'], 'linear-gradient(135deg,#38bdf8,#2563eb)', 'fa-envelope', $rdelta($agg['email_sent'], $prevAgg['email_sent']));
      ?>
    </div>

    <!-- KPIs délais (temps de traitement) -->
    <div class="row sia-kpi-grid">
      <?php
      $frh = $agg['first_response_hours'] ?? null;
      $frVal = $frh === null ? '—' : ((float) $frh < 24 ? number_format((float) $frh, 1, ',', ' ') . ' h' : number_format((float) $frh / 24, 1, ',', ' ') . ' j');
      $cvd = $agg['conversion_days'] ?? null;
      $cvVal = $cvd === null ? '—' : number_format((float) $cvd, 1, ',', ' ') . ' j';
      echo $rkpi('Délai moyen 1re réponse', $frVal, 'linear-gradient(135deg,#fbbf24,#d97706)', 'fa-clock-o', 'réception → 1re action', 'col-lg-4 col-sm-6');
      echo $rkpi('Délai moyen de conversion', $cvVal, 'linear-gradient(135deg,#a78bfa,#7c3aed)', 'fa-hourglass-half', 'réception → inscription', 'col-lg-4 col-sm-6');
      echo $rkpi('SMS envoyés', (int) $agg['sms_sent'], 'linear-gradient(135deg,#2dd4bf,#0d9488)', 'fa-mobile', $rdelta($agg['sms_sent'], $prevAgg['sms_sent']), 'col-lg-4 col-sm-6');
      ?>
    </div>

    <?php if (!empty($agg['has_fees'])) {
        $money = function ($v) use ($currency) { return number_format((float) $v, 0, ',', ' ') . ' ' . $currency; }; ?>
    <!-- KPIs financiers -->
    <div class="row sia-kpi-grid">
      <?php
      echo $rkpi('CA réalisé (période)', $money($agg['finance_realized']), 'linear-gradient(135deg,#34d399,#059669)', 'fa-money',
          $rdelta($agg['finance_realized'], $prevAgg['finance_realized']) . '<div class="text-muted" style="margin-top:4px;font-size:11px;color:rgba(255,255,255,.85) !important;">basé sur la date de conversion</div>',
          'col-lg-4 col-sm-6');
      echo $rkpi('Valeur ajoutée au pipeline', $money($agg['finance_pipeline']), 'linear-gradient(135deg,#8a63d2,#6d3fc4)', 'fa-line-chart',
          $rdelta($agg['finance_pipeline'], $prevAgg['finance_pipeline']) . '<div class="text-muted" style="margin-top:4px;font-size:11px;color:rgba(255,255,255,.85) !important;">leads reçus cette période</div>',
          'col-lg-4 col-sm-6');
      $fc = $financeForecast ?? ['has_fees' => false];
      echo $rkpi('Revenu prévisionnel', !empty($fc['has_fees']) ? $money($fc['projected']) : '—', 'linear-gradient(135deg,#f59e0b,#d97706)', 'fa-magic',
          !empty($fc['has_fees']) ? '<span style="display:inline-block;background:rgba(255,255,255,.22);color:#fff;font-size:11px;font-weight:700;padding:2px 9px;border-radius:20px;">Taux de conversion historique : ' . $fc['conversion_rate'] . ' %</span>'
              . '<div class="text-muted" style="margin-top:4px;font-size:11px;color:rgba(255,255,255,.85) !important;">pipeline actuel × taux historique</div>' : '',
          'col-lg-4 col-sm-6');
      ?>
    </div>
    <?php } ?>

    <!-- Tendance des nouveaux leads -->
    <div class="panel_s"><div class="panel-body">
      <h5 class="bold" style="margin-top:0;">
        <span class="sia-panel-icon sia-ic-primary"><i class="fa fa-bar-chart"></i></span>
        Évolution des nouveaux leads
      </h5>
      <?php echo sia_bar_chart($series); ?>
    </div></div>

    <!-- Entonnoir + formations -->
    <div class="row">
      <div class="col-md-6">
        <div class="panel_s sia-panel-fill"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">
            <span class="sia-panel-icon sia-ic-info"><i class="fa fa-filter"></i></span>
            Entonnoir par étape
          </h5>
          <?php
          $stageSlices = [];
          foreach ($model->stages() as $slug => $conf) {
              $stageSlices[] = ['label' => $conf[0], 'value' => (int) ($agg['by_stage'][$conf[0]] ?? 0), 'color' => $conf[1]];
          }
          echo sia_pie_block($stageSlices, 150, true); // true = affiche toutes les étapes, même à 0
          ?>
        </div></div>
      </div>
      <div class="col-md-6">
        <div class="panel_s sia-panel-fill"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">
            <span class="sia-panel-icon" style="background:#8a63d21a;color:#8a63d2;"><i class="fa fa-graduation-cap"></i></span>
            Par formation
          </h5>
          <?php
          $formPalette = ['#4f46e5', '#2563eb', '#0ea5e9', '#0a8f5b', '#d97706', '#e2683c', '#dc2626', '#8a63d2'];
          $formSlices = [];
          $i = 0;
          foreach ($agg['top_formations'] as $f => $n) {
              $formSlices[] = ['label' => (string) $f, 'value' => (int) $n, 'color' => $formPalette[$i % count($formPalette)]];
              $i++;
          }
          echo sia_pie_block($formSlices);
          ?>
        </div></div>
      </div>
    </div>

    <?php if (!empty($agg['has_fees']) && !empty($agg['revenue_by_formation'])) { ?>
    <!-- Revenu par formation -->
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s sia-panel-fill"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">
            <span class="sia-panel-icon" style="background:#0596691a;color:#059669;"><i class="fa fa-money"></i></span>
            Revenu par formation
          </h5>
          <p class="text-muted" style="margin:0 0 14px;font-size:12.5px;">
            Valeur (pipeline + réalisé) des leads reçus sur la période, par formation — un programme à faible volume
            mais coûteux peut peser plus qu'un programme à fort volume mais économique.
          </p>
          <?php
          $revMax = max($agg['revenue_by_formation']);
          $revPalette = ['#059669', '#0d9488', '#2563eb', '#8a63d2', '#d97706', '#e2683c', '#dc2626'];
          $ri = 0;
          foreach ($agg['revenue_by_formation'] as $formLabel => $val) {
              $pctWidth = $revMax > 0 ? max(4, round($val * 100 / $revMax)) : 0;
              $barColor = $revPalette[$ri % count($revPalette)]; $ri++;
              ?>
              <div style="margin-bottom:10px;">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                  <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding-right:10px;"><?php echo htmlspecialchars((string) $formLabel, ENT_QUOTES); ?></span>
                  <strong style="white-space:nowrap;"><?php echo number_format((float) $val, 0, ',', ' '); ?> <?php echo htmlspecialchars((string) $currency, ENT_QUOTES); ?></strong>
                </div>
                <div style="height:8px;border-radius:5px;background:var(--sia-border,#e6e9f0);overflow:hidden;">
                  <div style="height:8px;border-radius:5px;width:<?php echo $pctWidth; ?>%;background:<?php echo $barColor; ?>;"></div>
                </div>
              </div>
          <?php } ?>
        </div></div>
      </div>
    </div>
    <?php } ?>

    <!-- Sources d'acquisition -->
    <div class="row">
      <div class="col-md-6">
        <div class="panel_s sia-panel-fill"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">
            <span class="sia-panel-icon" style="background:#0a8f5b1a;color:#0a8f5b;"><i class="fa fa-share-alt"></i></span>
            Sources d'acquisition
          </h5>
          <?php
          $srcPalette = ['#0a8f5b', '#2563eb', '#d97706', '#8a63d2', '#dc2626', '#0ea5e9', '#e2683c'];
          $chSlices = [];
          $j = 0;
          foreach (($agg['by_channel'] ?? []) as $ch => $n) {
              $chSlices[] = ['label' => (string) $ch, 'value' => (int) $n, 'color' => $srcPalette[$j % count($srcPalette)]];
              $j++;
          }
          echo sia_pie_block($chSlices);
          // Détail des campagnes (utm_campaign) sous forme de liste compacte.
          if (!empty($agg['by_campaign'])) {
              echo '<div style="margin-top:14px;border-top:1px solid var(--sia-border,#e6e9f0);padding-top:10px;">';
              echo '<div class="text-muted" style="font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;font-weight:700;margin-bottom:6px;">Campagnes</div>';
              foreach ($agg['by_campaign'] as $camp => $n) {
                  echo '<div style="display:flex;justify-content:space-between;font-size:13px;padding:3px 0;">'
                     . '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . htmlspecialchars((string) $camp, ENT_QUOTES) . '</span>'
                     . '<strong style="padding-left:10px;">' . (int) $n . '</strong></div>';
              }
              echo '</div>';
          }
          ?>
          <p class="text-muted" style="margin:12px 0 0;font-size:12px;">
            <i class="fa fa-info-circle"></i> Canal <strong>détecté automatiquement</strong> (identifiant de clic
            publicitaire ou site référent) — aucun réglage requis. Ajoutez des <strong>UTM</strong> à vos liens
            (ex. <code>?utm_source=facebook&amp;utm_campaign=rentree</code>) seulement pour nommer précisément une campagne.
          </p>
        </div></div>
      </div>
      <div class="col-md-6">
        <div class="panel_s sia-panel-fill"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">
            <span class="sia-panel-icon" style="background:#dc26261a;color:#dc2626;"><i class="fa fa-times-circle"></i></span>
            Motifs de perte
          </h5>
          <?php
          $lossPalette = ['#dc2626', '#e2683c', '#d97706', '#8a63d2', '#64748b', '#0ea5e9'];
          $lossSlices = [];
          $k = 0;
          foreach (($agg['loss_reasons'] ?? []) as $reason => $n) {
              $lossSlices[] = ['label' => (string) $reason, 'value' => (int) $n, 'color' => $lossPalette[$k % count($lossPalette)]];
              $k++;
          }
          echo sia_pie_block($lossSlices);
          ?>
          <p class="text-muted" style="margin:12px 0 0;font-size:12px;">
            <i class="fa fa-info-circle"></i> Renseigné au passage d'un lead en « Perdu » (depuis sa fiche).
          </p>
        </div></div>
      </div>
    </div>

    <!-- Conseillers + canaux d'engagement -->
    <div class="row">
      <div class="col-md-6">
        <div class="panel_s sia-panel-fill"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">
            <span class="sia-panel-icon sia-ic-success"><i class="fa fa-users"></i></span>
            Performance par conseiller
          </h5>
          <table class="table no-margin">
            <thead><tr><th>Conseiller</th><th class="text-right">Leads</th><th class="text-right">Inscrits</th><th class="text-right">Conv.</th></tr></thead>
            <tbody>
              <?php if (empty($byStaff)) { ?>
                <tr><td colspan="4" class="text-muted">Aucun lead assigné à un conseiller sur cette période.</td></tr>
              <?php } else {
                  foreach ($byStaff as $st) {
                      $tx = $st->total > 0 ? round($st->inscrits * 100 / $st->total, 1) : 0; ?>
                <tr>
                  <td><?php echo htmlspecialchars((string) $st->name, ENT_QUOTES); ?></td>
                  <td class="text-right"><?php echo (int) $st->total; ?></td>
                  <td class="text-right"><?php echo (int) $st->inscrits; ?></td>
                  <td class="text-right"><span class="label label-success"><?php echo $tx; ?> %</span></td>
                </tr>
              <?php }
              } ?>
            </tbody>
          </table>
        </div></div>
      </div>
      <div class="col-md-6">
        <div class="panel_s sia-panel-fill"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">
            <span class="sia-panel-icon" style="background:#2563eb1a;color:#2563eb;"><i class="fa fa-paper-plane"></i></span>
            Canaux d'engagement
          </h5>
          <div class="sia-stat-row" style="margin-bottom:6px;">
            <div class="sia-stat">
              <div class="sia-stat-value" style="color:#2563eb;"><?php echo (int) $agg['email_sent']; ?></div>
              <div class="sia-stat-label">E-mails envoyés</div>
            </div>
            <div class="sia-stat">
              <div class="sia-stat-value" style="color:#16a34a;"><?php echo (int) $agg['email_opened']; ?> <small style="font-size:14px;color:var(--sia-muted);">(<?php echo $agg['open_rate']; ?> %)</small></div>
              <div class="sia-stat-label">Ouverts</div>
            </div>
            <div class="sia-stat">
              <div class="sia-stat-value" style="color:#8a63d2;"><?php echo (int) $agg['email_clicked']; ?> <small style="font-size:14px;color:var(--sia-muted);">(<?php echo $agg['click_rate']; ?> %)</small></div>
              <div class="sia-stat-label">Cliqués</div>
            </div>
          </div>
          <hr style="margin:12px 0;">
          <div class="sia-stat-row">
            <div class="sia-stat">
              <div class="sia-stat-value" style="color:#0d9488;"><?php echo (int) $agg['sms_sent']; ?></div>
              <div class="sia-stat-label">SMS envoyés</div>
            </div>
            <div class="sia-stat">
              <div class="sia-stat-value" style="color:#dc2626;"><?php echo (int) $agg['sms_failed']; ?></div>
              <div class="sia-stat-label">SMS en échec</div>
            </div>
            <div class="sia-stat">
              <?php
              $interTotal = array_sum($activityBreakdown);
              ?>
              <div class="sia-stat-value" style="color:#d11349;"><?php echo (int) $interTotal; ?></div>
              <div class="sia-stat-label">Interactions</div>
            </div>
          </div>
          <?php if (!empty($activityBreakdown)) {
              $actLabels = ['note' => 'Notes', 'email' => 'E-mails', 'sms' => 'SMS', 'stage_change' => 'Changements d\'étape', 'task' => 'Tâches', 'assignment' => 'Assignations']; ?>
            <div class="sia-doc-chips" style="margin-top:12px;">
              <?php foreach ($activityBreakdown as $type => $n) { ?>
                <span class="label label-default"><?php echo htmlspecialchars(($actLabels[$type] ?? ucfirst($type)) . ' : ' . (int) $n, ENT_QUOTES); ?></span>
              <?php } ?>
            </div>
          <?php } ?>
          <p class="text-muted" style="margin:12px 0 0; font-size:12px;">
            <i class="fa fa-info-circle"></i> WhatsApp, appels et rendez-vous ne sont pas encore suivis automatiquement — enregistrez-les en <strong>note</strong> sur la fiche lead pour qu'ils remontent ici.
          </p>
        </div></div>
      </div>
    </div>

    <!-- Rapport IA -->
    <?php $reportBase = admin_url('school_ia_bridge/reporting?period=' . urlencode($period) . ($date ? '&date=' . urlencode($date) : '')); ?>
    <div class="panel_s"><div class="panel-body">
      <div class="clearfix" style="margin-bottom:6px;">
        <h5 class="bold pull-left" style="margin-top:0;">
          <span class="sia-panel-icon" style="background:#8a63d21a;color:#8a63d2;"><i class="fa fa-magic"></i></span>
          Rapport rédigé par l'IA
          <?php if (!empty($reports)) { ?><span class="label label-default" style="margin-left:4px;"><?php echo count($reports); ?></span><?php } ?>
        </h5>
        <div class="pull-right sia-no-print" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <?php if (!empty($reports)) { ?>
            <select class="form-control input-sm" style="height:34px;min-width:230px;"
                    onchange="if(this.value)location.href='<?php echo $reportBase; ?>&report='+this.value;">
              <option value="">— Choisir un rapport (<?php echo count($reports); ?>) —</option>
              <?php foreach ($reports as $r) { ?>
                <option value="<?php echo (int) $r->id; ?>" <?php echo ($report && (int) $report->id === (int) $r->id) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars((string) $r->label . ' · ' . date('d/m/Y H:i', strtotime((string) $r->created_at)), ENT_QUOTES); ?>
                </option>
              <?php } ?>
            </select>
          <?php } ?>
          <?php if ($ai_ready) { ?>
            <?php echo form_open(admin_url('school_ia_bridge/reporting_generate'), ['style' => 'display:inline;', 'onsubmit' => "this.querySelector('button').disabled=true;this.querySelector('button').innerHTML='Génération…';"]); ?>
              <input type="hidden" name="period" value="<?php echo htmlspecialchars($period, ENT_QUOTES); ?>">
              <input type="hidden" name="date" value="<?php echo htmlspecialchars($date, ENT_QUOTES); ?>">
              <button type="submit" class="btn btn-primary"><i class="fa fa-magic"></i> <?php echo $report ? 'Nouveau' : 'Générer avec l\'IA'; ?></button>
            <?php echo form_close(); ?>
          <?php } ?>
        </div>
      </div>
      <?php if (!$ai_ready) { ?>
        <div class="alert alert-warning" style="margin-top:10px;">Configurez d'abord votre clé API Anthropic dans <a href="<?php echo admin_url('school_ia_bridge/settings'); ?>">Réglages → Rapports IA</a>.</div>
      <?php } ?>

      <?php if ($report) { ?>
        <div class="clearfix" style="border-top:1px solid var(--sia-border);padding-top:10px;margin-top:4px;margin-bottom:8px;">
          <span class="pull-left"><strong><?php echo htmlspecialchars((string) $report->label, ENT_QUOTES); ?></strong> <span class="text-muted" style="font-size:12px;">· généré le <?php echo htmlspecialchars(date('d/m/Y à H:i', strtotime((string) $report->created_at)), ENT_QUOTES); ?></span></span>
          <span class="pull-right sia-no-print">
            <a href="<?php echo admin_url('school_ia_bridge/reporting_delete/' . (int) $report->id); ?>" class="text-muted" style="font-size:12px;"
               onclick="return confirm('Supprimer ce rapport ?');"><i class="fa fa-trash"></i> Supprimer</a>
          </span>
        </div>
        <div class="sia-report-box">
          <div class="sia-report" style="line-height:1.55;">
            <?php echo $report->content; /* HTML produit par notre appel IA */ ?>
          </div>
        </div>
        <div class="sia-no-print" style="margin-top:12px;">
          <?php echo form_open(admin_url('school_ia_bridge/reporting_email'), ['class' => 'form-inline']); ?>
            <input type="hidden" name="report_id" value="<?php echo (int) $report->id; ?>">
            <div class="form-group" style="margin-right:6px;">
              <input type="email" name="email" class="form-control input-sm" placeholder="destinataire@exemple.com" style="min-width:240px;" required>
            </div>
            <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-paper-plane"></i> Envoyer par e-mail</button>
          <?php echo form_close(); ?>
        </div>
      <?php } else { ?>
        <p class="text-muted" style="margin-top:10px;">Aucun rapport IA pour cette période. Cliquez « Générer avec l'IA » pour produire une synthèse (points forts, points de vigilance, recommandations).</p>
      <?php } ?>
    </div></div>

  </div>
</div>
<?php init_tail(); ?>
</body>
</html>
