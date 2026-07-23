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

    $prenom = trim(explode('#', (string) $lead->name)[0]);
    $prenom = $prenom !== '' ? $prenom : 'bonjour';
    $formation = $lead->formation ?: 'votre formation';
    $mailBody = "Bonjour {$prenom},\n\nMerci de votre intérêt pour {$formation}. Je reste à votre disposition pour répondre à vos questions et vous accompagner dans votre projet.\n\nBien cordialement,";
    $smsBody = "Bonjour {$prenom}, merci pour votre intérêt pour {$formation}. Un conseiller vous recontacte très vite. — " . get_option('companyname');
    $notes = array_values(array_filter($activities, static function ($a) { return $a->type === 'note'; }));
    $ownerName = !empty($lead->owner_id) ? get_staff_full_name((int) $lead->owner_id) : '';
    ?>

    <!-- ===== HEADER + ONGLETS (collant) ===== -->
    <div class="sia-lead-sticky">
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
              <?php if ($ownerName !== '') { ?>
                <span class="label label-success"><i class="fa fa-user"></i> <?php echo htmlspecialchars($ownerName, ENT_QUOTES); ?></span>
              <?php } ?>
              <?php echo sia_help('lead'); ?>
            </div>
            <div class="sia-lead-head-meta">
              <?php if ($lead->email) { ?><span><i class="fa fa-envelope"></i> <?php echo htmlspecialchars((string) $lead->email, ENT_QUOTES); ?></span><?php } ?>
              <?php if ($lead->phone) { ?><span><i class="fa fa-phone"></i> <?php echo htmlspecialchars((string) $lead->phone, ENT_QUOTES); ?></span><?php } ?>
              <?php if ($lead->formation) { ?><span><i class="fa fa-graduation-cap"></i> <?php echo htmlspecialchars((string) $lead->formation, ENT_QUOTES); ?></span><?php } ?>
              <?php if ($lead->score !== null && $lead->score !== '') { ?>
                <span><i class="fa fa-star"></i> score <?php echo htmlspecialchars((string) $lead->score, ENT_QUOTES); ?><?php echo $lead->band ? ' · ' . htmlspecialchars(str_replace('_', ' ', (string) $lead->band), ENT_QUOTES) : ''; ?></span>
              <?php } ?>
              <?php if ($lead->source_site) { ?><span><i class="fa fa-globe"></i> <?php echo htmlspecialchars((string) $lead->source_site, ENT_QUOTES); ?></span><?php } ?>
              <?php if (!empty($lead->rentree)) { ?><span><i class="fa fa-calendar"></i> rentrée <?php echo htmlspecialchars((string) $lead->rentree, ENT_QUOTES); ?></span><?php } ?>
            </div>
          </div>
          <div class="sia-lead-head-actions">
            <?php if (empty($lead->owner_id)) { ?>
              <?php echo form_open(admin_url('school_ia_bridge/assign/' . (int) $lead->id), ['style' => 'display:inline;']); ?>
                <input type="hidden" name="owner_id" value="<?php echo (int) get_staff_user_id(); ?>">
                <button type="submit" class="btn btn-primary"><i class="fa fa-hand-paper-o"></i> Prendre en charge</button>
              <?php echo form_close(); ?>
            <?php } ?>
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
            <a href="<?php echo admin_url('school_ia_bridge/pipeline'); ?>" class="btn btn-default"><i class="fa fa-columns"></i> Pipeline</a>
            <a href="<?php echo admin_url('school_ia_bridge/lead_delete/' . (int) $lead->id); ?>" class="btn btn-default"
               onclick="return confirm('Supprimer définitivement ce lead et tout son historique ?');" title="Supprimer ce lead">
              <i class="fa fa-trash text-danger"></i>
            </a>
          </div>
        </div>
        <?php if (!empty($lead->description)) { ?>
          <p class="text-muted sia-lead-desc"><?php echo htmlspecialchars((string) $lead->description, ENT_QUOTES); ?></p>
        <?php } ?>
      </div></div>

      <ul class="nav nav-tabs sia-lead-tabs" role="tablist">
        <li role="presentation" class="active"><a href="#tab-work" data-toggle="tab"><i class="fa fa-briefcase"></i> Espace de travail</a></li>
        <li role="presentation"><a href="#tab-notes" data-toggle="tab"><i class="fa fa-sticky-note"></i> Notes &amp; suivi
          <?php if (!empty($notes)) { ?><span class="label label-default"><?php echo count($notes); ?></span><?php } ?></a></li>
        <li role="presentation"><a href="#tab-ia" data-toggle="tab"><i class="fa fa-comments"></i> Conversation IA
          <?php if (!empty($chatMessages)) { ?><span class="label label-default"><?php echo count($chatMessages); ?></span><?php } ?></a></li>
      </ul>
    </div>

    <div class="tab-content sia-lead-tabcontent">

      <!-- ===== ONGLET 1 : ESPACE DE TRAVAIL ===== -->
      <div role="tabpanel" class="tab-pane active" id="tab-work">
        <div class="row">
          <!-- Colonne action -->
          <div class="col-md-8">

            <div class="panel_s"><div class="panel-body">
              <div class="clearfix">
                <h5 class="bold pull-left" style="margin-top:0;">
                  <span class="sia-panel-icon sia-ic-warning"><i class="fa fa-paper-plane"></i></span>
                  Contacter le lead
                </h5>
                <?php if ($lead->phone) {
                    $waNum = preg_replace('/\D+/', '', (string) $lead->phone); ?>
                  <a href="https://wa.me/<?php echo $waNum; ?>?text=<?php echo rawurlencode($smsBody); ?>" target="_blank" rel="noopener"
                     class="btn btn-sm pull-right sia-btn-wa"><i class="fa fa-whatsapp"></i> WhatsApp</a>
                <?php } ?>
              </div>
              <ul class="nav nav-tabs" role="tablist">
                <li role="presentation" class="active"><a href="#sia-email" data-toggle="tab"><i class="fa fa-envelope"></i> E-mail</a></li>
                <li role="presentation"><a href="#sia-sms" data-toggle="tab"><i class="fa fa-mobile"></i> SMS</a></li>
              </ul>
              <div class="tab-content" style="padding-top:12px;">
                <div role="tabpanel" class="tab-pane active" id="sia-email">
                  <?php if (!$lead->email) { ?>
                    <p class="text-muted">Pas d'adresse e-mail pour ce lead.</p>
                  <?php } else { ?>
                    <?php echo form_open(admin_url('school_ia_bridge/send_email/' . (int) $lead->id), ['id' => 'sia-email-form']); ?>
                      <?php if (!empty($emailTpls)) { ?>
                        <div class="sia-field-icon">
                          <i class="fa fa-file-text-o"></i>
                          <select class="form-control" id="sia-email-tpl">
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
                        </div>
                      <?php } ?>
                      <div class="sia-field-icon">
                        <i class="fa fa-pencil"></i>
                        <input type="text" name="subject" id="sia-email-subject" class="form-control"
                               value="À propos de <?php echo htmlspecialchars($formation, ENT_QUOTES); ?>">
                      </div>

                      <div class="sia-rte">
                        <div class="sia-rte-toolbar">
                          <button type="button" data-cmd="bold" title="Gras"><i class="fa fa-bold"></i></button>
                          <button type="button" data-cmd="italic" title="Italique"><i class="fa fa-italic"></i></button>
                          <button type="button" data-cmd="underline" title="Souligné"><i class="fa fa-underline"></i></button>
                          <span class="sep"></span>
                          <button type="button" data-cmd="insertUnorderedList" title="Liste à puces"><i class="fa fa-list-ul"></i></button>
                          <button type="button" data-cmd="insertOrderedList" title="Liste numérotée"><i class="fa fa-list-ol"></i></button>
                          <button type="button" data-cmd="createLink" title="Insérer un lien"><i class="fa fa-link"></i></button>
                          <span class="sep"></span>
                          <button type="button" data-cmd="removeFormat" title="Effacer la mise en forme"><i class="fa fa-eraser"></i></button>
                        </div>
                        <div class="sia-rte-area" id="sia-email-editor" contenteditable="true"><?php echo nl2br(htmlspecialchars($mailBody, ENT_QUOTES)); ?></div>
                      </div>
                      <textarea name="message" id="sia-email-message" style="display:none;"><?php echo htmlspecialchars($mailBody, ENT_QUOTES); ?></textarea>

                      <?php if (!empty($documents)) { ?>
                        <div style="margin-top:8px;">
                          <button type="button" class="btn btn-default" data-toggle="modal" data-target="#sia-doc-modal">
                            <i class="fa fa-paperclip"></i> Joindre des documents
                            <span class="label label-primary" id="sia-doc-badge" style="display:none;margin-left:4px;">0</span>
                          </button>
                          <div class="sia-doc-chips" id="sia-doc-chips"></div>
                        </div>

                        <div class="modal fade" id="sia-doc-modal" tabindex="-1" role="dialog">
                          <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                              <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                <h4 class="modal-title"><i class="fa fa-paperclip"></i> Choisir des documents</h4>
                                <input type="text" id="sia-doc-search" class="form-control" placeholder="Rechercher un document…" style="margin-top:12px;">
                              </div>
                              <div class="modal-body">
                                <div class="sia-doc-grid" id="sia-doc-grid">
                                  <?php foreach ($documents as $doc) {
                                      $docLabel = ($doc->program ? $doc->program . ' · ' : '') . $doc->title; ?>
                                    <label class="sia-doc-card" data-search="<?php echo htmlspecialchars(mb_strtolower($docLabel), ENT_QUOTES); ?>">
                                      <input type="checkbox" name="attachments[]" value="<?php echo (int) $doc->id; ?>" data-label="<?php echo htmlspecialchars($docLabel, ENT_QUOTES); ?>">
                                      <span class="sia-doc-card-icon"><i class="fa fa-file-text-o"></i></span>
                                      <span class="sia-doc-card-title"><?php echo htmlspecialchars((string) $doc->title, ENT_QUOTES); ?></span>
                                      <?php if ($doc->program) { ?><span class="sia-doc-card-tag"><?php echo htmlspecialchars((string) $doc->program, ENT_QUOTES); ?></span><?php } ?>
                                    </label>
                                  <?php } ?>
                                </div>
                                <p class="text-muted" id="sia-doc-empty" style="display:none;margin-top:10px;">Aucun document trouvé.</p>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-primary" data-dismiss="modal">Terminé</button>
                              </div>
                            </div>
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
              <h5 class="bold" style="margin-top:0;">
                <span class="sia-panel-icon sia-ic-danger"><i class="fa fa-check-square-o"></i></span>
                Tâches, rendez-vous &amp; relances
              </h5>
              <?php echo form_open(admin_url('school_ia_bridge/task_add/' . (int) $lead->id)); ?>
                <div class="row">
                  <div class="col-sm-5" style="margin-bottom:6px;">
                    <input type="text" name="title" class="form-control" placeholder="Ex. Rappeler le prospect" required>
                  </div>
                  <div class="col-sm-4" style="margin-bottom:6px;">
                    <input type="datetime-local" name="due_at" class="form-control" title="Échéance (date et heure)">
                  </div>
                  <div class="col-sm-2" style="margin-bottom:6px;">
                    <select name="priority" class="form-control" title="Priorité">
                      <option value="moyenne">Moyenne</option>
                      <option value="haute">Haute</option>
                      <option value="basse">Basse</option>
                    </select>
                  </div>
                  <div class="col-sm-1" style="margin-bottom:6px;">
                    <button type="submit" class="btn btn-primary btn-block">+</button>
                  </div>
                </div>
              <?php echo form_close(); ?>

              <?php if (!empty($tasks)) { ?>
                <div class="sia-task-list">
                  <?php foreach ($tasks as $t) {
                      $overdue = (!$t->done && $t->due_at && strtotime($t->due_at) < time()); ?>
                    <div class="sia-task-item">
                      <a href="<?php echo admin_url('school_ia_bridge/task_toggle/' . (int) $t->id); ?>"
                         title="<?php echo $t->done ? 'Marquer à faire' : 'Marquer fait'; ?>">
                        <i class="fa <?php echo $t->done ? 'fa-check-square-o text-success' : 'fa-square-o'; ?>"></i>
                      </a>
                      <div style="flex:1 1 auto;">
                        <span style="<?php echo $t->done ? 'text-decoration:line-through;color:#999;' : ''; ?>">
                          <?php echo htmlspecialchars((string) $t->title, ENT_QUOTES); ?>
                        </span>
                        <?php if ($t->due_at) { ?>
                          <span class="label <?php echo $overdue ? 'label-danger' : 'label-default'; ?>" style="margin-left:6px;">
                            <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($t->due_at)), ENT_QUOTES); ?>
                          </span>
                        <?php } ?>
                      </div>
                      <a href="<?php echo admin_url('school_ia_bridge/task_delete/' . (int) $t->id); ?>"
                         class="text-muted" style="margin-left:8px;" onclick="return confirm('Supprimer cette tâche ?');"><i class="fa fa-trash"></i></a>
                    </div>
                  <?php } ?>
                </div>
              <?php } ?>
            </div></div>

          </div>

          <!-- Colonne enjeux métiers -->
          <div class="col-md-4">

            <div class="panel_s"><div class="panel-body">
              <h5 class="bold" style="margin-top:0;">
                <span class="sia-panel-icon sia-ic-success"><i class="fa fa-user"></i></span>
                Responsable
              </h5>
              <?php echo form_open(admin_url('school_ia_bridge/assign/' . (int) $lead->id)); ?>
                <div class="input-group">
                  <select name="owner_id" class="form-control">
                    <option value="0">— Aucun —</option>
                    <?php foreach ($staff as $st) { ?>
                      <option value="<?php echo (int) $st->staffid; ?>" <?php echo ((int) $lead->owner_id === (int) $st->staffid) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($st->firstname . ' ' . $st->lastname, ENT_QUOTES); ?>
                      </option>
                    <?php } ?>
                  </select>
                  <span class="input-group-btn"><button type="submit" class="btn btn-primary">OK</button></span>
                </div>
              <?php echo form_close(); ?>
            </div></div>

            <?php if (!empty($competitors)) { ?>
              <div class="panel_s sia-competitor-alert"><div class="panel-body">
                <h5 class="bold" style="margin-top:0;">
                  <span class="sia-panel-icon sia-ic-danger"><i class="fa fa-exclamation-triangle"></i></span>
                  Concurrents cités — à contrer
                </h5>
                <?php foreach ($competitors as $co) { ?>
                  <div style="padding:7px 0; border-bottom:1px solid var(--sia-border);">
                    <span class="label" style="background:#dc26261a;color:#dc2626;"><?php echo htmlspecialchars((string) $co->name, ENT_QUOTES); ?></span>
                    <?php if (!empty($co->context)) { ?>
                      <p class="text-muted" style="margin:6px 0 0; font-style:italic; font-size:12.5px;">« <?php echo htmlspecialchars((string) $co->context, ENT_QUOTES); ?> »</p>
                    <?php } ?>
                  </div>
                <?php } ?>
              </div></div>
            <?php } ?>

            <div class="panel_s"><div class="panel-body">
              <h5 class="bold" style="margin-top:0;">
                <span class="sia-panel-icon sia-ic-info"><i class="fa fa-calendar"></i></span>
                Rentrée / année académique
              </h5>
              <?php echo form_open(admin_url('school_ia_bridge/set_rentree/' . (int) $lead->id)); ?>
                <div class="input-group">
                  <input type="text" name="rentree" class="form-control" list="sia-rentrees"
                         value="<?php echo htmlspecialchars((string) ($lead->rentree ?? ''), ENT_QUOTES); ?>" placeholder="Ex. Septembre 2026">
                  <span class="input-group-btn"><button type="submit" class="btn btn-primary">OK</button></span>
                </div>
              <?php echo form_close(); ?>
            </div></div>

          </div>
        </div>
      </div>

      <!-- ===== ONGLET 2 : NOTES & SUIVI ===== -->
      <div role="tabpanel" class="tab-pane" id="tab-notes">
        <div class="row">
          <div class="col-md-8">

            <div class="panel_s"><div class="panel-body">
              <h5 class="bold" style="margin-top:0;">
                <span class="sia-panel-icon sia-ic-primary"><i class="fa fa-sticky-note"></i></span>
                Notes
              </h5>
              <?php echo form_open(admin_url('school_ia_bridge/note/' . (int) $lead->id)); ?>
                <textarea name="content" class="form-control" rows="3" placeholder="Compte-rendu d'appel, remarque…"></textarea>
                <button type="submit" class="btn btn-primary" style="margin-top:8px;">Enregistrer la note</button>
              <?php echo form_close(); ?>
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
              <h5 class="bold" style="margin-top:0;">
                <span class="sia-panel-icon sia-ic-info"><i class="fa fa-history"></i></span>
                Historique des activités
              </h5>
              <?php if (empty($activities)) { ?>
                <p class="text-muted">Aucune activité pour l'instant.</p>
              <?php } else { ?>
                <div class="sia-activity-list">
                  <?php
                  $icons  = ['note' => 'fa-comment', 'stage_change' => 'fa-random', 'task' => 'fa-check-square-o',
                             'email' => 'fa-envelope', 'sms' => 'fa-mobile', 'assignment' => 'fa-user'];
                  $colors = ['note' => 'sia-ic-primary', 'stage_change' => 'sia-ic-info', 'task' => 'sia-ic-danger',
                             'email' => 'sia-ic-success', 'sms' => 'sia-ic-success', 'assignment' => 'sia-ic-warning'];
                  foreach ($activities as $a) {
                      $icon = $icons[$a->type] ?? 'fa-circle-o';
                      $col  = $colors[$a->type] ?? 'sia-ic-muted';
                      $who = $a->staff_id ? get_staff_full_name((int) $a->staff_id) : 'Système'; ?>
                    <div class="sia-activity-item">
                      <span class="sia-activity-icon <?php echo $col; ?>"><i class="fa <?php echo $icon; ?>"></i></span>
                      <div>
                        <?php echo htmlspecialchars((string) $a->content, ENT_QUOTES); ?>
                        <div class="text-muted" style="font-size:11px;margin-top:2px;">
                          <?php echo htmlspecialchars($who . ' · ' . $a->created_at, ENT_QUOTES); ?>
                        </div>
                      </div>
                    </div>
                  <?php } ?>
                </div>
              <?php } ?>
            </div></div>

          </div>
          <div class="col-md-4">
            <div class="panel_s"><div class="panel-body">
              <h5 class="bold" style="margin-top:0;">
                <span class="sia-panel-icon sia-ic-primary"><i class="fa fa-refresh"></i></span>
                Séquences de relance
              </h5>
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
          </div>
        </div>
      </div>

      <!-- ===== ONGLET 3 : CONVERSATION IA ===== -->
      <div role="tabpanel" class="tab-pane" id="tab-ia">
        <div class="panel_s"><div class="panel-body">
          <div class="clearfix">
            <h5 class="bold pull-left" style="margin-top:0;">
              <span class="sia-panel-icon" style="background:#8a63d21a;color:#8a63d2;"><i class="fa fa-lightbulb-o"></i></span>
              Résumé de l'échange (IA)
            </h5>
            <?php if ($ai_ready && !empty($chatMessages)) { ?>
              <?php echo form_open(admin_url('school_ia_bridge/lead_summarize/' . (int) $lead->id), ['class' => 'pull-right', 'onsubmit' => "this.querySelector('button').disabled=true;this.querySelector('button').innerHTML='…';"]); ?>
                <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-magic"></i> <?php echo $aiSummary ? 'Régénérer' : 'Générer le résumé'; ?></button>
              <?php echo form_close(); ?>
            <?php } ?>
          </div>
          <?php if ($aiSummary) { ?>
            <div class="sia-report" style="line-height:1.55;"><?php echo $aiSummary['content']; ?></div>
            <div class="text-muted" style="font-size:11px;margin-top:6px;">Généré le <?php echo htmlspecialchars(date('d/m/Y à H:i', strtotime((string) $aiSummary['at'])), ENT_QUOTES); ?></div>
          <?php } elseif (empty($chatMessages)) { ?>
            <p class="text-muted" style="margin-top:8px;">Aucune conversation à résumer.</p>
          <?php } elseif (!$ai_ready) { ?>
            <p class="text-muted" style="margin-top:8px;">Configurez la clé API IA (<a href="<?php echo admin_url('school_ia_bridge/settings'); ?>">Réglages → Rapports IA</a>) pour générer un résumé automatique en 3 points.</p>
          <?php } else { ?>
            <p class="text-muted" style="margin-top:8px;">Cliquez « Générer le résumé » pour obtenir une synthèse actionnable de la conversation.</p>
          <?php } ?>
        </div></div>

        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">
            <span class="sia-panel-icon sia-ic-info"><i class="fa fa-comments"></i></span>
            Conversation avec l'IA
          </h5>
          <?php if (empty($chatMessages)) { ?>
            <p class="text-muted" style="margin-top:10px;">Aucun échange avec le chatbot pour ce lead.</p>
          <?php } else { ?>
            <div class="sia-chat-thread" style="max-height:none;">
              <?php foreach ($chatMessages as $m) {
                  $isUser = $m->role === 'user'; ?>
                <div class="sia-chat-row <?php echo $isUser ? 'sia-chat-row-user' : 'sia-chat-row-assistant'; ?>">
                  <div class="sia-chat-bubble">
                    <?php echo nl2br(htmlspecialchars((string) $m->content, ENT_QUOTES)); ?>
                    <div class="sia-chat-meta">
                      <?php echo htmlspecialchars(($isUser ? 'Lead' : 'IA') . ($m->canal ? ' · ' . $m->canal : '') . ' · ' . $m->created_at, ENT_QUOTES); ?>
                    </div>
                  </div>
                </div>
              <?php } ?>
            </div>
          <?php } ?>
        </div></div>
      </div>

    </div>

    <datalist id="sia-rentrees">
      <?php foreach (($rentrees ?? []) as $r) { ?>
        <option value="<?php echo htmlspecialchars($r, ENT_QUOTES); ?>"></option>
      <?php } ?>
    </datalist>

  </div>
</div>
<script>
(function () {
  // Mémorise l'onglet actif (utile après un envoi de formulaire qui recharge la page).
  var KEY = 'sia_lead_tab_<?php echo (int) $lead->id; ?>';
  try {
    var target = window.location.hash || localStorage.getItem(KEY);
    if (target && jQuery('.sia-lead-tabs a[href="' + target + '"]').length) {
      jQuery('.sia-lead-tabs a[href="' + target + '"]').tab('show');
    }
    jQuery('.sia-lead-tabs a[data-toggle="tab"]').on('shown.bs.tab', function () {
      localStorage.setItem(KEY, this.getAttribute('href'));
    });
  } catch (e) {}

  function opt(sel) { return sel && sel.options[sel.selectedIndex]; }

  // Éditeur de texte enrichi (e-mail) : synchronise vers un textarea caché.
  var editor = document.getElementById('sia-email-editor');
  var hidden = document.getElementById('sia-email-message');
  function syncEditor() { if (editor && hidden) { hidden.value = editor.innerHTML; } }
  if (editor && hidden) {
    editor.addEventListener('input', syncEditor);
    syncEditor();
    document.querySelectorAll('.sia-rte-toolbar [data-cmd]').forEach(function (b) {
      b.addEventListener('click', function () {
        var cmd = b.getAttribute('data-cmd');
        editor.focus();
        if (cmd === 'createLink') {
          var url = prompt('Adresse du lien :', 'https://');
          if (url) { document.execCommand('createLink', false, url); }
        } else {
          document.execCommand(cmd, false, null);
        }
        syncEditor();
      });
    });
    var emailForm = document.getElementById('sia-email-form');
    if (emailForm) { emailForm.addEventListener('submit', syncEditor); }
  }

  var et = document.getElementById('sia-email-tpl');
  if (et) {
    et.addEventListener('change', function () {
      var o = opt(et); if (!o) return;
      if (o.getAttribute('data-subject') !== null) { document.getElementById('sia-email-subject').value = o.getAttribute('data-subject') || ''; }
      if (o.getAttribute('data-body') !== null && editor) {
        editor.innerHTML = (o.getAttribute('data-body') || '').replace(/\n/g, '<br>');
        syncEditor();
      }
    });
  }
  var st = document.getElementById('sia-sms-tpl');
  if (st) {
    st.addEventListener('change', function () {
      var o = opt(st); if (!o) return;
      if (o.getAttribute('data-body') !== null) { document.getElementById('sia-sms-text').value = o.getAttribute('data-body') || ''; }
    });
  }

  var grid = document.getElementById('sia-doc-grid');
  if (grid) {
    var search = document.getElementById('sia-doc-search');
    var chips  = document.getElementById('sia-doc-chips');
    var badge  = document.getElementById('sia-doc-badge');
    var empty  = document.getElementById('sia-doc-empty');
    var cards  = Array.prototype.slice.call(grid.querySelectorAll('.sia-doc-card'));

    function refresh() {
      var checked = grid.querySelectorAll('input[type="checkbox"]:checked');
      chips.innerHTML = '';
      Array.prototype.forEach.call(checked, function (cb) {
        var chip = document.createElement('span');
        chip.className = 'sia-doc-chip';
        var label = document.createElement('span');
        label.textContent = cb.getAttribute('data-label');
        var rm = document.createElement('button');
        rm.type = 'button';
        rm.innerHTML = '&times;';
        rm.addEventListener('click', function () {
          cb.checked = false;
          cb.closest('.sia-doc-card').classList.remove('sia-doc-selected');
          refresh();
        });
        chip.appendChild(label);
        chip.appendChild(rm);
        chips.appendChild(chip);
      });
      if (checked.length) {
        badge.style.display = 'inline-block';
        badge.textContent = checked.length;
      } else {
        badge.style.display = 'none';
      }
    }

    cards.forEach(function (card) {
      var cb = card.querySelector('input[type="checkbox"]');
      cb.addEventListener('change', function () {
        card.classList.toggle('sia-doc-selected', cb.checked);
        refresh();
      });
    });

    if (search) {
      search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase();
        var any = false;
        cards.forEach(function (card) {
          var match = card.getAttribute('data-search').indexOf(q) !== -1;
          card.style.display = match ? '' : 'none';
          if (match) { any = true; }
        });
        empty.style.display = any ? 'none' : 'block';
      });
    }

    refresh();
  }
})();
</script>
<?php init_tail(); ?>
</body>
</html>
