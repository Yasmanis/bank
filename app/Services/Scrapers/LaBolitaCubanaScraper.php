<?php

namespace App\Services\Scrapers;

use App\Contracts\LotteryScraperInterface;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LaBolitaCubanaScraper implements LotteryScraperInterface
{
    protected string $url = 'https://www.labolitacubana.com/';

    /**
     * @param string $hourly 'am' o 'pm'
     */
    public function parse(string $hourly): ?array
    {
        try {
            // 1. Realizar la petición
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'])
                ->get($this->url);

            if (!$response->successful()) return null;

            $crawler = new Crawler($response->body());
            dd($crawler);

            // 2. Definir el sufijo según el horario
            // AM -> Midday | PM -> Night
            $suffix = ($hourly === 'am') ? 'Midday' : 'Night';

            // 3. Extraer Centena y Fijo
            // Según tu HTML: n1=6, n2=6, n3=9 => hundred=6, fixed=69
            $h  = $crawler->filter("#number{$suffix}1")->text(null);
            dd($h);
            $f1 = $crawler->filter("#number{$suffix}2")->text(null);
            $f2 = $crawler->filter("#number{$suffix}3")->text(null);

            // 4. Extraer Corridos (Se forman uniendo los pares de bolas)
            // c1=6, c2=7, c3=2, c4=3 => runner1=67, runner2=23
            $c1 = $crawler->filter("#numberCorrido{$suffix}1")->text(null);
            $c2 = $crawler->filter("#numberCorrido{$suffix}2")->text(null);
            $c3 = $crawler->filter("#numberCorrido{$suffix}3")->text(null);
            $c4 = $crawler->filter("#numberCorrido{$suffix}4")->text(null);



            // 5. NUEVA VALIDACIÓN: Verificar que todos sean números
            // Si alguno contiene '?' o no es número, devolvemos null
            if (!is_numeric($h) || !is_numeric($f1) || !is_numeric($f2)) {
                Log::info("LaBolitaCubanaScraper: El sorteo $hourly aún no tiene resultados (mostrando '?')");
                return null;
            }


            return [
                'hundred' => trim($h),
                'fixed'   => trim($f1) . trim($f2),
                'r1'      => trim($c1) . trim($c2),
                'r2'      => trim($c3) . trim($c4),
            ];

        } catch (\Exception $e) {
            Log::warning("LaBolitaCubanaScraper falló: " . $e->getMessage());
            return null;
        }
    }
}
