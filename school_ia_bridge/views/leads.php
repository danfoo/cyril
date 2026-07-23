<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">

        <div class="panel_s">
          <div class="panel-body">
            <div class="clearfix">
              <?php $qs = http_build_query(array_filter([
                  'q' => $filters['q'] ?? '', 'stage' => $filters['stage'] ?? '', 'min_score' => $filters['min_score'] ?? '',
                  'rentree' => $filters['rentree'] ?? '', 'unassigned' => $filters['unassigned'] ?? '',
              ])); ?>
              <h4 class="no-margin pull-left"><i class="fa fa-graduation-cap"></i> School IA — Contacts <?php echo sia_help('inbox'); ?></h4>
              <a href="<?php echo admin_url('school_ia_bridge/new_lead'); ?>" class="btn btn-primary pull-right">
                <i class="fa fa-user-plus"></i> Ajouter un lead
              </a>
              <div class="btn-group pull-right" style="margin-right:6px;">
                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                  <i class="fa fa-download"></i> Exporter <span class="caret"></span>
                </button>
                <ul class="dropdown-menu">
                  <li><a href="<?php echo admin_url('school_ia_bridge/export') . ($qs ? '?' . $qs : ''); ?>">CSV</a></li>
                  <li><a href="<?php echo admin_url('school_ia_bridge/export') . '?format=xlsx' . ($qs ? '&' . $qs : ''); ?>">Excel (.xlsx)</a></li>
                </ul>
              </div>
              <a href="<?php echo admin_url('school_ia_bridge/import'); ?>" class="btn btn-default pull-right" style="margin-right:6px;">
                <i class="fa fa-upload"></i> Importer
              </a>
              <a href="<?php echo admin_url('school_ia_bridge/pipeline'); ?>" class="btn btn-default pull-right" style="margin-right:6px;">
                <i class="fa fa-columns"></i> Voir le pipeline
              </a>
            </div>
            <hr class="hr-panel-heading" />
            <form method="get" action="<?php echo admin_url('school_ia_bridge'); ?>" class="row">
              <div class="col-md-4">
                <input type="text" name="q" class="form-control" placeholder="Rechercher (nom, e-mail, formation, téléphone)…"
                       value="<?php echo htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES); ?>">
              </div>
              <div class="col-md-2">
                <select name="stage" class="form-control">
                  <option value="">Toutes les étapes</option>
                  <?php foreach ($model->stages() as $s => $conf) { ?>
                    <option value="<?php echo $s; ?>" <?php echo (($filters['stage'] ?? '') === $s) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($conf[0], ENT_QUOTES); ?>
                    </option>
                  <?php } ?>
                </select>
              </div>
              <div class="col-md-2">
                <select name="rentree" class="form-control">
                  <option value="">Toutes les rentrées</option>
                  <?php foreach (($rentrees ?? []) as $r) { ?>
                    <option value="<?php echo htmlspecialchars($r, ENT_QUOTES); ?>" <?php echo (($filters['rentree'] ?? '') === $r) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($r, ENT_QUOTES); ?>
                    </option>
                  <?php } ?>
                </select>
              </div>
              <div class="col-md-1">
                <input type="number" name="min_score" class="form-control" placeholder="Score min"
                       value="<?php echo htmlspecialchars((string) ($filters['min_score'] ?? ''), ENT_QUOTES); ?>">
              </div>
              <div class="col-md-2">
                <label style="font-weight:normal;display:block;margin:9px 0 0;">
                  <input type="checkbox" name="unassigned" value="1" <?php echo !empty($filters['unassigned']) ? 'checked' : ''; ?>>
                  Non assignés
                </label>
              </div>
              <div class="col-md-1">
                <button type="submit" class="btn btn-default btn-block"><i class="fa fa-search"></i></button>
              </div>
            </form>
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
                  <th>Rentrée</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($leads)) { ?>
                <tr>
                  <td colspan="10" class="text-center text-muted" style="padding:30px;">
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
                    <span class="label" style="background:<?php echo $model->stageColor($stage); ?>1a;color:<?php echo $model->stageColor($stage); ?>;">
                      <?php echo htmlspecialchars($model->stageLabel($stage), ENT_QUOTES); ?>
                    </span>
                  </td>
                  <td><?php echo htmlspecialchars((string) ($lead->rentree ?? ''), ENT_QUOTES); ?></td>
                  <td class="text-right">
                    <a href="<?php echo admin_url('school_ia_bridge/lead_delete/' . (int) $lead->id); ?>" class="text-muted"
                       onclick="return confirm('Supprimer définitivement ce lead et tout son historique ?');" title="Supprimer">
                      <i class="fa fa-trash"></i>
                    </a>
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
