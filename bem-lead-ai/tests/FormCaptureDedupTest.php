<?php

namespace BemLeadAi\Tests;

use BemLeadAi\Integrations\FormCapture;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Vérifie la résolution d'identité de FormCapture::ingest() :
 * email connu → lead de chat (cookie) → création. But : une même personne ne
 * doit jamais produire deux fiches selon l'ordre chat / formulaire.
 */
final class FormCaptureDedupTest extends TestCase
{
    private ReflectionMethod $ingest;
    private FormCapture $capture;

    protected function setUp(): void
    {
        \FakeLeadStore::reset();
        $_COOKIE = [];
        $this->capture = new FormCapture();
        $this->ingest = new ReflectionMethod($this->capture, 'ingest');
        $this->ingest->setAccessible(true);
    }

    private function ingest(?string $email, ?string $phone, ?string $name, ?string $formation, string $source, string $formTitle = ''): void
    {
        $this->ingest->invoke($this->capture, $email, $phone, $name, $formation, $source, $formTitle);
    }

    public function testChatThenFormDoesNotCreateDuplicate(): void
    {
        // Le widget a créé un lead de chat anonyme et posé le cookie de session.
        $chat = \FakeLeadStore::insert(['session_id' => 'web_abc123', 'prenom' => 'Anonyme']);
        $_COOKIE['bem_lead_session'] = 'web_abc123';

        // La même personne soumet ensuite un formulaire avec un email inédit.
        $this->ingest('marie@bem.gn', '613063895', 'Marie', 'Master Finance', 'gravityforms', 'Candidature Master');

        $this->assertCount(1, \FakeLeadStore::$leads, 'un seul lead, pas de doublon');
        $lead = \FakeLeadStore::$leads[$chat->id];
        $this->assertSame('marie@bem.gn', $lead->email, 'email rattaché au lead de chat existant');
        $this->assertSame('+224613063895', $lead->phone, 'téléphone normalisé à l\'indicatif par défaut');
        $this->assertSame('Candidature Master', $lead->source_form, 'formulaire d\'origine tracé');
    }

    public function testFormWithoutCookieCreatesLead(): void
    {
        $this->ingest('paul@bem.gn', null, 'Paul', 'Bachelor Marketing', 'wpforms', 'Brochure');

        $this->assertCount(1, \FakeLeadStore::$leads);
        $lead = array_values(\FakeLeadStore::$leads)[0];
        $this->assertSame('paul@bem.gn', $lead->email);
    }

    public function testKnownEmailStaysCanonicalIdentity(): void
    {
        // Fiche déjà connue par email + chat anonyme distinct en cours.
        $known = \FakeLeadStore::insert(['session_id' => 'form_old', 'email' => 'lea@bem.gn', 'prenom' => 'Léa', 'consent' => 1]);
        \FakeLeadStore::insert(['session_id' => 'web_xyz']);
        $_COOKIE['bem_lead_session'] = 'web_xyz';

        $this->ingest('lea@bem.gn', null, 'Léa', 'MBA', 'contactform7', 'Contact');

        // L'email prime comme identité : le formulaire s'applique à la fiche connue.
        $this->assertSame('lea@bem.gn', \FakeLeadStore::$leads[$known->id]->email);
        $this->assertSame('MBA', \FakeLeadStore::$leads[$known->id]->formation_interet);
    }

    public function testOrphanCookieFallsBackToCreation(): void
    {
        // Cookie présent mais session expirée / lead supprimé.
        $_COOKIE['bem_lead_session'] = 'web_ghost';

        $this->ingest('tom@bem.gn', '620000000', 'Tom', null, 'ninjaforms', 'Inscription');

        $this->assertCount(1, \FakeLeadStore::$leads);
        $lead = array_values(\FakeLeadStore::$leads)[0];
        $this->assertSame('tom@bem.gn', $lead->email);
        $this->assertSame('+224620000000', $lead->phone);
    }

    public function testSubmissionWithoutContactIsIgnored(): void
    {
        // Ni email ni téléphone : rien d'exploitable, aucun lead.
        $this->ingest(null, null, 'Sans Contact', 'Master', 'wpforms', 'Sondage');

        $this->assertCount(0, \FakeLeadStore::$leads);
    }

    /* ---------- Capture externe (site non-WordPress) : captureLead() ---------- */

    public function testExternalCaptureLinksToChatBySessionId(): void
    {
        // Site non-WordPress : la session vient du session_id du widget (pas d'un
        // cookie). On rattache au lead de chat existant plutôt qu'un doublon.
        $chat = \FakeLeadStore::insert(['session_id' => 'web_ext', 'prenom' => 'Anonyme']);

        $id = $this->capture->captureLead('sara@bem.gn', '613063895', 'Sara', 'MBA', 'embed', 'Candidature', 'web_ext');

        $this->assertSame($chat->id, $id, 'même lead que le chat, pas de doublon');
        $this->assertCount(1, \FakeLeadStore::$leads);
        $this->assertSame('+224613063895', \FakeLeadStore::$leads[$chat->id]->phone);
    }

    public function testExternalCaptureCreatesLeadWithoutSession(): void
    {
        $id = $this->capture->captureLead('yaya@bem.gn', null, 'Yaya', null, 'embed', 'Brochure', null);

        $this->assertNotNull($id);
        $this->assertCount(1, \FakeLeadStore::$leads);
        $this->assertSame('yaya@bem.gn', \FakeLeadStore::$leads[$id]->email);
    }

    public function testExternalCaptureReturnsNullWithoutContact(): void
    {
        $id = $this->capture->captureLead(null, null, 'Anonyme', 'Master', 'embed', 'Sondage', 'web_x');

        $this->assertNull($id);
        $this->assertCount(0, \FakeLeadStore::$leads);
    }

    /* ---------- Attribution de campagne (UTM) ---------- */

    public function testCaptureStoresUtmAttribution(): void
    {
        $utm = ['source' => 'facebook', 'medium' => 'cpc', 'campaign' => 'rentree2026'];
        $id = $this->capture->captureLead('faty@bem.gn', null, 'Faty', null, 'embed', 'Pub', null, $utm);

        $lead = \FakeLeadStore::$leads[$id];
        $this->assertSame('facebook', $lead->utm_source);
        $this->assertSame('cpc', $lead->utm_medium);
        $this->assertSame('rentree2026', $lead->utm_campaign);
    }

    public function testUtmIsFirstTouchAndNotOverwritten(): void
    {
        // Lead arrivé via Facebook, en cours de chat.
        $chat = \FakeLeadStore::insert(['session_id' => 'web_ft', 'utm_source' => 'facebook', 'utm_campaign' => 'rentree']);

        // Il soumet ensuite un formulaire avec une autre UTM (dernière touche) :
        // l'attribution d'origine (Facebook) ne doit PAS être écrasée.
        $this->capture->captureLead('ft@bem.gn', null, 'FT', null, 'embed', 'Form', 'web_ft', [
            'source' => 'google', 'medium' => 'organic', 'campaign' => 'autre',
        ]);

        $lead = \FakeLeadStore::$leads[$chat->id];
        $this->assertSame('facebook', $lead->utm_source, 'la 1re campagne est conservée');
        $this->assertSame('rentree', $lead->utm_campaign);
        // Le champ vide au départ (medium) peut, lui, être complété.
        $this->assertSame('organic', $lead->utm_medium);
    }
}
