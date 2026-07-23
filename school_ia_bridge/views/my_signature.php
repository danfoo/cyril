<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">

        <div class="clearfix" style="margin-bottom:15px;">
          <h4 class="no-margin pull-left"><i class="fa fa-pencil-square-o"></i> Ma signature e-mail</h4>
        </div>

        <div class="panel_s"><div class="panel-body">
          <p class="text-muted">
            Cette signature est ajoutée automatiquement au bas de chaque e-mail que
            <strong>vous</strong> envoyez depuis le CRM (individuel, envoi groupé, séquences).
            Chaque conseiller a la sienne.
          </p>

          <?php echo form_open(admin_url('school_ia_bridge/my_signature'), ['id' => 'sia-sig-form']); ?>
            <input type="hidden" name="signature_form" value="1">

            <div class="sia-rte">
              <div class="sia-rte-toolbar">
                <button type="button" data-cmd="bold" title="Gras"><i class="fa fa-bold"></i></button>
                <button type="button" data-cmd="italic" title="Italique"><i class="fa fa-italic"></i></button>
                <button type="button" data-cmd="underline" title="Souligné"><i class="fa fa-underline"></i></button>
                <span class="sep"></span>
                <button type="button" data-cmd="insertUnorderedList" title="Liste à puces"><i class="fa fa-list-ul"></i></button>
                <button type="button" data-cmd="createLink" title="Insérer un lien"><i class="fa fa-link"></i></button>
                <span class="sep"></span>
                <button type="button" data-cmd="removeFormat" title="Effacer la mise en forme"><i class="fa fa-eraser"></i></button>
                <span class="sep"></span>
                <button type="button" id="sia-sig-template" title="Insérer un modèle"><i class="fa fa-magic"></i> Modèle</button>
              </div>
              <div class="sia-rte-area" id="sia-sig-editor" contenteditable="true"><?php echo $signature; ?></div>
            </div>
            <textarea name="signature" id="sia-sig-hidden" style="display:none;"><?php echo htmlspecialchars((string) $signature, ENT_QUOTES); ?></textarea>

            <div style="margin-top:12px;">
              <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Enregistrer ma signature</button>
              <span class="text-muted" style="margin-left:10px;font-size:12px;">Laissez vide pour n'ajouter aucune signature.</span>
            </div>
          <?php echo form_close(); ?>
        </div></div>

        <!-- Aperçu tel qu'affiché en bas de l'e-mail -->
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;"><i class="fa fa-eye"></i> Aperçu</h5>
          <div style="background:#f1f5f9;padding:18px;border-radius:12px;">
            <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(15,23,42,.08);">
              <div style="background:#4f46e5;padding:16px 24px;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;color:#fff;">
                <?php echo htmlspecialchars((string) (get_option('companyname') ?: 'School IA'), ENT_QUOTES); ?>
              </div>
              <div style="padding:20px 24px 6px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#1e293b;">
                <p style="margin:0 0 10px;">Bonjour Prénom,</p>
                <p style="margin:0;">…le contenu de votre message apparaîtra ici…</p>
              </div>
              <div style="padding:0 24px;"><hr style="border:none;border-top:1px solid #e6e9f0;margin:18px 0 14px;"></div>
              <div id="sia-sig-preview" style="padding:0 24px 18px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.55;color:#475569;"><?php echo $signature ?: '<span style="color:#94a3b8;">Votre signature apparaîtra ici.</span>'; ?></div>
            </div>
          </div>
        </div></div>

      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var editor  = document.getElementById('sia-sig-editor');
  var hidden  = document.getElementById('sia-sig-hidden');
  var preview = document.getElementById('sia-sig-preview');
  if (!editor || !hidden) { return; }

  function sync() {
    hidden.value = editor.innerHTML;
    if (preview) {
      preview.innerHTML = editor.innerHTML.trim() !== ''
        ? editor.innerHTML
        : '<span style="color:#94a3b8;">Votre signature apparaîtra ici.</span>';
    }
  }
  editor.addEventListener('input', sync);
  editor.addEventListener('blur', sync);

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
      sync();
    });
  });

  var tplBtn = document.getElementById('sia-sig-template');
  if (tplBtn) {
    var fullName = <?php echo json_encode(function_exists('get_staff_full_name') ? get_staff_full_name() : ''); ?>;
    var company  = <?php echo json_encode((string) (get_option('companyname') ?: 'School IA')); ?>;
    tplBtn.addEventListener('click', function () {
      var tpl = '<strong>' + (fullName || 'Votre nom') + '</strong><br>'
              + 'Conseiller·ère admissions — ' + company + '<br>'
              + '<span style="color:#64748b;">Tél. : +221 XX XXX XX XX · '
              + '<a href="mailto:vous@ecole.com" style="color:#4f46e5;">vous@ecole.com</a></span>';
      editor.innerHTML = tpl;
      sync();
    });
  }

  var form = document.getElementById('sia-sig-form');
  if (form) { form.addEventListener('submit', sync); }
})();
</script>
<?php init_tail(); ?>
</body>
</html>
