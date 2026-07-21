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
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
</body>
</html>
