<?php
/**
 * "Espace mariés" app shell (PWA). Static on purpose: it is cached for offline
 * use, all user data is loaded by assets/app/app.js.
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
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="robots" content="noindex, nofollow">
	<meta name="theme-color" content="#1d1714">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
	<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( WRP_Settings::couple_names( '&' ) ); ?>">
	<title><?php echo esc_html( sprintf( __( 'Espace mariés – %s', 'wedding-rsvp-pass' ), $config['names'] ) ); ?></title>
	<link rel="manifest" href="<?php echo esc_url( WRP_Public::url( 'manifest' ) ); ?>">
	<link rel="apple-touch-icon" href="<?php echo esc_url( WRP_Public::asset( 'img/icon-192.png' ) ); ?>">
	<link rel="icon" href="<?php echo esc_url( WRP_Public::asset( 'img/icon-192.png' ) ); ?>">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="stylesheet" href="<?php echo esc_url( WRP_Public::fonts_url() ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( WRP_Public::asset( 'app/app.css' ) ); ?>">
	<style><?php echo WRP_Public::css_vars(); // phpcs:ignore ?></style>
</head>
<body>
<div class="app" id="app" data-screen="loading">

	<!-- Loading -->
	<section class="screen screen--loading" data-screen-id="loading">
		<div class="ring"><span>♥</span></div>
	</section>

	<!-- Login -->
	<section class="screen screen--login" data-screen-id="login">
		<div class="login">
			<div class="login__petals" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></div>
			<div class="ring"><span>♥</span></div>
			<p class="kicker"><?php esc_html_e( 'Espace des mariés', 'wedding-rsvp-pass' ); ?></p>
			<h1 class="script"><?php echo esc_html( $config['names'] ); ?></h1>
			<p class="muted"><?php echo esc_html( $config['date'] ); ?></p>

			<form class="login__form" data-login novalidate>
				<label><span><?php esc_html_e( 'E-mail ou identifiant', 'wedding-rsvp-pass' ); ?></span>
					<input type="text" name="login" autocomplete="username" autocapitalize="none" spellcheck="false" required>
				</label>
				<label><span><?php esc_html_e( 'Mot de passe', 'wedding-rsvp-pass' ); ?></span>
					<span class="pw">
						<input type="password" name="password" autocomplete="current-password" required>
						<button type="button" class="pw__toggle" data-pw-toggle aria-label="<?php esc_attr_e( 'Afficher le mot de passe', 'wedding-rsvp-pass' ); ?>">👁</button>
					</span>
				</label>
				<p class="login__error" data-login-error hidden></p>
				<button type="submit" class="btn btn--gold"><?php esc_html_e( 'Se connecter', 'wedding-rsvp-pass' ); ?></button>
				<a class="login__lost" href="<?php echo esc_url( $config['lostPassword'] ); ?>"><?php esc_html_e( 'Mot de passe oublié ?', 'wedding-rsvp-pass' ); ?></a>
			</form>
			<button type="button" class="install-chip" data-install hidden>⤓ <?php esc_html_e( 'Installer l’application', 'wedding-rsvp-pass' ); ?></button>
			<p class="muted small login__help"><?php esc_html_e( 'Pour les mariés et l’équipe d’accueil. Les invités, eux, utilisent le lien personnel reçu par message.', 'wedding-rsvp-pass' ); ?></p>
		</div>
	</section>

	<!-- Main -->
	<section class="screen screen--main" data-screen-id="main">
		<header class="top">
			<div class="top__title">
				<span class="script" data-names><?php echo esc_html( $config['names'] ); ?></span>
				<small data-subtitle></small>
			</div>
			<button type="button" class="avatar" data-go="account" aria-label="<?php esc_attr_e( 'Mon compte', 'wedding-rsvp-pass' ); ?>"><span data-initials></span></button>
		</header>
		<div class="offline-bar" data-offline hidden><?php esc_html_e( 'Hors connexion – affichage de la dernière liste enregistrée', 'wedding-rsvp-pass' ); ?></div>

		<main class="views">
			<!-- Home -->
			<div class="view" data-view="home">
				<button type="button" class="alert-wishes" data-go="wishes" data-wishes-alert hidden>
					<span class="alert-wishes__icon">✒</span>
					<span><b data-wishes-alert-text></b><small><?php esc_html_e( 'Touchez ici pour les lire et les publier sur le site', 'wedding-rsvp-pass' ); ?></small></span>
					<span class="alert-wishes__go">›</span>
				</button>
				<div class="hello">
					<p class="kicker" data-hello-kicker></p>
					<h2 class="script" data-hello></h2>
					<div class="countdown" data-countdown></div>
				</div>
				<div class="cards" data-stats></div>
				<div class="progress"><div class="progress__bar"><span data-progress></span></div><small data-progress-label></small></div>
				<div class="quick">
					<button type="button" class="quick__btn" data-action="add"><b>＋</b><?php esc_html_e( 'Ajouter un invité', 'wedding-rsvp-pass' ); ?></button>
					<button type="button" class="quick__btn" data-action="pending"><b>✉</b><?php esc_html_e( 'Inviter les retardataires', 'wedding-rsvp-pass' ); ?></button>
					<a class="quick__btn" data-scanner-link href="<?php echo esc_url( $config['scannerUrl'] ); ?>"><b>▣</b><?php esc_html_e( 'Scanner le jour J', 'wedding-rsvp-pass' ); ?></a>
					<button type="button" class="quick__btn" data-go="wishes"><b>✒</b><?php esc_html_e( 'Livre d’or', 'wedding-rsvp-pass' ); ?> <em class="badge-count" data-wishes-badge hidden></em></button>
					<button type="button" class="quick__btn" data-install hidden><b>⤓</b><?php esc_html_e( 'Installer l’application', 'wedding-rsvp-pass' ); ?></button>
				</div>
				<h3 class="section-title"><?php esc_html_e( 'Dernières réponses', 'wedding-rsvp-pass' ); ?></h3>
				<ul class="list" data-latest></ul>
			</div>

			<!-- Guests -->
			<div class="view" data-view="guests" hidden>
				<div class="search">
					<input type="search" data-search placeholder="<?php esc_attr_e( 'Rechercher un invité, une table…', 'wedding-rsvp-pass' ); ?>">
				</div>
				<div class="chips" data-lists></div>
				<div class="chips" data-filters></div>
				<p class="muted small count" data-count></p>
				<ul class="list" data-guests></ul>
				<button type="button" class="fab" data-action="add" aria-label="<?php esc_attr_e( 'Ajouter un invité', 'wedding-rsvp-pass' ); ?>">＋</button>
			</div>

			<!-- Guest book -->
			<div class="view" data-view="wishes" hidden>
				<div class="panel">
					<h2 class="script"><?php esc_html_e( 'Livre d’or', 'wedding-rsvp-pass' ); ?></h2>
					<p class="muted" data-wishes-help></p>
					<a class="btn btn--ghost" data-wishes-page href="#" target="_blank" rel="noopener" hidden><?php esc_html_e( 'Voir la page du livre d’or', 'wedding-rsvp-pass' ); ?></a>
				</div>
				<div class="chips" data-wish-filters></div>
				<ul class="list" data-wishes></ul>
			</div>

			<!-- Team -->
			<div class="view" data-view="team" hidden>
				<div class="panel">
					<h2 class="script"><?php esc_html_e( 'Équipe d’accueil', 'wedding-rsvp-pass' ); ?></h2>
					<p class="muted"><?php esc_html_e( 'Créez un accès pour chaque personne qui scannera les cartons à l’entrée. Elle se connecte ici avec son identifiant et arrive directement sur le scanner, déjà configuré.', 'wedding-rsvp-pass' ); ?></p>
					<button type="button" class="btn btn--gold" data-action="add-member"><?php esc_html_e( 'Ajouter une personne', 'wedding-rsvp-pass' ); ?></button>
				</div>
				<ul class="list" data-team></ul>
				<a class="btn btn--ghost" data-scanner-link href="<?php echo esc_url( $config['scannerUrl'] ); ?>"><?php esc_html_e( 'Ouvrir le scanner sur ce téléphone', 'wedding-rsvp-pass' ); ?></a>
			</div>

			<!-- Account -->
			<div class="view" data-view="account" hidden>
				<div class="panel panel--center">
					<div class="avatar avatar--big"><span data-initials></span></div>
					<h2 class="script" data-user-name></h2>
					<p class="muted" data-user-role></p>
				</div>
				<div class="menu">
					<button type="button" class="menu__item" data-install hidden>⤓ <?php esc_html_e( 'Installer l’application sur ce téléphone', 'wedding-rsvp-pass' ); ?></button>
					<button type="button" class="menu__item" data-ios-help hidden>⤓ <?php esc_html_e( 'Ajouter à l’écran d’accueil (iPhone)', 'wedding-rsvp-pass' ); ?></button>
					<button type="button" class="menu__item" data-go="wishes">✒ <?php esc_html_e( 'Livre d’or (valider les messages)', 'wedding-rsvp-pass' ); ?></button>
					<a class="menu__item" data-scanner-link href="<?php echo esc_url( $config['scannerUrl'] ); ?>">▣ <?php esc_html_e( 'Scanner du jour J', 'wedding-rsvp-pass' ); ?></a>
					<a class="menu__item" data-admin-link href="#" target="_blank" rel="noopener">⚙ <?php esc_html_e( 'Administration complète (import, messages…)', 'wedding-rsvp-pass' ); ?></a>
					<a class="menu__item" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">♥ <?php esc_html_e( 'Voir le site du mariage', 'wedding-rsvp-pass' ); ?></a>
					<button type="button" class="menu__item menu__item--danger" data-logout>⎋ <?php esc_html_e( 'Se déconnecter', 'wedding-rsvp-pass' ); ?></button>
				</div>
				<p class="muted small center">v<?php echo esc_html( WRP_VERSION ); ?></p>
			</div>
		</main>

		<nav class="tabbar" data-tabbar>
			<button type="button" data-go="home" class="is-active"><i>⌂</i><span><?php esc_html_e( 'Accueil', 'wedding-rsvp-pass' ); ?></span></button>
			<button type="button" data-go="guests"><i>♥</i><span><?php esc_html_e( 'Invités', 'wedding-rsvp-pass' ); ?></span></button>
			<button type="button" data-go="wishes" class="tab-wishes"><i>✒</i><span><?php esc_html_e( 'Livre d’or', 'wedding-rsvp-pass' ); ?></span><em class="tab-badge" data-wishes-tab-badge hidden></em></button>
			<a data-scanner-link href="<?php echo esc_url( $config['scannerUrl'] ); ?>"><i>▣</i><span><?php esc_html_e( 'Scanner', 'wedding-rsvp-pass' ); ?></span></a>
			<button type="button" data-go="team"><i>☺</i><span><?php esc_html_e( 'Équipe', 'wedding-rsvp-pass' ); ?></span></button>
		</nav>
	</section>

	<!-- Bottom sheet (guest detail, forms) -->
	<div class="sheet" data-sheet hidden>
		<div class="sheet__backdrop" data-sheet-close></div>
		<div class="sheet__panel" role="dialog" aria-modal="true">
			<div class="sheet__grip" data-sheet-close></div>
			<div class="sheet__body" data-sheet-body></div>
		</div>
	</div>

	<div class="toast" data-toast></div>
</div>

<script>window.WRP_APP = <?php echo wp_json_encode( $config ); ?>;</script>
<script src="<?php echo esc_url( WRP_Public::asset( 'app/app.js' ) ); ?>"></script>
</body>
</html>
