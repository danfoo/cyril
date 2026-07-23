<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">

    <?php
    $qs = http_build_query(array_filter(['period' => $period !== 'all' ? $period : '', 'program' => $program, 'status' => $status]));
    $returnUrl = admin_url('school_ia_bridge/competitors') . ($qs ? '?' . $qs : '');

    $scoreBadge = function ($score) {
        $s = (float) $score;
        if ($s >= 60) { return ['#dc2626', 'chaud']; }
        if ($s >= 40) { return ['#d97706', 'tiède']; }
        return ['#2563eb', 'froid'];
    };
    ?>

    <div class="clearfix" style="margin-bottom:6px;">
      <?php if ($ai_ready) { ?>
        <div class="pull-right" style="display:flex;gap:8px;">
          <a href="<?php echo admin_url('school_ia_bridge/competitors_scan'); ?>" class="btn btn-primary"
             onclick="this.classList.add('disabled');this.innerHTML='<i class=\'fa fa-spinner fa-spin\'></i> Analyse…';">
            <i class="fa fa-magic"></i> Analyser les nouvelles conversations
          </a>
          <a href="<?php echo admin_url('school_ia_bridge/competitors_scan?force=1'); ?>" class="btn btn-default"
             title="Réanalyser toutes les conversations (ignore ce qui a déjà été analysé)"
             onclick="this.classList.add('disabled');this.innerHTML='<i class=\'fa fa-spinner fa-spin\'></i> Analyse…';">
            <i class="fa fa-refresh"></i> Tout réanalyser
          </a>
        </div>
      <?php } ?>
      <h4 class="no-margin"><i class="fa fa-binoculars"></i> Veille concurrentielle <?php echo sia_help('competitors'); ?></h4>
    </div>
    <p class="text-muted" style="margin-bottom:16px;">Écoles concurrentes citées spontanément par les prospects. Repérez les concurrents les plus agressifs et préparez vos arguments de contre.</p>
    <?php if (!$ai_ready) { ?>
      <div class="alert alert-warning">Pour l'analyse automatique, configurez votre clé API Claude dans <a href="<?php echo admin_url('school_ia_bridge/settings'); ?>">Réglages → Rapports IA</a>.</div>
    <?php } ?>

    <!-- Filtres -->
    <div class="panel_s"><div class="panel-body" style="padding:12px 16px;">
      <form method="get" action="<?php echo admin_url('school_ia_bridge/competitors'); ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
        <div>
          <label class="control-label" style="display:block;">Période</label>
          <select name="period" class="form-control input-sm" style="height:34px;width:150px;">
            <?php foreach (['all' => 'Tout', 'month' => 'Ce mois', 'quarter' => 'Ce trimestre', 'year' => 'Cette année'] as $k => $lab) { ?>
              <option value="<?php echo $k; ?>" <?php echo $period === $k ? 'selected' : ''; ?>><?php echo $lab; ?></option>
            <?php } ?>
          </select>
        </div>
        <div>
          <label class="control-label" style="display:block;">Programme visé</label>
          <select name="program" class="form-control input-sm" style="height:34px;width:180px;">
            <option value="">Tous</option>
            <?php foreach ($programs as $p) { ?>
              <option value="<?php echo htmlspecialchars($p, ENT_QUOTES); ?>" <?php echo $program === $p ? 'selected' : ''; ?>><?php echo htmlspecialchars($p, ENT_QUOTES); ?></option>
            <?php } ?>
          </select>
        </div>
        <div>
          <label class="control-label" style="display:block;">Statut du lead</label>
          <select name="status" class="form-control input-sm" style="height:34px;width:150px;">
            <option value="">Tous</option>
            <option value="inscrit" <?php echo $status === 'inscrit' ? 'selected' : ''; ?>>Gagné (inscrit)</option>
            <option value="perdu" <?php echo $status === 'perdu' ? 'selected' : ''; ?>>Perdu</option>
          </select>
        </div>
        <button type="submit" class="btn btn-sm btn-default"><i class="fa fa-filter"></i> Filtrer</button>
        <?php if ($qs) { ?><a href="<?php echo admin_url('school_ia_bridge/competitors'); ?>" class="text-muted" style="font-size:12px;margin-bottom:8px;">Réinitialiser</a><?php } ?>
      </form>
    </div></div>

    <!-- Indicateurs + tendance -->
    <div class="row">
      <?php
      // Badge de tendance des mentions (mois en cours vs précédent), lisible sur fond dégradé.
      if ($trend['pct'] === null) {
          $trendTxt = '▲ nouveau ce mois';
      } elseif ($trend['pct'] === 0) {
          $trendTxt = '→ stable vs mois préc.';
      } else {
          $trendTxt = ($trend['pct'] > 0 ? '▲ +' : '▼ ') . $trend['pct'] . ' % vs mois préc.';
      }
      $trendHtml = '<span style="display:inline-block;background:rgba(255,255,255,.22);color:#fff;'
          . 'font-size:11px;font-weight:700;padding:2px 9px;border-radius:20px;">' . $trendTxt . '</span>';
      $kpis = [
          ['Mentions totales', $totals['mentions'], 'linear-gradient(135deg,#fb7185,#e11d48)', 'fa-fire', $trendHtml],
          ['Concurrents identifiés', $totals['concurrents'], 'linear-gradient(135deg,#a78bfa,#7c3aed)', 'fa-binoculars', ''],
          ['Prospects concernés', $totals['leads'], 'linear-gradient(135deg,#38bdf8,#2563eb)', 'fa-users', ''],
      ];
      foreach ($kpis as $c) { ?>
        <div class="col-md-4 col-sm-6 sia-stat-col">
          <div class="sia-stat-card" style="position:relative;overflow:hidden;border-radius:16px;padding:18px 20px;min-height:104px;color:#fff;display:flex;flex-direction:column;justify-content:center;background:<?php echo $c[2]; ?>;box-shadow:0 6px 18px rgba(15,23,42,.14);">
            <div style="position:relative;z-index:1;font-size:26px;font-weight:800;line-height:1.08;letter-spacing:-.02em;"><?php echo (int) $c[1]; ?></div>
            <div style="position:relative;z-index:1;font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;opacity:.92;margin-top:5px;"><?php echo $c[0]; ?></div>
            <?php if ($c[4] !== '') { ?><div style="position:relative;z-index:1;margin-top:8px;"><?php echo $c[4]; ?></div><?php } ?>
            <i class="fa <?php echo $c[3]; ?>" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:54px;opacity:.20;"></i>
          </div>
        </div>
      <?php } ?>
    </div>

    <?php if (empty($ranking)) { ?>
      <div class="panel_s"><div class="panel-body" style="text-align:center; padding:40px;">
        <div class="sia-kpi-icon" style="margin:0 auto 12px; color:#8a63d2; background:#8a63d21a; width:56px; height:56px; font-size:24px;"><i class="fa fa-binoculars"></i></div>
        <h5 class="bold">Aucune mention de concurrent pour ces critères.</h5>
        <p class="text-muted">Les écoles citées par les prospects dans leurs conversations apparaîtront ici automatiquement.</p>
      </div></div>
    <?php } else { ?>

      <!-- Classement analytique -->
      <div class="panel_s"><div class="panel-body">
        <h5 class="bold" style="margin-top:0;">
          <span class="sia-panel-icon sia-ic-primary"><i class="fa fa-trophy"></i></span>
          Classement des concurrents
        </h5>
        <div style="overflow-x:auto;">
          <table class="table no-margin">
            <thead><tr>
              <th>Concurrent</th>
              <th class="text-right">Mentions</th>
              <th class="text-right">Prospects</th>
              <th>Programme le plus ciblé</th>
              <th class="text-right">Taux de perte</th>
              <th>Argumentaire</th>
            </tr></thead>
            <tbody>
              <?php foreach ($ranking as $row) {
                  $key = mb_strtolower(trim((string) $row->name));
                  $card = $cards[$key] ?? null;
                  $hasCard = $card && trim((string) $card->argument) !== '';
                  $lossColor = $row->loss_rate >= 50 ? '#dc2626' : ($row->loss_rate >= 25 ? '#d97706' : '#16a34a'); ?>
                <tr>
                  <td><span class="label" style="background:#8a63d21a;color:#8a63d2;"><?php echo htmlspecialchars((string) $row->name, ENT_QUOTES); ?></span></td>
                  <td class="text-right bold"><?php echo (int) $row->mentions; ?></td>
                  <td class="text-right"><?php echo (int) $row->leads; ?></td>
                  <td><?php echo $row->top_program !== '' ? htmlspecialchars((string) $row->top_program, ENT_QUOTES) : '<span class="text-muted">—</span>'; ?></td>
                  <td class="text-right"><span class="label" style="background:<?php echo $lossColor; ?>1a;color:<?php echo $lossColor; ?>;"><?php echo (int) $row->loss_rate; ?> %</span></td>
                  <td>
                    <?php if ($hasCard) { ?>
                      <span class="label label-success"><i class="fa fa-check"></i> Prêt</span>
                    <?php } else { ?>
                      <span class="label label-danger"><i class="fa fa-exclamation"></i> À rédiger</span>
                    <?php } ?>
                    <a href="#" class="text-muted" style="margin-left:6px;font-size:12px;"
                       onclick="siaEditCard(<?php echo htmlspecialchars(json_encode((string) $row->name), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($card ? (string) $card->argument : ''), ENT_QUOTES); ?>);return false;">
                      <i class="fa fa-pencil"></i> <?php echo $hasCard ? 'Modifier' : 'Ajouter'; ?>
                    </a>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div></div>

      <!-- Verbatims orientés action -->
      <?php if (!empty($recent)) { ?>
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">
            <span class="sia-panel-icon sia-ic-info"><i class="fa fa-quote-left"></i></span>
            Derniers extraits de conversation
          </h5>
          <div class="sia-quote-list">
            <?php foreach ($recent as $row) {
                $leadLabel = $row->lead_name ?: ('Lead #' . (int) $row->lead_id);
                $key = mb_strtolower(trim((string) $row->name));
                $card = $cards[$key] ?? null;
                $arg = $card ? trim((string) $card->argument) : '';
                [$scColor, $scLabel] = $scoreBadge($row->lead_score); ?>
              <div class="sia-quote-card <?php echo $row->handled ? 'sia-quote-done' : ''; ?>">
                <div class="clearfix" style="margin-bottom:6px;">
                  <span class="pull-left" style="display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap;">
                    <span class="label" style="background:#8a63d21a;color:#8a63d2;"><?php echo htmlspecialchars((string) $row->name, ENT_QUOTES); ?></span>
                    <?php if ($arg !== '') { ?>
                      <span class="sia-tip" title="Argument de contre : <?php echo htmlspecialchars($arg, ENT_QUOTES); ?>">💡</span>
                    <?php } else { ?>
                      <a href="#" class="sia-tip sia-tip-empty" title="Aucun argumentaire — cliquez pour en rédiger"
                         onclick="siaEditCard(<?php echo htmlspecialchars(json_encode((string) $row->name), ENT_QUOTES); ?>, '');return false;">💡</a>
                    <?php } ?>
                    <?php if ($row->lead_score !== null && $row->lead_score !== '') { ?>
                      <span class="label" style="background:<?php echo $scColor; ?>1a;color:<?php echo $scColor; ?>;" title="Score de qualification"><i class="fa fa-star"></i> <?php echo (int) $row->lead_score; ?> · <?php echo $scLabel; ?></span>
                    <?php } ?>
                  </span>
                  <span class="pull-right text-muted" style="font-size:11px;">
                    <?php echo htmlspecialchars((string) $row->created_at, ENT_QUOTES); ?> ·
                    <a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $row->lead_id); ?>"><?php echo htmlspecialchars((string) $leadLabel, ENT_QUOTES); ?></a>
                  </span>
                </div>
                <p style="margin:0 0 8px; font-style:italic;">« <?php echo htmlspecialchars((string) $row->context, ENT_QUOTES); ?> »</p>
                <div>
                  <a href="<?php echo admin_url('school_ia_bridge/competitor_toggle/' . (int) $row->id . '?return=' . urlencode($returnUrl)); ?>"
                     class="label <?php echo $row->handled ? 'label-success' : 'label-warning'; ?>" style="cursor:pointer;">
                    <i class="fa <?php echo $row->handled ? 'fa-check-square-o' : 'fa-square-o'; ?>"></i>
                    <?php echo $row->handled ? 'Objection contrée' : 'À traiter'; ?>
                  </a>
                </div>
              </div>
            <?php } ?>
          </div>
        </div></div>
      <?php } ?>

    <?php } ?>

    <!-- Modale : argumentaire de contre -->
    <div class="modal fade" id="sia-card-modal" tabindex="-1" role="dialog">
      <div class="modal-dialog" role="document"><div class="modal-content">
        <?php echo form_open(admin_url('school_ia_bridge/competitor_card_save')); ?>
          <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnUrl, ENT_QUOTES); ?>">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title"><i class="fa fa-lightbulb-o"></i> Argumentaire de contre</h4>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label class="control-label">Concurrent</label>
              <input type="text" name="name" id="sia-card-name" class="form-control" readonly>
            </div>
            <div class="form-group">
              <label class="control-label">Vos arguments (affichés dans l'infobulle 💡 au conseiller)</label>
              <textarea name="argument" id="sia-card-arg" class="form-control" rows="5"
                        placeholder="Ex. Rappeler nos atouts vs cette école : accréditations, taux d'insertion, accompagnement, réseau alumni…"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
          </div>
        <?php echo form_close(); ?>
      </div></div>
    </div>

  </div>
</div>
<script>
window.siaEditCard = function (name, argument) {
  document.getElementById('sia-card-name').value = name || '';
  document.getElementById('sia-card-arg').value = argument || '';
  try { jQuery('#sia-card-modal').modal('show'); } catch (e) {}
};
</script>
<?php init_tail(); ?>
</body>
</html>
