<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">

        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin"><i class="fa fa-graduation-cap"></i> School IA — Leads reçus</h4>
            <hr class="hr-panel-heading" />
            <p class="text-muted">
              Configurez le plugin WordPress <strong>School IA</strong> pour qu'il envoie les leads ici.
            </p>
            <div class="row">
              <div class="col-md-8">
                <label class="control-label">URL du point d'entrée</label>
                <input type="text" class="form-control" readonly onclick="this.select()"
                       value="<?php echo htmlspecialchars($endpoint, ENT_QUOTES); ?>">
              </div>
              <div class="col-md-4">
                <label class="control-label">Secret partagé</label>
                <input type="text" class="form-control" readonly onclick="this.select()"
                       value="<?php echo htmlspecialchars($secret, ENT_QUOTES); ?>">
              </div>
            </div>
          </div>
        </div>

        <div class="panel_s">
          <div class="panel-body">
            <div class="row" style="margin-bottom:15px;">
              <div class="col-md-12">
                <a href="<?php echo admin_url('school_ia_bridge/convert'); ?>" class="btn btn-primary">
                  <i class="fa fa-exchange"></i> Convertir en leads Perfex
                </a>
                <span class="text-muted" style="margin-left:8px;">
                  Crée un lead natif dans <strong>Perfex → Leads</strong> pour chaque lead reçu non encore converti (source « School IA »).
                </span>
              </div>
            </div>
            <table class="table dt-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Reçu le</th>
                  <th>Nom</th>
                  <th>E-mail</th>
                  <th>Téléphone</th>
                  <th>Formation</th>
                  <th>Score</th>
                  <th>Site</th>
                  <th>Perfex</th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($leads)) { ?>
                <tr>
                  <td colspan="9" class="text-center text-muted" style="padding:30px;">
                    Aucun lead reçu pour l'instant.
                  </td>
                </tr>
              <?php } else {
                  foreach ($leads as $lead) { ?>
                <tr>
                  <td><?php echo (int) $lead->id; ?></td>
                  <td><?php echo htmlspecialchars((string) $lead->received_at, ENT_QUOTES); ?></td>
                  <td><?php echo htmlspecialchars((string) $lead->name, ENT_QUOTES); ?></td>
                  <td><?php echo htmlspecialchars((string) $lead->email, ENT_QUOTES); ?></td>
                  <td><?php echo htmlspecialchars((string) $lead->phone, ENT_QUOTES); ?></td>
                  <td><?php echo htmlspecialchars((string) $lead->formation, ENT_QUOTES); ?></td>
                  <td>
                    <span class="label label-info">
                      <?php echo htmlspecialchars((string) $lead->score, ENT_QUOTES); ?>
                      <?php echo $lead->band ? '· ' . htmlspecialchars(str_replace('_', ' ', (string) $lead->band), ENT_QUOTES) : ''; ?>
                    </span>
                  </td>
                  <td><?php echo htmlspecialchars((string) $lead->source_site, ENT_QUOTES); ?></td>
                  <td>
                    <?php if (!empty($lead->perfex_lead_id)) { ?>
                      <a href="<?php echo admin_url('leads/index/' . (int) $lead->perfex_lead_id); ?>" class="label label-success" style="text-decoration:none;">
                        Lead #<?php echo (int) $lead->perfex_lead_id; ?>
                      </a>
                    <?php } else { ?>
                      <span class="text-muted">—</span>
                    <?php } ?>
                  </td>
                </tr>
              <?php }
              } ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
</body>
</html>
