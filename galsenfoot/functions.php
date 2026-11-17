<?php
/**
 * GalsenFoot theme bootstrap.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;

define( 'GF_VERSION', '1.0.0' );
define( 'GF_DIR', get_template_directory() );
define( 'GF_URI', get_template_directory_uri() );

require GF_DIR . '/inc/setup.php';
require GF_DIR . '/inc/template-tags.php';
require GF_DIR . '/inc/customizer.php';
require GF_DIR . '/inc/seo.php';
require GF_DIR . '/inc/ads.php';
require GF_DIR . '/inc/elementor.php';
if ( is_admin() ) {
	require GF_DIR . '/inc/admin.php';
}
