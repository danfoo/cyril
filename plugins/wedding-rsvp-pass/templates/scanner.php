<?php
/**
 * Offline QR scanner (Progressive Web App shell).
 * This page is cached by the service worker and must not depend on the theme.
 *
 * @var array $config
 * @package WeddingRSVPPass
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
	<meta name="robots" content="noindex, nofollow">
	<meta name="theme-color" content="#1d1714">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
	<meta name="apple-mobile-web-app-title" content="<?php esc_attr_e( 'Scanner', 'wedding-rsvp-pass' ); ?>">
	<title><?php echo esc_html( sprintf( __( 'Scanner – Mariage %s', 'wedding-rsvp-pass' ), $config['names'] ) ); ?></title>
	<link rel="manifest" href="<?php echo esc_url( WRP_Public::url( 'manifest' ) ); ?>">
	<link rel="apple-touch-icon" href="<?php echo esc_url( WRP_Public::asset( 'img/icon-192.png' ) ); ?>">
	<link rel="icon" href="<?php echo esc_url( WRP_Public::asset( 'img/icon-192.png' ) ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( WRP_Public::asset( 'scanner/scanner.css' ) ); ?>">
	<style><?php echo WRP_Public::css_vars(); // phpcs:ignore ?></style>
</head>
<body>
<div id="app" class="app" data-screen="loading">

	<!-- Loading -->
	<section class="screen screen--loading" data-screen-id="loading">
		<div class="logo-ring"><span>♥</span></div>
	</section>

	<!-- Setup -->
	<section class="screen screen--setup" data-screen-id="setup">
		<div class="setup">
			<div class="logo-ring"><span>♥</span></div>
			<p class="kicker"><?php esc_html_e( 'Scanner du jour J', 'wedding-rsvp-pass' ); ?></p>
			<h1 class="script"><?php echo esc_html( $config['names'] ); ?></h1>
			<p class="muted"><?php echo esc_html( $config['date'] ); ?></p>

			<form class="setup__form" data-setup-form>
				<label><span><?php esc_html_e( 'Clé d’accès du scanner', 'wedding-rsvp-pass' ); ?></span>
					<input type="text" name="key" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="XXXX-XXXX-XXXX-XXXX" required>
				</label>
				<label><span><?php esc_html_e( 'Nom de ce téléphone (optionnel)', 'wedding-rsvp-pass' ); ?></span>
					<input type="text" name="device" autocomplete="off" placeholder="<?php esc_attr_e( 'ex : Entrée principale', 'wedding-rsvp-pass' ); ?>">
				</label>
				<button type="submit" class="btn btn--gold"><?php esc_html_e( 'Activer & télécharger la liste', 'wedding-rsvp-pass' ); ?></button>
				<button type="button" class="btn btn--ghost" data-scan-key><?php esc_html_e( 'Scanner le QR d’activation', 'wedding-rsvp-pass' ); ?></button>
				<p class="setup__error" data-setup-error hidden></p>
			</form>
			<a class="btn btn--ghost" href="<?php echo esc_url( $config['appUrl'] ); ?>"><?php esc_html_e( 'Se connecter avec mon compte', 'wedding-rsvp-pass' ); ?></a>
			<p class="muted small"><?php esc_html_e( 'Connectez-vous avec le compte reçu des mariés : le scanner se configure tout seul. Sinon, la clé se trouve dans l’administration : Invités → Jour J – Scanner.', 'wedding-rsvp-pass' ); ?></p>
		</div>
	</section>

	<!-- Main -->
	<section class="screen screen--main" data-screen-id="main">
		<header class="topbar">
			<a class="back" data-back hidden href="<?php echo esc_url( $config['appUrl'] ); ?>" aria-label="<?php esc_attr_e( 'Retour à l’espace des mariés', 'wedding-rsvp-pass' ); ?>">‹</a>
			<div class="topbar__title">
				<strong class="script" data-names><?php echo esc_html( $config['names'] ); ?></strong>
				<span class="net" data-net><i></i><b data-net-label></b></span>
			</div>
			<button class="sync" data-sync type="button" aria-label="<?php esc_attr_e( 'Synchroniser', 'wedding-rsvp-pass' ); ?>">
				<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 4V1L8 5l4 4V6a6 6 0 0 1 6 6c0 1-.25 1.97-.7 2.8l1.46 1.46A8 8 0 0 0 12 4Zm0 14a6 6 0 0 1-6-6c0-1 .25-1.97.7-2.8L5.24 7.74A8 8 0 0 0 12 20v3l4-4-4-4v3Z"/></svg>
				<em class="badge" data-pending hidden>0</em>
			</button>
		</header>

		<button type="button" class="update-bar" data-update hidden>✨ <?php esc_html_e( 'Nouvelle version du scanner – toucher pour mettre à jour', 'wedding-rsvp-pass' ); ?></button>
		<div class="counter">
			<div class="counter__ring">
				<svg viewBox="0 0 120 120"><circle cx="60" cy="60" r="52" class="bg"/><circle cx="60" cy="60" r="52" class="fg" data-ring/></svg>
				<div class="counter__txt"><strong data-arrived>0</strong><span>/ <b data-expected>0</b></span></div>
			</div>
			<div class="counter__info">
				<p><b data-arrived-cards>0</b> <?php esc_html_e( 'cartons validés', 'wedding-rsvp-pass' ); ?></p>
				<p class="muted small" data-last-sync></p>
			</div>
		</div>

		<nav class="tabs" role="tablist">
			<button type="button" class="is-active" data-tab="scan"><?php esc_html_e( 'Scanner', 'wedding-rsvp-pass' ); ?></button>
			<button type="button" data-tab="search"><?php esc_html_e( 'Rechercher', 'wedding-rsvp-pass' ); ?></button>
			<button type="button" data-tab="history"><?php esc_html_e( 'Historique', 'wedding-rsvp-pass' ); ?></button>
		</nav>

		<div class="panel panel--scan" data-panel="scan">
			<div class="viewport" data-viewport>
				<video playsinline muted autoplay data-video></video>
				<canvas data-canvas hidden></canvas>
				<div class="frame"><i></i><i></i><i></i><i></i><span class="laser"></span></div>
				<div class="viewport__cta" data-cam-cta>
					<button type="button" class="btn btn--gold" data-cam-start><?php esc_html_e( 'Activer la caméra', 'wedding-rsvp-pass' ); ?></button>
					<p class="muted small" data-cam-error></p>
				</div>
			</div>
			<form class="manual" data-manual>
				<input type="text" name="code" placeholder="<?php esc_attr_e( 'Saisir un code (ex : ABCD 2345)', 'wedding-rsvp-pass' ); ?>" autocomplete="off" autocapitalize="characters" spellcheck="false">
				<button type="submit" class="btn btn--small"><?php esc_html_e( 'Valider', 'wedding-rsvp-pass' ); ?></button>
			</form>
		</div>

		<div class="panel" data-panel="search" hidden>
			<input type="search" class="search" data-search placeholder="<?php esc_attr_e( 'Nom, table, 4 derniers caractères du code…', 'wedding-rsvp-pass' ); ?>">
			<ul class="list" data-search-results></ul>
		</div>

		<div class="panel" data-panel="history" hidden>
			<ul class="list" data-history></ul>
			<div class="settings">
				<p class="muted small" data-device-info></p>
				<button type="button" class="btn btn--ghost btn--small" data-logout><?php esc_html_e( 'Déconnecter ce téléphone', 'wedding-rsvp-pass' ); ?></button>
			</div>
		</div>
	</section>

	<!-- Result overlay -->
	<div class="result" data-result hidden>
		<div class="result__card">
			<div class="result__icon" data-r-icon></div>
			<p class="result__title" data-r-title></p>
			<p class="result__name script" data-r-name></p>
			<p class="result__comp" data-r-comp></p>
			<div class="result__meta">
				<span data-r-seats></span>
				<span data-r-table></span>
			</div>
			<p class="result__note" data-r-note></p>
			<div class="result__actions">
				<button type="button" class="btn btn--gold" data-r-confirm hidden><?php esc_html_e( 'Valider l’entrée', 'wedding-rsvp-pass' ); ?></button>
				<button type="button" class="btn btn--light" data-r-close><?php esc_html_e( 'Scanner suivant', 'wedding-rsvp-pass' ); ?></button>
			</div>
			<div class="result__timer" data-r-timer></div>
		</div>
	</div>

	<div class="toast" data-toast></div>
</div>

<script>window.WRP_SCANNER = <?php echo wp_json_encode( $config ); ?>;</script>
<script src="<?php echo esc_url( WRP_Public::asset( 'vendor/jsQR.js' ) ); ?>"></script>
<script src="<?php echo esc_url( WRP_Public::asset( 'scanner/scanner.js' ) ); ?>"></script>
</body>
</html>
