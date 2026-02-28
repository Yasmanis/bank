<?php

namespace App\Services\Scrapers;

use App\Contracts\LotteryScraperInterface;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DirectorioCubanoScraper implements LotteryScraperInterface
{
    protected string $url = 'https://www.directoriocubano.info/bolita/';

    public function parse(string $hourly): ?array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'])
                ->get($this->url);

            if (!$response->successful()) return null;

            $crawler = new Crawler($response->body());

            // 1. Identificar el contenedor según el horario
            $containerId = ($hourly === 'am') ? '#loteria-tarde' : '#loteria-noche';
            $container = $crawler->filter($containerId);

            if ($container->count() === 0) return null;

            // 2. VERIFICACIÓN CRÍTICA: ¿Son resultados de HOY?
            // Si existe la clase 'loteria-fallback-notice', son resultados viejos.
            if ($container->filter('.loteria-fallback-notice')->count() > 0) {
                Log::info("DirectorioCubanoScraper: Sorteo $hourly aún no disponible (mostrando histórico).");
                return null;
            }

            $fechaText = $container->filter('.fecha-loteria')->text('');
            $hoyEnEspanol = $this->getFechaEnEspanol(); // Método auxiliar abajo

            if (!str_contains(strtolower($fechaText), strtolower($hoyEnEspanol))) {
                Log::info("DirectorioCubanoScraper: La fecha del sitio no coincide con hoy.");
                return null;
            }

            // 3. Extraer datos de la tabla
            // La estructura es: Fireball(0), Fijo(1), Corrido(2), Suma(3), Corrido 1(4), Corrido 2(5)
            $tableRow = $container->filter('.loteria-tabla table tbody tr td');

            if ($tableRow->count() < 6) return null;

            $fijoFull = trim($tableRow->eq(1)->text()); // Ejemplo: "669"
            $c1 = trim($tableRow->eq(4)->text());       // Ejemplo: "67"
            $c2 = trim($tableRow->eq(5)->text());       // Ejemplo: "23"

            // 4. Validar que tengamos números y no "?"
            if (!is_numeric($fijoFull) || strlen($fijoFull) < 3) {
                return null;
            }

            return [
                'hundred' => substr($fijoFull, 0, 1), // Primer dígito: 6
                'fixed'   => substr($fijoFull, 1, 2), // Últimos dos: 69
                'r1'      => str_pad($c1, 2, '0', STR_PAD_LEFT),
                'r2'      => str_pad($c2, 2, '0', STR_PAD_LEFT),
            ];

        } catch (\Exception $e) {
            Log::warning("DirectorioCubanoScraper falló: " . $e->getMessage());
            return null;
        }
    }

    private function getFechaEnEspanol() {
        $meses = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
        return now()->day . " de " . $meses[now()->month - 1];
    }
}
