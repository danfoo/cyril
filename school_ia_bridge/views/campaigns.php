<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <h4 class="no-margin" style="margin-bottom:15px;"><i class="fa fa-bar-chart"></i> Statistiques des campagnes <?php echo sia_help('campaigns'); ?></h4>

    <?php
    $qsFor = function ($over) use ($period, $type) {
        $q = array_filter(['period' => $period ?: null, 'type' => $type ?: null]);
        foreach ($over as $k => $v) { if ($v === null) { unset($q[$k]); } else { $q[$k] = $v; } }
        return admin_url('school_ia_bridge/campaigns') . ($q ? '?' . http_build_query($q) : '');
    };
    $campLabel = ['single' => 'Individuel', 'bulk' => 'Massive', 'sequence' => 'Séquence'];
    ?>

    <!-- Filtre par type de stratégie (évite de fausser les taux) -->
    <div style="margin-bottom:12px; display:flex; gap:6px; flex-wrap:wrap;">
      <?php
      $types = ['' => 'Toutes', 'bulk' => 'Campagnes massives', 'sequence' => 'Séquences automatisées', 'single' => 'Envois manuels'];
      foreach ($types as $k => $lab) {
          $active = ($type === $k) ? 'btn-primary' : 'btn-default'; ?>
        <a href="<?php echo $qsFor(['type' => $k ?: null]); ?>" class="btn btn-sm <?php echo $active; ?>"><?php echo $lab; ?></a>
      <?php } ?>
    </div>

    <!-- Période -->
    <div style="margin-bottom:15px;">
      <?php
      $periods = [0 => 'Tout', 7 => '7 jours', 30 => '30 jours', 90 => '90 jours'];
      foreach ($periods as $days => $label) {
          $active = ((int) $period === $days) ? 'btn-primary' : 'btn-default'; ?>
        <a href="<?php echo $qsFor(['period' => $days ?: null]); ?>" class="btn btn-sm <?php echo $active; ?>"><?php echo $label; ?></a>
      <?php } ?>
    </div>

    <?php if ($type === 'bulk') { ?>
      <div class="alert alert-info" style="padding:8px 12px;font-size:12.5px;"><i class="fa fa-info-circle"></i> Vue <strong>campagnes massives</strong> : les taux d'ouverture sont naturellement plus bas que sur les relances individuelles.</div>
    <?php } elseif ($type === 'sequence') { ?>
      <div class="alert alert-info" style="padding:8px 12px;font-size:12.5px;"><i class="fa fa-info-circle"></i> Vue <strong>séquences automatisées</strong> : relances déclenchées après un échange, aux taux d'engagement généralement élevés.</div>
    <?php } ?>

    <!-- KPIs -->
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

    <!-- KPI Conversion (l'indicateur ROI) -->
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s"><div class="panel-body" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
          <div class="sia-kpi-icon" style="color:#16a34a;background:#16a34a1a;width:52px;height:52px;font-size:22px;"><i class="fa fa-trophy"></i></div>
          <div style="flex:1 1 auto;">
            <div class="text-muted" style="font-size:12.5px;font-weight:600;">Conversions — prospects ayant cliqué puis inscrits</div>
            <h2 class="bold no-margin" style="color:#16a34a;">
              <?php echo (int) $stats['conversions']; ?>
              <small class="text-muted" style="font-size:15px;">/ <?php echo (int) $stats['clickers']; ?> cliqueur(s) · <?php echo $stats['conv_rate']; ?> % de conversion</small>
            </h2>
          </div>
          <span class="text-muted" style="font-size:12px;max-width:340px;">Le vrai ROI de vos envois : combien de destinataires engagés sont passés à l'étape « Inscrit » du pipeline.</span>
        </div></div>
      </div>
    </div>

    <!-- Tableau des campagnes (groupé, pas par individu) -->
    <div class="panel_s"><div class="panel-body">
      <h5 class="bold" style="margin-top:0;">Campagnes &amp; séquences</h5>
      <table class="table no-margin sia-campaign-table">
        <thead><tr>
          <th style="width:26px;"></th>
          <th>Canal</th>
          <th>Campagne</th>
          <th class="text-right">Volume</th>
          <th class="text-right">Ouvert</th>
          <th class="text-right">Clics</th>
          <th class="text-right">Conversions</th>
        </tr></thead>
        <tbody>
          <?php if (empty($groups)) { ?>
            <tr><td colspan="7" class="text-center text-muted" style="padding:20px;">Aucun envoi pour cette vue.</td></tr>
          <?php } else {
              foreach ($groups as $i => $g) {
                  $openPct = $g->volume > 0 ? round($g->opened * 100 / $g->volume) : 0;
                  $clickPct = $g->volume > 0 ? round($g->clicked * 100 / $g->volume) : 0;
                  $name = $g->subject !== '' ? $g->subject : ($g->channel === 'sms' ? 'SMS' : '(sans objet)');
                  $detailQs = http_build_query(['channel' => $g->channel, 'campaign' => $g->campaign, 'subject' => $g->subject, 'day' => $g->day]); ?>
              <tr class="sia-campaign-row" data-detail="<?php echo htmlspecialchars($detailQs, ENT_QUOTES); ?>" data-target="sia-cd-<?php echo $i; ?>" style="cursor:pointer;">
                <td><i class="fa fa-caret-right sia-campaign-caret"></i></td>
                <td><span class="label <?php echo $g->channel === 'sms' ? 'label-info' : 'label-primary'; ?>"><?php echo strtoupper($g->channel); ?></span></td>
                <td>
                  <span class="bold"><?php echo htmlspecialchars($name, ENT_QUOTES); ?></span>
                  <div class="text-muted" style="font-size:11px;">
                    <span class="label label-default"><?php echo htmlspecialchars($campLabel[$g->campaign] ?? $g->campaign, ENT_QUOTES); ?></span>
                    <?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $g->day)), ENT_QUOTES); ?>
                  </div>
                </td>
                <td class="text-right bold"><?php echo (int) $g->volume; ?></td>
                <td class="text-right"><?php echo (int) $g->opened; ?> <span class="text-muted">(<?php echo $openPct; ?> %)</span></td>
                <td class="text-right"><?php echo (int) $g->clicked; ?> <span class="text-muted">(<?php echo $clickPct; ?> %)</span></td>
                <td class="text-right"><?php echo (int) $g->conversions > 0 ? '<span class="label label-success">' . (int) $g->conversions . '</span>' : '<span class="text-muted">0</span>'; ?></td>
              </tr>
              <tr class="sia-campaign-detail" id="sia-cd-<?php echo $i; ?>" style="display:none;">
                <td colspan="7" style="background:var(--sia-surface-2);">
                  <div class="sia-cd-content text-muted" style="padding:6px;">Chargement…</div>
                </td>
              </tr>
            <?php }
          } ?>
        </tbody>
      </table>
      <?php if (!empty($groups)) { ?>
        <p class="text-muted" style="font-size:12px;margin:10px 0 0;"><i class="fa fa-hand-o-up"></i> Cliquez une ligne pour voir <strong>qui a ouvert / cliqué</strong> et rappeler ces prospects.</p>
      <?php } ?>
    </div></div>

  </div>
</div>
<script>
(function () {
  var BASE = '<?php echo admin_url('school_ia_bridge/campaign_detail'); ?>';
  document.querySelectorAll('.sia-campaign-row').forEach(function (row) {
    row.addEventListener('click', function () {
      var detail = document.getElementById(row.getAttribute('data-target'));
      if (!detail) return;
      var caret = row.querySelector('.sia-campaign-caret');
      var open = detail.style.display !== 'none';
      if (open) {
        detail.style.display = 'none';
        if (caret) caret.className = 'fa fa-caret-right sia-campaign-caret';
        return;
      }
      detail.style.display = '';
      if (caret) caret.className = 'fa fa-caret-down sia-campaign-caret';
      var box = detail.querySelector('.sia-cd-content');
      if (box && !box.getAttribute('data-loaded')) {
        fetch(BASE + '?' + row.getAttribute('data-detail'), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
          .then(function (r) { return r.text(); })
          .then(function (html) { box.innerHTML = html; box.setAttribute('data-loaded', '1'); })
          .catch(function () { box.textContent = 'Erreur de chargement.'; });
      }
    });
  });
})();
</script>
<?php init_tail(); ?>
</body>
</html>
