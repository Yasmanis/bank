<?php

namespace App\Services\Scrapers;

use App\Contracts\LotteryScraperInterface;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TuBoliterosScraper implements LotteryScraperInterface
{
    protected string $url = 'https://tuboliteros.com/loterias/florida';

    public function parse(string $hourly): ?array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'])
                ->get($this->url);

            if (!$response->successful()) return null;

            $crawler = new Crawler($response->body());

            // 1. Determinar el alt de la imagen de tiempo
            // AM -> tarde (Afternoon) | PM -> noche (Evening)
            $timeAlt = ($hourly === 'am') ? 'tarde' : 'noche';

            // 2. Buscar el contenedor principal (la "card") que tiene la imagen de tiempo
            // Filtramos por la imagen y buscamos el contenedor padre que agrupa todo
            $card = $crawler->filter("img[alt='{$timeAlt}']")->closest('.rounded-lg');

            if ($card->count() === 0) {
                Log::info("TuBoliterosScraper: No se encontró el sorteo de la {$timeAlt}");
                return null;
            }

            // 3. Extraer Pick 3 (Fijo y Centena)
            // Buscamos el div que está justo después de la imagen con alt="pick3-logo"
            $pick3Raw = $card->filter("img[alt='pick3-logo'] + div")->text(null);

            // 4. Extraer Pick 4 (Corridos)
            // Buscamos el div que está justo después de la imagen con alt="pick4-logo"
            $pick4Raw = $card->filter("img[alt='pick4-logo'] + div")->text(null);

            // 5. Validaciones de datos
            if (!$pick3Raw || !$pick4Raw || !is_numeric($pick3Raw) || !is_numeric($pick4Raw)) {
                return null;
            }

            // Según tu ejemplo: Pick3 "007" y Pick4 "1347"
            return [
                'hundred' => substr($pick3Raw, 0, 1),               // "0"
                'fixed'   => substr($pick3Raw, 1, 2),               // "07"
                'r1'      => substr($pick4Raw, 0, 2),               // "13"
                'r2'      => substr($pick4Raw, 2, 2),               // "47"
            ];

        } catch (\Exception $e) {
            Log::warning("TuBoliterosScraper error: " . $e->getMessage());
            return null;
        }
    }
}
