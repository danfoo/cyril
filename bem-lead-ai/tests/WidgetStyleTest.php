<?php

namespace BemLeadAi\Tests;

use BemLeadAi\Core\WidgetStyle;
use PHPUnit\Framework\TestCase;

/**
 * CSS de design du widget — source unique partagée par l'affichage WordPress et
 * l'embarquement inter-sites. Un site externe récupérait le widget mais pas ses
 * couleurs tant que ce CSS n'était pas exposé côté config.
 */
final class WidgetStyleTest extends TestCase
{
    protected function setUp(): void
    {
        \FakeLeadStore::reset();
    }

    public function testDarkenReducesEachChannel(): void
    {
        // #0b3d91 assombri de 18 % (comme --bem-primary-dark).
        $this->assertSame('#093277', WidgetStyle::darken('#0b3d91', 0.18));
    }

    public function testDarkenExpandsShorthandHex(): void
    {
        $this->assertSame('#000000', WidgetStyle::darken('#000', 0.5));
        $this->assertSame('#808080', WidgetStyle::darken('#fff', 0.5));
    }

    public function testDarkenFallsBackOnInvalidHex(): void
    {
        $this->assertSame('#082a66', WidgetStyle::darken('pas-une-couleur', 0.2));
    }

    public function testCssExposesDesignVariables(): void
    {
        \FakeLeadStore::$options['widget_primary_color'] = '#123456';
        \FakeLeadStore::$options['widget_accent_color'] = '#abcdef';

        $css = WidgetStyle::css();

        $this->assertStringContainsString('--bem-primary:#123456', $css);
        $this->assertStringContainsString('--bem-accent:#abcdef', $css);
        $this->assertStringContainsString('.bem-widget{', $css);
    }

    public function testCssUsesFallbackWhenUnset(): void
    {
        // Aucune couleur configurée → valeurs par défaut de la marque.
        $css = WidgetStyle::css();
        $this->assertStringContainsString('--bem-primary:#0b3d91', $css);
    }
}
