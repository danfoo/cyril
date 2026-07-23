<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">

    <?php
    $isEmail = $campaign->channel === 'email';
    $scoreBadge = function ($score) {
        $s = (float) $score;
        if ($s >= 60) { return ['#dc2626', 'chaud']; }
        if ($s >= 40) { return ['#d97706', 'tiède']; }
        return ['#2563eb', 'froid'];
    };
    $kpi = function (string $label, $value, string $grad, string $icon, string $sub = '') {
        ob_start(); ?>
        <div class="col-lg-3 col-sm-6 sia-stat-col">
          <div class="sia-stat-card" style="position:relative;overflow:hidden;border-radius:16px;padding:18px 20px;min-height:104px;color:#fff;display:flex;flex-direction:column;justify-content:center;background:<?php echo $grad; ?>;box-shadow:0 6px 18px rgba(15,23,42,.14);">
            <div style="position:relative;z-index:1;font-size:26px;font-weight:800;line-height:1.08;letter-spacing:-.02em;"><?php echo $value; ?></div>
            <div style="position:relative;z-index:1;font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;opacity:.92;margin-top:5px;"><?php echo $label; ?></div>
            <?php if ($sub !== '') { ?><div style="position:relative;z-index:1;font-size:11.5px;opacity:.85;margin-top:3px;"><?php echo $sub; ?></div><?php } ?>
            <i class="fa <?php echo $icon; ?>" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:54px;opacity:.20;"></i>
          </div>
        </div>
        <?php return ob_get_clean();
    };
    ?>

    <div class="clearfix" style="margin-bottom:15px;">
      <h4 class="no-margin pull-left">
        <i class="fa fa-paper-plane"></i> <?php echo htmlspecialchars((string) $campaign->name, ENT_QUOTES); ?>
        <span class="label <?php echo $isEmail ? 'label-primary' : 'label-info'; ?>" style="margin-left:6px;"><?php echo strtoupper((string) $campaign->channel); ?></span>
      </h4>
      <div class="pull-right">
        <a href="<?php echo admin_url('school_ia_bridge/campaigns_list'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Campagnes</a>
        <a href="<?php echo admin_url('school_ia_bridge/campaign_delete/' . (int) $campaign->id); ?>" class="btn btn-default"
           onclick="return confirm('Supprimer cette campagne ? (l\'historique des envois est conservé)');"><i class="fa fa-trash text-danger"></i></a>
      </div>
    </div>

    <p class="text-muted" style="margin-bottom:14px;">
      Envoyée le <?php echo htmlspecialchars(date('d/m/Y à H:i', strtotime((string) $campaign->created_at)), ENT_QUOTES); ?>
      <?php if ($campaign->subject) { ?> · Objet : <strong><?php echo htmlspecialchars((string) $campaign->subject, ENT_QUOTES); ?></strong><?php } ?>
    </p>

    <!-- KPIs -->
    <div class="row sia-kpi-grid">
      <?php
      echo $kpi('Volume envoyé', (int) $kpis['sent'], 'linear-gradient(135deg,#6366f1,#4f46e5)', 'fa-users');
      if ($isEmail) {
          echo $kpi("Taux d'ouverture", $kpis['open_rate'] . ' %', 'linear-gradient(135deg,#34d399,#059669)', 'fa-eye', (int) $kpis['opened'] . ' ouvert(s)');
          echo $kpi('Taux de clic', $kpis['click_rate'] . ' %', 'linear-gradient(135deg,#38bdf8,#2563eb)', 'fa-mouse-pointer', (int) $kpis['clicked'] . ' cliqué(s)');
      } else {
          echo $kpi('En échec', (int) $kpis['failed'], 'linear-gradient(135deg,#fb7185,#e11d48)', 'fa-exclamation-triangle');
      }
      echo $kpi('Conversions', (int) $kpis['conversions'], 'linear-gradient(135deg,#2dd4bf,#0d9488)', 'fa-trophy', (int) $kpis['clickers'] . ' cliqueur(s) · ' . $kpis['conv_rate'] . ' %');
      ?>
    </div>

    <?php if ($isEmail && $nonOpeners > 0) { ?>
      <div class="panel_s"><div class="panel-body" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
        <span class="sia-panel-icon sia-ic-warning"><i class="fa fa-bell"></i></span>
        <div style="flex:1 1 auto;">
          <strong><?php echo (int) $nonOpeners; ?> destinataire(s) n'ont pas ouvert</strong> cet e-mail.
          <span class="text-muted">Relancez-les avec un objet « Rappel : … ».</span>
        </div>
        <a href="<?php echo admin_url('school_ia_bridge/campaign_resend/' . (int) $campaign->id); ?>" class="btn btn-primary"
           onclick="return confirm('Envoyer une relance aux <?php echo (int) $nonOpeners; ?> non-ouvreurs ?');">
          <i class="fa fa-paper-plane"></i> Relancer les non-ouvreurs
        </a>
      </div></div>
    <?php } ?>

    <!-- Destinataires -->
    <div class="panel_s"><div class="panel-body">
      <div class="clearfix" style="margin-bottom:10px;">
        <h5 class="bold pull-left" style="margin-top:0;">Destinataires</h5>
        <div class="pull-right" style="display:flex;gap:6px;flex-wrap:wrap;">
          <?php
          $filters = ['all' => 'Tous', 'opened' => 'Ouvreurs', 'unopened' => 'Non-ouvreurs', 'clicked' => 'Cliqueurs', 'converted' => 'Convertis'];
          if (!$isEmail) { unset($filters['opened'], $filters['unopened'], $filters['clicked']); }
          foreach ($filters as $f => $lab) {
              $active = ($filter === $f) ? 'btn-primary' : 'btn-default'; ?>
            <a href="<?php echo admin_url('school_ia_bridge/campaign/' . (int) $campaign->id . '?f=' . $f); ?>" class="btn btn-sm <?php echo $active; ?>"><?php echo $lab; ?></a>
          <?php } ?>
        </div>
      </div>
      <table class="table no-margin">
        <thead><tr>
          <th>Lead</th>
          <th>Contact</th>
          <?php if ($isEmail) { ?><th>Ouverture</th><th>Clics</th><?php } else { ?><th>Statut</th><?php } ?>
          <th>Étape</th>
          <th>Envoyé</th>
        </tr></thead>
        <tbody>
          <?php if (empty($recipients)) { ?>
            <tr><td colspan="6" class="text-center text-muted" style="padding:20px;">Aucun destinataire dans cette vue.</td></tr>
          <?php } else {
              foreach ($recipients as $r) {
                  $name = $r->lead_name ?: ('Lead #' . (int) $r->lead_id);
                  [$scColor, $scLabel] = $scoreBadge($r->lead_score); ?>
            <tr>
              <td>
                <a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $r->lead_id); ?>" class="bold"><?php echo htmlspecialchars($name, ENT_QUOTES); ?></a>
                <?php if ($r->lead_score !== null && $r->lead_score !== '') { ?>
                  <span class="label" style="background:<?php echo $scColor; ?>1a;color:<?php echo $scColor; ?>;margin-left:4px;"><?php echo (int) $r->lead_score; ?></span>
                <?php } ?>
              </td>
              <td class="text-muted"><?php echo htmlspecialchars((string) ($r->email ?: $r->phone), ENT_QUOTES); ?></td>
              <?php if ($isEmail) { ?>
                <td><?php echo $r->opened_at ? '<span class="label label-success">Ouvert</span>' : '<span class="label label-default">—</span>'; ?></td>
                <td><?php echo ((int) $r->clicks > 0) ? '<span class="label label-info">' . (int) $r->clicks . '</span>' : '<span class="text-muted">—</span>'; ?></td>
              <?php } else { ?>
                <td><span class="label <?php echo $r->status === 'failed' ? 'label-danger' : 'label-success'; ?>"><?php echo htmlspecialchars((string) $r->status, ENT_QUOTES); ?></span></td>
              <?php } ?>
              <td>
                <?php $stColor = $model->stageColor((string) $r->lead_stage); ?>
                <span class="label" style="background:<?php echo $stColor; ?>1a;color:<?php echo $stColor; ?>;"><?php echo htmlspecialchars($model->stageLabel((string) $r->lead_stage), ENT_QUOTES); ?></span>
              </td>
              <td class="text-muted sia-date"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) $r->sent_at)), ENT_QUOTES); ?></td>
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
