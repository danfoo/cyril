<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">

    <div class="clearfix" style="margin-bottom:15px;">
      <h4 class="no-margin pull-left"><i class="fa fa-paper-plane"></i> Campagnes</h4>
      <div class="pull-right">
        <a href="<?php echo admin_url('school_ia_bridge/campaigns'); ?>" class="btn btn-default"><i class="fa fa-bar-chart"></i> Statistiques globales</a>
        <a href="<?php echo admin_url('school_ia_bridge/bulk'); ?>" class="btn btn-primary"><i class="fa fa-plus"></i> Nouvelle campagne</a>
      </div>
    </div>

    <div class="panel_s"><div class="panel-body">
      <table class="table dt-table">
        <thead>
          <tr>
            <th>Campagne</th>
            <th>Canal</th>
            <th>Date</th>
            <th class="text-right">Volume</th>
            <th class="text-right">Ouvert</th>
            <th class="text-right">Clics</th>
            <th class="text-right">Conversions</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($campaigns)) { ?>
            <tr><td colspan="8" class="text-center text-muted" style="padding:30px;">
              Aucune campagne pour l'instant. <a href="<?php echo admin_url('school_ia_bridge/bulk'); ?>">Créer une campagne →</a>
            </td></tr>
          <?php } else {
              foreach ($campaigns as $c) {
                  $sent = (int) $c->sent;
                  $openPct = $sent > 0 ? round($c->opened * 100 / $sent) : 0;
                  $clickPct = $sent > 0 ? round($c->clicked * 100 / $sent) : 0; ?>
            <tr>
              <td>
                <a href="<?php echo admin_url('school_ia_bridge/campaign/' . (int) $c->id); ?>" class="bold">
                  <?php echo htmlspecialchars((string) $c->name, ENT_QUOTES); ?>
                </a>
                <?php if ($c->subject) { ?><div class="text-muted" style="font-size:11.5px;"><?php echo htmlspecialchars((string) $c->subject, ENT_QUOTES); ?></div><?php } ?>
              </td>
              <td><span class="label <?php echo $c->channel === 'sms' ? 'label-info' : 'label-primary'; ?>"><?php echo strtoupper((string) $c->channel); ?></span></td>
              <td class="text-muted"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) $c->created_at)), ENT_QUOTES); ?></td>
              <td class="text-right bold"><?php echo $sent; ?></td>
              <td class="text-right"><?php echo (int) $c->opened; ?> <span class="text-muted">(<?php echo $openPct; ?> %)</span></td>
              <td class="text-right"><?php echo (int) $c->clicked; ?> <span class="text-muted">(<?php echo $clickPct; ?> %)</span></td>
              <td class="text-right"><?php echo (int) $c->conversions > 0 ? '<span class="label label-success">' . (int) $c->conversions . '</span>' : '<span class="text-muted">0</span>'; ?></td>
              <td class="text-right">
                <a href="<?php echo admin_url('school_ia_bridge/campaign/' . (int) $c->id); ?>" class="btn btn-default btn-xs"><i class="fa fa-eye"></i> Détail</a>
              </td>
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
