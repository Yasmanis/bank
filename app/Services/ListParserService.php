<?php

namespace App\Services;

use App\Dto\Bet\DetectedBet;
use App\Repositories\BankList\BankListRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListParserService
{
    // Parejas: Soporta "las parejas 100", "00-99 con 50", etc.
    private const PAREJAS = '/^(?:(?:las\s+)?pareja[as]?|(?:del\s+)?00\s+al\s+99|00-99)\D+(?<amt1>\d+)(?:\D+(?<amt2>\d+))?(?:\D+(?<amt3>\d+))?$/i';

    private const TERMINALES = '/^(?:(?:del\s+)?\d?(?<d1>\d)\s+al\s+\d?\k<d1>|ter(?:min(?:al(?:es)?|ar)?)?\s*\d?(?<d2>\d)|t\s*[-]?\s*(?<d3>\d)|\d?(?<d4>\d)-\d?\k<d4>)\D+(?<amt>\d+)$/i';

    private const LINEAS = '/^(?:(?:(?:los|del|l|d|lineas?)\s*(?<dec1>\d)0(?:s)?|(?<dec2>\d)0\s*al\s*\k<dec2>9|(?<dec3>\d)0-\k<dec3>9)\D+(?<amt1>\d+)|(?<amt2>\d+)\D+todos\s+(?:los\s+)?(?<dec4>\d)0)$/i';

    private const PARLET = '/^(?:p[- ])?(?<n1>\d{1,2})[x\*](?<n2>\d{1,2})\D+(?<amt>\d+)$/i';

    private const NORMAL = '/^(?:t|p)?\s?(?<num>\d{1,3})\D+(?<amt>\d+)(?:\D+(?<c1>\d+))?(?:\D+(?<c2>\d+))?$/i';

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
     * Calcula los TOTALES (Ventas/Riesgo)
     */
    /**
     * Calcula los TOTALES (Ventas/Riesgo) basándose en la colección de apuestas
     */
    public function calculateTotals(Collection $bets): array
    {
        return [
            // Totales globales (Simples y directos)
            'fixed'   => (int) $bets->where('type', 'fixed')->sum('amount'),
            'hundred' => (int) $bets->where('type', 'hundred')->sum('amount'),
            'parlet'  => (int) $bets->where('type', 'parlet')->sum('amount'),
            'runner1' => (int) $bets->sum('runner1'),
            'runner2' => (int) $bets->sum('runner2'),
            'total'   => (int) $bets->sum(fn($bet) => $bet->amount + $bet->runner1 + $bet->runner2),

            // Detalles (Agrupamos por número y sumamos)
            // Usamos sortKeys() para que siempre salgan en orden (00, 01, 02...)
            'fixed_details'   => $this->sumByNumber($bets->where('type', 'fixed'), 'amount'),
            'hundred_details' => $this->sumByNumber($bets->where('type', 'hundred'), 'amount'),
            'parlet_details'  => $this->sumByNumber($bets->where('type', 'parlet'), 'amount'),

            // Detalles de corridas (solo si el monto es > 0)
            'runner1_details' => $this->sumByNumber($bets->where('runner1', '>', 0), 'runner1'),
            'runner2_details' => $this->sumByNumber($bets->where('runner2', '>', 0), 'runner2'),
        ];
    }

    /**
     * Método auxiliar para evitar repetir código de suma y asegurar el formato
     */
    private function sumByNumber(Collection $items, string $field): array
    {
        return $items->groupBy('number')
            ->map(fn($group) => (int) $group->sum($field))
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
            $bets          = $this->extractBets($cleanedText);
            $processedData = $this->calculateTotals($bets);
            return $this->repository->store([
                'user_id'        => $user->id,
                'text'           => $data['text'],
                'processed_text' => $processedData,
                'hourly'         => $data['hourly']
            ]);
        });
    }

    /**
     * Motor Único de Extracción
     * Convierte texto plano en una Colección de objetos DetectedBet
     */
    public function extractBets(string $cleanText): Collection
    {
        $lines = explode("\n", $cleanText);
        $bets = collect();

        foreach ($lines as $line) {
            $line = strtolower(trim($line));
            // Ignorar si no hay números o es basura conocida
            if (empty($line) || !preg_match('/\d/', $line) || str_contains($line, 'attached:')) continue;

            // 2. TERMINALES
            if (preg_match(self::TERMINALES, $line, $m)) {
                $digit = $m['d1'] ?: ($m['d2'] ?: ($m['d3'] ?: $m['d4']));
                $amt = (int)$m['amt'];
                for ($i = 0; $i <= 9; $i++) {
                    $bets->push(new DetectedBet('fixed', $i.$digit, $amt, originalLine: $line));
                }
                continue;
            }
            // 1. PAREJAS
            if (preg_match(self::PAREJAS, $line, $m)) {
                $amt = (int)$m['amt1'];
                $r1  = (int)($m['amt2'] ?? 0);
                $r2  = (int)($m['amt3'] ?? 0);
                for ($i = 0; $i <= 9; $i++) {
                    $bets->push(new DetectedBet('fixed', $i.$i, $amt, $r1, $r2, $line));
                }
                continue;
            }

            // 3. LÍNEAS (NUEVO)
            if (preg_match(self::LINEAS, $line, $m)) {
                $decade = $m['dec1'] ?: ($m['dec2'] ?: ($m['dec3'] ?: $m['dec4']));
                $amt = (int)($m['amt1'] ?: $m['amt2']);
                for ($i = 0; $i <= 9; $i++) {
                    $bets->push(new DetectedBet('fixed', $decade.$i, $amt, originalLine: $line));
                }
                continue;
            }

            // 4. PARLET
            if (preg_match(self::PARLET, $line, $m)) {
                $n1 = str_pad($m['n1'], 2, '0', STR_PAD_LEFT);
                $n2 = str_pad($m['n2'], 2, '0', STR_PAD_LEFT);
                $nums = [$n1, $n2]; sort($nums);
                $bets->push(new DetectedBet('parlet', $nums[0].'x'.$nums[1], (int)$m['amt'], originalLine: $line));
                continue;
            }

            // 5. NORMAL / TRIPLETAS (Soporta 1 a 3 dígitos y prefijos t/p)
            if (preg_match(self::NORMAL, $line, $m)) {
                $num = str_pad($m['num'], (strlen($m['num']) > 2 ? 3 : 2), '0', STR_PAD_LEFT);
                $type = strlen($num) === 3 ? 'hundred' : 'fixed';
                $bets->push(new DetectedBet($type, $num, (int)$m['amt'], (int)($m['c1'] ?? 0), (int)($m['c2'] ?? 0), $line));
                continue;
            }

            $bets->push(new DetectedBet('error',"ND",0,0,0,$line));
        }

        return $bets;
    }


}
