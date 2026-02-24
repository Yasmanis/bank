<?php

namespace App\Services\Scrapers;

use App\Contracts\LotteryScraperInterface;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class LaBolitaCubanaScraper implements LotteryScraperInterface
{
    protected string $url = 'https://www.labolitacubana.com/';

    public function parse(string $hourly): ?array
    {
        try {
            // 1. Obtener el HTML como STRING (Asegúrate de llamar a bodyHtml() al final)
            $html = Browsershot::url($this->url)
                ->setNodeBinary('/usr/bin/node')
                ->setNpmBinary('/usr/bin/npm')
                ->noSandbox()
                ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                ->waitUntilNetworkIdle()
                ->bodyHtml();

            // Verificación de seguridad: si por algo falló y no es un string, abortamos
            if (!is_string($html)) {
                return null;
            }

            $crawler = new Crawler($html);

            $suffix = ($hourly === 'am') ? 'Midday' : 'Night';

            // Extraer Fijo
            $h  = $this->getTextSafe($crawler, "#number{$suffix}1");

            $f1 = $this->getTextSafe($crawler, "#number{$suffix}2");
            $f2 = $this->getTextSafe($crawler, "#number{$suffix}3");

            // Extraer Corridos
            $c1 = $this->getTextSafe($crawler, "#numberCorrido{$suffix}1");
            $c2 = $this->getTextSafe($crawler, "#numberCorrido{$suffix}2");
            $c3 = $this->getTextSafe($crawler, "#numberCorrido{$suffix}3");
            $c4 = $this->getTextSafe($crawler, "#numberCorrido{$suffix}4");

            if (!is_numeric($h) || !is_numeric($f1) || !is_numeric($f2)) {
                return null;
            }

            return [
                'hundred' => $h,
                'fixed'   => $f1 . $f2,
                'r1'      => $c1 . $c2,
                'r2'      => $c3 . $c4,
            ];

        } catch (\Exception $e) {
            Log::error("LaBolitaCubanaScraper falló: " . $e->getMessage());
            return null;
        }
    }

    private function getTextSafe(Crawler $crawler, string $selector): ?string
    {
        $node = $crawler->filter($selector);
        return $node->count() > 0 ? trim($node->text()) : null;
    }
}
