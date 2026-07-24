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
}
