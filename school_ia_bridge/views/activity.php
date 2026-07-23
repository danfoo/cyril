<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$icons = ['note' => 'fa-comment', 'stage_change' => 'fa-random', 'task' => 'fa-check-square-o',
          'email' => 'fa-envelope', 'sms' => 'fa-mobile', 'assignment' => 'fa-user'];
$labels = ['note' => 'Note', 'stage_change' => 'Changement d\'étape', 'task' => 'Tâche',
           'email' => 'E-mail', 'sms' => 'SMS', 'assignment' => 'Assignation'];
$colors = ['note' => 'sia-ic-primary', 'stage_change' => 'sia-ic-info', 'task' => 'sia-ic-danger',
           'email' => 'sia-ic-success', 'sms' => 'sia-ic-success', 'assignment' => 'sia-ic-warning'];
// Query string courant (pour revenir au journal après une action rapide).
$curQs = http_build_query(array_filter([
    'type' => $type, 'staff' => $staffId ?: '', 'from' => $from, 'to' => $to,
]));
// Conserve les filtres dans les liens de type.
$typeQs = function ($t) use ($staffId, $from, $to) {
    return http_build_query(array_filter([
        'type' => $t, 'staff' => $staffId ?: '', 'from' => $from, 'to' => $to,
    ]));
};
?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <h4 class="no-margin" style="margin-bottom:15px;"><i class="fa fa-history"></i> Journal d'activité <?php echo sia_help('activity'); ?></h4>

        <!-- Classement des plus actifs -->
        <?php if (!empty($leaderboard)) { ?>
          <div class="panel_s"><div class="panel-body">
            <h5 class="bold" style="margin-top:0;">
              <span class="sia-panel-icon sia-ic-warning"><i class="fa fa-trophy"></i></span>
              Conseillers les plus actifs <span class="text-muted" style="font-weight:400;">· <?php echo htmlspecialchars($lbLabel, ENT_QUOTES); ?></span>
            </h5>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
              <?php $lbMax = max(1, (int) $leaderboard[0]->n);
              foreach ($leaderboard as $i => $row) {
                  $name = $row->staff_id ? get_staff_full_name((int) $row->staff_id) : 'Système';
                  $active = ((int) $staffId === (int) $row->staff_id); ?>
                <a href="<?php echo admin_url('school_ia_bridge/activity?' . http_build_query(array_filter(['type' => $type, 'staff' => (int) $row->staff_id, 'from' => $from, 'to' => $to]))); ?>"
                   class="label <?php echo $active ? 'label-primary' : 'label-default'; ?>"
                   style="font-size:12.5px;padding:6px 12px;text-decoration:none;">
                  <?php echo $i === 0 ? '🏆 ' : ''; ?><?php echo htmlspecialchars($name, ENT_QUOTES); ?>
                  <strong style="margin-left:4px;"><?php echo (int) $row->n; ?></strong>
                </a>
              <?php } ?>
            </div>
          </div></div>
        <?php } ?>

        <!-- Filtres -->
        <div class="panel_s"><div class="panel-body" style="padding:12px 16px;">
          <form method="get" action="<?php echo admin_url('school_ia_bridge/activity'); ?>" style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
            <input type="hidden" name="type" value="<?php echo htmlspecialchars((string) $type, ENT_QUOTES); ?>">
            <div>
              <label class="control-label" style="display:block;">Conseiller</label>
              <select name="staff" class="form-control input-sm" style="height:34px;width:190px;">
                <option value="">Tous les conseillers</option>
                <?php foreach ($staff as $st) { ?>
                  <option value="<?php echo (int) $st->staffid; ?>" <?php echo ((int) $staffId === (int) $st->staffid) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($st->firstname . ' ' . $st->lastname, ENT_QUOTES); ?>
                  </option>
                <?php } ?>
              </select>
            </div>
            <div>
              <label class="control-label" style="display:block;">Du</label>
              <input type="date" name="from" class="form-control input-sm" style="height:34px;" value="<?php echo htmlspecialchars((string) $from, ENT_QUOTES); ?>">
            </div>
            <div>
              <label class="control-label" style="display:block;">Au</label>
              <input type="date" name="to" class="form-control input-sm" style="height:34px;" value="<?php echo htmlspecialchars((string) $to, ENT_QUOTES); ?>">
            </div>
            <button type="submit" class="btn btn-sm btn-default"><i class="fa fa-filter"></i> Filtrer</button>
            <?php if ($curQs) { ?>
              <a href="<?php echo admin_url('school_ia_bridge/activity'); ?>" class="text-muted" style="font-size:12px;margin-bottom:8px;">Réinitialiser</a>
            <?php } ?>
            <span style="flex:1 1 auto;"></span>
            <div class="btn-group" role="group">
              <a href="<?php echo admin_url('school_ia_bridge/activity?range=today' . ($type ? '&type=' . $type : '') . ($staffId ? '&staff=' . $staffId : '')); ?>" class="btn btn-sm btn-default <?php echo $range === 'today' ? 'btn-primary' : ''; ?>">Aujourd'hui</a>
              <a href="<?php echo admin_url('school_ia_bridge/activity?range=7' . ($type ? '&type=' . $type : '') . ($staffId ? '&staff=' . $staffId : '')); ?>" class="btn btn-sm btn-default <?php echo $range === '7' ? 'btn-primary' : ''; ?>">7 jours</a>
              <a href="<?php echo admin_url('school_ia_bridge/activity?range=30' . ($type ? '&type=' . $type : '') . ($staffId ? '&staff=' . $staffId : '')); ?>" class="btn btn-sm btn-default <?php echo $range === '30' ? 'btn-primary' : ''; ?>">30 jours</a>
            </div>
          </form>
        </div></div>

        <!-- Filtres par type -->
        <div style="margin-bottom:12px;">
          <a href="<?php echo admin_url('school_ia_bridge/activity' . ($typeQs(null) ? '?' . $typeQs(null) : '')); ?>" class="btn btn-sm <?php echo !$type ? 'btn-primary' : 'btn-default'; ?>">Tout</a>
          <?php foreach ($labels as $k => $lab) { ?>
            <a href="<?php echo admin_url('school_ia_bridge/activity?' . $typeQs($k)); ?>"
               class="btn btn-sm <?php echo $type === $k ? 'btn-primary' : 'btn-default'; ?>">
              <i class="fa <?php echo $icons[$k]; ?>"></i> <?php echo $lab; ?>
            </a>
          <?php } ?>
        </div>

        <div class="panel_s"><div class="panel-body">
          <?php if (empty($activities)) { ?>
            <p class="text-muted text-center" style="padding:30px;">Aucune activité pour ces critères.</p>
          <?php } else { ?>
            <table class="table">
              <tbody>
                <?php foreach ($activities as $a) {
                    $who = $a->staff_id ? get_staff_full_name((int) $a->staff_id) : 'Système';
                    $col = $colors[$a->type] ?? 'sia-ic-muted';
                    $pendingTask = ($a->type === 'task' && !empty($a->task_id) && (int) $a->task_done === 0); ?>
                  <tr>
                    <td style="width:34px;">
                      <span class="sia-activity-icon <?php echo $col; ?>"><i class="fa <?php echo $icons[$a->type] ?? 'fa-circle-o'; ?>"></i></span>
                    </td>
                    <td>
                      <?php echo htmlspecialchars((string) $a->content, ENT_QUOTES); ?>
                      <?php if ($a->type === 'task' && !empty($a->task_id) && (int) $a->task_done === 1) { ?>
                        <span class="label label-success" style="margin-left:6px;"><i class="fa fa-check"></i> fait</span>
                      <?php } ?>
                    </td>
                    <td style="width:170px;">
                      <a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $a->lead_id); ?>">
                        <?php echo htmlspecialchars((string) ($a->lead_name ?: ('Lead #' . $a->lead_id)), ENT_QUOTES); ?>
                      </a>
                    </td>
                    <td class="text-muted" style="width:200px;font-size:12.5px;">
                      <?php echo htmlspecialchars($who . ' · ' . $a->created_at, ENT_QUOTES); ?>
                    </td>
                    <td style="width:110px;" class="text-right">
                      <?php if ($pendingTask) { ?>
                        <a href="<?php echo admin_url('school_ia_bridge/task_toggle/' . (int) $a->task_id . '?back=activity' . ($curQs ? '&qs=' . rawurlencode($curQs) : '')); ?>"
                           class="btn btn-xs btn-default" title="Marquer la tâche comme terminée">
                          <i class="fa fa-check text-success"></i> Terminer
                        </a>
                      <?php } ?>
                    </td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          <?php } ?>
        </div></div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
</body>
</html>
