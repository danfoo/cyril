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

    <!-- KPIs principaux -->
    <div class="row">
      <?php
      $cards = [
          ['Leads au total', $stats['total'], '#2e6ff2', 'fa-users'],
          ['Leads chauds (≥ 60)', $stats['hot'], '#d64545', 'fa-fire'],
          ['Inscrits', $stats['inscrits'], '#0a8f5b', 'fa-graduation-cap'],
          ['Taux de conversion', $stats['conversion'] . ' %', '#d6a63a', 'fa-line-chart'],
      ];
      foreach ($cards as $c) { ?>
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

    <!-- KPIs opérationnels -->
    <div class="row">
      <?php
      $delayLabel = '—';
      if ($avgFirstContact !== null) {
          $delayLabel = $avgFirstContact < 48
              ? number_format($avgFirstContact, 1, ',', ' ') . ' h'
              : number_format($avgFirstContact / 24, 1, ',', ' ') . ' j';
      }
      $opCards = [
          ['Leads non assignés', $unassignedCount, '#d6a63a', 'fa-user-times'],
          ['Délai moyen 1ᵉʳ contact', $delayLabel, '#2563eb', 'fa-hourglass-half'],
      ];
      foreach ($opCards as $c) { ?>
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
      <?php if (!empty($finance['has_fees'])) { ?>
        <div class="col-md-3 col-sm-6">
          <div class="panel_s sia-kpi"><div class="panel-body">
            <div class="sia-kpi-icon" style="color:#8a63d2;background:#8a63d21a;">
              <i class="fa fa-briefcase"></i>
            </div>
            <div class="sia-kpi-meta">
              <div class="sia-kpi-value"><?php echo number_format($finance['pipeline'], 0, ',', ' '); ?></div>
              <div class="sia-kpi-label">Valeur du pipeline</div>
            </div>
          </div></div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="panel_s sia-kpi"><div class="panel-body">
            <div class="sia-kpi-icon" style="color:#0a8f5b;background:#0a8f5b1a;">
              <i class="fa fa-money"></i>
            </div>
            <div class="sia-kpi-meta">
              <div class="sia-kpi-value"><?php echo number_format($finance['realized'], 0, ',', ' '); ?></div>
              <div class="sia-kpi-label">CA réalisé (inscrits)</div>
            </div>
          </div></div>
        </div>
      <?php } else { ?>
        <div class="col-md-6">
          <div class="panel_s"><div class="panel-body" style="display:flex;align-items:center;gap:12px;">
            <i class="fa fa-info-circle text-muted"></i>
            <span class="text-muted">
              Configurez les frais par formation dans
              <a href="<?php echo admin_url('school_ia_bridge/settings'); ?>">Réglages</a>
              pour afficher la valeur du pipeline et le CA réalisé.
            </span>
          </div></div>
        </div>
      <?php } ?>
    </div>

    <!-- Objectif d'inscrits -->
    <?php if ($target > 0) {
        $pct = min(100, round($stats['inscrits'] * 100 / $target)); ?>
      <div class="row">
        <div class="col-md-12">
          <div class="panel_s"><div class="panel-body">
            <div class="clearfix" style="margin-bottom:6px;">
              <h5 class="bold pull-left" style="margin:0;"><i class="fa fa-bullseye"></i> Objectif d'inscrits</h5>
              <span class="pull-right bold"><?php echo (int) $stats['inscrits']; ?> / <?php echo (int) $target; ?> (<?php echo $pct; ?> %)</span>
            </div>
            <div style="height:12px; background:var(--sia-surface-2); border-radius:6px; overflow:hidden;">
              <div style="height:100%; width:<?php echo $pct; ?>%; background:<?php echo $pct >= 100 ? '#0a8f5b' : '#2e6ff2'; ?>;"></div>
            </div>
          </div></div>
        </div>
      </div>
    <?php } ?>

    <!-- Tâches à venir -->
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s"><div class="panel-body">
          <div class="clearfix">
            <h5 class="bold pull-left" style="margin-top:0;">Relances à faire</h5>
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

    <!-- Leads non assignés + Aperçu des derniers leads -->
    <div class="row">
      <div class="col-md-6">
        <div class="panel_s"><div class="panel-body">
          <div class="clearfix">
            <h5 class="bold pull-left" style="margin-top:0;"><i class="fa fa-user-times"></i> Leads non assignés</h5>
            <a href="<?php echo admin_url('school_ia_bridge') . '?unassigned=1'; ?>" class="pull-right">Voir tout →</a>
          </div>
          <?php if (empty($unassignedLeads)) { ?>
            <p class="text-muted" style="margin:8px 0 0;">Tous les leads de la période ont un responsable.</p>
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
                    <td class="text-right text-muted"><?php echo htmlspecialchars((string) $l->received_at, ENT_QUOTES); ?></td>
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
                    <td class="text-right text-muted"><?php echo htmlspecialchars((string) $l->received_at, ENT_QUOTES); ?></td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          <?php } ?>
        </div></div>
      </div>
    </div>

    <!-- Par conseiller -->
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;"><i class="fa fa-users"></i> Performance par conseiller</h5>
          <table class="table no-margin">
            <thead><tr><th>Conseiller</th><th class="text-right">Leads</th><th class="text-right">Inscrits</th><th class="text-right">Conversion</th></tr></thead>
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

    <!-- Par source, par formation, par score -->
    <div class="row">
      <div class="col-md-4">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;"><i class="fa fa-sitemap"></i> Par source</h5>
          <?php
          $srcMax = 1;
          foreach ($bySource as $s) { $srcMax = max($srcMax, (int) $s->n); }
          if (empty($bySource)) { echo '<p class="text-muted">Aucun lead sur cette période.</p>'; }
          foreach ($bySource as $s) {
              $pct = round((int) $s->n / $srcMax * 100); ?>
            <div style="margin-bottom:8px;">
              <div class="clearfix" style="margin-bottom:2px;">
                <span class="pull-left"><?php echo htmlspecialchars((string) $s->src, ENT_QUOTES); ?></span>
                <span class="pull-right bold"><?php echo (int) $s->n; ?></span>
              </div>
              <div style="height:8px; background:#eef1f5; border-radius:5px; overflow:hidden;">
                <div style="height:100%; width:<?php echo $pct; ?>%; background:#2e6ff2;"></div>
              </div>
            </div>
          <?php } ?>
        </div></div>
      </div>
      <div class="col-md-4">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;"><i class="fa fa-graduation-cap"></i> Par formation</h5>
          <?php
          $formMax = 1;
          foreach ($byFormation as $s) { $formMax = max($formMax, (int) $s->n); }
          if (empty($byFormation)) { echo '<p class="text-muted">Aucun lead sur cette période.</p>'; }
          foreach ($byFormation as $s) {
              $pct = round((int) $s->n / $formMax * 100); ?>
            <div style="margin-bottom:8px;">
              <div class="clearfix" style="margin-bottom:2px;">
                <span class="pull-left"><?php echo htmlspecialchars((string) $s->formation, ENT_QUOTES); ?></span>
                <span class="pull-right bold"><?php echo (int) $s->n; ?></span>
              </div>
              <div style="height:8px; background:#eef1f5; border-radius:5px; overflow:hidden;">
                <div style="height:100%; width:<?php echo $pct; ?>%; background:#8a63d2;"></div>
              </div>
            </div>
          <?php } ?>
        </div></div>
      </div>
      <div class="col-md-4">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;"><i class="fa fa-thermometer-half"></i> Maturité des leads</h5>
          <?php
          $sd = $stats['scoreDist'];
          $sdTotal = max(1, $sd['froid'] + $sd['tiede'] + $sd['chaud']);
          $sdRows = [
              ['froid', 'Froids (< 40)', $sd['froid'], '#2563eb'],
              ['tiede', 'Tièdes (40-59)', $sd['tiede'], '#d6a63a'],
              ['chaud', 'Chauds (≥ 60)', $sd['chaud'], '#d64545'],
          ];
          foreach ($sdRows as [$key, $label, $n, $color]) {
              $pct = round($n * 100 / $sdTotal); ?>
            <div style="margin-bottom:8px;">
              <div class="clearfix" style="margin-bottom:2px;">
                <span class="pull-left"><?php echo $label; ?></span>
                <span class="pull-right bold"><?php echo (int) $n; ?></span>
              </div>
              <div style="height:8px; background:#eef1f5; border-radius:5px; overflow:hidden;">
                <div style="height:100%; width:<?php echo $pct; ?>%; background:<?php echo $color; ?>;"></div>
              </div>
            </div>
          <?php } ?>
        </div></div>
      </div>
    </div>

    <!-- Entonnoir par étape -->
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">Entonnoir par étape</h5>
          <?php
          $max = max(1, ...array_values($stats['byStage']));
          foreach ($stats['byStage'] as $slug => $n) {
              $pct = round($n / $max * 100);
              $color = $model->stageColor($slug); ?>
            <div style="margin-bottom:10px;">
              <div class="clearfix" style="margin-bottom:3px;">
                <span class="pull-left"><?php echo htmlspecialchars($model->stageLabel($slug), ENT_QUOTES); ?></span>
                <span class="pull-right bold"><?php echo (int) $n; ?></span>
              </div>
              <div style="height:10px; background:#eef1f5; border-radius:6px; overflow:hidden;">
                <div style="height:100%; width:<?php echo $pct; ?>%; background:<?php echo $color; ?>;"></div>
              </div>
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
