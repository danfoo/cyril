<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">

        <?php
        $qs = http_build_query(array_filter([
            'q' => $filters['q'] ?? '', 'stage' => $filters['stage'] ?? '', 'min_score' => $filters['min_score'] ?? '',
            'rentree' => $filters['rentree'] ?? '', 'unassigned' => $filters['unassigned'] ?? '',
        ]));
        $returnUrl = admin_url('school_ia_bridge') . ($qs ? '?' . $qs : '');
        // Construit une URL en modifiant un sous-ensemble des filtres courants.
        $mk = function (array $over) use ($filters) {
            $q = array_filter([
                'q' => $filters['q'] ?? '', 'stage' => $filters['stage'] ?? '', 'min_score' => $filters['min_score'] ?? '',
                'rentree' => $filters['rentree'] ?? '', 'unassigned' => $filters['unassigned'] ?? '',
            ]);
            foreach ($over as $k => $v) { if ($v === null) { unset($q[$k]); } else { $q[$k] = $v; } }
            return admin_url('school_ia_bridge') . ($q ? '?' . http_build_query($q) : '');
        };
        ?>

        <div class="panel_s">
          <div class="panel-body">
            <div class="clearfix">
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

        <!-- Synthèse rapide (sur le résultat filtré) -->
        <div class="panel_s"><div class="panel-body" style="padding:12px 16px;">
          <div class="sia-chip-strip">
            <span class="sia-chip"><span class="sia-chip-dot" style="background:#d11349;"></span><strong><?php echo (int) $summary['total']; ?></strong> au total</span>
            <a class="sia-chip" href="<?php echo $mk(['min_score' => 60]); ?>"><span class="sia-chip-dot" style="background:#dc2626;"></span><strong><?php echo (int) $summary['hot']; ?></strong> chauds (≥ 60)</a>
            <span class="sia-chip"><span class="sia-chip-dot" style="background:#d97706;"></span><strong><?php echo (int) $summary['warm']; ?></strong> tièdes</span>
            <span class="sia-chip"><span class="sia-chip-dot" style="background:#2563eb;"></span><strong><?php echo (int) $summary['cold']; ?></strong> froids</span>
            <a class="sia-chip" href="<?php echo $mk(['unassigned' => 1]); ?>"><span class="sia-chip-dot" style="background:#ea580c;"></span><strong><?php echo (int) $summary['unassigned']; ?></strong> non assignés</a>
            <?php
            $topForm = array_slice($summary['by_formation'], 0, 3, true);
            foreach ($topForm as $fname => $fn) { ?>
              <span class="sia-chip sia-chip-muted"><i class="fa fa-graduation-cap"></i> <?php echo htmlspecialchars((string) $fname, ENT_QUOTES); ?> · <strong><?php echo (int) $fn; ?></strong></span>
            <?php } ?>
            <?php if ($qs) { ?>
              <a class="sia-chip sia-chip-muted" href="<?php echo admin_url('school_ia_bridge'); ?>"><i class="fa fa-times"></i> Réinitialiser</a>
            <?php } ?>
          </div>
        </div></div>

        <?php echo form_open(admin_url('school_ia_bridge/leads_bulk'), ['id' => 'sia-leads-form']); ?>
        <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnUrl, ENT_QUOTES); ?>">

        <!-- Barre d'actions groupées -->
        <div class="panel_s sia-no-print"><div class="panel-body" style="padding:12px 16px;">
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <span class="text-muted" style="font-size:12.5px;"><i class="fa fa-hand-o-up"></i> Sélection :</span>
            <span style="display:inline-flex;gap:4px;align-items:center;">
              <select name="assign_staff" class="form-control input-sm" style="height:30px;width:160px;">
                <option value="0">— Conseiller —</option>
                <?php foreach ($staff as $s) { ?>
                  <option value="<?php echo (int) $s->staffid; ?>"><?php echo htmlspecialchars($s->firstname . ' ' . $s->lastname, ENT_QUOTES); ?></option>
                <?php } ?>
              </select>
              <button type="submit" name="do" value="assign" class="btn btn-sm btn-default"><i class="fa fa-user"></i> Assigner</button>
            </span>
            <span style="display:inline-flex;gap:4px;align-items:center;">
              <select name="stage" class="form-control input-sm" style="height:30px;width:150px;">
                <option value="">— Étape —</option>
                <?php foreach ($model->stages() as $s => $conf) { ?>
                  <option value="<?php echo $s; ?>"><?php echo htmlspecialchars($conf[0], ENT_QUOTES); ?></option>
                <?php } ?>
              </select>
              <button type="submit" name="do" value="stage" class="btn btn-sm btn-default"><i class="fa fa-random"></i> Changer d'étape</button>
            </span>
            <button type="submit" name="do" value="delete" class="btn btn-sm btn-default" onclick="return confirm('Supprimer définitivement les leads sélectionnés et tout leur historique ?');"><i class="fa fa-trash text-danger"></i> Supprimer</button>
            <a href="<?php echo admin_url('school_ia_bridge/bulk'); ?>" class="btn btn-sm btn-default" style="margin-left:auto;"><i class="fa fa-paper-plane"></i> Campagne e-mail / SMS →</a>
          </div>
          <p class="text-muted" style="margin:8px 0 0;font-size:11.5px;">Cochez des leads puis choisissez une action. L'envoi de campagne ciblée par filtres se fait sur la page <strong>Envoi groupé</strong>.</p>
        </div></div>

        <div class="panel_s">
          <div class="panel-body">
            <table class="table dt-table">
              <thead>
                <tr>
                  <th style="width:26px;" data-orderable="false"><input type="checkbox" onclick="var b=this.checked;document.querySelectorAll('#sia-leads-form input[name=\'ids[]\']').forEach(function(c){c.checked=b;});"></th>
                  <th>#</th>
                  <th style="white-space:nowrap;">Reçu le</th>
                  <th>Nom</th>
                  <th>E-mail</th>
                  <th>Téléphone</th>
                  <th>Formation</th>
                  <th>Score</th>
                  <th style="white-space:nowrap;">Valeur</th>
                  <th>Étape</th>
                  <th>Conseiller</th>
                  <th>Rentrée</th>
                  <th data-orderable="false"></th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($leads)) { ?>
                <tr>
                  <td colspan="13" class="text-center text-muted" style="padding:30px;">
                    Aucun lead reçu pour l'instant.
                  </td>
                </tr>
              <?php } else {
                  foreach ($leads as $lead) {
                      $stage = $lead->stage ?? 'nouveau'; ?>
                <tr>
                  <td><input type="checkbox" name="ids[]" value="<?php echo (int) $lead->id; ?>"></td>
                  <td><?php echo (int) $lead->id; ?></td>
                  <td style="font-size:11px;line-height:1.25;white-space:nowrap;"><?php
                    $rc = (string) $lead->received_at;
                    echo htmlspecialchars(substr($rc, 0, 10), ENT_QUOTES)
                       . '<br><span style="opacity:.6;">' . htmlspecialchars(substr($rc, 11, 5), ENT_QUOTES) . '</span>';
                  ?></td>
                  <td>
                    <a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $lead->id); ?>">
                      <?php echo htmlspecialchars((string) ($lead->name ?: ('Lead #' . $lead->id)), ENT_QUOTES); ?>
                    </a>
                  </td>
                  <td><?php echo htmlspecialchars((string) $lead->email, ENT_QUOTES); ?></td>
                  <td><?php echo htmlspecialchars((string) $lead->phone, ENT_QUOTES); ?></td>
                  <td><?php echo htmlspecialchars((string) $lead->formation, ENT_QUOTES); ?></td>
                  <td><span class="label label-info"><?php echo htmlspecialchars((string) $lead->score, ENT_QUOTES); ?></span></td>
                  <td style="white-space:nowrap;"><?php
                    $val = $model->resolve_fee((string) $lead->formation, $feesIndex);
                    if ($val !== null) {
                        echo '<span style="font-size:11.5px;color:#0f766e;font-weight:600;">' . number_format($val, 0, ',', ' ')
                           . ' <span style="opacity:.55;font-weight:400;">' . htmlspecialchars((string) $currency, ENT_QUOTES) . '</span></span>';
                    } else {
                        echo '<span class="text-muted">—</span>';
                    } ?></td>
                  <td>
                    <span class="label" style="background:<?php echo $model->stageColor($stage); ?>1a;color:<?php echo $model->stageColor($stage); ?>;">
                      <?php echo htmlspecialchars($model->stageLabel($stage), ENT_QUOTES); ?>
                    </span>
                  </td>
                  <td>
                    <?php $owner = trim((string) ($lead->owner_name ?? ''));
                    if ($owner !== '') { ?>
                      <span class="label label-success"><?php echo htmlspecialchars($owner, ENT_QUOTES); ?></span>
                    <?php } else { ?>
                      <span class="text-muted">—</span>
                    <?php } ?>
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
        <?php echo form_close(); ?>

      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
</body>
</html>
