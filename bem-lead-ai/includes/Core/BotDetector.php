<?php

namespace BemLeadAi\Core;

defined('ABSPATH') || exit;

/**
 * Détection de robots / scanners à partir du User-Agent, pour ne PAS créer
 * de leads à partir de trafic non humain (crawlers, monitors d'uptime,
 * bots de prévisualisation de liens, bibliothèques HTTP, headless browsers).
 *
 * Objectif : le tableau de bord ne compte que de vrais visiteurs.
 */
final class BotDetector
{
    /** Fragments de User-Agent typiques du trafic automatisé (minuscule). */
    private const SIGNATURES = [
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'bingpreview',
        'facebookexternalhit', 'facebot', 'ia_archiver', 'semrush', 'ahrefs',
        'mj12', 'dotbot', 'petalbot', 'yandex', 'baiduspider', 'duckduckbot',
        'applebot', 'headless', 'phantomjs', 'puppeteer', 'playwright',
        'python-requests', 'python-urllib', 'aiohttp', 'httpx', 'curl', 'wget',
        'go-http-client', 'okhttp', 'scrapy', 'node-fetch', 'axios', 'libwww',
        'httpclient', 'apache-httpclient', 'java/', 'jakarta', 'guzzle',
        'pingdom', 'uptimerobot', 'statuscake', 'gtmetrix', 'lighthouse',
        'pagespeed', 'monitis', 'newrelic', 'datadog', 'site24x7',
        'whatsapp', 'telegrambot', 'slackbot', 'discordbot', 'twitterbot',
        'linkedinbot', 'embedly', 'redditbot', 'pinterest', 'skypeuripreview',
        'vkshare', 'w3c_validator', 'chrome-lighthouse', 'google-inspectiontool',
        'petal', 'bytespider', 'gptbot', 'ccbot', 'claudebot', 'anthropic',
        'perplexity', 'amazonbot', 'dataforseo', 'zoominfobot', 'headlesschrome',
    ];

    /** True si la requête courante provient (très probablement) d'un robot. */
    public static function isBot(?string $userAgent = null): bool
    {
        $ua = $userAgent ?? (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $ua = trim($ua);

        // Un vrai navigateur envoie toujours un User-Agent non vide.
        if ($ua === '') {
            return true;
        }
        $ua = strtolower($ua);
        foreach (self::SIGNATURES as $sig) {
            if (str_contains($ua, $sig)) {
                return true;
            }
        }
        return false;
    }
}
