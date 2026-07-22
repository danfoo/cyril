<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <h4 class="no-margin" style="margin-bottom:15px;"><i class="fa fa-random"></i> Séquences de relance automatiques <?php echo sia_help('sequences'); ?></h4>

    <div class="row">
      <!-- Liste + création -->
      <div class="col-md-4">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">Nouvelle séquence</h5>
          <?php echo form_open(admin_url('school_ia_bridge/sequence_save')); ?>
            <div class="input-group">
              <input type="text" name="name" class="form-control" placeholder="Ex. Relance admission" required>
              <span class="input-group-btn"><button class="btn btn-primary" type="submit">Créer</button></span>
            </div>
          <?php echo form_close(); ?>
          <hr>
          <ul class="list-unstyled">
            <?php foreach ($sequences as $s) { ?>
              <li style="padding:6px 0; border-bottom:1px solid #f0f0f0;">
                <a href="<?php echo admin_url('school_ia_bridge/sequences?id=' . (int) $s->id); ?>"
                   class="<?php echo ($current && $current->id == $s->id) ? 'bold' : ''; ?>">
                  <?php echo htmlspecialchars((string) $s->name, ENT_QUOTES); ?>
                </a>
                <?php echo $s->active ? '<span class="label label-success">Active</span>' : '<span class="label label-default">Inactive</span>'; ?>
                <a href="<?php echo admin_url('school_ia_bridge/sequence_delete/' . (int) $s->id); ?>"
                   class="pull-right text-muted" onclick="return confirm('Supprimer cette séquence ?');"><i class="fa fa-trash"></i></a>
              </li>
            <?php } ?>
            <?php if (empty($sequences)) { ?><li class="text-muted">Aucune séquence.</li><?php } ?>
          </ul>
        </div></div>
      </div>

      <!-- Étapes de la séquence sélectionnée -->
      <div class="col-md-8">
        <?php if (!$current) { ?>
          <div class="panel_s"><div class="panel-body text-muted text-center" style="padding:30px;">
            Sélectionnez ou créez une séquence pour définir ses étapes.
          </div></div>
        <?php } else { ?>
          <div class="panel_s"><div class="panel-body">
            <div class="clearfix">
              <h5 class="bold pull-left" style="margin-top:0;"><?php echo htmlspecialchars((string) $current->name, ENT_QUOTES); ?></h5>
              <?php echo form_open(admin_url('school_ia_bridge/sequence_save'), ['class' => 'pull-right']); ?>
                <input type="hidden" name="id" value="<?php echo (int) $current->id; ?>">
                <input type="hidden" name="name" value="<?php echo htmlspecialchars((string) $current->name, ENT_QUOTES); ?>">
                <label style="font-weight:normal;">
                  <input type="checkbox" name="active" value="1" onchange="this.form.submit()" <?php echo $current->active ? 'checked' : ''; ?>> Active
                </label>
              <?php echo form_close(); ?>
            </div>
            <hr>

            <table class="table">
              <thead><tr><th>#</th><th>Canal</th><th>Modèle</th><th>Délai</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($steps as $i => $st) {
                    $tpl = $st->template_id ? $model->get_template((int) $st->template_id) : null; ?>
                  <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><span class="label <?php echo $st->channel === 'sms' ? 'label-info' : 'label-primary'; ?>"><?php echo strtoupper($st->channel); ?></span></td>
                    <td><?php echo $tpl ? htmlspecialchars((string) $tpl->name, ENT_QUOTES) : '<span class="text-danger">modèle supprimé</span>'; ?></td>
                    <td>+<?php echo (int) $st->delay_days; ?> j <?php echo (int) $st->delay_hours; ?> h</td>
                    <td class="text-right">
                      <a href="<?php echo admin_url('school_ia_bridge/step_delete/' . (int) $st->id); ?>"
                         class="text-muted" onclick="return confirm('Supprimer cette étape ?');"><i class="fa fa-trash"></i></a>
                    </td>
                  </tr>
                <?php } ?>
                <?php if (empty($steps)) { ?><tr><td colspan="5" class="text-muted">Aucune étape.</td></tr><?php } ?>
              </tbody>
            </table>

            <h6 class="bold">Ajouter une étape</h6>
            <?php echo form_open(admin_url('school_ia_bridge/step_add')); ?>
              <input type="hidden" name="sequence_id" value="<?php echo (int) $current->id; ?>">
              <div class="row">
                <div class="col-sm-3 form-group">
                  <label class="control-label">Canal</label>
                  <select name="channel" class="form-control" id="step-channel">
                    <option value="email">E-mail</option>
                    <option value="sms">SMS</option>
                  </select>
                </div>
                <div class="col-sm-4 form-group">
                  <label class="control-label">Modèle</label>
                  <select name="template_id" class="form-control">
                    <optgroup label="E-mail">
                      <?php foreach ($emailTpls as $t) { ?><option value="<?php echo (int) $t->id; ?>"><?php echo htmlspecialchars((string) $t->name, ENT_QUOTES); ?></option><?php } ?>
                    </optgroup>
                    <optgroup label="SMS">
                      <?php foreach ($smsTpls as $t) { ?><option value="<?php echo (int) $t->id; ?>"><?php echo htmlspecialchars((string) $t->name, ENT_QUOTES); ?></option><?php } ?>
                    </optgroup>
                  </select>
                </div>
                <div class="col-sm-2 form-group">
                  <label class="control-label">Délai (jours)</label>
                  <input type="number" name="delay_days" class="form-control" value="0" min="0">
                </div>
                <div class="col-sm-2 form-group">
                  <label class="control-label">Heures</label>
                  <input type="number" name="delay_hours" class="form-control" value="0" min="0" max="23">
                </div>
                <div class="col-sm-1 form-group">
                  <label class="control-label">&nbsp;</label>
                  <button type="submit" class="btn btn-primary btn-block">+</button>
                </div>
              </div>
              <p class="text-muted" style="font-size:12px;">Le délai est le temps d'attente <em>avant</em> l'envoi de cette étape (0 = immédiat à l'inscription pour la 1ʳᵉ, sinon après l'étape précédente).</p>
            <?php echo form_close(); ?>
          </div></div>
        <?php } ?>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
</body>
</html>
