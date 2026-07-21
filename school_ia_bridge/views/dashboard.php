<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">

    <div class="clearfix" style="margin-bottom:15px;">
      <h4 class="no-margin pull-left"><i class="fa fa-dashboard"></i> Tableau de bord — Admissions</h4>
      <a href="<?php echo admin_url('school_ia_bridge/pipeline'); ?>" class="btn btn-primary pull-right">
        <i class="fa fa-columns"></i> Pipeline
      </a>
    </div>

    <!-- KPIs -->
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
          <div class="panel_s"><div class="panel-body">
            <div style="border-left:4px solid <?php echo $c[2]; ?>; padding-left:12px;">
              <div class="text-muted"><i class="fa <?php echo $c[3]; ?>"></i> <?php echo $c[0]; ?></div>
              <h2 class="bold no-margin" style="margin-top:4px;"><?php echo $c[1]; ?></h2>
            </div>
          </div></div>
        </div>
      <?php } ?>
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
