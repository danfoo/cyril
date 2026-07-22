<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">

    <?php
    $cur = $lead->stage ?? 'nouveau';
    $stageColor = $model->stageColor($cur);
    $stageLabel = $model->stageLabel($cur);
    $fullName = trim((string) $lead->name);
    $displayName = $fullName !== '' ? $fullName : ('Lead #' . $lead->id);
    $initials = '';
    foreach (array_slice(preg_split('/\s+/', $fullName), 0, 2) as $p) {
        $p = preg_replace('/[^\p{L}0-9]/u', '', (string) $p);
        if ($p !== '') { $initials .= mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8'); }
    }
    if ($initials === '') { $initials = 'L'; }
    ?>

    <div class="panel_s sia-lead-header"><div class="panel-body">
      <div class="sia-lead-head">
        <div class="sia-avatar" style="background:<?php echo $stageColor; ?>1a;color:<?php echo $stageColor; ?>;">
          <?php echo htmlspecialchars($initials, ENT_QUOTES); ?>
        </div>
        <div class="sia-lead-head-info">
          <div class="sia-lead-head-title">
            <h4 class="no-margin"><?php echo htmlspecialchars($displayName, ENT_QUOTES); ?></h4>
            <span class="label sia-stage-pill" style="background:<?php echo $stageColor; ?>1a;color:<?php echo $stageColor; ?>;">
              <?php echo htmlspecialchars($stageLabel, ENT_QUOTES); ?>
            </span>
            <?php echo sia_help('lead'); ?>
          </div>
          <div class="sia-lead-head-meta">
            <?php if ($lead->email) { ?><span><i class="fa fa-envelope"></i> <?php echo htmlspecialchars((string) $lead->email, ENT_QUOTES); ?></span><?php } ?>
            <?php if ($lead->phone) { ?><span><i class="fa fa-phone"></i> <?php echo htmlspecialchars((string) $lead->phone, ENT_QUOTES); ?></span><?php } ?>
            <?php if ($lead->formation) { ?><span><i class="fa fa-graduation-cap"></i> <?php echo htmlspecialchars((string) $lead->formation, ENT_QUOTES); ?></span><?php } ?>
            <?php if ($lead->source_site) { ?><span><i class="fa fa-globe"></i> <?php echo htmlspecialchars((string) $lead->source_site, ENT_QUOTES); ?></span><?php } ?>
            <?php if ($lead->received_at) { ?><span><i class="fa fa-clock-o"></i> reçu le <?php echo htmlspecialchars((string) $lead->received_at, ENT_QUOTES); ?></span><?php } ?>
            <?php if ($lead->score !== null && $lead->score !== '') { ?>
              <span><i class="fa fa-star"></i> score <?php echo htmlspecialchars((string) $lead->score, ENT_QUOTES); ?><?php echo $lead->band ? ' · ' . htmlspecialchars(str_replace('_', ' ', (string) $lead->band), ENT_QUOTES) : ''; ?></span>
            <?php } ?>
          </div>
        </div>
        <div class="sia-lead-head-actions">
          <div class="dropdown">
            <button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown">
              <i class="fa fa-random"></i> Changer d'étape <span class="caret"></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-right">
              <?php foreach ($model->stages() as $s => $conf) {
                  if ($s === $cur) { continue; } ?>
                <li><a href="<?php echo admin_url('school_ia_bridge/move/' . (int) $lead->id . '?stage=' . $s); ?>">
                  <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?php echo $conf[1]; ?>;margin-right:7px;"></span>
                  <?php echo htmlspecialchars($conf[0], ENT_QUOTES); ?>
                </a></li>
              <?php } ?>
            </ul>
          </div>
          <a href="<?php echo admin_url('school_ia_bridge/pipeline'); ?>" class="btn btn-default">
            <i class="fa fa-columns"></i> Pipeline
          </a>
        </div>
      </div>
      <?php if (!empty($lead->description)) { ?>
        <p class="text-muted sia-lead-desc"><?php echo htmlspecialchars((string) $lead->description, ENT_QUOTES); ?></p>
      <?php } ?>
    </div></div>

    <div class="row">
      <!-- Colonne actions secondaires -->
      <div class="col-md-4">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">Séquences de relance</h5>
          <?php if (!empty($sequences)) { ?>
            <?php echo form_open(admin_url('school_ia_bridge/enroll/' . (int) $lead->id)); ?>
              <div class="input-group">
                <select name="sequence_id" class="form-control">
                  <?php foreach ($sequences as $sq) { ?>
                    <option value="<?php echo (int) $sq->id; ?>"><?php echo htmlspecialchars((string) $sq->name, ENT_QUOTES); ?></option>
                  <?php } ?>
                </select>
                <span class="input-group-btn"><button type="submit" class="btn btn-primary">Inscrire</button></span>
              </div>
            <?php echo form_close(); ?>
          <?php } else { ?>
            <p class="text-muted">Aucune séquence active. Créez-en dans l'onglet <a href="<?php echo admin_url('school_ia_bridge/sequences'); ?>">Séquences</a>.</p>
          <?php } ?>

          <?php if (!empty($enrollments)) { ?>
            <ul class="list-unstyled" style="margin-top:8px;">
              <?php foreach ($enrollments as $en) {
                  $badge = $en->status === 'active' ? 'label-success' : ($en->status === 'done' ? 'label-default' : 'label-warning'); ?>
                <li style="padding:4px 0;">
                  <span class="label <?php echo $badge; ?>"><?php echo $en->status; ?></span>
                  <?php echo htmlspecialchars((string) $en->sequence_name, ENT_QUOTES); ?>
                  <?php if ($en->status === 'active') { ?>
                    <a href="<?php echo admin_url('school_ia_bridge/unenroll/' . (int) $en->id); ?>"
                       class="pull-right text-muted" onclick="return confirm('Arrêter la séquence pour ce lead ?');">arrêter</a>
                  <?php } ?>
                </li>
              <?php } ?>
            </ul>
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
      <div class="col-md-8">

        <?php
        $prenom = trim(explode('#', (string) $lead->name)[0]);
        $prenom = $prenom !== '' ? $prenom : 'bonjour';
        $formation = $lead->formation ?: 'votre formation';
        $mailBody = "Bonjour {$prenom},\n\nMerci de votre intérêt pour {$formation}. Je reste à votre disposition pour répondre à vos questions et vous accompagner dans votre projet.\n\nBien cordialement,";
        $smsBody = "Bonjour {$prenom}, merci pour votre intérêt pour {$formation}. Un conseiller vous recontacte très vite. — " . get_option('companyname');
        ?>

        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">Contacter le lead</h5>
          <ul class="nav nav-tabs" role="tablist">
            <li role="presentation" class="active"><a href="#sia-email" data-toggle="tab"><i class="fa fa-envelope"></i> E-mail</a></li>
            <li role="presentation"><a href="#sia-sms" data-toggle="tab"><i class="fa fa-mobile"></i> SMS</a></li>
          </ul>
          <div class="tab-content" style="padding-top:12px;">
            <div role="tabpanel" class="tab-pane active" id="sia-email">
              <?php if (!$lead->email) { ?>
                <p class="text-muted">Pas d'adresse e-mail pour ce lead.</p>
              <?php } else { ?>
                <?php echo form_open(admin_url('school_ia_bridge/send_email/' . (int) $lead->id)); ?>
                  <?php if (!empty($emailTpls)) { ?>
                    <select class="form-control" id="sia-email-tpl" style="margin-bottom:6px;">
                      <option value="">— Choisir un modèle —</option>
                      <?php foreach ($emailTpls as $tpl) {
                          $subj = strtr((string) $tpl->subject, ['{prenom}' => $prenom, '{formation}' => $formation]);
                          $bod  = strtr((string) $tpl->body, ['{prenom}' => $prenom, '{formation}' => $formation]); ?>
                        <option value="" data-subject="<?php echo htmlspecialchars($subj, ENT_QUOTES); ?>"
                                data-body="<?php echo htmlspecialchars($bod, ENT_QUOTES); ?>">
                          <?php echo htmlspecialchars((string) $tpl->name, ENT_QUOTES); ?>
                        </option>
                      <?php } ?>
                    </select>
                  <?php } ?>
                  <input type="text" name="subject" id="sia-email-subject" class="form-control" style="margin-bottom:6px;"
                         value="À propos de <?php echo htmlspecialchars($formation, ENT_QUOTES); ?>">
                  <textarea name="message" id="sia-email-message" class="form-control" rows="5"><?php echo htmlspecialchars($mailBody, ENT_QUOTES); ?></textarea>

                  <?php if (!empty($documents)) { ?>
                    <div style="margin-top:8px;">
                      <label class="control-label" style="display:block;"><i class="fa fa-paperclip"></i> Pièces jointes</label>
                      <div style="max-height:140px; overflow-y:auto; border:1px solid #eee; border-radius:4px; padding:6px;">
                        <?php foreach ($documents as $doc) { ?>
                          <label style="display:block; font-weight:normal; margin:2px 0;">
                            <input type="checkbox" name="attachments[]" value="<?php echo (int) $doc->id; ?>">
                            <?php echo htmlspecialchars(($doc->program ? $doc->program . ' · ' : '') . $doc->title, ENT_QUOTES); ?>
                          </label>
                        <?php } ?>
                      </div>
                    </div>
                  <?php } ?>

                  <button type="submit" class="btn btn-primary" style="margin-top:8px;"><i class="fa fa-paper-plane"></i> Envoyer l'e-mail</button>
                  <span class="text-muted" style="margin-left:6px;">à <?php echo htmlspecialchars((string) $lead->email, ENT_QUOTES); ?></span>
                <?php echo form_close(); ?>
              <?php } ?>
            </div>
            <div role="tabpanel" class="tab-pane" id="sia-sms">
              <?php if (!$lead->phone) { ?>
                <p class="text-muted">Pas de numéro de téléphone pour ce lead.</p>
              <?php } else { ?>
                <?php echo form_open(admin_url('school_ia_bridge/send_sms/' . (int) $lead->id)); ?>
                  <?php if (!empty($smsTpls)) { ?>
                    <select class="form-control" id="sia-sms-tpl" style="margin-bottom:6px;">
                      <option value="">— Choisir un modèle —</option>
                      <?php foreach ($smsTpls as $tpl) {
                          $bod = strtr((string) $tpl->body, ['{prenom}' => $prenom, '{formation}' => $formation]); ?>
                        <option value="" data-body="<?php echo htmlspecialchars($bod, ENT_QUOTES); ?>">
                          <?php echo htmlspecialchars((string) $tpl->name, ENT_QUOTES); ?>
                        </option>
                      <?php } ?>
                    </select>
                  <?php } ?>
                  <textarea name="text" id="sia-sms-text" class="form-control" rows="3" maxlength="459"><?php echo htmlspecialchars($smsBody, ENT_QUOTES); ?></textarea>
                  <button type="submit" class="btn btn-primary" style="margin-top:8px;"><i class="fa fa-paper-plane"></i> Envoyer le SMS</button>
                  <span class="text-muted" style="margin-left:6px;">au <?php echo htmlspecialchars((string) $lead->phone, ENT_QUOTES); ?></span>
                <?php echo form_close(); ?>
              <?php } ?>
            </div>
          </div>
        </div></div>

        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">Tâches & relances</h5>
          <?php echo form_open(admin_url('school_ia_bridge/task_add/' . (int) $lead->id)); ?>
            <div class="row">
              <div class="col-sm-6" style="margin-bottom:6px;">
                <input type="text" name="title" class="form-control" placeholder="Ex. Rappeler le prospect" required>
              </div>
              <div class="col-sm-4" style="margin-bottom:6px;">
                <input type="datetime-local" name="due_at" class="form-control" title="Échéance (date et heure)">
              </div>
              <div class="col-sm-2" style="margin-bottom:6px;">
                <button type="submit" class="btn btn-primary btn-block">+</button>
              </div>
            </div>
          <?php echo form_close(); ?>

          <?php if (!empty($tasks)) { ?>
            <ul class="list-unstyled" style="margin-top:6px;">
              <?php foreach ($tasks as $t) {
                  $overdue = (!$t->done && $t->due_at && strtotime($t->due_at) < time()); ?>
                <li style="padding:6px 0; border-bottom:1px solid #f0f0f0;">
                  <a href="<?php echo admin_url('school_ia_bridge/task_toggle/' . (int) $t->id); ?>"
                     title="<?php echo $t->done ? 'Marquer à faire' : 'Marquer fait'; ?>">
                    <i class="fa <?php echo $t->done ? 'fa-check-square-o text-success' : 'fa-square-o'; ?>"></i>
                  </a>
                  <span style="<?php echo $t->done ? 'text-decoration:line-through;color:#999;' : ''; ?>">
                    <?php echo htmlspecialchars((string) $t->title, ENT_QUOTES); ?>
                  </span>
                  <?php if ($t->due_at) { ?>
                    <span class="label <?php echo $overdue ? 'label-danger' : 'label-default'; ?>" style="margin-left:6px;">
                      <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($t->due_at)), ENT_QUOTES); ?>
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
          <h5 class="bold" style="margin-top:0;">Notes</h5>
          <?php echo form_open(admin_url('school_ia_bridge/note/' . (int) $lead->id)); ?>
            <textarea name="content" class="form-control" rows="3" placeholder="Écrire une note…"></textarea>
            <button type="submit" class="btn btn-primary" style="margin-top:8px;">Enregistrer la note</button>
          <?php echo form_close(); ?>

          <?php
          $notes = array_values(array_filter($activities, static function ($a) { return $a->type === 'note'; }));
          ?>
          <?php if (!empty($notes)) { ?>
            <div class="sia-note-list">
              <?php foreach ($notes as $n) {
                  $who = $n->staff_id ? get_staff_full_name((int) $n->staff_id) : 'Système'; ?>
                <div class="sia-note-card">
                  <div class="sia-note-content"><?php echo nl2br(htmlspecialchars((string) $n->content, ENT_QUOTES)); ?></div>
                  <div class="sia-note-meta"><?php echo htmlspecialchars($who . ' · ' . $n->created_at, ENT_QUOTES); ?></div>
                </div>
              <?php } ?>
            </div>
          <?php } else { ?>
            <p class="text-muted" style="margin-top:10px;">Aucune note pour l'instant.</p>
          <?php } ?>
        </div></div>

        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">Historique</h5>
          <?php if (empty($activities)) { ?>
            <p class="text-muted">Aucune activité pour l'instant.</p>
          <?php } else { ?>
            <ul class="list-unstyled">
              <?php
              $icons = ['note' => 'fa-comment', 'stage_change' => 'fa-random', 'task' => 'fa-check-square-o',
                        'email' => 'fa-envelope', 'sms' => 'fa-mobile', 'assignment' => 'fa-user'];
              foreach ($activities as $a) {
                  $icon = $icons[$a->type] ?? 'fa-circle-o';
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
<script>
(function () {
  function opt(sel) { return sel && sel.options[sel.selectedIndex]; }
  var et = document.getElementById('sia-email-tpl');
  if (et) {
    et.addEventListener('change', function () {
      var o = opt(et); if (!o) return;
      if (o.getAttribute('data-subject') !== null) { document.getElementById('sia-email-subject').value = o.getAttribute('data-subject') || ''; }
      if (o.getAttribute('data-body') !== null) { document.getElementById('sia-email-message').value = o.getAttribute('data-body') || ''; }
    });
  }
  var st = document.getElementById('sia-sms-tpl');
  if (st) {
    st.addEventListener('change', function () {
      var o = opt(st); if (!o) return;
      if (o.getAttribute('data-body') !== null) { document.getElementById('sia-sms-text').value = o.getAttribute('data-body') || ''; }
    });
  }
})();
</script>
<?php init_tail(); ?>
</body>
</html>
