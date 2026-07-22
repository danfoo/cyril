<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <div class="clearfix" style="margin-bottom:15px;">
          <h4 class="no-margin pull-left"><i class="fa fa-upload"></i> Importer des leads (CSV / Excel) <?php echo sia_help('import'); ?></h4>
          <a href="<?php echo admin_url('school_ia_bridge'); ?>" class="btn btn-default pull-right">Retour</a>
        </div>

        <div class="panel_s"><div class="panel-body">
          <p>
            Votre fichier doit avoir une <strong>première ligne d'en-têtes</strong>. Les colonnes sont
            reconnues automatiquement d'après leur nom :
          </p>
          <ul class="text-muted">
            <li><strong>Nom</strong> : nom, prénom, name</li>
            <li><strong>E-mail</strong> : email, e-mail, courriel</li>
            <li><strong>Téléphone</strong> : téléphone, tel, mobile, numéro</li>
            <li><strong>Formation</strong> : formation, programme, filière</li>
            <li><strong>Score</strong> : score, note &nbsp;·&nbsp; <strong>Étape</strong> : étape, statut</li>
          </ul>
          <a href="<?php echo admin_url('school_ia_bridge/import_template'); ?>" class="btn btn-default btn-sm">
            <i class="fa fa-download"></i> Télécharger un modèle CSV
          </a>
          <hr>

          <?php echo form_open_multipart(admin_url('school_ia_bridge/import_run')); ?>
            <div class="form-group">
              <label class="control-label">Fichier (.csv, .xlsx, .xls)</label>
              <input type="file" name="file" class="form-control" accept=".csv,.xlsx,.xls,.txt" required>
            </div>
            <div class="row">
              <div class="col-md-6 form-group">
                <label class="control-label">Programme par défaut (si absent du fichier)</label>
                <select name="default_program" class="form-control">
                  <option value="">— Aucun —</option>
                  <?php foreach ($programs as $p) { ?>
                    <option value="<?php echo htmlspecialchars($p, ENT_QUOTES); ?>"><?php echo htmlspecialchars($p, ENT_QUOTES); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="col-md-6 form-group">
                <label class="control-label">Étape par défaut</label>
                <select name="default_stage" class="form-control">
                  <?php foreach ($model->stages() as $s => $conf) { ?>
                    <option value="<?php echo $s; ?>"><?php echo htmlspecialchars($conf[0], ENT_QUOTES); ?></option>
                  <?php } ?>
                </select>
              </div>
            </div>
            <p class="text-muted" style="font-size:12px;">Les leads dont l'e-mail existe déjà sont ignorés (pas de doublon).</p>
            <button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> Importer</button>
          <?php echo form_close(); ?>
        </div></div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
</body>
</html>
