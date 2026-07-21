<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">

    <div class="clearfix" style="margin-bottom:15px;">
      <a href="<?php echo admin_url('school_ia_bridge/pipeline'); ?>" class="btn btn-default pull-right">
        <i class="fa fa-columns"></i> Pipeline
      </a>
      <h4 class="no-margin"><i class="fa fa-user"></i>
        <?php echo htmlspecialchars((string) ($lead->name ?: ('Lead #' . $lead->id)), ENT_QUOTES); ?>
      </h4>
    </div>

    <div class="row">
      <!-- Colonne infos + actions -->
      <div class="col-md-5">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">Informations</h5>
          <table class="table table-striped">
            <tr><td class="bold">E-mail</td><td><?php echo htmlspecialchars((string) ($lead->email ?: '—'), ENT_QUOTES); ?></td></tr>
            <tr><td class="bold">Téléphone</td><td><?php echo htmlspecialchars((string) ($lead->phone ?: '—'), ENT_QUOTES); ?></td></tr>
            <tr><td class="bold">Formation</td><td><?php echo htmlspecialchars((string) ($lead->formation ?: '—'), ENT_QUOTES); ?></td></tr>
            <tr><td class="bold">Score</td><td>
              <span class="label label-info"><?php echo htmlspecialchars((string) $lead->score, ENT_QUOTES); ?></span>
              <?php echo $lead->band ? ' ' . htmlspecialchars(str_replace('_', ' ', (string) $lead->band), ENT_QUOTES) : ''; ?>
            </td></tr>
            <tr><td class="bold">Site source</td><td><?php echo htmlspecialchars((string) ($lead->source_site ?: '—'), ENT_QUOTES); ?></td></tr>
            <tr><td class="bold">Reçu le</td><td><?php echo htmlspecialchars((string) $lead->received_at, ENT_QUOTES); ?></td></tr>
          </table>
          <?php if (!empty($lead->description)) { ?>
            <p class="text-muted" style="white-space:pre-wrap;"><?php echo htmlspecialchars((string) $lead->description, ENT_QUOTES); ?></p>
          <?php } ?>
        </div></div>

        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">Étape du pipeline</h5>
          <?php $cur = $lead->stage ?? 'nouveau'; ?>
          <p>Actuelle :
            <span class="label" style="background:<?php echo $model->stageColor($cur); ?>;">
              <?php echo htmlspecialchars($model->stageLabel($cur), ENT_QUOTES); ?>
            </span>
          </p>
          <?php foreach ($model->stages() as $s => $conf) {
              if ($s === $cur) { continue; } ?>
            <a href="<?php echo admin_url('school_ia_bridge/move/' . (int) $lead->id . '?stage=' . $s); ?>"
               class="btn btn-xs btn-default" style="margin:2px;">
              → <?php echo htmlspecialchars($conf[0], ENT_QUOTES); ?>
            </a>
          <?php } ?>
        </div></div>

        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">Responsable</h5>
          <?php echo form_open(admin_url('school_ia_bridge/assign/' . (int) $lead->id)); ?>
            <div class="input-group">
              <select name="owner_id" class="form-control selectpicker" data-width="100%">
                <option value="0">— Aucun —</option>
                <?php foreach ($staff as $st) { ?>
                  <option value="<?php echo (int) $st->staffid; ?>" <?php echo ((int) $lead->owner_id === (int) $st->staffid) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($st->firstname . ' ' . $st->lastname, ENT_QUOTES); ?>
                  </option>
                <?php } ?>
              </select>
              <span class="input-group-btn">
                <button type="submit" class="btn btn-primary">OK</button>
              </span>
            </div>
          <?php echo form_close(); ?>
        </div></div>
      </div>

      <!-- Colonne activité / notes -->
      <div class="col-md-7">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">Tâches & relances</h5>
          <?php echo form_open(admin_url('school_ia_bridge/task_add/' . (int) $lead->id)); ?>
            <div class="row">
              <div class="col-sm-7" style="margin-bottom:6px;">
                <input type="text" name="title" class="form-control" placeholder="Ex. Rappeler le prospect" required>
              </div>
              <div class="col-sm-3" style="margin-bottom:6px;">
                <input type="date" name="due_date" class="form-control" title="Échéance">
              </div>
              <div class="col-sm-2" style="margin-bottom:6px;">
                <button type="submit" class="btn btn-primary btn-block">+</button>
              </div>
            </div>
          <?php echo form_close(); ?>

          <?php if (!empty($tasks)) { ?>
            <ul class="list-unstyled" style="margin-top:6px;">
              <?php foreach ($tasks as $t) {
                  $overdue = (!$t->done && $t->due_date && $t->due_date < date('Y-m-d')); ?>
                <li style="padding:6px 0; border-bottom:1px solid #f0f0f0;">
                  <a href="<?php echo admin_url('school_ia_bridge/task_toggle/' . (int) $t->id); ?>"
                     title="<?php echo $t->done ? 'Marquer à faire' : 'Marquer fait'; ?>">
                    <i class="fa <?php echo $t->done ? 'fa-check-square-o text-success' : 'fa-square-o'; ?>"></i>
                  </a>
                  <span style="<?php echo $t->done ? 'text-decoration:line-through;color:#999;' : ''; ?>">
                    <?php echo htmlspecialchars((string) $t->title, ENT_QUOTES); ?>
                  </span>
                  <?php if ($t->due_date) { ?>
                    <span class="label <?php echo $overdue ? 'label-danger' : 'label-default'; ?>" style="margin-left:6px;">
                      <?php echo htmlspecialchars((string) $t->due_date, ENT_QUOTES); ?>
                    </span>
                  <?php } ?>
                  <a href="<?php echo admin_url('school_ia_bridge/task_delete/' . (int) $t->id); ?>"
                     class="pull-right text-muted" onclick="return confirm('Supprimer cette tâche ?');"><i class="fa fa-trash"></i></a>
                </li>
              <?php } ?>
            </ul>
          <?php } ?>
        </div></div>

        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">Ajouter une note</h5>
          <?php echo form_open(admin_url('school_ia_bridge/note/' . (int) $lead->id)); ?>
            <textarea name="content" class="form-control" rows="3" placeholder="Écrire une note…"></textarea>
            <button type="submit" class="btn btn-primary" style="margin-top:8px;">Enregistrer la note</button>
          <?php echo form_close(); ?>
        </div></div>

        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">Historique</h5>
          <?php if (empty($activities)) { ?>
            <p class="text-muted">Aucune activité pour l'instant.</p>
          <?php } else { ?>
            <ul class="list-unstyled">
              <?php foreach ($activities as $a) {
                  $icon = $a->type === 'note' ? 'fa-comment' : ($a->type === 'stage_change' ? 'fa-random' : 'fa-user');
                  $who = $a->staff_id ? get_staff_full_name((int) $a->staff_id) : 'Système'; ?>
                <li style="padding:8px 0; border-bottom:1px solid #eee;">
                  <i class="fa <?php echo $icon; ?> text-muted"></i>
                  <?php echo htmlspecialchars((string) $a->content, ENT_QUOTES); ?>
                  <div class="text-muted" style="font-size:11px;">
                    <?php echo htmlspecialchars($who . ' · ' . $a->created_at, ENT_QUOTES); ?>
                  </div>
                </li>
              <?php } ?>
            </ul>
          <?php } ?>
        </div></div>
      </div>
    </div>

  </div>
</div>
<?php init_tail(); ?>
</body>
</html>
