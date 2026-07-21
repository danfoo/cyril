<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">

        <div class="panel_s">
          <div class="panel-body">
            <div class="clearfix">
              <h4 class="no-margin pull-left"><i class="fa fa-graduation-cap"></i> School IA — Boîte de réception</h4>
              <a href="<?php echo admin_url('school_ia_bridge/pipeline'); ?>" class="btn btn-primary pull-right">
                <i class="fa fa-columns"></i> Voir le pipeline
              </a>
            </div>
          </div>
        </div>

        <div class="panel_s">
          <div class="panel-body">
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
                  <th>Étape</th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($leads)) { ?>
                <tr>
                  <td colspan="8" class="text-center text-muted" style="padding:30px;">
                    Aucun lead reçu pour l'instant.
                  </td>
                </tr>
              <?php } else {
                  foreach ($leads as $lead) {
                      $stage = $lead->stage ?? 'nouveau'; ?>
                <tr>
                  <td><?php echo (int) $lead->id; ?></td>
                  <td><?php echo htmlspecialchars((string) $lead->received_at, ENT_QUOTES); ?></td>
                  <td>
                    <a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $lead->id); ?>">
                      <?php echo htmlspecialchars((string) ($lead->name ?: ('Lead #' . $lead->id)), ENT_QUOTES); ?>
                    </a>
                  </td>
                  <td><?php echo htmlspecialchars((string) $lead->email, ENT_QUOTES); ?></td>
                  <td><?php echo htmlspecialchars((string) $lead->phone, ENT_QUOTES); ?></td>
                  <td><?php echo htmlspecialchars((string) $lead->formation, ENT_QUOTES); ?></td>
                  <td><span class="label label-info"><?php echo htmlspecialchars((string) $lead->score, ENT_QUOTES); ?></span></td>
                  <td>
                    <span class="label" style="background:<?php echo $model->stageColor($stage); ?>;">
                      <?php echo htmlspecialchars($model->stageLabel($stage), ENT_QUOTES); ?>
                    </span>
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
