<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <div class="clearfix" style="margin-bottom:15px;">
          <h4 class="no-margin pull-left"><i class="fa fa-pencil"></i> Modifier le lead</h4>
          <a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $lead->id); ?>" class="btn btn-default pull-right">Annuler</a>
        </div>

        <div class="panel_s"><div class="panel-body">
          <?php echo form_open(admin_url('school_ia_bridge/update_lead')); ?>
            <input type="hidden" name="id" value="<?php echo (int) $lead->id; ?>">
            <div class="row">
              <div class="col-md-6 form-group">
                <label class="control-label">Nom complet</label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars((string) $lead->name, ENT_QUOTES); ?>" placeholder="Ex. Awa Diallo">
              </div>
              <div class="col-md-6 form-group">
                <label class="control-label">E-mail</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars((string) $lead->email, ENT_QUOTES); ?>" placeholder="prenom@exemple.com">
              </div>
              <div class="col-md-6 form-group">
                <label class="control-label">Téléphone</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars((string) $lead->phone, ENT_QUOTES); ?>" placeholder="221771234567">
              </div>
              <div class="col-md-6 form-group">
                <label class="control-label">Formation / Programme</label>
                <input type="text" name="formation" class="form-control" list="sia-programs" value="<?php echo htmlspecialchars((string) $lead->formation, ENT_QUOTES); ?>" placeholder="Ex. Licence Marketing">
                <datalist id="sia-programs">
                  <?php foreach ($programs as $p) { ?>
                    <option value="<?php echo htmlspecialchars($p, ENT_QUOTES); ?>"></option>
                  <?php } ?>
                </datalist>
              </div>
              <div class="col-md-6 form-group">
                <label class="control-label">Étape</label>
                <select name="stage" class="form-control">
                  <?php foreach ($model->stages() as $s => $conf) { ?>
                    <option value="<?php echo $s; ?>" <?php echo ($lead->stage === $s) ? 'selected' : ''; ?>><?php echo htmlspecialchars($conf[0], ENT_QUOTES); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="col-md-6 form-group">
                <label class="control-label">Score</label>
                <input type="number" name="score" class="form-control" min="0" max="100" value="<?php echo htmlspecialchars((string) (int) $lead->score, ENT_QUOTES); ?>" placeholder="0 à 100">
              </div>
              <div class="col-md-6 form-group">
                <label class="control-label">Rentrée (optionnel)</label>
                <input type="text" name="rentree" class="form-control" list="sia-rentrees" value="<?php echo htmlspecialchars((string) $lead->rentree, ENT_QUOTES); ?>" placeholder="Ex. Septembre 2026">
                <datalist id="sia-rentrees">
                  <?php foreach ($rentrees as $r) { ?>
                    <option value="<?php echo htmlspecialchars($r, ENT_QUOTES); ?>"></option>
                  <?php } ?>
                </datalist>
              </div>
            </div>
            <p class="text-muted" style="font-size:12px;">Renseignez au moins un nom, un e-mail ou un téléphone.</p>
            <button type="submit" class="btn btn-primary"><i class="fa fa-check"></i> Enregistrer les modifications</button>
            <a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $lead->id); ?>" class="btn btn-default">Annuler</a>
          <?php echo form_close(); ?>
        </div></div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
</body>
</html>
