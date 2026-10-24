<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Support\MarcacaoMailCopy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarcacaoMailCopyAlertsBoxTest extends TestCase
{
    #[Test]
    public function alerts_box_returns_null_when_client_has_no_alerts(): void
    {
        $client = new Client([
            'allergies' => null,
            'contraindications' => '  ',
        ]);

        $this->assertNull(MarcacaoMailCopy::alertsBox($client));
        $this->assertNull(MarcacaoMailCopy::alertsBox(null));
    }

    #[Test]
    public function alerts_box_renders_red_html_with_allergies_and_contraindications(): void
    {
        $client = new Client([
            'allergies' => "Alergia a PPD\nSensibilidade a amónia",
            'contraindications' => 'Evitar oxidante alto',
        ]);

        $box = MarcacaoMailCopy::alertsBox($client);

        $this->assertNotNull($box);
        $html = (string) $box;
        $this->assertStringContainsString('Alertas', $html);
        $this->assertStringContainsString('bgcolor="#fef2f2"', $html);
        $this->assertStringContainsString('border-radius:4px', $html);
        $this->assertStringContainsString('#991b1b', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Alergia a PPD', $html);
        $this->assertStringContainsString('<br />', $html);
        $this->assertStringContainsString('Sensibilidade a amónia', $html);
        $this->assertStringContainsString('Evitar oxidante alto', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    #[Test]
    public function alerts_box_can_omit_title(): void
    {
        $client = new Client([
            'allergies' => 'Alergia a PPD',
        ]);

        $html = (string) MarcacaoMailCopy::alertsBox($client, withTitle: false, withBorder: false);

        $this->assertStringContainsString('Alergia a PPD', $html);
        $this->assertStringNotContainsString('>Alertas<', $html);
        $this->assertStringContainsString('border:0;', $html);
        $this->assertStringContainsString('border-radius:4px', $html);
    }

    #[Test]
    public function alerts_box_escapes_html_in_alert_text(): void
    {
        $client = new Client([
            'allergies' => '<b>risco</b> & "teste"',
        ]);

        $html = (string) MarcacaoMailCopy::alertsBox($client);

        $this->assertStringContainsString('&lt;b&gt;risco&lt;/b&gt;', $html);
        $this->assertStringContainsString('&amp;', $html);
        $this->assertStringNotContainsString('<b>risco</b>', $html);
    }
}
