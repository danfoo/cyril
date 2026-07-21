<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <h4 class="no-margin" style="margin-bottom:15px;"><i class="fa fa-folder-open"></i> Documents par programme</h4>

    <div class="row">
      <!-- Upload -->
      <div class="col-md-4">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">Ajouter un document</h5>
          <?php if (empty($programs)) { ?>
            <div class="alert alert-warning">
              Définissez d'abord vos programmes dans <a href="<?php echo admin_url('school_ia_bridge/settings'); ?>">Réglages</a>.
            </div>
          <?php } ?>
          <?php echo form_open_multipart(admin_url('school_ia_bridge/doc_upload')); ?>
            <div class="form-group">
              <label class="control-label">Programme</label>
              <select name="program" class="form-control">
                <option value="">— Sans programme —</option>
                <?php foreach ($programs as $p) { ?>
                  <option value="<?php echo htmlspecialchars($p, ENT_QUOTES); ?>"><?php echo htmlspecialchars($p, ENT_QUOTES); ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="form-group">
              <label class="control-label">Titre (optionnel)</label>
              <input type="text" name="title" class="form-control" placeholder="Nom affiché du document">
            </div>
            <div class="form-group">
              <label class="control-label">Fichier</label>
              <input type="file" name="file" class="form-control" required>
              <p class="text-muted" style="font-size:12px;">PDF, Word, Excel, PPT, images, zip — max 20 Mo.</p>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> Téléverser</button>
          <?php echo form_close(); ?>
        </div></div>
      </div>

      <!-- Liste groupée -->
      <div class="col-md-8">
        <?php if (empty($grouped)) { ?>
          <div class="panel_s"><div class="panel-body text-center text-muted" style="padding:30px;">
            Aucun document pour l'instant.
          </div></div>
        <?php } else {
            foreach ($grouped as $program => $docs) { ?>
          <div class="panel_s"><div class="panel-body">
            <h5 class="bold" style="margin-top:0;"><i class="fa fa-graduation-cap"></i> <?php echo htmlspecialchars((string) $program, ENT_QUOTES); ?></h5>
            <table class="table no-margin">
              <tbody>
                <?php foreach ($docs as $doc) { ?>
                  <tr>
                    <td><i class="fa fa-file-o text-muted"></i> <?php echo htmlspecialchars((string) $doc->title, ENT_QUOTES); ?></td>
                    <td class="text-muted" style="width:110px;"><?php echo round(((int) $doc->filesize) / 1024); ?> Ko</td>
                    <td class="text-right" style="width:120px;">
                      <a href="<?php echo admin_url('school_ia_bridge/doc_download/' . (int) $doc->id); ?>" class="btn btn-xs btn-default"><i class="fa fa-download"></i></a>
                      <a href="<?php echo admin_url('school_ia_bridge/doc_delete/' . (int) $doc->id); ?>" class="btn btn-xs btn-default"
                         onclick="return confirm('Supprimer ce document ?');"><i class="fa fa-trash"></i></a>
                    </td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          </div></div>
        <?php }
        } ?>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
</body>
</html>
