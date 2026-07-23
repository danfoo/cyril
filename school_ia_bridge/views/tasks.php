<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">

    <?php
    $prioConf = [
        'haute'   => ['Haute',   '#dc2626'],
        'moyenne' => ['Moyenne', '#d97706'],
        'basse'   => ['Basse',   '#64748b'],
    ];
    $curPriority = $priority ?? '';
    $filterQs = function ($f) use ($curPriority) {
        return admin_url('school_ia_bridge/tasks?filter=' . $f . ($curPriority ? '&priority=' . urlencode($curPriority) : ''));
    };
    $returnUrl = admin_url('school_ia_bridge/tasks?filter=' . $filter . ($curPriority ? '&priority=' . urlencode($curPriority) : ''));

    $todayStart = strtotime(date('Y-m-d 00:00:00'));
    $todayEnd   = strtotime(date('Y-m-d 23:59:59'));
    $weekEnd    = $todayStart + 7 * 86400;
    $bucketOf = function ($due) use ($todayStart, $todayEnd, $weekEnd) {
        if (!$due) { return ['none', 'Sans échéance', '#94a3b8']; }
        $t = strtotime($due);
        if ($t < $todayStart) { return ['overdue', 'En retard', '#dc2626']; }
        if ($t <= $todayEnd)  { return ['today', "Aujourd'hui", '#d97706']; }
        if ($t <= $weekEnd)   { return ['week', 'Cette semaine', '#2563eb']; }
        return ['later', 'À venir', '#16a34a'];
    };
    ?>

    <div class="clearfix" style="margin-bottom:15px;">
      <h4 class="no-margin pull-left"><i class="fa fa-check-square-o"></i> Tâches à faire <?php echo sia_help('tasks'); ?></h4>
    </div>

    <!-- Création rapide -->
    <div class="panel_s"><div class="panel-body">
      <h5 class="bold" style="margin-top:0;">
        <span class="sia-panel-icon sia-ic-primary"><i class="fa fa-plus"></i></span>
        Nouvelle tâche
      </h5>
      <?php echo form_open(admin_url('school_ia_bridge/task_quick_add'), ['class' => 'row']); ?>
        <div class="col-md-4 form-group">
          <label class="control-label">Intitulé</label>
          <input type="text" name="title" class="form-control" placeholder="Ex. Rappeler le prospect" required>
        </div>
        <div class="col-md-2 form-group">
          <label class="control-label">Échéance</label>
          <input type="datetime-local" name="due_at" class="form-control">
        </div>
        <div class="col-md-2 form-group">
          <label class="control-label">Priorité</label>
          <select name="priority" class="form-control">
            <option value="moyenne">Moyenne</option>
            <option value="haute">Haute</option>
            <option value="basse">Basse</option>
          </select>
        </div>
        <div class="col-md-2 form-group">
          <label class="control-label">Lead (facultatif)</label>
          <select name="lead_id" class="form-control selectpicker" data-live-search="true" data-width="100%" title="—">
            <option value="0">—</option>
            <?php foreach ($leads as $l) { ?>
              <option value="<?php echo (int) $l->id; ?>"><?php echo htmlspecialchars((string) ($l->name ?: ('Lead #' . $l->id)), ENT_QUOTES); ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="col-md-2 form-group">
          <label class="control-label">Responsable</label>
          <select name="staff_id" class="form-control">
            <option value="0">— Moi —</option>
            <?php foreach ($staff as $s) { ?>
              <option value="<?php echo (int) $s->staffid; ?>"><?php echo htmlspecialchars($s->firstname . ' ' . $s->lastname, ENT_QUOTES); ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="col-md-12">
          <button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> Ajouter la tâche</button>
        </div>
      <?php echo form_close(); ?>
    </div></div>

    <!-- Onglets de statut + filtre priorité -->
    <div class="panel_s"><div class="panel-body">
      <div class="clearfix" style="margin-bottom:6px;">
        <div class="pull-left" style="display:flex;gap:6px;flex-wrap:wrap;">
          <?php
          $tabs = [
              'todo'     => ['À faire', (int) $counts['todo'], 'label-primary'],
              'overdue'  => ['En retard', (int) $counts['overdue'], 'label-danger'],
              'today'    => ["Aujourd'hui", (int) $counts['today'], 'label-warning'],
              'upcoming' => ['À venir', (int) $counts['upcoming'], 'label-info'],
              'done'     => ['Terminées', (int) $counts['done'], 'label-default'],
          ];
          foreach ($tabs as $f => $conf) {
              $active = ($filter === $f) ? 'btn-primary' : 'btn-default'; ?>
            <a href="<?php echo $filterQs($f); ?>" class="btn btn-sm <?php echo $active; ?>">
              <?php echo $conf[0]; ?>
              <span class="label <?php echo $active === 'btn-primary' ? 'label-default' : $conf[2]; ?>" style="margin-left:4px;"><?php echo $conf[1]; ?></span>
            </a>
          <?php } ?>
        </div>
        <div class="pull-right">
          <select class="form-control input-sm" style="height:32px;width:170px;"
                  onchange="location.href='<?php echo admin_url('school_ia_bridge/tasks?filter=' . $filter); ?>'+(this.value?'&priority='+this.value:'');">
            <option value="">Toutes priorités</option>
            <?php foreach ($prioConf as $k => $c) { ?>
              <option value="<?php echo $k; ?>" <?php echo $curPriority === $k ? 'selected' : ''; ?>><?php echo $c[0]; ?></option>
            <?php } ?>
          </select>
        </div>
      </div>

      <?php if (empty($tasks)) { ?>
        <div class="text-center" style="padding:36px 10px;">
          <div style="font-size:34px;">🎉</div>
          <p class="text-muted" style="margin:8px 0 14px;">Aucune tâche dans cette vue.</p>
          <a href="#" class="btn btn-primary" onclick="document.querySelector('input[name=title]').focus();return false;">
            <i class="fa fa-plus"></i> Créer une tâche
          </a>
        </div>
      <?php } else { ?>
        <?php echo form_open(admin_url('school_ia_bridge/tasks_bulk')); ?>
        <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnUrl, ENT_QUOTES); ?>">

        <!-- Barre d'actions groupées -->
        <div class="sia-bulk-bar" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px;">
          <span class="text-muted" style="font-size:12.5px;"><i class="fa fa-hand-o-up"></i> Sélection :</span>
          <button type="submit" name="do" value="complete" class="btn btn-sm btn-default"><i class="fa fa-check text-success"></i> Terminer</button>
          <button type="submit" name="do" value="postpone" class="btn btn-sm btn-default"><i class="fa fa-clock-o"></i> Reporter +7j</button>
          <?php if ($filter === 'done') { ?>
            <button type="submit" name="do" value="reopen" class="btn btn-sm btn-default"><i class="fa fa-undo"></i> Rouvrir</button>
          <?php } ?>
          <span style="display:inline-flex;gap:4px;align-items:center;">
            <select name="reassign_staff" class="form-control input-sm" style="height:30px;width:150px;">
              <option value="0">— Responsable —</option>
              <?php foreach ($staff as $s) { ?>
                <option value="<?php echo (int) $s->staffid; ?>"><?php echo htmlspecialchars($s->firstname . ' ' . $s->lastname, ENT_QUOTES); ?></option>
              <?php } ?>
            </select>
            <button type="submit" name="do" value="reassign" class="btn btn-sm btn-default"><i class="fa fa-user"></i> Réassigner</button>
          </span>
          <button type="submit" name="do" value="delete" class="btn btn-sm btn-default" onclick="return confirm('Supprimer les tâches sélectionnées ?');"><i class="fa fa-trash text-danger"></i> Supprimer</button>
        </div>

        <table class="table no-margin">
          <thead>
            <tr>
              <th style="width:28px;"><input type="checkbox" onclick="var b=this.checked;this.closest('table').querySelectorAll('input[name=\'ids[]\']').forEach(function(c){c.checked=b;});"></th>
              <th>Tâche</th>
              <th>Priorité</th>
              <th>Lead</th>
              <th>Responsable</th>
              <th>Échéance</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php
            $lastBucket = null;
            $showBuckets = ($filter === 'todo');
            foreach ($tasks as $t) {
                $pc = $prioConf[$t->priority] ?? $prioConf['moyenne'];
                if ($showBuckets) {
                    [$bk, $blabel, $bcolor] = $bucketOf($t->due_at);
                    if ($bk !== $lastBucket) {
                        $lastBucket = $bk; ?>
                        <tr class="sia-bucket-row"><td colspan="7" style="background:var(--sia-surface-2);font-weight:700;font-size:12px;color:<?php echo $bcolor; ?>;text-transform:uppercase;letter-spacing:.04em;padding:8px 12px;"><?php echo $blabel; ?></td></tr>
                    <?php }
                }
                $overdue = ($t->due_at && !$t->done && strtotime($t->due_at) < time()); ?>
              <tr>
                <td><input type="checkbox" name="ids[]" value="<?php echo (int) $t->id; ?>"></td>
                <td>
                  <a href="<?php echo admin_url('school_ia_bridge/task_toggle/' . (int) $t->id . '?back=tasks'); ?>" title="<?php echo $t->done ? 'Rouvrir' : 'Marquer fait'; ?>" style="margin-right:6px;">
                    <i class="fa <?php echo $t->done ? 'fa-check-square text-success' : 'fa-square-o text-muted'; ?>"></i>
                  </a>
                  <span style="<?php echo $t->done ? 'text-decoration:line-through;color:var(--sia-muted);' : ''; ?>"><?php echo htmlspecialchars((string) $t->title, ENT_QUOTES); ?></span>
                </td>
                <td>
                  <div class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" style="text-decoration:none;">
                      <span class="label" style="background:<?php echo $pc[1]; ?>1a;color:<?php echo $pc[1]; ?>;"><?php echo $pc[0]; ?> <i class="fa fa-caret-down"></i></span>
                    </a>
                    <ul class="dropdown-menu">
                      <?php foreach ($prioConf as $pk => $pcc) { ?>
                        <li><a href="<?php echo admin_url('school_ia_bridge/task_priority/' . (int) $t->id . '?p=' . $pk . '&return=' . urlencode($returnUrl)); ?>">
                          <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?php echo $pcc[1]; ?>;margin-right:7px;"></span><?php echo $pcc[0]; ?>
                        </a></li>
                      <?php } ?>
                    </ul>
                  </div>
                </td>
                <td>
                  <?php if (!empty($t->lead_id) && $t->lead_name !== null) { ?>
                    <a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $t->lead_id); ?>"><?php echo htmlspecialchars((string) ($t->lead_name ?: ('Lead #' . $t->lead_id)), ENT_QUOTES); ?></a>
                  <?php } elseif (!empty($t->lead_id)) { ?>
                    <a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $t->lead_id); ?>">Lead #<?php echo (int) $t->lead_id; ?></a>
                  <?php } else { ?>
                    <span class="text-muted">—</span>
                  <?php } ?>
                </td>
                <td>
                  <?php echo $t->staff_name ? htmlspecialchars((string) $t->staff_name, ENT_QUOTES) : '<span class="text-muted">—</span>'; ?>
                </td>
                <td>
                  <?php if ($t->due_at) { ?>
                    <span class="label <?php echo $overdue ? 'label-danger' : 'label-default'; ?>"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) $t->due_at)), ENT_QUOTES); ?></span>
                  <?php } else { ?>
                    <span class="text-muted">—</span>
                  <?php } ?>
                </td>
                <td class="text-right">
                  <a href="<?php echo admin_url('school_ia_bridge/task_delete/' . (int) $t->id . '?back=tasks'); ?>" class="text-muted"
                     onclick="return confirm('Supprimer cette tâche ?');"><i class="fa fa-trash"></i></a>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
        <?php echo form_close(); ?>
      <?php } ?>
    </div></div>

  </div>
</div>
<?php init_tail(); ?>
</body>
</html>
