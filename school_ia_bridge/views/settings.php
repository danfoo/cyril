<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin"><i class="fa fa-cog"></i> Réglages — Connexion du plugin School IA <?php echo sia_help('settings'); ?></h4>
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
            <h4 class="no-margin"><i class="fa fa-magic"></i> Rapports IA (Claude)</h4>
            <hr class="hr-panel-heading" />
            <p class="text-muted">Clé API Anthropic pour générer les rapports de la page <strong>Reporting</strong>. Obtenue sur console.anthropic.com.</p>
            <?php echo form_open(admin_url('school_ia_bridge/save_settings')); ?>
              <input type="hidden" name="ai_form" value="1">
              <div class="form-group">
                <label class="control-label">Clé API Anthropic</label>
                <input type="password" name="ai_api_key" class="form-control" autocomplete="new-password"
                       placeholder="<?php echo $ai_has_key ? '•••••••• (laisser vide pour ne pas changer)' : 'sk-ant-...'; ?>">
              </div>
              <div class="form-group">
                <label class="control-label">Modèle</label>
                <input type="text" name="ai_model" class="form-control" value="<?php echo htmlspecialchars((string) $ai_model, ENT_QUOTES); ?>">
                <p class="text-muted" style="font-size:12px;">Par défaut : <code>claude-opus-4-8</code>.</p>
              </div>
              <label style="font-weight:normal;display:block;margin-bottom:8px;">
                <input type="checkbox" name="comp_auto" value="1" <?php echo get_option('sia_comp_auto') !== '0' ? 'checked' : ''; ?>>
                Analyser automatiquement les conversations pour la <strong>veille concurrentielle</strong> (à chaque cron ; ne traite que les nouvelles conversations)
              </label>
              <button type="submit" class="btn btn-primary">Enregistrer</button>
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
            <h4 class="no-margin"><i class="fa fa-money"></i> Frais de scolarité & objectif</h4>
            <hr class="hr-panel-heading" />
            <p class="text-muted">Alimente les indicateurs financiers du tableau de bord (valeur du pipeline, CA réalisé, objectif d'inscrits).</p>
            <?php echo form_open(admin_url('school_ia_bridge/save_settings')); ?>
              <div class="form-group">
                <label class="control-label">Frais par formation (un par ligne, format « Formation:Montant »)</label>
                <textarea name="program_fees" class="form-control" rows="5"
                          placeholder="Ex.&#10;Licence Marketing:500000&#10;Master Finance:900000"><?php echo htmlspecialchars((string) $program_fees, ENT_QUOTES); ?></textarea>
              </div>
              <div class="form-group">
                <label class="control-label">Objectif d'inscrits (pour la jauge du tableau de bord)</label>
                <input type="number" name="target_inscrits" class="form-control" min="0"
                       value="<?php echo (int) $target_inscrits; ?>">
              </div>
              <button type="submit" class="btn btn-primary">Enregistrer</button>
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
              <div class="form-group">
                <label class="control-label">Chemin de l'API (endpoint)</label>
                <div class="input-group">
                  <span class="input-group-addon">https://lamsms.lafricamobile.com</span>
                  <input type="text" name="sms_endpoint" class="form-control"
                         value="<?php echo htmlspecialchars((string) $sms_endpoint, ENT_QUOTES); ?>" placeholder="/apiSend">
                </div>
                <p class="text-muted" style="font-size:12px;">Saisissez uniquement le <strong>chemin</strong> (ex. <code>/apiSend</code>) — pas l'URL complète. Défaut : <code>/apiSend</code> (endpoint « Send via JSON » de LAfricaMobile).</p>
              </div>
              <button type="submit" class="btn btn-primary">Enregistrer</button>
            <?php echo form_close(); ?>

            <hr>
            <h5 class="bold">Tester l'envoi</h5>
            <p class="text-muted" style="font-size:12px;">Envoie un vrai SMS de test et affiche la <strong>réponse brute</strong> de LAfricaMobile — utile pour vérifier que la configuration fonctionne réellement (et pas seulement un « envoyé » optimiste).</p>
            <?php echo form_open(admin_url('school_ia_bridge/test_sms'), ['class' => 'form-inline']); ?>
              <div class="form-group" style="margin-right:6px;">
                <input type="text" name="test_number" class="form-control" placeholder="Ex. 221771234567">
              </div>
              <button type="submit" class="btn btn-default"><i class="fa fa-paper-plane"></i> Envoyer un SMS de test</button>
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
