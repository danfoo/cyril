<?php
/**
 * Plugin Name:       BEM Lead AI — Conseiller IA & Lead Scoring Comportemental
 * Plugin URI:        https://bem.sn
 * Description:       Transforme le site de BEM Conakry en conseiller d'orientation actif : chatbot IA ancré dans le catalogue (contexte + cache LLM), lead scoring comportemental et intentionnel, triggers CRM, escalade humaine, passerelle WhatsApp, veille concurrentielle et relances auto-apprenantes. Modèles Claude et design du widget configurables. Nom de l'école modifiable dans les réglages.
 * Version:           2.2.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            BEM Conakry
 * License:           GPL-2.0-or-later
 * Text Domain:       bem-lead-ai
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;

define('BEM_LEAD_AI_VERSION', '2.2.0');
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
