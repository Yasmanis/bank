<?php

namespace App\Services;

use App\Dto\Bet\DetectedBet;
use App\Repositories\BankList\BankListRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListParserService
{
    private const PAREJAS = '/^(?:(?:las\s+)?pare\w*|(?:del\s+)?00\s+al\s+99|00-99|^p\b)\D+(?<amt1>\d+)(?:\D+(?<amt2>\d+))?(?:\D+(?<amt3>\d+))?$/iu';
    private const TERMINALES = '/^(?:(?:los\s+)?ter(?:min(?:al(?:es)?|ar)?)?\s*\d?(?<d1>\d)|(?:del\s+)?\d?(?<d2>\d)\s+al\s+\d?\k<d2>|t\s*[-]?\s*(?<d3>\d)|0(?<d4>\d)-9\k<d4>)\D+(?<amt1>\d+)(?:\D+(?<amt2>\d+))?(?:\D+(?<amt3>\d+))?$/iu';

    private const LINEAS = '/^(?:(?:(?:los|del|l|d|lineas?)\s*(?<dec1>\d)0(?:s)?|(?<dec2>\d)0\s*al\s*\k<dec2>9|(?<dec3>\d)0-\k<dec3>9)\D+(?<amt1>\d+)(?:\D+(?<amt2>\d+))?(?:\D+(?<amt3>\d+))?|(?<t_amt1>\d+)(?:\D+(?<t_amt2>\d+))?(?:\D+(?<t_amt3>\d+))?\D+todos\s+(?:los\s+)?(?<dec4>\d)0)$/iu';

    private const PARLET = '/^(?:p[- ])?(?<n1>\d{1,2})[x\*×](?<n2>\d{1,2})\D+(?<amt>\d+)$/iu';
    private const NORMAL = '/^(?:t|p)?\s?(?<list>\d{1,3}(?:[,\.]\d{1,3})*)\D+(?<amt>\d+)(?:\D+(?<c1>\d+))?(?:\D+(?<c2>\d+))?$/iu';

    protected BankListRepository $repository;

    public function __construct(BankListRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * ETAPA 1: Limpia el ruido técnico de WhatsApp (Metadatos)
     */
    public function cleanWhatsAppChat(string $text): string
    {
        // Definimos los pasos de limpieza
        $steps = [
            'normalizeLineBreaks',   // Corrige saltos de línea literales
            'stripWhatsAppMetadata', // Quita "[fecha, hora] Usuario: "
            'stripSystemMessages',   // Quita mensajes de cifrado, grupos, etc.
            'stripAttachments',      // Quita menciones a archivos adjuntos
            'stripComments',
            'stripEmptyLines',       // Elimina líneas en blanco sobrantes
        ];

        // Aplicamos cada paso secuencialmente
        return array_reduce($steps, function ($carry, $step) {
            return $this->$step($carry);
        }, $text);
    }

    /**
     * Convierte el texto "\n" literal en saltos de línea reales.
     */
    private function normalizeLineBreaks(string $text): string
    {
        return str_replace("\\n", "\n", $text);
    }

    /**
     * Elimina la cabecera de WhatsApp: "[1/2/26, 22:12:47] Jose Carlos SF: "
     */
    private function stripWhatsAppMetadata(string $text): string
    {
        return preg_replace('/^\[.*?] [^:]+: /m', '', $text);
    }

    /**
     * Elimina mensajes automáticos del sistema de WhatsApp.
     */
    private function stripSystemMessages(string $text): string
    {
        $patterns = [
            '/.*Messages and calls are end-to-end encrypted.*/i',
            '/.*You created group.*/i',
            '/.*changed the group description.*/i',
            '/.*joined using an invite link.*/i',
        ];

        return preg_replace($patterns, '', $text);
    }

    /**
     * Elimina las líneas que indican archivos adjuntos.
     */
    private function stripAttachments(string $text): string
    {
        return preg_replace('/.*attached:.*/i', '', $text);
    }

    /**
     * Elimina líneas vacías o que solo contienen espacios.
     */
    private function stripEmptyLines(string $text): string
    {
        // Elimina líneas vacías y espacios al inicio/final del string
        $text = preg_replace('/^[ \t]*[\r\n]+/m', '', $text);
        return trim($text);
    }

    private function stripComments(string $text): string
    {
        // Elimina todo lo que esté después de un # o texto sobrante al final de la línea
        return preg_replace('/[#].*$/m', '', $text);
    }

    /**
     * Calcula los TOTALES y guarda el rastro de las apuestas
     */
    public function calculateTotals(Collection $bets, string $fullText = ''): array
    {
        $normalBets = $bets->where('type', '!=', 'triplet')->where('type', '!=', 'error');
        $tripletBets = $bets->where('type', 'triplet');

        return [
            // Totales globales
            'fixed'   => (int)$normalBets->where('type', 'fixed')->sum('amount'),
            'hundred' => (int)$normalBets->where('type', 'hundred')->sum('amount'),
            'parlet'  => (int)$bets->where('type', 'parlet')->sum('amount'),
            'triplet' => (int)$tripletBets->sum('amount'),
            'runner1' => (int)$normalBets->sum('runner1'),
            'runner2' => (int)$normalBets->sum('runner2'),
            'total'   => (int)$bets->sum(fn($bet) => $bet->amount + $bet->runner1 + $bet->runner2),

            // Detalles para la vista rápida
            'fixed_details'   => $this->sumByNumber($normalBets->where('type', 'fixed'), 'amount'),
            'hundred_details' => $this->sumByNumber($normalBets->where('type', 'hundred'), 'amount'),
            'parlet_details'  => $this->sumByNumber($bets->where('type', 'parlet'), 'amount'),
            'triplet_details' => $this->sumByNumber($tripletBets, 'amount'),
            'runner1_details' => $this->sumByNumber($normalBets->where('runner1', '>', 0), 'runner1'),
            'runner2_details' => $this->sumByNumber($normalBets->where('runner2', '>', 0), 'runner2'),

            // --- IMPORTANTE PARA LA LIQUIDACIÓN ---
            // Guardamos todas las apuestas individuales para procesar premios después
            'bets' => $bets->where('type', '!=', 'error')->values()->toArray(),

            'not_processed' => $bets->where('type', 'error')->pluck('originalLine')->values()->toArray(),
            'full_text_cleaned' => $fullText
        ];
    }

    /**
     * Método auxiliar para evitar repetir código de suma y asegurar el formato
     */
    private function sumByNumber(Collection $items, string $field): array
    {
        return $items->groupBy('number')
            ->map(fn($group) => (int)$group->sum($field))
            ->sortKeys()
            ->toArray();
    }


    private function formatRes($type, $num, $amt): array
    {
        return ['type' => $type, 'number' => $num, 'amount' => $amt, 'isWinner' => true];
    }


    public function processAndStoreChat($user, $data)
    {
        return DB::transaction(function () use ($user, $data) {
            $cleanedText = $this->cleanWhatsAppChat($data['text']);
            $extraction = $this->extractBets($cleanedText);
            $bets = $extraction['bets'];
            $fullText = $extraction['full_text'];
            $errorLines = $bets->where('type', 'error')->pluck('originalLine');

            if ($errorLines->isNotEmpty()) {
                throw new \App\Exceptions\UnprocessedLinesException($errorLines->toArray());
            }

            $processedData = $this->calculateTotals($bets);
            return $this->repository->store([
                'user_id' => $user->id,
                'text' => $data['text'],
                'processed_text' => $processedData,
                'hourly' => $data['hourly']
            ]);
        });
    }

    /**
     * Motor Único de Extracción
     * Convierte texto plano en una Colección de objetos DetectedBet
     */
    public function extractBets(string $cleanText): array
    {
        $lines = explode("\n", $cleanText);
        $bets = collect();
        $finalLines = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            $lowerLine = strtolower($trimmedLine);

            if (empty($lowerLine) || !preg_match('/\d/', $lowerLine) || str_contains($lowerLine, 'attached:')) {
                continue;
            }

            $finalLines[] = $trimmedLine;
            $matched = false;

            // 1. PARLET (Máxima prioridad)
            if (preg_match(self::PARLET, $lowerLine, $m)) {
                $n1 = str_pad($m['n1'] ?? '', 2, '0', STR_PAD_LEFT);
                $n2 = str_pad($m['n2'] ?? '', 2, '0', STR_PAD_LEFT);
                $nums = [$n1, $n2]; sort($nums);
                $bets->push(new DetectedBet('parlet', $nums[0] . 'x' . $nums[1], (int)($m['amt'] ?? 0), originalLine: $trimmedLine));
                $matched = true;
            }
            // 2. TERMINALES
            elseif (preg_match(self::TERMINALES, $lowerLine, $m)) {
                $digit = ($m['d1'] ?? '') ?: ($m['d2'] ?? '') ?: ($m['d3'] ?? '') ?: ($m['d4'] ?? '');
                $amt = (int)($m['amt1'] ?? 0);
                $c1  = (int)($m['amt2'] ?? 0);
                $c2  = (int)($m['amt3'] ?? 0);
                $type = ($amt > 0 && $c1 > 0 && $c2 > 0) ? 'triplet' : 'fixed';

                for ($i = 0; $i <= 9; $i++) {
                    $bets->push(new DetectedBet($type, $i . $digit, $amt, $c1, $c2, $trimmedLine));
                }
                $matched = true;
            }
            // 3. PAREJAS (Ahora capturará "P-50" antes que el bloque normal)
            elseif (preg_match(self::PAREJAS, $lowerLine, $m)) {
                $amt = (int)($m['amt1'] ?? 0);
                $c1  = (int)($m['amt2'] ?? 0);
                $c2  = (int)($m['amt3'] ?? 0);
                $type = ($amt > 0 && $c1 > 0 && $c2 > 0) ? 'triplet' : 'fixed';

                for ($i = 0; $i <= 9; $i++) {
                    $bets->push(new DetectedBet($type, $i . $i, $amt, $c1, $c2, $trimmedLine));
                }
                $matched = true;
            }
            // 4. LÍNEAS
            elseif (preg_match(self::LINEAS, $lowerLine, $m)) {
                $decade = ($m['dec1'] ?? '') ?: ($m['dec2'] ?? '') ?: ($m['dec3'] ?? '') ?: ($m['dec4'] ?? '');
                $amt = (int)(($m['amt1'] ?? '') ?: ($m['t_amt1'] ?? 0));
                $c1  = (int)(($m['amt2'] ?? '') ?: ($m['t_amt2'] ?? 0));
                $c2  = (int)(($m['amt3'] ?? '') ?: ($m['t_amt3'] ?? 0));
                $type = ($amt > 0 && $c1 > 0 && $c2 > 0) ? 'triplet' : 'fixed';

                for ($i = 0; $i <= 9; $i++) {
                    $bets->push(new DetectedBet($type, $decade . $i, $amt, $c1, $c2, $trimmedLine));
                }
                $matched = true;
            }
            // 5. NORMAL / LISTAS / TRIPLETAS
            elseif (preg_match(self::NORMAL, $lowerLine, $m)) {
                $numbers = preg_split('/[,\.]/', $m['list'] ?? '');
                $amt = (int)($m['amt'] ?? 0);
                $c1  = (int)($m['c1'] ?? 0);
                $c2  = (int)($m['c2'] ?? 0);

                $type = ($amt > 0 && $c1 > 0 && $c2 > 0) ? 'triplet' : null;

                foreach ($numbers as $rawNum) {
                    $cleanNum = trim($rawNum);
                    if ($cleanNum === '') continue;
                    $num = str_pad($cleanNum, (strlen($cleanNum) > 2 ? 3 : 2), '0', STR_PAD_LEFT);
                    $finalType = $type ?: (strlen($num) === 3 ? 'hundred' : 'fixed');
                    $bets->push(new DetectedBet($finalType, $num, $amt, $c1, $c2, $trimmedLine));
                }
                $matched = true;
            }

            if (!$matched) {
                $bets->push(new DetectedBet('error', "ND", 0, 0, 0, $trimmedLine));
            }
        }

        return ['bets' => $bets, 'full_text' => implode("\n", $finalLines)];
    }


}
