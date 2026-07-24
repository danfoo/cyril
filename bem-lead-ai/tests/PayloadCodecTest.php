<?php

namespace BemLeadAi\Tests;

use BemLeadAi\Crm\PayloadCodec;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Transport du contenu des messages vers Perfex : compression gzip + base64url
 * derrière le préfixe « SIAZ1: ». C'est un contrat d'interop entre deux
 * codebases (plugin WordPress → module Perfex) ; une divergence perd le message.
 */
final class PayloadCodecTest extends TestCase
{
    /** Réplique du décodeur Perfex (Api::unpack_content) pour verrouiller le contrat. */
    private static function perfexUnpack(string $content): string
    {
        if (strncmp($content, 'SIAZ1:', 6) !== 0) {
            return $content;
        }
        $s = strtr(substr($content, 6), '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) {
            $s .= str_repeat('=', 4 - $pad);
        }
        $gz = (string) base64_decode($s);
        $plain = @gzdecode($gz);
        return $plain !== false ? $plain : $content;
    }

    private function longText(): string
    {
        // > 400 octets, avec accents / markdown / emoji / sauts de ligne.
        return str_repeat("Bonjour ! Voici les **modalités** d'admission à l'École : "
            . "frais, bourses d'excellence 🎓, et délais.\n", 20);
    }

    /* ---------- Compression conditionnelle ---------- */

    public function testShortContentIsSentInClear(): void
    {
        $short = 'Bonjour, je veux candidater au Master Finance.';
        // Sous le seuil : aucun préfixe, transmis tel quel.
        $this->assertSame($short, PayloadCodec::encode($short));
    }

    public function testLongContentIsCompressedWithPrefix(): void
    {
        $encoded = PayloadCodec::encode($this->longText());
        $this->assertStringStartsWith('SIAZ1:', $encoded);
        // La compression doit réellement raccourcir (tout l'intérêt).
        $this->assertLessThan(strlen($this->longText()), strlen($encoded));
    }

    public function testEncodedPayloadIsFirewallSafe(): void
    {
        $encoded = PayloadCodec::encode($this->longText());
        $body = substr($encoded, 6); // après « SIAZ1: »
        // Uniquement l'alphabet base64url : aucun +, /, = (déclencheurs de pare-feu).
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $body);
    }

    /* ---------- Round-trip encode/decode ---------- */

    #[DataProvider('contents')]
    public function testRoundTrip(string $original): void
    {
        $this->assertSame($original, PayloadCodec::decode(PayloadCodec::encode($original)));
    }

    public static function contents(): array
    {
        return [
            'court'          => ['Bonjour !'],
            'accents'        => ['Frais de scolarité, éligibilité à la bourse d’excellence.'],
            'long markdown'  => [str_repeat("- **Étape 1** : dossier\n- **Étape 2** : entretien\n", 30)],
            'emoji'          => [str_repeat('Félicitations 🎓🎉 pour votre candidature ! ', 20)],
            'vide'           => [''],
        ];
    }

    /* ---------- Contrat d'interop avec Perfex ---------- */

    public function testPerfexDecoderRecoversEncodedContent(): void
    {
        $original = $this->longText();
        $encoded = PayloadCodec::encode($original);
        // Le décodeur Perfex (réplique) doit retrouver le texte exact.
        $this->assertSame($original, self::perfexUnpack($encoded));
    }

    public function testPerfexDecoderPassesPlainContentThrough(): void
    {
        $plain = 'Message court non compressé.';
        $this->assertSame($plain, self::perfexUnpack(PayloadCodec::encode($plain)));
    }

    /* ---------- Garde-fous ---------- */

    public function testContentIsTruncatedToMaxLength(): void
    {
        $huge = str_repeat('A', 10000);
        $decoded = PayloadCodec::decode(PayloadCodec::encode($huge));
        $this->assertSame(6000, mb_strlen($decoded), 'tronqué à 6000 caractères');
    }

    public function testDecodeLeavesNonPrefixedContentUntouched(): void
    {
        $this->assertSame('texte brut', PayloadCodec::decode('texte brut'));
    }
}
