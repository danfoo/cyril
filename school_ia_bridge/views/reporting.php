<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <h4 class="no-margin" style="margin-bottom:15px;"><i class="fa fa-file-text"></i> Reporting <?php echo sia_help('reporting'); ?></h4>

    <!-- Sélecteur de période -->
    <div class="panel_s"><div class="panel-body">
      <?php echo form_open(admin_url('school_ia_bridge/reporting'), ['method' => 'get', 'class' => 'row']); ?>
        <div class="col-sm-3">
          <label class="control-label">Type de rapport</label>
          <select name="period" class="form-control">
            <?php foreach (['day' => 'Journalier', 'week' => 'Hebdomadaire', 'month' => 'Mensuel', 'year' => 'Annuel'] as $k => $lab) { ?>
              <option value="<?php echo $k; ?>" <?php echo $period === $k ? 'selected' : ''; ?>><?php echo $lab; ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="col-sm-3">
          <label class="control-label">Date de référence</label>
          <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date, ENT_QUOTES); ?>">
        </div>
        <div class="col-sm-2">
          <label class="control-label">&nbsp;</label>
          <button type="submit" class="btn btn-default btn-block">Aperçu</button>
        </div>
      <?php echo form_close(); ?>
      <p class="text-muted" style="margin-top:10px;"><strong><?php echo htmlspecialchars($label, ENT_QUOTES); ?></strong> · du <?php echo date('d/m/Y', strtotime($from)); ?> au <?php echo date('d/m/Y', strtotime($to)); ?></p>
    </div></div>

    <!-- Aperçu des chiffres -->
    <div class="row">
      <?php
      $kpis = [
          ['Nouveaux leads', $agg['leads_total'], '#4f46e5', 'fa-users'],
          ['Inscrits', $agg['inscrits'], '#16a34a', 'fa-graduation-cap'],
          ['Conversion', $agg['conversion'] . ' %', '#d97706', 'fa-line-chart'],
          ['E-mails · ouv.', $agg['email_sent'] . ' · ' . $agg['open_rate'] . '%', '#2563eb', 'fa-envelope'],
      ];
      foreach ($kpis as $c) { ?>
        <div class="col-md-3 col-sm-6">
          <div class="panel_s sia-kpi"><div class="panel-body">
            <div class="sia-kpi-icon" style="color:<?php echo $c[2]; ?>;background:<?php echo $c[2]; ?>1a;"><i class="fa <?php echo $c[3]; ?>"></i></div>
            <div class="sia-kpi-meta"><div class="sia-kpi-value"><?php echo $c[1]; ?></div><div class="sia-kpi-label"><?php echo $c[0]; ?></div></div>
          </div></div>
        </div>
      <?php } ?>
    </div>

    <!-- Génération IA -->
    <div class="panel_s"><div class="panel-body">
      <div class="clearfix">
        <h5 class="bold pull-left" style="margin-top:0;"><i class="fa fa-magic"></i> Rapport rédigé par l'IA</h5>
        <?php if ($ai_ready) { ?>
          <?php echo form_open(admin_url('school_ia_bridge/reporting_generate'), ['class' => 'pull-right', 'onsubmit' => "this.querySelector('button').disabled=true;this.querySelector('button').innerHTML='Génération…';"]); ?>
            <input type="hidden" name="period" value="<?php echo htmlspecialchars($period, ENT_QUOTES); ?>">
            <input type="hidden" name="date" value="<?php echo htmlspecialchars($date, ENT_QUOTES); ?>">
            <button type="submit" class="btn btn-primary"><i class="fa fa-magic"></i> Générer avec l'IA</button>
          <?php echo form_close(); ?>
        <?php } ?>
      </div>
      <?php if (!$ai_ready) { ?>
        <div class="alert alert-warning" style="margin-top:10px;">Configurez d'abord votre clé API Anthropic dans <a href="<?php echo admin_url('school_ia_bridge/settings'); ?>">Réglages → Rapports IA</a>.</div>
      <?php } ?>

      <?php if ($report) { ?>
        <hr>
        <div class="clearfix" style="margin-bottom:8px;">
          <strong class="pull-left"><?php echo htmlspecialchars((string) $report->label, ENT_QUOTES); ?></strong>
          <span class="pull-right text-muted">Généré le <?php echo htmlspecialchars((string) $report->created_at, ENT_QUOTES); ?></span>
        </div>
        <div class="sia-report" style="line-height:1.6;">
          <?php echo $report->content; /* HTML produit par notre appel IA */ ?>
        </div>
      <?php } ?>
    </div></div>

    <!-- Historique des rapports -->
    <?php if (!empty($reports)) { ?>
      <div class="panel_s"><div class="panel-body">
        <h5 class="bold" style="margin-top:0;">Rapports précédents</h5>
        <table class="table">
          <tbody>
            <?php foreach ($reports as $r) { ?>
              <tr>
                <td><a href="<?php echo admin_url('school_ia_bridge/reporting?report=' . (int) $r->id); ?>"><?php echo htmlspecialchars((string) $r->label, ENT_QUOTES); ?></a></td>
                <td class="text-muted"><?php echo htmlspecialchars((string) $r->created_at, ENT_QUOTES); ?></td>
                <td class="text-right">
                  <a href="<?php echo admin_url('school_ia_bridge/reporting_delete/' . (int) $r->id); ?>" class="text-muted"
                     onclick="return confirm('Supprimer ce rapport ?');"><i class="fa fa-trash"></i></a>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div></div>
    <?php } ?>

  </div>
</div>
<?php init_tail(); ?>
</body>
</html>
