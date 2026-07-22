<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$icons = ['note' => 'fa-comment', 'stage_change' => 'fa-random', 'task' => 'fa-check-square-o',
          'email' => 'fa-envelope', 'sms' => 'fa-mobile', 'assignment' => 'fa-user'];
$labels = ['note' => 'Note', 'stage_change' => 'Changement d\'étape', 'task' => 'Tâche',
           'email' => 'E-mail', 'sms' => 'SMS', 'assignment' => 'Assignation'];
?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <h4 class="no-margin" style="margin-bottom:15px;"><i class="fa fa-history"></i> Journal d'activité <?php echo sia_help('activity'); ?></h4>

        <div style="margin-bottom:12px;">
          <a href="<?php echo admin_url('school_ia_bridge/activity'); ?>" class="btn btn-sm <?php echo !$type ? 'btn-primary' : 'btn-default'; ?>">Tout</a>
          <?php foreach ($labels as $k => $lab) { ?>
            <a href="<?php echo admin_url('school_ia_bridge/activity?type=' . $k); ?>"
               class="btn btn-sm <?php echo $type === $k ? 'btn-primary' : 'btn-default'; ?>">
              <i class="fa <?php echo $icons[$k]; ?>"></i> <?php echo $lab; ?>
            </a>
          <?php } ?>
        </div>

        <div class="panel_s"><div class="panel-body">
          <?php if (empty($activities)) { ?>
            <p class="text-muted text-center" style="padding:30px;">Aucune activité.</p>
          <?php } else { ?>
            <table class="table">
              <tbody>
                <?php foreach ($activities as $a) {
                    $who = $a->staff_id ? get_staff_full_name((int) $a->staff_id) : 'Système'; ?>
                  <tr>
                    <td style="width:24px;"><i class="fa <?php echo $icons[$a->type] ?? 'fa-circle-o'; ?> text-muted"></i></td>
                    <td><?php echo htmlspecialchars((string) $a->content, ENT_QUOTES); ?></td>
                    <td style="width:180px;">
                      <a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $a->lead_id); ?>">
                        <?php echo htmlspecialchars((string) ($a->lead_name ?: ('Lead #' . $a->lead_id)), ENT_QUOTES); ?>
                      </a>
                    </td>
                    <td class="text-muted" style="width:210px;">
                      <?php echo htmlspecialchars($who . ' · ' . $a->created_at, ENT_QUOTES); ?>
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
