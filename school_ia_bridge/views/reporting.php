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
        <button type="button" class="btn btn-default" onclick="window.print()"><i class="fa fa-print"></i> Imprimer / PDF</button>
      </div>
    </div>

    <?php
    // Générateur de carte KPI avec variation vs période précédente.
    $rkpi = function (string $label, $value, string $color, string $icon, string $delta = '') {
        ob_start(); ?>
        <div class="col-md-3 col-sm-6 sia-stat-col">
          <div class="panel_s" style="width:100%;"><div class="panel-body">
            <div style="display:flex;align-items:center;gap:12px;">
              <div class="sia-kpi-icon" style="color:<?php echo $color; ?>;background:<?php echo $color; ?>1a;"><i class="fa <?php echo $icon; ?>"></i></div>
              <div>
                <div class="sia-kpi-value"><?php echo $value; ?></div>
                <div class="sia-kpi-label"><?php echo $label; ?></div>
              </div>
            </div>
            <?php if ($delta !== '') { ?><div style="margin-top:10px;"><?php echo $delta; ?></div><?php } ?>
          </div></div>
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
      echo $rkpi('Nouveaux leads', (int) $agg['leads_total'], '#4f46e5', 'fa-users', sia_delta($agg['leads_total'], $prevAgg['leads_total']));
      echo $rkpi('Inscrits', (int) $agg['inscrits'], '#16a34a', 'fa-graduation-cap', sia_delta($agg['inscrits'], $prevAgg['inscrits']));
      echo $rkpi('Taux de conversion', $agg['conversion'] . ' %', '#0d9488', 'fa-line-chart', sia_delta($agg['conversion'], $prevAgg['conversion']));
      echo $rkpi('E-mails envoyés', (int) $agg['email_sent'], '#2563eb', 'fa-envelope', sia_delta($agg['email_sent'], $prevAgg['email_sent']));
      ?>
    </div>

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
          echo sia_pie_block($stageSlices);
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
              <div class="sia-stat-value" style="color:#4f46e5;"><?php echo (int) $interTotal; ?></div>
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
    <div class="panel_s"><div class="panel-body">
      <div class="clearfix">
        <h5 class="bold pull-left" style="margin-top:0;">
          <span class="sia-panel-icon" style="background:#8a63d21a;color:#8a63d2;"><i class="fa fa-magic"></i></span>
          Rapport rédigé par l'IA
        </h5>
        <?php if ($ai_ready) { ?>
          <?php echo form_open(admin_url('school_ia_bridge/reporting_generate'), ['class' => 'pull-right sia-no-print', 'onsubmit' => "this.querySelector('button').disabled=true;this.querySelector('button').innerHTML='Génération…';"]); ?>
            <input type="hidden" name="period" value="<?php echo htmlspecialchars($period, ENT_QUOTES); ?>">
            <input type="hidden" name="date" value="<?php echo htmlspecialchars($date, ENT_QUOTES); ?>">
            <button type="submit" class="btn btn-primary"><i class="fa fa-magic"></i> <?php echo $report ? 'Régénérer' : 'Générer avec l\'IA'; ?></button>
          <?php echo form_close(); ?>
        <?php } ?>
      </div>
      <?php if (!$ai_ready) { ?>
        <div class="alert alert-warning" style="margin-top:10px;">Configurez d'abord votre clé API Anthropic dans <a href="<?php echo admin_url('school_ia_bridge/settings'); ?>">Réglages → Rapports IA</a>.</div>
      <?php } ?>

      <?php if ($report) { ?>
        <hr>
        <div class="clearfix" style="margin-bottom:8px;">
          <strong class="pull-left"><?php echo htmlspecialchars((string) $report->label, ENT_QUOTES); ?></strong>
          <span class="pull-right text-muted">Généré le <?php echo htmlspecialchars((string) $report->created_at, ENT_QUOTES); ?></span>
        </div>
        <div class="sia-report" style="line-height:1.6;">
          <?php echo $report->content; /* HTML produit par notre appel IA */ ?>
        </div>
        <hr class="sia-no-print">
        <div class="sia-no-print">
          <?php echo form_open(admin_url('school_ia_bridge/reporting_email'), ['class' => 'form-inline']); ?>
            <input type="hidden" name="report_id" value="<?php echo (int) $report->id; ?>">
            <div class="form-group" style="margin-right:6px;">
              <input type="email" name="email" class="form-control" placeholder="destinataire@exemple.com" style="min-width:260px;" required>
            </div>
            <button type="submit" class="btn btn-default"><i class="fa fa-paper-plane"></i> Envoyer par e-mail</button>
          <?php echo form_close(); ?>
        </div>
      <?php } else { ?>
        <p class="text-muted" style="margin-top:10px;">Aucun rapport IA pour cette période. Cliquez « Générer avec l'IA » pour produire une synthèse (points forts, points de vigilance, recommandations).</p>
      <?php } ?>
    </div></div>

    <!-- Historique des rapports -->
    <?php if (!empty($reports)) { ?>
      <div class="panel_s sia-no-print"><div class="panel-body">
        <h5 class="bold" style="margin-top:0;">Rapports précédents</h5>
        <table class="table">
          <tbody>
            <?php foreach ($reports as $r) { ?>
              <tr>
                <td><a href="<?php echo admin_url('school_ia_bridge/reporting?report=' . (int) $r->id); ?>"><?php echo htmlspecialchars((string) $r->label, ENT_QUOTES); ?></a></td>
                <td class="text-muted"><?php echo htmlspecialchars((string) $r->created_at, ENT_QUOTES); ?></td>
                <td class="text-right">
                  <a href="<?php echo admin_url('school_ia_bridge/reporting_delete/' . (int) $r->id); ?>" class="text-muted"
                     onclick="return confirm('Supprimer ce rapport ?');"><i class="fa fa-trash"></i></a>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div></div>
    <?php } ?>

  </div>
</div>
<?php init_tail(); ?>
</body>
</html>
