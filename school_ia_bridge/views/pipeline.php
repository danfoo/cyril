<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
  /* Piste horizontale : colonnes plus larges et de largeur fixe → scroll
     horizontal propre quand il y a beaucoup d'étapes. */
  .sia-pipe-track { display: flex; gap: 14px; padding-bottom: 12px; }
  .sia-pipe-col { flex: 0 0 300px; width: 300px; }
  /* Toutes les colonnes à la MÊME hauteur, même vides : hauteur fixe de la
     liste (elle défile verticalement quand une colonne a une vingtaine de leads). */
  .sia-col { height: calc(100vh - 235px); border-radius: 6px; transition: background .15s; overflow-y: auto; overflow-x: hidden; padding: 2px; }
  .sia-col.sia-over { background: #eef4ff; outline: 2px dashed #2e6ff2; }

  /* Carte : bordure fine sur tout le cadre (plus d'accent à gauche), poignée de
     glisser-déposer et retour visuel au survol. */
  .sia-card { cursor: grab; position: relative; border: 1px solid var(--sia-border, #e6e9f0); box-shadow: none; transition: box-shadow .15s ease, transform .15s ease; }
  .sia-card:hover { box-shadow: 0 6px 16px rgba(15,23,42,.13); transform: translateY(-1px); }
  .sia-card:active { cursor: grabbing; }
  .sia-drag-handle { position: absolute; top: 8px; right: 9px; color: #c3c9d4; font-size: 13px; line-height: 1; cursor: grab; }
  .sia-card:hover .sia-drag-handle { color: #6b7280; }
  .sia-stage-badge { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 1px 8px; border-radius: 20px; margin-top: 6px; }
  .sia-score { font-size: 10.5px; padding: 1px 6px; }
  @media (max-width: 768px) { .sia-pipe-col { flex-basis: 260px; width: 260px; } }
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
          <?php if ($canCreate) { ?>
          <a href="<?php echo admin_url('school_ia_bridge/new_lead'); ?>" class="btn btn-primary pull-right">
            <i class="fa fa-user-plus"></i> Ajouter un lead
          </a>
          <?php } ?>
          <a href="<?php echo admin_url('school_ia_bridge'); ?>" class="btn btn-default pull-right" style="margin-right:6px;">
            <i class="fa fa-inbox"></i> Contacts
          </a>
        </div>
      </div>
    </div>

    <div style="overflow-x:auto;">
      <div class="sia-pipe-track">
        <?php foreach ($grouped as $slug => $leads) {
            $color = $model->stageColor($slug); ?>
          <div class="sia-pipe-col">
            <div class="panel_s" style="border-top:3px solid <?php echo $color; ?>;">
              <div class="panel-body">
                <h5 class="bold" style="margin-top:0;">
                  <?php echo htmlspecialchars($model->stageLabel($slug), ENT_QUOTES); ?>
                  <span class="pull-right text-muted sia-count"><?php echo count($leads); ?></span>
                </h5>
                <hr style="margin:8px 0;">

                <div class="sia-col" data-stage="<?php echo $slug; ?>">
                <?php foreach ($leads as $lead) { ?>
                  <div class="panel_s sia-card" draggable="<?php echo $canEdit ? 'true' : 'false'; ?>" data-id="<?php echo (int) $lead->id; ?>" style="margin-bottom:8px;">
                    <div class="panel-body" style="padding:10px 12px;">
                      <?php if ($canEdit) { ?>
                      <span class="sia-drag-handle" title="Glissez la carte pour changer d'étape"><i class="fa fa-arrows"></i></span>
                      <?php } ?>
                      <a href="<?php echo admin_url('school_ia_bridge/lead/' . (int) $lead->id); ?>" class="bold" style="padding-right:18px; display:inline-block;">
                        <?php echo htmlspecialchars((string) ($lead->name ?: ('Lead #' . $lead->id)), ENT_QUOTES); ?>
                      </a>
                      <div class="text-muted" style="font-size:12px; margin:4px 0;">
                        <?php echo htmlspecialchars((string) ($lead->formation ?: '—'), ENT_QUOTES); ?>
                        · <span class="label label-info sia-score"><?php echo htmlspecialchars((string) $lead->score, ENT_QUOTES); ?></span>
                      </div>
                      <span class="sia-stage-badge" style="background:<?php echo $color; ?>1a; color:<?php echo $color; ?>;">
                        <?php echo htmlspecialchars($model->stageLabel($slug), ENT_QUOTES); ?>
                      </span>
                      <?php if ($canEdit) { ?>
                      <div class="btn-group btn-group-xs" style="margin-top:6px;">
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
                      <?php } ?>
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
