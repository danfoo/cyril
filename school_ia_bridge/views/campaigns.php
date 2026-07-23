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

    <!-- KPIs (cartes dégradées, style tableau de bord) -->
    <?php
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
    <div class="row sia-kpi-grid">
      <?php
      echo $kpi('E-mails envoyés', (int) $stats['email_sent'], 'linear-gradient(135deg,#6366f1,#4f46e5)', 'fa-envelope');
      echo $kpi("Taux d'ouverture", $stats['open_rate'] . ' %', 'linear-gradient(135deg,#34d399,#059669)', 'fa-eye', (int) $stats['email_opened'] . ' ouvert(s)');
      echo $kpi('Taux de clic', $stats['click_rate'] . ' %', 'linear-gradient(135deg,#38bdf8,#2563eb)', 'fa-mouse-pointer', (int) $stats['email_clicked'] . ' cliqué(s)');
      echo $kpi('SMS', (int) $stats['sms_sent'] . ' <small style="opacity:.8;font-size:16px;">/ ' . (int) $stats['sms_total'] . '</small>', 'linear-gradient(135deg,#64748b,#334155)', 'fa-mobile', (int) $stats['sms_failed'] . ' échec(s)');
      echo $kpi('Conversions', (int) $stats['conversions'], 'linear-gradient(135deg,#2dd4bf,#0d9488)', 'fa-trophy', (int) $stats['clickers'] . ' cliqueur(s) · ' . $stats['conv_rate'] . ' %');
      ?>
    </div>
    <p class="text-muted" style="font-size:12px;margin:-4px 0 14px;"><i class="fa fa-trophy" style="color:#0d9488;"></i> <strong>Conversions</strong> = le vrai ROI : prospects ayant cliqué puis passés à l'étape « Inscrit » du pipeline.</p>

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
