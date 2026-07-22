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
            <h4 class="no-margin"><i class="fa fa-bell"></i> Rappels automatiques</h4>
            <hr class="hr-panel-heading" />
            <p class="text-muted">Envoie un e-mail au responsable pour chaque tâche arrivée à échéance. Nécessite que le <strong>cron de Perfex</strong> soit configuré.</p>
            <?php echo form_open(admin_url('school_ia_bridge/save_settings')); ?>
              <input type="hidden" name="reminders_form" value="1">
              <label style="font-weight:normal;">
                <input type="checkbox" name="reminders_enabled" value="1"
                       <?php echo get_option('sia_reminders_enabled') !== '0' ? 'checked' : ''; ?>>
                Activer les rappels automatiques des tâches
              </label>
              <div style="margin-top:8px;"><button type="submit" class="btn btn-primary">Enregistrer</button></div>
            <?php echo form_close(); ?>
          </div>
        </div>

        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin"><i class="fa fa-graduation-cap"></i> Programmes</h4>
            <hr class="hr-panel-heading" />
            <p class="text-muted">Un programme par ligne. Ils servent à classer les documents.</p>
            <?php echo form_open(admin_url('school_ia_bridge/save_settings')); ?>
              <textarea name="programs" class="form-control" rows="5"
                        placeholder="Ex.&#10;Licence Marketing&#10;Master Finance&#10;BTS Informatique"><?php echo htmlspecialchars((string) get_option('sia_programs'), ENT_QUOTES); ?></textarea>
              <button type="submit" class="btn btn-primary" style="margin-top:8px;">Enregistrer les programmes</button>
            <?php echo form_close(); ?>
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
