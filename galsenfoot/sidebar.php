<?php
/**
 * Sidebar.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;
?>
<aside class="gf-sidebar" aria-label="<?php esc_attr_e( 'Colonne latérale', 'galsenfoot' ); ?>">
	<div class="gf-sidebar__inner">
		<?php
		if ( is_active_sidebar( 'sidebar-1' ) ) {
			dynamic_sidebar( 'sidebar-1' );
		} else {
			gf_default_sidebar();
		}
		?>
	</div>
</aside>
