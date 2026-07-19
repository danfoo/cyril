<?php

namespace BemLeadAi\Notifications;

use BemLeadAi\Core\Options;

defined('ABSPATH') || exit;

/**
 * Envoi centralisé des notifications e-mail (équipe admissions).
 *
 * Toutes les alertes internes passent par ici : gabarit HTML brandé lisible
 * dans tous les clients mail, en-têtes corrects, respect des interrupteurs
 * par événement, et diagnostic d'envoi (bouton « e-mail de test »). Le but est
 * de rendre les notifications VISIBLES et VÉRIFIABLES — la panne classique
 * étant un wp_mail() qui échoue en silence faute de configuration SMTP.
 */
final class Mailer
{
    public static function brand(): string
    {
        return defined('BEM_LEAD_AI_BRAND') ? BEM_LEAD_AI_BRAND : 'School IA';
    }

    private static function fromName(): string
    {
        $custom = trim((string) Options::get('notify_from_name'));
        return $custom !== '' ? $custom : self::brand();
    }

    /** Destinataire des alertes internes (équipe admissions). */
    public static function recipient(): string
    {
        $to = trim((string) Options::get('admissions_email'));
        return $to !== '' ? $to : (string) get_option('admin_email');
    }

    private static function headers(): array
    {
        $from = self::fromName();
        $email = (string) get_option('admin_email'); // domaine du site = meilleure délivrabilité
        return [
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $from, $email),
        ];
    }

    /**
     * Un événement doit-il déclencher un e-mail ? Interrupteur maître +
     * interrupteur par type. Événements connus :
     * hot_lead | handoff | task_reminder.
     */
    public static function eventEnabled(string $event): bool
    {
        if (!(int) Options::get('notify_email_enabled')) {
            return false;
        }
        return match ($event) {
            'hot_lead' => (bool) (int) Options::get('notify_hot_lead'),
            'handoff' => (bool) (int) Options::get('notify_handoff'),
            'task_reminder' => (bool) (int) Options::get('notify_task_reminder'),
            default => true,
        };
    }

    /** Envoi d'une alerte liée à un événement (respecte les interrupteurs). */
    public static function sendEvent(string $event, string $subject, string $bodyHtml, string $ctaUrl = '', string $ctaLabel = ''): bool
    {
        if (!self::eventEnabled($event)) {
            return false;
        }
        return self::send(self::recipient(), $subject, $bodyHtml, $ctaUrl, $ctaLabel);
    }

    /** Envoi brut d'un e-mail HTML brandé. */
    public static function send(string $to, string $subject, string $bodyHtml, string $ctaUrl = '', string $ctaLabel = ''): bool
    {
        if ($to === '') {
            return false;
        }
        $full = self::wrap($subject, $bodyHtml, $ctaUrl, $ctaLabel);
        $prefixed = '[' . self::brand() . '] ' . $subject;
        return (bool) wp_mail($to, $prefixed, $full, self::headers());
    }

    /**
     * Envoi de test avec diagnostic : capture l'erreur PHPMailer le cas
     * échéant (wp_mail_failed) pour afficher la cause exacte à l'admin.
     *
     * @return array{ok:bool, msg:string}
     */
    public static function sendTest(string $to): array
    {
        if (!is_email($to)) {
            return ['ok' => false, 'msg' => __('Adresse e-mail invalide.', 'bem-lead-ai')];
        }
        $captured = '';
        $listener = static function ($wpError) use (&$captured): void {
            if (is_wp_error($wpError)) {
                $captured = $wpError->get_error_message();
            }
        };
        add_action('wp_mail_failed', $listener);

        $body = '<p>' . esc_html__('Ceci est un e-mail de test.', 'bem-lead-ai') . '</p>'
            . '<p>' . esc_html__('Si vous le recevez, les notifications e-mail de School IA fonctionnent correctement sur ce site.', 'bem-lead-ai') . '</p>';
        $ok = self::send($to, __('E-mail de test', 'bem-lead-ai'), $body);

        remove_action('wp_mail_failed', $listener);

        if ($ok && $captured === '') {
            return ['ok' => true, 'msg' => sprintf(__('E-mail de test remis à wp_mail() pour %s. Vérifiez la boîte de réception (et les indésirables).', 'bem-lead-ai'), $to)];
        }
        $hint = __('Cause probable : aucun service d\'envoi configuré sur le serveur. Installez un plugin SMTP (ex. « WP Mail SMTP ») et renseignez un expéditeur authentifié.', 'bem-lead-ai');
        return ['ok' => false, 'msg' => ($captured !== '' ? $captured . ' — ' : '') . $hint];
    }

    /* --- Gabarit HTML --------------------------------------------------- */

    /** Enrobe le contenu dans un e-mail HTML responsive (tables + styles inline). */
    public static function wrap(string $title, string $bodyHtml, string $ctaUrl = '', string $ctaLabel = ''): string
    {
        $brand = esc_html(self::brand());
        $primary = sanitize_hex_color((string) Options::get('widget_primary_color')) ?: '#0b3d91';
        $accent = sanitize_hex_color((string) Options::get('widget_accent_color')) ?: '#e6b800';
        $site = esc_html((string) wp_parse_url(home_url(), PHP_URL_HOST));
        $year = esc_html((string) gmdate('Y'));

        $cta = '';
        if ($ctaUrl !== '') {
            $label = $ctaLabel !== '' ? $ctaLabel : __('Ouvrir', 'bem-lead-ai');
            $cta = '<tr><td style="padding:8px 28px 28px;">'
                . '<a href="' . esc_url($ctaUrl) . '" style="display:inline-block;background:' . esc_attr($primary) . ';color:#ffffff;'
                . 'text-decoration:none;font-weight:600;padding:11px 20px;border-radius:8px;font-size:14px;">'
                . esc_html($label) . '</a></td></tr>';
        }

        return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#f3f5f9;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f5f9;padding:24px 0;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e7f0;font-family:Arial,Helvetica,sans-serif;">'
            // En-tête.
            . '<tr><td style="background:' . esc_attr($primary) . ';padding:18px 28px;">'
            . '<span style="color:#ffffff;font-size:17px;font-weight:700;letter-spacing:.02em;">' . $brand . '</span>'
            . '<span style="display:inline-block;width:34px;height:3px;background:' . esc_attr($accent) . ';border-radius:2px;margin-left:10px;vertical-align:middle;"></span>'
            . '</td></tr>'
            // Titre.
            . '<tr><td style="padding:26px 28px 6px;"><h1 style="margin:0;font-size:19px;color:#10233f;">' . esc_html($title) . '</h1></td></tr>'
            // Corps.
            . '<tr><td style="padding:8px 28px 4px;font-size:14px;line-height:1.6;color:#3b4657;">' . $bodyHtml . '</td></tr>'
            . $cta
            // Pied.
            . '<tr><td style="padding:18px 28px;border-top:1px solid #eef1f6;font-size:12px;color:#98a2b3;">'
            . esc_html(sprintf(__('Notification envoyée par %s', 'bem-lead-ai'), self::brand())) . ' · ' . $site . ' · &copy; ' . $year
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    /** Rend une liste clé/valeur en HTML pour le corps d'une alerte. */
    public static function detailList(array $rows): string
    {
        $out = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:6px 0 4px;">';
        foreach ($rows as $label => $value) {
            $out .= '<tr>'
                . '<td style="padding:6px 12px 6px 0;color:#8a93a6;font-size:13px;white-space:nowrap;vertical-align:top;">' . esc_html((string) $label) . '</td>'
                . '<td style="padding:6px 0;color:#1f2430;font-size:13px;font-weight:600;">' . esc_html((string) $value) . '</td>'
                . '</tr>';
        }
        return $out . '</table>';
    }
}
