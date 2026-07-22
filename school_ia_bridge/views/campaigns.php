<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <h4 class="no-margin" style="margin-bottom:15px;"><i class="fa fa-bar-chart"></i> Statistiques des campagnes <?php echo sia_help('campaigns'); ?></h4>

    <div style="margin-bottom:15px;">
      <?php
      $periods = [0 => 'Tout', 7 => '7 jours', 30 => '30 jours', 90 => '90 jours'];
      foreach ($periods as $days => $label) {
          $active = ((int) $period === $days) ? 'btn-primary' : 'btn-default'; ?>
        <a href="<?php echo admin_url('school_ia_bridge/campaigns') . ($days ? '?period=' . $days : ''); ?>"
           class="btn btn-sm <?php echo $active; ?>"><?php echo $label; ?></a>
      <?php } ?>
    </div>

    <!-- E-mails -->
    <div class="row">
      <div class="col-md-3 col-sm-6"><div class="panel_s"><div class="panel-body">
        <div class="text-muted"><i class="fa fa-envelope"></i> E-mails envoyés</div>
        <h2 class="bold no-margin"><?php echo (int) $stats['email_sent']; ?></h2>
      </div></div></div>
      <div class="col-md-3 col-sm-6"><div class="panel_s"><div class="panel-body">
        <div class="text-muted"><i class="fa fa-eye"></i> Taux d'ouverture</div>
        <h2 class="bold no-margin" style="color:#0a8f5b;"><?php echo $stats['open_rate']; ?> %</h2>
        <span class="text-muted"><?php echo (int) $stats['email_opened']; ?> ouvert(s)</span>
      </div></div></div>
      <div class="col-md-3 col-sm-6"><div class="panel_s"><div class="panel-body">
        <div class="text-muted"><i class="fa fa-mouse-pointer"></i> Taux de clic</div>
        <h2 class="bold no-margin" style="color:#2e6ff2;"><?php echo $stats['click_rate']; ?> %</h2>
        <span class="text-muted"><?php echo (int) $stats['email_clicked']; ?> cliqué(s)</span>
      </div></div></div>
      <div class="col-md-3 col-sm-6"><div class="panel_s"><div class="panel-body">
        <div class="text-muted"><i class="fa fa-mobile"></i> SMS</div>
        <h2 class="bold no-margin"><?php echo (int) $stats['sms_sent']; ?><small class="text-muted"> / <?php echo (int) $stats['sms_total']; ?></small></h2>
        <span class="text-muted"><?php echo (int) $stats['sms_failed']; ?> échec(s)</span>
      </div></div></div>
    </div>

    <!-- Derniers messages -->
    <div class="panel_s"><div class="panel-body">
      <h5 class="bold" style="margin-top:0;">Derniers envois</h5>
      <table class="table">
        <thead><tr><th>Canal</th><th>Campagne</th><th>Sujet</th><th>Lead</th><th>Envoyé</th><th>Ouvert</th><th>Clics</th></tr></thead>
        <tbody>
          <?php
          $camp = ['single' => 'Individuel', 'bulk' => 'Groupé', 'sequence' => 'Séquence'];
          if (empty($messages)) { ?>
            <tr><td colspan="7" class="text-center text-muted" style="padding:20px;">Aucun envoi pour l'instant.</td></tr>
          <?php } else {
              foreach ($messages as $m) { ?>
            <tr>
              <td><span class="label <?php echo $m->channel === 'sms' ? 'label-info' : 'label-primary'; ?>"><?php echo strtoupper($m->channel); ?></span></td>
              <td><?php echo htmlspecialchars($camp[$m->campaign] ?? $m->campaign, ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars((string) ($m->subject ?: '—'), ENT_QUOTES); ?></td>
              <td><a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $m->lead_id); ?>"><?php echo htmlspecialchars((string) ($m->lead_name ?: ('#' . $m->lead_id)), ENT_QUOTES); ?></a></td>
              <td class="text-muted"><?php echo htmlspecialchars((string) $m->sent_at, ENT_QUOTES); ?></td>
              <td>
                <?php if ($m->channel === 'sms') { ?>
                  <span class="label <?php echo $m->status === 'failed' ? 'label-danger' : 'label-success'; ?>"><?php echo $m->status; ?></span>
                <?php } elseif ($m->opened_at) { ?>
                  <i class="fa fa-check text-success"></i>
                <?php } else { ?>
                  <span class="text-muted">—</span>
                <?php } ?>
              </td>
              <td><?php echo (int) $m->clicks; ?></td>
            </tr>
          <?php }
          } ?>
        </tbody>
      </table>
    </div></div>

  </div>
</div>
<?php init_tail(); ?>
</body>
</html>
