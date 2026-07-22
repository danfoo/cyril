<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">

        <h4 class="no-margin" style="margin-bottom:15px;"><i class="fa fa-check-square-o"></i> Tâches à faire <?php echo sia_help('tasks'); ?></h4>

        <div class="panel_s"><div class="panel-body">
          <?php if (empty($tasks)) { ?>
            <p class="text-muted text-center" style="padding:30px;">Aucune tâche en attente. 🎉</p>
          <?php } else { ?>
            <table class="table">
              <thead>
                <tr><th></th><th>Tâche</th><th>Lead</th><th>Échéance</th><th></th></tr>
              </thead>
              <tbody>
                <?php foreach ($tasks as $t) {
                    $overdue = ($t->due_at && strtotime($t->due_at) < time()); ?>
                  <tr class="<?php echo $overdue ? 'danger' : ''; ?>">
                    <td>
                      <a href="<?php echo admin_url('school_ia_bridge/task_toggle/' . (int) $t->id . '?back=tasks'); ?>" title="Marquer fait">
                        <i class="fa fa-square-o"></i>
                      </a>
                    </td>
                    <td><?php echo htmlspecialchars((string) $t->title, ENT_QUOTES); ?></td>
                    <td>
                      <a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $t->lead_id); ?>">
                        <?php echo htmlspecialchars((string) ($t->lead_name ?: ('Lead #' . $t->lead_id)), ENT_QUOTES); ?>
                      </a>
                    </td>
                    <td>
                      <?php if ($t->due_at) { ?>
                        <span class="label <?php echo $overdue ? 'label-danger' : 'label-default'; ?>">
                          <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($t->due_at)), ENT_QUOTES); ?>
                        </span>
                      <?php } else { ?>
                        <span class="text-muted">—</span>
                      <?php } ?>
                    </td>
                    <td class="text-right">
                      <a href="<?php echo admin_url('school_ia_bridge/task_delete/' . (int) $t->id); ?>"
                         class="text-muted" onclick="return confirm('Supprimer cette tâche ?');"><i class="fa fa-trash"></i></a>
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
