<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">

    <h4 class="no-margin" style="margin-bottom:15px;"><i class="fa fa-stethoscope"></i> Diagnostic du pont</h4>

    <div class="panel_s"><div class="panel-body">
      <h5 class="bold" style="margin-top:0;">
        <span class="sia-panel-icon sia-ic-info"><i class="fa fa-database"></i></span>
        Tables & contenu stocké
      </h5>
      <table class="table">
        <thead><tr><th>Table</th><th>Existe</th><th>Lignes</th><th>Rattachées</th><th>Orphelines</th></tr></thead>
        <tbody>
          <?php foreach ($tables as $name => $t) { ?>
            <tr>
              <td class="bold"><?php echo htmlspecialchars($name, ENT_QUOTES); ?></td>
              <td><?php echo !empty($t['exists'])
                    ? '<span class="label label-success">oui</span>'
                    : '<span class="label label-danger">non</span>'; ?></td>
              <td><?php echo (int) ($t['rows'] ?? 0); ?></td>
              <td><?php echo isset($t['linked']) ? (int) $t['linked'] : '—'; ?></td>
              <td><?php echo isset($t['orphaned']) ? (int) $t['orphaned'] : '—'; ?></td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div></div>

    <div class="panel_s"><div class="panel-body">
      <h5 class="bold" style="margin-top:0;">
        <span class="sia-panel-icon sia-ic-warning"><i class="fa fa-binoculars"></i></span>
        Veille concurrentielle — réception
      </h5>
      <p>
        Appels reçus par le point d'entrée concurrent :
        <span class="label <?php echo $compCalls > 0 ? 'label-success' : 'label-default'; ?>"><?php echo (int) $compCalls; ?></span>
      </p>
      <?php if (!empty($lastCall)) { ?>
        <p class="text-muted" style="margin-bottom:4px;">Dernier appel reçu :</p>
        <pre style="white-space:pre-wrap;"><?php echo htmlspecialchars(json_encode($lastCall, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?></pre>
      <?php } else { ?>
        <p class="text-muted">Aucun appel concurrent reçu pour l'instant.</p>
      <?php } ?>
    </div></div>

    <div class="panel_s"><div class="panel-body">
      <h5 class="bold" style="margin-top:0;">
        <span class="sia-panel-icon sia-ic-success"><i class="fa fa-pencil"></i></span>
        Test d'écriture (table concurrents)
      </h5>
      <p>
        Insertion directe :
        <?php echo !empty($writeTest['stored_via_method'])
              ? '<span class="label label-success">réussie</span>'
              : '<span class="label label-danger">échouée</span>'; ?>
      </p>
      <?php if (!empty($writeTest['db_error']['message'])) { ?>
        <p class="text-danger">Erreur SQL : <?php echo htmlspecialchars((string) $writeTest['db_error']['message'], ENT_QUOTES); ?></p>
      <?php } ?>
    </div></div>

    <div class="panel_s"><div class="panel-body">
      <h5 class="bold" style="margin-top:0;">
        <span class="sia-panel-icon sia-ic-primary"><i class="fa fa-info-circle"></i></span>
        Comment lire ce diagnostic
      </h5>
      <ul>
        <li><strong>chat_messages</strong> avec des lignes « rattachées » &gt; 0 → les conversations s'affichent sur les fiches des leads concernés.</li>
        <li><strong>Appels concurrent = 0</strong> alors que WordPress dit « envoyées » → les requêtes n'atteignent pas le serveur (pare-feu de l'hébergeur).</li>
        <li><strong>Test d'écriture réussi</strong> mais <strong>competitors = 0</strong> → le stockage marche ; le problème est en amont (réception).</li>
      </ul>
    </div></div>

  </div>
</div>
<?php init_tail(); ?>
</body>
</html>
