<?php

namespace App\Services\Scrapers;

class LotteryConsensusService
{
    protected array $scrapers;

    public function __construct()
    {
        // Instanciamos los drivers
        $this->scrapers = [
//            new LaBolitaCubanaScraper(),
            new DirectorioCubanoScraper(),
            new TuBoliterosScraper()
        ];
    }

    public function getConsensusResult(string $hourly): ?array
    {
        $results = [];

        foreach ($this->scrapers as $scraper) {
            // Le pasamos si es am o pm al driver
            $data = $scraper->parse($hourly);

            if ($data) {
                $key = "{$data['hundred']}-{$data['fixed']}-{$data['r1']}-{$data['r2']}";
                $results[$key] = ($results[$key] ?? 0) + 1;
                $results[$key . '_data'] = $data;
            }
        }

        // Buscamos si alguna llave tiene 2 o más votos
        foreach ($results as $key => $count) {
            if (is_int($count) && $count >= 2) {
                return $results[$key . '_data'];
            }
        }

        return null; // No hubo consenso
    }
}
