<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
  .sia-col { min-height: 60px; border-radius: 6px; transition: background .15s; }
  .sia-col.sia-over { background: #eef4ff; outline: 2px dashed #2e6ff2; }
  .sia-card { cursor: grab; }
  .sia-card:active { cursor: grabbing; }
</style>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="clearfix" style="margin-bottom:15px;">
          <h4 class="no-margin pull-left"><i class="fa fa-columns"></i> Pipeline d'admission
            <small class="text-muted">— glissez-déposez les cartes</small>
            <?php echo sia_help('pipeline'); ?>
          </h4>
          <a href="<?php echo admin_url('school_ia_bridge/new_lead'); ?>" class="btn btn-primary pull-right">
            <i class="fa fa-user-plus"></i> Ajouter un lead
          </a>
          <a href="<?php echo admin_url('school_ia_bridge'); ?>" class="btn btn-default pull-right" style="margin-right:6px;">
            <i class="fa fa-inbox"></i> Boîte de réception
          </a>
        </div>
      </div>
    </div>

    <div style="overflow-x:auto;">
      <div style="display:flex; gap:12px; min-width:900px; padding-bottom:10px;">
        <?php foreach ($grouped as $slug => $leads) {
            $color = $model->stageColor($slug); ?>
          <div style="flex:1 1 0; min-width:210px;">
            <div class="panel_s" style="border-top:3px solid <?php echo $color; ?>;">
              <div class="panel-body">
                <h5 class="bold" style="margin-top:0;">
                  <?php echo htmlspecialchars($model->stageLabel($slug), ENT_QUOTES); ?>
                  <span class="pull-right text-muted sia-count"><?php echo count($leads); ?></span>
                </h5>
                <hr style="margin:8px 0;">

                <div class="sia-col" data-stage="<?php echo $slug; ?>">
                <?php foreach ($leads as $lead) { ?>
                  <div class="panel_s sia-card" draggable="true" data-id="<?php echo (int) $lead->id; ?>" style="margin-bottom:8px;">
                    <div class="panel-body" style="padding:10px;">
                      <a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $lead->id); ?>" class="bold">
                        <?php echo htmlspecialchars((string) ($lead->name ?: ('Lead #' . $lead->id)), ENT_QUOTES); ?>
                      </a>
                      <div class="text-muted" style="font-size:12px; margin:4px 0;">
                        <?php echo htmlspecialchars((string) ($lead->formation ?: '—'), ENT_QUOTES); ?>
                        · <span class="label label-info"><?php echo htmlspecialchars((string) $lead->score, ENT_QUOTES); ?></span>
                      </div>
                      <div class="btn-group btn-group-xs">
                        <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                          Déplacer <span class="caret"></span>
                        </button>
                        <ul class="dropdown-menu">
                          <?php foreach ($model->stages() as $s => $conf) {
                              if ($s === $slug) { continue; } ?>
                            <li>
                              <a href="<?php echo admin_url('school_ia_bridge/move/' . (int) $lead->id . '?stage=' . $s . '&back=pipeline'); ?>">
                                <?php echo htmlspecialchars($conf[0], ENT_QUOTES); ?>
                              </a>
                            </li>
                          <?php } ?>
                        </ul>
                      </div>
                    </div>
                  </div>
                <?php } ?>
                </div>

              </div>
            </div>
          </div>
        <?php } ?>
      </div>
    </div>

  </div>
</div>

<script>
(function () {
  var MOVE = '<?php echo admin_url('school_ia_bridge/move/'); ?>';
  var dragged = null;

  function recount() {
    document.querySelectorAll('.sia-col').forEach(function (col) {
      var panel = col.closest('.panel-body');
      if (panel) {
        var badge = panel.querySelector('.sia-count');
        if (badge) { badge.textContent = col.querySelectorAll('.sia-card').length; }
      }
    });
  }

  document.addEventListener('dragstart', function (e) {
    var card = e.target.closest('.sia-card');
    if (card) { dragged = card; e.dataTransfer.effectAllowed = 'move'; }
  });

  document.querySelectorAll('.sia-col').forEach(function (col) {
    col.addEventListener('dragover', function (e) { e.preventDefault(); col.classList.add('sia-over'); });
    col.addEventListener('dragleave', function () { col.classList.remove('sia-over'); });
    col.addEventListener('drop', function (e) {
      e.preventDefault();
      col.classList.remove('sia-over');
      if (!dragged) { return; }
      var id = dragged.getAttribute('data-id');
      var stage = col.getAttribute('data-stage');
      col.appendChild(dragged);
      recount();
      fetch(MOVE + id + '?ajax=1&stage=' + encodeURIComponent(stage), { credentials: 'same-origin' })
        .catch(function () { location.reload(); });
      dragged = null;
    });
  });
})();
</script>
<?php init_tail(); ?>
</body>
</html>
