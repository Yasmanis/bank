<?php

namespace App\Services;

use App\Repositories\BankList\BankListRepository;
use Illuminate\Support\Facades\DB;

class ListParserService
{
    private const PAREJAS = '/(?:las\s+)?parejas[- ](\d+)/';
    private const PAREJAS2 = '/00-99[- ](\d+)/';
    private const TERMINALES = '/(?:ter(?:minales)?|del)\s+0?(\d{1})\s+(?:al\s+9\1|[-]\d{2})?[- ](\d+)/';
    private const LINEAS = '/(?:los|del)\s+(\d)0[- ](\d+)/';
    private const LINEAS2 = '/(\d)0[-]\1[9][- ](\d+)/';
    private const PARLET = '/(\d{1,2})x(\d{1,2})[- ](\d+)/';
    private const NORMAL = '/^t?\s?(\d{2,3})\D+(\d+)(?:\D+(\d+))?(?:\D+(\d+))?/';

    /**
     * ETAPA 1: Limpia el ruido técnico de WhatsApp (Metadatos)
     */
    public function cleanWhatsAppChat(string $text): string
    {
        // Eliminar metadatos: "[1/2/26, 22:12:47] Nombre de Usuario: "
        $text = preg_replace('/^\[\d+\/\d+\/\d+, \d+:\d+:\d+\] [^:]+: /m', '', $text);

        $systemPatterns = [
            '/.*Messages and calls are end-to-end encrypted.*/i',
            '/.*You created group.*/i',
            '/.*attached:.*/i',
            '/.*changed the group description.*/i',
            '/.*joined using an invite link.*/i',
            '/^[\s]*$/m' // Líneas vacías
        ];

        return preg_replace($systemPatterns, '', $text);
    }

    /**
     * Calcula los TOTALES (Ventas/Riesgo)
     */
    public function calculateTotals(string $cleanText): array
    {
        $lines = explode("\n", $cleanText);
        $summary = [
            'fixed' => 0,
            'hundred' => 0,
            'parlet' => 0,
            'terminal' => 0,
            'range' => 0,
            'runner1' => 0,
            'runner2' => 0,
            'total' => 0,
            'fixed_details' => [],
            'hundred_details' => [],
            'parlet_details' => [],
            'runner1_details' => [],
            'runner2_details' => [],
        ];

        // Inicializar fixed_details
        for ($i = 0; $i <= 99; $i++) {
            $num = str_pad($i, 2, '0', STR_PAD_LEFT);
            $summary['fixed_details'][$num] = 0;
        }

        foreach ($lines as $line) {
            $line = strtolower(trim($line));
            if (empty($line) || !preg_match('/\d/', $line)) continue;

            // --- PAREJAS ---
            if (preg_match(self::PAREJAS, $line, $matches) || preg_match(self::PAREJAS2, $line, $matches)) {
                $amt = (int)$matches[1];
                for ($i = 0; $i <= 9; $i++) {
                    $num = $i . $i; // Números pares: 00,11,22,...99
                    $summary['fixed_details'][$num] += $amt;
                    $summary['fixed'] += $amt;
                    $summary['total'] += $amt;
                }
                continue;
            }

            // --- TERMINALES ---
            if (preg_match(self::TERMINALES, $line, $matches)) {
                $lastDigit = $matches[1];
                $amt = (int)$matches[2];
                for ($i = 0; $i <= 9; $i++) {
                    $num = $i . $lastDigit;
                    $summary['fixed_details'][$num] += $amt;
                    $summary['fixed'] += $amt;
                    $summary['total'] += $amt;
                }
                $summary['terminal'] += ($amt * 10);
                continue;
            }

            // --- LÍNEAS ---
            if (preg_match(self::LINEAS, $line, $matches) || preg_match(self::LINEAS2, $line, $matches)) {
                $decade = $matches[1];
                $amt = (int)$matches[2];
                for ($i = 0; $i <= 9; $i++) {
                    $num = $decade . $i;
                    $summary['fixed_details'][$num] += $amt;
                    $summary['fixed'] += $amt;
                    $summary['total'] += $amt;
                }
                $summary['range'] += ($amt * 10);
                continue;
            }

            // --- PARLET ---
            if (preg_match(self::PARLET, $line, $matches)) {
                $n1 = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $n2 = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $amt = (int)$matches[3];
                $pair = [$n1, $n2]; sort($pair);
                $key = $pair[0] . 'x' . $pair[1];
                $summary['parlet'] += $amt;
                $summary['total'] += $amt;
                $summary['parlet_details'][$key] = ($summary['parlet_details'][$key] ?? 0) + $amt;
                continue;
            }

            // --- TRIPLETAS / NORMAL ---
            if (preg_match(self::NORMAL, $line, $matches)) {
                $num = str_pad($matches[1], (strlen($matches[1]) > 2 ? 3 : 2), '0', STR_PAD_LEFT);
                $amtFijo = (int)$matches[2];
                $amtC1 = isset($matches[3]) ? (int)$matches[3] : 0;
                $amtC2 = isset($matches[4]) ? (int)$matches[4] : 0;

                if (strlen($num) === 3) {
                    $summary['hundred'] += $amtFijo;
                    $summary['hundred_details'][$num] = ($summary['hundred_details'][$num] ?? 0) + $amtFijo;
                    $summary['total'] += $amtFijo;
                } else {
                    $summary['fixed'] += $amtFijo;
                    $summary['fixed_details'][$num] += $amtFijo;
                    $summary['total'] += ($amtFijo + $amtC1 + $amtC2);

                    // Sumar C1 y C2 por separado
                    if ($amtC1 > 0) {
                        $summary['runner1'] += $amtC1;
                        $summary['runner1_details'][$num] = ($summary['runner1_details'][$num] ?? 0) + $amtC1;
                    }
                    if ($amtC2 > 0) {
                        $summary['runner2'] += $amtC2;
                        $summary['runner2_details'][$num] = ($summary['runner2_details'][$num] ?? 0) + $amtC2;
                    }
                }
                continue;
            }
        }

        ksort($summary['fixed_details']);
        ksort($summary['hundred_details']);
        ksort($summary['parlet_details']);
        ksort($summary['runner1_details']);
        ksort($summary['runner2_details']);
        $summary['fixed_details'] = array_filter($summary['fixed_details']);

        return $summary;
    }


    /**
     * Calcula los GANADORES (Winners)
     */
    public function calculateWinners(string $cleanText, array $win): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $cleanText);
        $results = [];
        $summary = [
            'fixed' => 0,
            'hundred' => 0,
            'parlet' => 0,
            'terminal' => 0,
            'range' => 0,
            'runner1' => 0, // Runner 1
            'runner2' => 0, // Runner 2
        ];

        $winF = $win['fijo'] ?? null;
        $winH = $win['hundred'] ?? null;
        $winRunners1 = $win['runners1'] ?? [];
        $winRunners2 = $win['runners2'] ?? [];

        foreach ($lines as $line) {
            $line = strtolower(trim($line));
            if (empty($line) || !preg_match('/\d/', $line)) continue;

            // --- 1. PAREJAS ---
            if (preg_match(self::PAREJAS, $line, $matches) || preg_match(self::PAREJAS2, $line, $matches)) {
                $amt = (int)$matches[1];
                if ($winF && in_array($winF, ['00','11','22','33','44','55','66','77','88','99'])) {
                    $results[] = $this->formatRes('fixed', "Pareja ($winF)", $amt);
                    $summary['fixed'] += $amt;
                }
                continue;
            }

            // --- 2. TERMINALES ---
            if (preg_match(self::TERMINALES, $line, $matches)) {
                $targetDigit = $matches[1];
                $amt = (int)$matches[2];
                if ($winF && str_ends_with($winF, $targetDigit)) {
                    $results[] = $this->formatRes('terminal', "Ter. $targetDigit", $amt);
                    $summary['terminal'] += $amt;
                }
                continue;
            }

            // --- 3. LÍNEAS / RANGOS ---
            if (preg_match(self::LINEAS, $line, $matches) || preg_match(self::LINEAS2, $line, $matches)) {
                $decade = $matches[1];
                $amt = (int)$matches[2];
                if ($winF && str_starts_with($winF, $decade)) {
                    $results[] = $this->formatRes('range', "Línea {$decade}0s", $amt);
                    $summary['range'] += $amt;
                }
                continue;
            }

            // --- 4. PARLET ---
            if (preg_match(self::PARLET, $line, $matches)) {
                $a = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $b = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $amt = (int)$matches[3];

                if ($winF && ($a === $winF || $b === $winF)) {
                    $results[] = $this->formatRes('parlet', "$a x $b", $amt);
                    $summary['parlet'] += $amt;
                }

                if (in_array($a, $winRunners1) || in_array($b, $winRunners1)) {
                    $results[] = $this->formatRes('runner1', "$a x $b", $amt);
                    $summary['runner1'] += $amt;
                }

                if (in_array($a, $winRunners2) || in_array($b, $winRunners2)) {
                    $results[] = $this->formatRes('runner2', "$a x $b", $amt);
                    $summary['runner2'] += $amt;
                }

                continue;
            }

            // --- 5. TRIPLETAS / NORMAL ---
            if (preg_match(self::NORMAL, $line, $matches)) {
                $num = str_pad($matches[1], (strlen($matches[1]) > 2 ? 3 : 2), '0', STR_PAD_LEFT);
                $amtFijo = (int)$matches[2];
                $amtC1 = isset($matches[3]) ? (int)$matches[3] : 0;
                $amtC2 = isset($matches[4]) ? (int)$matches[4] : 0;

                if ($winF && strlen($num) === 2 && $num === $winF) {
                    $results[] = $this->formatRes('fixed', $num, $amtFijo);
                    $summary['fixed'] += $amtFijo;
                } elseif ($winH && strlen($num) === 3 && $num === $winH) {
                    $results[] = $this->formatRes('hundred', $num, $amtFijo);
                    $summary['hundred'] += $amtFijo;
                }

                // runners separados
                if (in_array($num, $winRunners1)) {
                    foreach ([$amtFijo, $amtC1, $amtC2] as $amt) {
                        if ($amt > 0) {
                            $results[] = $this->formatRes('runner1', $num, $amt);
                            $summary['runner1'] += $amt;
                        }
                    }
                }

                if (in_array($num, $winRunners2)) {
                    foreach ([$amtFijo, $amtC1, $amtC2] as $amt) {
                        if ($amt > 0) {
                            $results[] = $this->formatRes('runner2', $num, $amt);
                            $summary['runner2'] += $amt;
                        }
                    }
                }

                continue;
            }
        }

        return ['results' => $results, 'summary' => array_filter($summary)];
    }


    private function formatRes($type, $num, $amt): array
    {
        return ['type' => $type, 'number' => $num, 'amount' => $amt, 'isWinner' => true];
    }


    public function processAndStoreChat($user, $data)
    {
        return DB::transaction(function () use ($user, $data) {
            $cleanedText = $this->cleanWhatsAppChat($data['text']);
            $processedData = $this->calculateTotals($cleanedText);
            $bankListRepository = new BankListRepository();

            return $bankListRepository->store([
                'user_id' => $user->id,
                'created_by' => $user->id,
                'text' => $data['text'],
                'processed_text' => $processedData,
                'hourly' => $data['hourly']
            ]);
        });
    }


}
