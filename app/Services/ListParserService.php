<?php

namespace App\Services;

class ListParserService
{
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
        $lines = preg_split('/\r\n|\n|\r/', $cleanText);
        $summary = [
            'fixed' => 0, 'hundred' => 0, 'parlet' => 0, 'terminal' => 0, 'range' => 0, 'total' => 0,
            'fixed_details' => [], 'hundred_details' => [], 'parlet_details' => []
        ];

        // Inicializar 00-99 en cero
        for ($i = 0; $i <= 99; $i++) {
            $summary['fixed_details'][str_pad($i, 2, '0', STR_PAD_LEFT)] = 0;
        }

        foreach ($lines as $line) {
            $line = strtolower(trim($line));
            if (empty($line) || !preg_match('/\d/', $line)) continue;

            // --- 1. PAREJAS (00-99-10 o parejas-10) ---
            if (preg_match('/(?:las\s+)?parejas[-_=>\s](\d+)/', $line, $matches) || preg_match('/00[-_]99[-_=>\s](\d+)/', $line, $matches)) {
                $amt = (int)$matches[1];
                foreach (['00','11','22','33','44','55','66','77','88','99'] as $p) {
                    $summary['fixed_details'][$p] += $amt;
                    $summary['fixed'] += $amt;
                    $summary['total'] += $amt;
                }
                continue;
            }

            // --- 2. TERMINALES (ter 7-10 o 07-97-10) ---
            if (preg_match('/(?:ter(?:minales)?|del)\s+(\d{1,2})\s+(?:al\s+97|[-_]\d{2})?[-_=>\s](\d+)/', $line, $matches)) {
                $lastDigit = substr($matches[1], -1);
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

            // --- 3. LÍNEAS / DÉCADAS (Los 70-10 o 70-79-10) ---
            if (preg_match('/(?:los|del)\s+(\d)0[-_=>\s](\d+)/', $line, $matches) || preg_match('/(\d)0[-_]\1[9][-_=>\s](\d+)/', $line, $matches)) {
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

            // --- 4. PARLET (38x70-10 o 38*70_10 o 38×70-10) ---
            if (preg_match('/(\d{1,2})[x*×](\d{1,2})[-_=>\s](\d+)/', $line, $matches)) {
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

            // --- 5. TRIPLETAS / FIJO-CORRIDO / NORMAL (50-10-20-30) ---
            if (preg_match('/^t?\s?(\d{2,3})[-_=>\s](\d+)(?:[-_=>\s](\d+))?(?:[-_=>\s](\d+))?/', $line, $matches)) {
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
                }
                continue;
            }
        }

        ksort($summary['fixed_details']);
        ksort($summary['hundred_details']);
        ksort($summary['parlet_details']);
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
        $summary = ['fixed' => 0, 'hundred' => 0, 'parlet' => 0, 'terminal' => 0, 'range' => 0];

        $winF = $win['fijo'] ?? null;
        $winH = $win['hundred'] ?? null;
        $winRunners = $win['runners'] ?? [];

        foreach ($lines as $line) {
            $line = strtolower(trim($line));
            if (empty($line) || !preg_match('/\d/', $line)) continue;

            // --- 1. PAREJAS ---
            if (preg_match('/(?:las\s+)?parejas[-_=>\s](\d+)/', $line, $matches) || preg_match('/00[-_]99[-_=>\s](\d+)/', $line, $matches)) {
                $amt = (int)$matches[1];
                if ($winF && in_array($winF, ['00','11','22','33','44','55','66','77','88','99'])) {
                    $results[] = $this->formatRes('fixed', "Pareja ($winF)", $amt);
                    $summary['fixed'] += $amt;
                }
                continue;
            }

            // --- 2. TERMINALES ---
            if (preg_match('/(?:ter(?:minales)?|del)\s+(\d{1,2})\s+(?:al\s+97|[-_]\d{2})?[-_=>\s](\d+)/', $line, $matches)) {
                $targetDigit = substr($matches[1], -1);
                $amt = (int)$matches[2];
                if ($winF && str_ends_with($winF, $targetDigit)) {
                    $results[] = $this->formatRes('terminal', "Ter. $targetDigit", $amt);
                    $summary['terminal'] += $amt;
                }
                continue;
            }

            // --- 3. LÍNEAS / RANGOS ---
            if (preg_match('/(?:los|del)\s+(\d)0[-_=>\s](\d+)/', $line, $matches) || preg_match('/(\d)0[-_]\1[9][-_=>\s](\d+)/', $line, $matches)) {
                $decade = $matches[1];
                $amt = (int)$matches[2];
                if ($winF && str_starts_with($winF, $decade)) {
                    $results[] = $this->formatRes('range', "Línea $decade"."0s", $amt);
                    $summary['range'] += $amt;
                }
                continue;
            }

            // --- 4. PARLET ---
            if ($winF && !empty($winRunners) && preg_match('/(\d{1,2})[x*×](\d{1,2})[-_=>\s](\d+)/', $line, $matches)) {
                $a = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $b = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $amt = (int)$matches[3];
                if (($a === $winF && in_array($b, $winRunners)) || ($b === $winF && in_array($a, $winRunners))) {
                    $results[] = $this->formatRes('parlet', "$a x $b", $amt);
                    $summary['parlet'] += $amt;
                }
                continue;
            }

            // --- 5. TRIPLETAS / NORMAL ---
            if (preg_match('/^t?\s?(\d{2,3})[-_=>\s](\d+)(?:[-_=>\s](\d+))?(?:[-_=>\s](\d+))?/', $line, $matches)) {
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

                if (in_array($num, $winRunners)) {
                    if ($amtC1 > 0) {
                        $results[] = $this->formatRes('fixed', "$num (C1)", $amtC1);
                        $summary['fixed'] += $amtC1;
                    }
                    if ($amtC2 > 0) {
                        $results[] = $this->formatRes('fixed', "$num (C2)", $amtC2);
                        $summary['fixed'] += $amtC2;
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
}
