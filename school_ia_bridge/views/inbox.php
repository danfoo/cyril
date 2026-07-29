<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">

    <div class="clearfix" style="margin-bottom:15px;">
      <div class="pull-left">
        <h4 class="no-margin">
          Inbox conseiller — escalades humaines
          <?php if ($isGlobal) { ?>
            <span class="label label-primary" style="margin-left:8px;font-weight:600;" title="Vous voyez les escalades de tous les conseillers"><i class="fa fa-globe"></i> Vue globale</span>
          <?php } else { ?>
            <span class="label label-default" style="margin-left:8px;font-weight:600;" title="Vous voyez vos leads + les leads non assignés"><i class="fa fa-user"></i> Mes données</span>
          <?php } ?>
        </h4>
        <p class="text-muted" style="max-width:720px;margin-top:6px;">
          Quand l'IA détecte une urgence ou qu'un conseiller répond en direct depuis le chat WordPress ou cette page,
          la conversation apparaît ici et l'IA se met en pause. Votre réponse part instantanément vers le prospect.
        </p>
      </div>
    </div>

    <?php if (empty($handoffs)) { ?>
      <div class="panel_s"><div class="panel-body" style="text-align:center;padding:50px 20px;">
        <i class="fa fa-check-circle-o text-success" style="font-size:42px;"></i>
        <p style="margin:14px 0 2px;"><strong>Aucune escalade en attente.</strong></p>
        <p class="text-muted">Tout est sous contrôle — l'IA gère les conversations en cours.</p>
      </div></div>
    <?php } else { ?>

      <?php foreach ($handoffs as $lead) {
          $fullName = trim((string) $lead->name);
          $displayName = $fullName !== '' ? $fullName : ('Lead #' . $lead->id);
          $initial = mb_strtoupper(mb_substr($fullName !== '' ? $fullName : '?', 0, 1, 'UTF-8'), 'UTF-8');
          $contact = $lead->email ?: ($lead->phone ?: 'anonyme');
          $leadUrl = admin_url('school_ia_bridge/lead/' . (int) $lead->id);
          $thread = array_slice($model->chat_messages((int) $lead->id), -12);
          $siaVal = $model->resolve_fee((string) $lead->formation, $feesIndex); ?>

        <div class="panel_s"><div class="panel-body">
          <div class="clearfix" style="margin-bottom:10px;">
            <div class="pull-left" style="display:flex;align-items:center;gap:12px;">
              <span class="sia-avatar" style="background:#d1134933;color:#d11349;width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;">
                <?php echo htmlspecialchars($initial, ENT_QUOTES); ?>
              </span>
              <div>
                <a href="<?php echo $leadUrl; ?>" class="bold"><?php echo htmlspecialchars($displayName, ENT_QUOTES); ?></a>
                <div class="text-muted" style="font-size:12px;"><?php echo htmlspecialchars((string) $contact, ENT_QUOTES); ?></div>
              </div>
            </div>
            <div class="pull-right text-right">
              <?php if (!empty($lead->handoff_motif)) { ?>
                <span class="label label-danger"><i class="fa fa-clock-o"></i> <?php echo htmlspecialchars((string) $lead->handoff_motif, ENT_QUOTES); ?></span>
              <?php } ?>
              <?php if ($lead->score !== null && $lead->score !== '') { ?>
                <span class="label label-default">score <?php echo htmlspecialchars((string) $lead->score, ENT_QUOTES); ?></span>
              <?php } ?>
              <?php if ($siaVal !== null) { ?>
                <span class="label label-default"><?php echo number_format($siaVal, 0, ',', ' '); ?> <?php echo htmlspecialchars($currency, ENT_QUOTES); ?></span>
              <?php } ?>
            </div>
          </div>

          <?php if (empty($thread)) { ?>
            <p class="text-muted">Aucun message pour l'instant.</p>
          <?php } else { ?>
            <div class="sia-chat-thread" style="max-height:260px;">
              <?php foreach ($thread as $m) {
                  $isUser = $m->role === 'user';
                  $isAgent = $m->role === 'agent';
                  $who = $isUser ? 'Lead' : ($isAgent ? 'Conseiller' : 'IA'); ?>
                <div class="sia-chat-row <?php echo $isUser ? 'sia-chat-row-user' : 'sia-chat-row-assistant'; ?><?php echo $isAgent ? ' sia-chat-row-agent' : ''; ?>">
                  <div class="sia-chat-bubble">
                    <?php echo nl2br(htmlspecialchars((string) $m->content, ENT_QUOTES)); ?>
                    <div class="sia-chat-meta">
                      <?php echo htmlspecialchars($who . ' · ' . $m->created_at, ENT_QUOTES); ?>
                    </div>
                  </div>
                </div>
              <?php } ?>
            </div>
          <?php } ?>

          <?php if ($canReply && !empty($lead->source_site) && !empty($lead->external_id)) { ?>
            <?php echo form_open(admin_url('school_ia_bridge/send_chat_reply/' . (int) $lead->id), ['style' => 'margin-top:12px;']); ?>
              <div class="input-group">
                <textarea name="message" class="form-control" rows="2" required placeholder="Votre réponse au prospect…"
                          style="resize:vertical;"></textarea>
                <span class="input-group-btn" style="vertical-align:top;">
                  <button type="submit" class="btn btn-primary" style="height:100%;"><i class="fa fa-paper-plane"></i></button>
                </span>
              </div>
            <?php echo form_close(); ?>
          <?php } elseif ($canReply) { ?>
            <p class="text-muted" style="margin-top:12px;">Ce lead n'est pas rattaché à un site — réponse directe indisponible.</p>
          <?php } ?>

          <div style="margin-top:10px;">
            <?php if ($canReply) { ?>
              <?php echo form_open(admin_url('school_ia_bridge/close_handoff/' . (int) $lead->id), ['style' => 'display:inline;']); ?>
                <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-check-circle-o"></i> Clôturer — l'IA reprend la main</button>
              <?php echo form_close(); ?>
            <?php } ?>
            <a href="<?php echo $leadUrl; ?>#tab-ia" class="btn btn-default btn-sm"><i class="fa fa-external-link"></i> Ouvrir la fiche</a>
          </div>
        </div></div>
      <?php } ?>

    <?php } ?>

  </div>
</div>
<?php init_tail(); ?>
