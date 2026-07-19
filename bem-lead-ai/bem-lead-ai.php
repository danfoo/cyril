<?php
/**
 * Plugin Name:       School IA — Conseiller IA & CRM d'admission
 * Plugin URI:        https://maestrodan.art
 * Description:       Transforme le site d'une école en conseiller d'orientation actif : chatbot IA ancré dans le catalogue (contexte + cache LLM), lead scoring comportemental et intentionnel, CRM d'admission natif, escalade humaine, passerelle WhatsApp, veille concurrentielle et relances auto-apprenantes. Modèles Claude et design du widget configurables. Nom de l'école modifiable dans les réglages.
 * Version:           2.6.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Maestro Dan
 * Author URI:        https://maestrodan.art
 * License:           GPL-2.0-or-later
 * Text Domain:       bem-lead-ai
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;

define('BEM_LEAD_AI_VERSION', '2.6.0');
// Marque produit (éditeur : Maestro Dan). Les identifiants internes (slug,
// options, namespace) restent inchangés pour préserver les données existantes.
define('BEM_LEAD_AI_BRAND', 'School IA');
define('BEM_LEAD_AI_VENDOR', 'Maestro Dan');
define('BEM_LEAD_AI_FILE', __FILE__);
define('BEM_LEAD_AI_DIR', plugin_dir_path(__FILE__));
define('BEM_LEAD_AI_URL', plugin_dir_url(__FILE__));
define('BEM_LEAD_AI_REST_NS', 'bem-lead-ai/v1');

require_once BEM_LEAD_AI_DIR . 'includes/Core/Autoloader.php';
\BemLeadAi\Core\Autoloader::register();

register_activation_hook(__FILE__, ['\BemLeadAi\Core\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['\BemLeadAi\Core\Deactivator', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    load_plugin_textdomain('bem-lead-ai', false, dirname(plugin_basename(__FILE__)) . '/languages');
    \BemLeadAi\Core\Plugin::instance()->boot();
});
