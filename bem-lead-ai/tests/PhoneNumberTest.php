<?php

namespace BemLeadAi\Tests;

use BemLeadAi\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    /* ---------- normalize() ---------- */

    public function testNormalizeAddsDefaultDialCodeToLocalNumber(): void
    {
        // Le cas exact du bug signalé : numéro guinéen local à 9 chiffres.
        $this->assertSame('+224613063895', PhoneNumber::normalize('613063895', '+224'));
    }

    public function testNormalizeIsIdempotentOnInternationalFormats(): void
    {
        $this->assertSame('+224613063895', PhoneNumber::normalize('+224 613 06 38 95', '+224'));
        $this->assertSame('+224613063895', PhoneNumber::normalize('00224613063895', '+224'));
        // Déjà préfixé de l'indicatif mais sans le « + ».
        $this->assertSame('+224613063895', PhoneNumber::normalize('224613063895', '+224'));
    }

    public function testNormalizeStripsNationalTrunkZero(): void
    {
        // France : le 0 national saute au profit de l'indicatif.
        $this->assertSame('+33612345678', PhoneNumber::normalize('0612345678', '+33'));
    }

    public function testNormalizeWithoutDialCodeKeepsLocalNumber(): void
    {
        $this->assertSame('613063895', PhoneNumber::normalize('613063895', ''));
    }

    public function testNormalizeAcceptsDialCodeWithoutPlus(): void
    {
        $this->assertSame('+224613063895', PhoneNumber::normalize('613063895', '224'));
    }

    public function testNormalizeEmptyInput(): void
    {
        $this->assertSame('', PhoneNumber::normalize('', '+224'));
    }

    /* ---------- extract() / extractAndNormalize() ---------- */

    public function testExtractFindsBareNumberInSentence(): void
    {
        $this->assertSame(
            '+224613063895',
            PhoneNumber::extractAndNormalize('Bonjour, mon numéro est 613063895 merci', '+224')
        );
    }

    public function testExtractHandlesGroupedInternationalNumber(): void
    {
        $this->assertSame(
            '+224613063895',
            PhoneNumber::extractAndNormalize('Joignable au +224 613 06 38 95 svp', '+224')
        );
    }

    public function testExtractIgnoresCurrencyAmounts(): void
    {
        // Un montant en francs guinéens n'est pas un téléphone.
        $this->assertNull(PhoneNumber::extractAndNormalize('Les frais sont 15 000 000 GNF par an', '+224'));
        $this->assertNull(PhoneNumber::extractAndNormalize('Budget de 2 500 000 FCFA', '+224'));
    }

    public function testExtractReturnsNullWhenNoNumber(): void
    {
        $this->assertNull(PhoneNumber::extractAndNormalize('Bonjour je suis intéressé par vos formations', '+224'));
    }

    public function testExtractRejectsTooShortSequences(): void
    {
        // 5 chiffres : ni un téléphone, ni assez long (ex. un code postal).
        $this->assertNull(PhoneNumber::extractAndNormalize('code 12345 fin', '+224'));
    }

    public function testExtractRejectsRepeatedDigits(): void
    {
        $this->assertNull(PhoneNumber::extractAndNormalize('test 00000000 test', '+224'));
    }
}
