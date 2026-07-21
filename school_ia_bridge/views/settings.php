<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin"><i class="fa fa-cog"></i> Réglages — Connexion du plugin School IA</h4>
            <hr class="hr-panel-heading" />
            <p class="text-muted">
              Recopiez ces deux valeurs dans WordPress → <strong>School IA → Réglages → CRM</strong>
              pour que les leads du site remontent ici.
            </p>

            <div class="form-group">
              <label class="control-label">URL du point d'entrée</label>
              <input type="text" class="form-control" readonly onclick="this.select()"
                     value="<?php echo htmlspecialchars($endpoint, ENT_QUOTES); ?>">
            </div>

            <div class="form-group">
              <label class="control-label">Secret partagé</label>
              <input type="text" class="form-control" readonly onclick="this.select()"
                     value="<?php echo htmlspecialchars($secret, ENT_QUOTES); ?>">
            </div>

            <a href="<?php echo admin_url('school_ia_bridge/regenerate_secret'); ?>"
               class="btn btn-default"
               onclick="return confirm('Régénérer le secret ? Il faudra le recopier dans le plugin, sinon les leads cesseront d\'arriver.');">
              <i class="fa fa-refresh"></i> Régénérer le secret
            </a>
          </div>
        </div>

        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin"><i class="fa fa-mobile"></i> SMS — LAfricaMobile</h4>
            <hr class="hr-panel-heading" />
            <?php echo form_open(admin_url('school_ia_bridge/save_settings')); ?>
              <div class="form-group">
                <label class="control-label">Account ID</label>
                <input type="text" name="sms_accountid" class="form-control"
                       value="<?php echo htmlspecialchars((string) $sms_account, ENT_QUOTES); ?>">
              </div>
              <div class="form-group">
                <label class="control-label">Mot de passe API</label>
                <input type="password" name="sms_password" class="form-control" autocomplete="new-password"
                       placeholder="<?php echo $sms_has_pwd ? '•••••••• (laisser vide pour ne pas changer)' : ''; ?>">
              </div>
              <div class="form-group">
                <label class="control-label">Expéditeur (sender)</label>
                <input type="text" name="sms_sender" class="form-control" maxlength="11"
                       value="<?php echo htmlspecialchars((string) $sms_sender, ENT_QUOTES); ?>" placeholder="Ex. SchoolIA">
              </div>
              <button type="submit" class="btn btn-primary">Enregistrer</button>
            <?php echo form_close(); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
</body>
</html>
