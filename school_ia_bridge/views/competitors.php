<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">

    <h4 class="no-margin" style="margin-bottom:6px;"><i class="fa fa-binoculars"></i> Veille concurrentielle <?php echo sia_help('competitors'); ?></h4>
    <p class="text-muted" style="margin-bottom:16px;">Écoles concurrentes citées spontanément par les prospects dans leurs conversations avec le chatbot.</p>

    <!-- Indicateurs -->
    <div class="row">
      <?php
      $kpis = [
          ['Mentions totales', $totals['mentions'], '#dc2626', 'fa-fire'],
          ['Concurrents identifiés', $totals['concurrents'], '#8a63d2', 'fa-binoculars'],
          ['Prospects concernés', $totals['leads'], '#2563eb', 'fa-users'],
      ];
      foreach ($kpis as $c) { ?>
        <div class="col-md-4 col-sm-6">
          <div class="panel_s sia-kpi"><div class="panel-body">
            <div class="sia-kpi-icon" style="color:<?php echo $c[2]; ?>;background:<?php echo $c[2]; ?>1a;"><i class="fa <?php echo $c[3]; ?>"></i></div>
            <div class="sia-kpi-meta"><div class="sia-kpi-value"><?php echo (int) $c[1]; ?></div><div class="sia-kpi-label"><?php echo $c[0]; ?></div></div>
          </div></div>
        </div>
      <?php } ?>
    </div>

    <?php if (empty($ranking)) { ?>
      <div class="panel_s"><div class="panel-body" style="text-align:center; padding:40px;">
        <div class="sia-kpi-icon" style="margin:0 auto 12px; color:#8a63d2; background:#8a63d21a; width:56px; height:56px; font-size:24px;"><i class="fa fa-binoculars"></i></div>
        <h5 class="bold">Aucune mention de concurrent pour l'instant.</h5>
        <p class="text-muted">Les écoles citées par les prospects dans leurs conversations apparaîtront ici automatiquement.</p>
      </div></div>
    <?php } else { ?>

      <!-- Classement -->
      <div class="row">
        <div class="col-md-12">
          <div class="panel_s"><div class="panel-body">
            <h5 class="bold" style="margin-top:0;">
              <span class="sia-panel-icon sia-ic-primary"><i class="fa fa-trophy"></i></span>
              Classement des concurrents
            </h5>
            <?php $max = max(1, (int) $ranking[0]->mentions);
            foreach ($ranking as $row) {
                $pct = round((int) $row->mentions / $max * 100); ?>
              <div style="margin-bottom:12px;">
                <div class="clearfix" style="margin-bottom:3px;">
                  <span class="pull-left bold"><?php echo htmlspecialchars((string) $row->name, ENT_QUOTES); ?></span>
                  <span class="pull-right text-muted">
                    <?php echo (int) $row->mentions; ?> mention<?php echo $row->mentions > 1 ? 's' : ''; ?>
                    · <?php echo (int) $row->leads; ?> prospect<?php echo $row->leads > 1 ? 's' : ''; ?>
                  </span>
                </div>
                <div style="height:10px; background:var(--sia-surface-2); border-radius:6px; overflow:hidden;">
                  <div style="height:100%; width:<?php echo (int) max(6, $pct); ?>%; background:#8a63d2;"></div>
                </div>
              </div>
            <?php } ?>
          </div></div>
        </div>
      </div>

      <!-- Extraits de contexte -->
      <?php if (!empty($recent)) { ?>
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">
            <span class="sia-panel-icon sia-ic-info"><i class="fa fa-quote-left"></i></span>
            Derniers extraits de conversation
          </h5>
          <div class="sia-quote-list">
            <?php foreach ($recent as $row) {
                $leadLabel = $row->lead_name ?: ('Lead #' . (int) $row->lead_id); ?>
              <div class="sia-quote-card">
                <div class="clearfix" style="margin-bottom:6px;">
                  <span class="label" style="background:#8a63d21a;color:#8a63d2;"><?php echo htmlspecialchars((string) $row->name, ENT_QUOTES); ?></span>
                  <span class="pull-right text-muted" style="font-size:11px;">
                    <?php echo htmlspecialchars((string) $row->created_at, ENT_QUOTES); ?> ·
                    <a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $row->lead_id); ?>"><?php echo htmlspecialchars((string) $leadLabel, ENT_QUOTES); ?></a>
                  </span>
                </div>
                <p style="margin:0; font-style:italic;">« <?php echo htmlspecialchars((string) $row->context, ENT_QUOTES); ?> »</p>
              </div>
            <?php } ?>
          </div>
        </div></div>
      <?php } ?>

    <?php } ?>

  </div>
</div>
<?php init_tail(); ?>
</body>
</html>
