<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">

    <div class="clearfix" style="margin-bottom:15px;">
      <h4 class="no-margin pull-left"><i class="fa fa-dashboard"></i> Tableau de bord — Admissions <?php echo sia_help("dashboard"); ?></h4>
      <a href="<?php echo admin_url('school_ia_bridge/pipeline'); ?>" class="btn btn-primary pull-right">
        <i class="fa fa-columns"></i> Pipeline
      </a>
    </div>

    <?php
    $rentree = $filters['rentree'] ?? '';
    $customRange = ($filters['date_from'] ?? '') !== '' || ($filters['date_to'] ?? '') !== '';
    $delayLabel = '—';
    if ($avgFirstContact !== null) {
        $delayLabel = $avgFirstContact < 48
            ? number_format($avgFirstContact, 1, ',', ' ') . ' h'
            : number_format($avgFirstContact / 24, 1, ',', ' ') . ' j';
    }
    ?>

    <!-- Filtres : période, plage de dates personnalisée, rentrée -->
    <div class="panel_s"><div class="panel-body" style="padding:12px 16px;">
      <div class="clearfix" style="margin-bottom:<?php echo $customRange ? '10px' : '0'; ?>;">
        <div class="pull-left" style="margin-right:14px;">
          <?php
          $periods = [0 => 'Tout', 7 => '7 jours', 30 => '30 jours', 90 => '90 jours'];
          foreach ($periods as $days => $label) {
              $active = (!$customRange && (int) $filters['period'] === $days) ? 'btn-primary' : 'btn-default';
              $qs = array_filter(['period' => $days ?: null, 'rentree' => $rentree ?: null]); ?>
            <a href="<?php echo admin_url('school_ia_bridge/dashboard') . ($qs ? '?' . http_build_query($qs) : ''); ?>"
               class="btn btn-sm <?php echo $active; ?>"><?php echo $label; ?></a>
          <?php } ?>
        </div>
        <form method="get" action="<?php echo admin_url('school_ia_bridge/dashboard'); ?>" class="pull-left" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
          <input type="date" name="date_from" class="form-control input-sm" style="height:31px;width:140px;"
                 value="<?php echo htmlspecialchars((string) ($filters['date_from'] ?? ''), ENT_QUOTES); ?>">
          <span class="text-muted">→</span>
          <input type="date" name="date_to" class="form-control input-sm" style="height:31px;width:140px;"
                 value="<?php echo htmlspecialchars((string) ($filters['date_to'] ?? ''), ENT_QUOTES); ?>">
          <?php if (!empty($rentrees)) { ?>
            <select name="rentree" class="form-control input-sm" style="height:31px;width:160px;">
              <option value="">Toutes les rentrées</option>
              <?php foreach ($rentrees as $r) { ?>
                <option value="<?php echo htmlspecialchars($r, ENT_QUOTES); ?>" <?php echo ($rentree === $r) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($r, ENT_QUOTES); ?>
                </option>
              <?php } ?>
            </select>
          <?php } ?>
          <button type="submit" class="btn btn-sm btn-default"><i class="fa fa-filter"></i> Filtrer</button>
          <?php if ($customRange) { ?>
            <a href="<?php echo admin_url('school_ia_bridge/dashboard') . ($rentree ? '?rentree=' . urlencode($rentree) : ''); ?>" class="text-muted" style="font-size:12px;">Réinitialiser la plage</a>
          <?php } ?>
        </form>
      </div>
    </div></div>

    <!-- =====================================================================
         ZONE 1 — INDICATEURS CLÉS
         ===================================================================== -->
    <div class="sia-section-title">Indicateurs clés</div>

    <!-- Volume & qualité -->
    <div class="row">
      <?php
      $sd = $stats['scoreDist'];
      $qualityCards = [
          ['Leads au total', $stats['total'], '#2e6ff2', 'fa-users'],
          ['Leads chauds (≥ 60)', $sd['chaud'], '#d64545', 'fa-fire'],
          ['Leads tièdes (40-59)', $sd['tiede'], '#d6a63a', 'fa-thermometer-half'],
          ['Leads froids (< 40)', $sd['froid'], '#2563eb', 'fa-snowflake-o'],
      ];
      foreach ($qualityCards as $c) { ?>
        <div class="col-md-3 col-sm-6">
          <div class="panel_s sia-kpi"><div class="panel-body">
            <div class="sia-kpi-icon" style="color:<?php echo $c[2]; ?>;background:<?php echo $c[2]; ?>1a;">
              <i class="fa <?php echo $c[3]; ?>"></i>
            </div>
            <div class="sia-kpi-meta">
              <div class="sia-kpi-value"><?php echo $c[1]; ?></div>
              <div class="sia-kpi-label"><?php echo $c[0]; ?></div>
            </div>
          </div></div>
        </div>
      <?php } ?>
    </div>

    <!-- Performance commerciale -->
    <div class="row">
      <?php
      $perfCards = [
          ['Inscrits', $stats['inscrits'], '#0a8f5b', 'fa-graduation-cap'],
          ['Taux de conversion', $stats['conversion'] . ' %', '#d6a63a', 'fa-line-chart'],
          ['Délai moyen 1ᵉʳ contact', $delayLabel, '#2563eb', 'fa-hourglass-half'],
      ];
      foreach ($perfCards as $c) { ?>
        <div class="col-md-4 col-sm-6">
          <div class="panel_s sia-kpi"><div class="panel-body">
            <div class="sia-kpi-icon" style="color:<?php echo $c[2]; ?>;background:<?php echo $c[2]; ?>1a;">
              <i class="fa <?php echo $c[3]; ?>"></i>
            </div>
            <div class="sia-kpi-meta">
              <div class="sia-kpi-value"><?php echo $c[1]; ?></div>
              <div class="sia-kpi-label"><?php echo $c[0]; ?></div>
            </div>
          </div></div>
        </div>
      <?php } ?>
    </div>

    <!-- Financier & objectif -->
    <div class="row">
      <?php $finCol = $target > 0 ? 'col-md-3' : 'col-md-6'; ?>
      <?php if (!empty($finance['has_fees'])) { ?>
        <div class="<?php echo $finCol; ?> col-sm-6">
          <div class="panel_s sia-kpi"><div class="panel-body">
            <div class="sia-kpi-icon" style="color:#8a63d2;background:#8a63d21a;"><i class="fa fa-briefcase"></i></div>
            <div class="sia-kpi-meta">
              <div class="sia-kpi-value"><?php echo number_format($finance['pipeline'], 0, ',', ' '); ?></div>
              <div class="sia-kpi-label">Valeur du pipeline</div>
            </div>
          </div></div>
        </div>
        <div class="<?php echo $finCol; ?> col-sm-6">
          <div class="panel_s sia-kpi"><div class="panel-body">
            <div class="sia-kpi-icon" style="color:#0a8f5b;background:#0a8f5b1a;"><i class="fa fa-money"></i></div>
            <div class="sia-kpi-meta">
              <div class="sia-kpi-value"><?php echo number_format($finance['realized'], 0, ',', ' '); ?></div>
              <div class="sia-kpi-label">CA réalisé (inscrits)</div>
            </div>
          </div></div>
        </div>
      <?php } ?>
      <?php if ($target > 0) {
          $pct = min(100, round($stats['inscrits'] * 100 / $target));
          $objCol = !empty($finance['has_fees']) ? 'col-md-6' : 'col-md-12'; ?>
        <div class="<?php echo $objCol; ?>">
          <div class="panel_s"><div class="panel-body">
            <div class="clearfix" style="margin-bottom:8px;">
              <h5 class="bold pull-left" style="margin:0;"><i class="fa fa-bullseye"></i> Objectif d'inscrits</h5>
              <span class="pull-right bold"><?php echo (int) $stats['inscrits']; ?> / <?php echo (int) $target; ?> (<?php echo $pct; ?> %)</span>
            </div>
            <div style="height:12px; background:var(--sia-surface-2); border-radius:6px; overflow:hidden;">
              <div style="height:100%; width:<?php echo $pct; ?>%; background:<?php echo $pct >= 100 ? '#0a8f5b' : '#2e6ff2'; ?>;"></div>
            </div>
            <p class="text-muted" style="margin:10px 0 0; font-size:12.5px;">
              <?php echo max(0, (int) $target - (int) $stats['inscrits']); ?> inscription(s) restante(s) pour atteindre l'objectif.
            </p>
          </div></div>
        </div>
      <?php } ?>
      <?php if (empty($finance['has_fees'])) { ?>
        <div class="col-md-12">
          <div class="panel_s"><div class="panel-body" style="display:flex;align-items:center;gap:12px;">
            <i class="fa fa-info-circle text-muted"></i>
            <span class="text-muted">
              Configurez les frais par formation<?php echo $target > 0 ? '' : ' et un objectif d\'inscrits'; ?> dans
              <a href="<?php echo admin_url('school_ia_bridge/settings'); ?>">Réglages</a>
              pour afficher la valeur du pipeline, le CA réalisé<?php echo $target > 0 ? '' : ' et la progression vers l\'objectif'; ?>.
            </span>
          </div></div>
        </div>
      <?php } ?>
    </div>

    <!-- =====================================================================
         ZONE 2 — ALERTES & ACTIONS PRIORITAIRES
         ===================================================================== -->
    <div class="sia-section-title">Actions prioritaires</div>

    <div class="row">
      <div class="col-md-6">
        <div class="panel_s"><div class="panel-body">
          <div class="clearfix">
            <h5 class="bold pull-left" style="margin-top:0;">
              <i class="fa fa-user-times"></i> Leads non assignés
              <?php if ($unassignedCount > 0) { ?><span class="label label-warning"><?php echo (int) $unassignedCount; ?></span><?php } ?>
            </h5>
            <a href="<?php echo admin_url('school_ia_bridge') . '?unassigned=1'; ?>" class="pull-right">Voir tout →</a>
          </div>
          <?php if (empty($unassignedLeads)) { ?>
            <p class="text-muted" style="margin:8px 0 0;">Tous les leads de la période ont un responsable. 👍</p>
          <?php } else { ?>
            <table class="table no-margin">
              <tbody>
                <?php foreach ($unassignedLeads as $l) { ?>
                  <tr>
                    <td>
                      <a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $l->id); ?>">
                        <?php echo htmlspecialchars((string) ($l->name ?: ('Lead #' . $l->id)), ENT_QUOTES); ?>
                      </a>
                    </td>
                    <td class="text-muted"><?php echo htmlspecialchars((string) $l->formation, ENT_QUOTES); ?></td>
                    <td class="text-right text-muted sia-date"><?php echo $l->received_at ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $l->received_at)), ENT_QUOTES) : ''; ?></td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          <?php } ?>
        </div></div>
      </div>
      <div class="col-md-6">
        <div class="panel_s"><div class="panel-body">
          <div class="clearfix">
            <h5 class="bold pull-left" style="margin-top:0;"><i class="fa fa-bell"></i> Relances à faire</h5>
            <a href="<?php echo admin_url('school_ia_bridge/tasks'); ?>" class="pull-right">Voir tout →</a>
          </div>
          <?php if (empty($dueTasks)) { ?>
            <p class="text-muted" style="margin:8px 0 0;">Aucune relance en attente.</p>
          <?php } else { ?>
            <table class="table no-margin">
              <tbody>
                <?php foreach ($dueTasks as $t) {
                    $overdue = ($t->due_at && strtotime($t->due_at) < time()); ?>
                  <tr>
                    <td><i class="fa fa-square-o text-muted"></i> <?php echo htmlspecialchars((string) $t->title, ENT_QUOTES); ?></td>
                    <td>
                      <a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $t->lead_id); ?>">
                        <?php echo htmlspecialchars((string) ($t->lead_name ?: ('Lead #' . $t->lead_id)), ENT_QUOTES); ?>
                      </a>
                    </td>
                    <td class="text-right">
                      <?php if ($t->due_at) { ?>
                        <span class="label <?php echo $overdue ? 'label-danger' : 'label-default'; ?>">
                          <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($t->due_at)), ENT_QUOTES); ?>
                        </span>
                      <?php } ?>
                    </td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          <?php } ?>
        </div></div>
      </div>
    </div>

    <!-- =====================================================================
         ZONE 3 — VENTILATION DÉTAILLÉE (RÉPARTITION ANALYTIQUE)
         ===================================================================== -->
    <div class="sia-section-title">Répartition analytique</div>

    <div class="row">
      <div class="col-md-6">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;"><i class="fa fa-graduation-cap"></i> Par formation</h5>
          <?php
          $formPalette = ['#4f46e5', '#2563eb', '#0ea5e9', '#0a8f5b', '#d6a63a', '#e2683c', '#d64545', '#8a63d2'];
          $formSlices = [];
          foreach ($byFormation as $i => $s) {
              $formSlices[] = [
                  'label' => (string) $s->formation,
                  'value' => (int) $s->n,
                  'color' => $formPalette[$i % count($formPalette)],
              ];
          }
          echo sia_pie_block($formSlices);
          ?>
        </div></div>
      </div>
      <div class="col-md-6">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;"><i class="fa fa-thermometer-half"></i> Maturité des leads</h5>
          <?php
          echo sia_pie_block([
              ['label' => 'Froids (< 40)',   'value' => (int) $sd['froid'], 'color' => '#2563eb'],
              ['label' => 'Tièdes (40-59)',  'value' => (int) $sd['tiede'], 'color' => '#d6a63a'],
              ['label' => 'Chauds (≥ 60)',   'value' => (int) $sd['chaud'], 'color' => '#d64545'],
          ]);
          ?>
        </div></div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;"><i class="fa fa-filter"></i> Entonnoir par étape</h5>
          <?php
          $stageSlices = [];
          foreach ($stats['byStage'] as $slug => $n) {
              $stageSlices[] = [
                  'label' => $model->stageLabel($slug),
                  'value' => (int) $n,
                  'color' => $model->stageColor($slug),
              ];
          }
          echo sia_pie_block($stageSlices);
          ?>
        </div></div>
      </div>
      <div class="col-md-6">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;"><i class="fa fa-users"></i> Performance par conseiller</h5>
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
    </div>

    <!-- =====================================================================
         ZONE 4 — FLUX DES ACTIVITÉS RÉCENTES
         ===================================================================== -->
    <div class="sia-section-title">Activité récente</div>

    <div class="row">
      <div class="col-md-6">
        <div class="panel_s"><div class="panel-body">
          <div class="clearfix">
            <h5 class="bold pull-left" style="margin-top:0;"><i class="fa fa-inbox"></i> Derniers leads reçus</h5>
            <a href="<?php echo admin_url('school_ia_bridge'); ?>" class="pull-right">Voir tout →</a>
          </div>
          <?php if (empty($recentLeads)) { ?>
            <p class="text-muted" style="margin:8px 0 0;">Aucun lead reçu sur cette période.</p>
          <?php } else { ?>
            <table class="table no-margin">
              <tbody>
                <?php foreach ($recentLeads as $l) { ?>
                  <tr>
                    <td>
                      <a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $l->id); ?>">
                        <?php echo htmlspecialchars((string) ($l->name ?: ('Lead #' . $l->id)), ENT_QUOTES); ?>
                      </a>
                    </td>
                    <td class="text-muted"><?php echo htmlspecialchars((string) ($l->email ?: $l->phone), ENT_QUOTES); ?></td>
                    <td class="text-muted"><?php echo htmlspecialchars((string) $l->formation, ENT_QUOTES); ?></td>
                    <td class="text-right text-muted sia-date"><?php echo $l->received_at ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $l->received_at)), ENT_QUOTES) : ''; ?></td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          <?php } ?>
        </div></div>
      </div>
      <div class="col-md-6">
        <div class="panel_s"><div class="panel-body">
          <div class="clearfix">
            <h5 class="bold pull-left" style="margin-top:0;"><i class="fa fa-history"></i> Interactions récentes</h5>
            <a href="<?php echo admin_url('school_ia_bridge/activity'); ?>" class="pull-right">Voir tout →</a>
          </div>
          <?php if (empty($recentActivities)) { ?>
            <p class="text-muted" style="margin:8px 0 0;">Aucune activité pour l'instant.</p>
          <?php } else { ?>
            <div class="sia-activity-list">
              <?php
              $actIcons  = ['note' => 'fa-comment', 'stage_change' => 'fa-random', 'task' => 'fa-check-square-o',
                            'email' => 'fa-envelope', 'sms' => 'fa-mobile', 'assignment' => 'fa-user'];
              $actColors = ['note' => 'sia-ic-primary', 'stage_change' => 'sia-ic-info', 'task' => 'sia-ic-danger',
                            'email' => 'sia-ic-success', 'sms' => 'sia-ic-success', 'assignment' => 'sia-ic-warning'];
              foreach ($recentActivities as $a) {
                  $icon = $actIcons[$a->type] ?? 'fa-circle-o';
                  $col  = $actColors[$a->type] ?? 'sia-ic-muted';
                  $who  = $a->staff_id ? get_staff_full_name((int) $a->staff_id) : 'Système'; ?>
                <div class="sia-activity-item">
                  <span class="sia-activity-icon <?php echo $col; ?>"><i class="fa <?php echo $icon; ?>"></i></span>
                  <div style="min-width:0;">
                    <?php echo htmlspecialchars((string) $a->content, ENT_QUOTES); ?>
                    <div class="text-muted" style="font-size:11px;margin-top:2px;">
                      <?php if (!empty($a->lead_id)) { ?>
                        <a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $a->lead_id); ?>">
                          <?php echo htmlspecialchars((string) ($a->lead_name ?: ('Lead #' . $a->lead_id)), ENT_QUOTES); ?>
                        </a> ·
                      <?php } ?>
                      <?php echo htmlspecialchars($who . ' · ' . date('d/m/Y H:i', strtotime((string) $a->created_at)), ENT_QUOTES); ?>
                    </div>
                  </div>
                </div>
              <?php } ?>
            </div>
          <?php } ?>
        </div></div>
      </div>
    </div>

  </div>
</div>
<?php init_tail(); ?>
</body>
</html>
