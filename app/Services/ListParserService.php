<?php

namespace App\Services;

use App\Dto\Bet\DetectedBet;
use App\Repositories\BankList\BankListRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListParserService
{
    // Parejas: Soporta "las parejas 100", "00-99 con 50", etc.
    private const PAREJAS = '/^(?:(?:las\s+)?pareja[as]?|(?:del\s+)?00\s+al\s+99|00-99)\D+(\d+)(?:\D+(\d+))?(?:\D+(\d+))?$/i';

    // Terminales: Soporta "ter 7-10", "terminal 8 a 50", "t-5 20", "07-97-10"
    private const TERMINALES = '/^(?:(?:del\s+)?\d?(\d)\s+al\s+\d?\1|ter(?:min(?:al(?:es)?|ar)?)?\s*\d?(\d)|t\s*[-]?\s*(\d)|\d?(\d)-\d?\4)\D+(\d+)$/i';

    // Líneas: Soporta "los 30-50", "del 20 al 29-10" Y el nuevo "50 pesos a todos los 70"
    private const LINEAS = '/^(?:(?:(?:los|del|l|d|lineas?)\s*(\d)0(?:s)?|(\d)0\s*al\s*\2[9]|(\d)0-\3[9])\D+(\d+)|(\d+)\D+todos\s+(?:los\s+)?(\d)0)$/i';

    // Parlet: Soporta "38x70-100", "p-38*70 50"
    private const PARLET = '/^(?:p[- ])?(\d{1,2})[x\*](\d{1,2})\D+(\d+)$/i';

    // Normal: Soporta "77-100", "t05-10-10", "123-500", "1-500"
    private const NORMAL = '/^(?:t|p)?\s?(\d{1,3})\D+(\d+)(?:\D+(\d+))?(?:\D+(\d+))?$/i';

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
        // 1. Inicializamos la estructura base de los detalles fijos (00-99)
        $fixedDetails = collect(range(0, 99))
            ->mapWithKeys(fn($i) => [str_pad($i, 2, '0', STR_PAD_LEFT) => 0]);

        // 2. Procesamos los totales por categorías usando filtros de la colección
        $summary = [
            'fixed'           => $bets->where('type', 'fixed')->sum('amount'),
            'hundred'         => $bets->where('type', 'hundred')->sum('amount'),
            'parlet'          => $bets->where('type', 'parlet')->sum('amount'),
            'runner1'         => $bets->sum('runner1'),
            'runner2'         => $bets->sum('runner2'),
            'total'           => $bets->sum(fn($bet) => $bet->amount + $bet->runner1 + $bet->runner2),

            // Agrupaciones detalladas
            'fixed_details'   => $fixedDetails->merge(
                $bets->where('type', 'fixed')
                    ->groupBy('number')
                    ->map->sum('amount')
            )->filter()->toArray(),

            'hundred_details' => $bets->where('type', 'hundred')
                ->groupBy('number')
                ->map->sum('amount')
                ->sortKeys()
                ->toArray(),

            'parlet_details'  => $bets->where('type', 'parlet')
                ->groupBy('number')
                ->map->sum('amount')
                ->sortKeys()
                ->toArray(),

            'runner1_details' => $bets->where('runner1', '>', 0)
                ->groupBy('number')
                ->map->sum('runner1')
                ->sortKeys()
                ->toArray(),

            'runner2_details' => $bets->where('runner2', '>', 0)
                ->groupBy('number')
                ->map->sum('runner2')
                ->sortKeys()
                ->toArray(),
        ];

        return $summary;
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
            if (preg_match(self::TERMINALES, $line, $matches)) {
                $values = array_values(array_filter(array_slice($matches, 1), fn($v) => $v !== ''));
                $digit = $values[0];
                $amt = (int) end($values);
                for ($i = 0; $i <= 9; $i++) {
                    $bets->push(new DetectedBet('fixed', $i.$digit, $amt, originalLine: $line));
                }
                continue;
            }

            // 1. PAREJAS (Ahora con soporte para 3 montos)
            if (preg_match(self::PAREJAS, $line, $matches)) {
                $amt = (int)$matches[1];
                $r1  = (int)($matches[2] ?? 0);
                $r2  = (int)($matches[3] ?? 0);
                for ($i = 0; $i <= 9; $i++) {
                    $bets->push(new DetectedBet('fixed', $i.$i, $amt, $r1, $r2, $line));
                }
                continue;
            }

            // 3. LÍNEAS (NUEVO)
            if (preg_match(self::LINEAS, $line, $matches)) {
                $values = array_values(array_filter($matches, fn($v) => $v !== ''));

                // Si la coincidencia vino de "50 a todos los 70"
                if (str_contains($line, 'todos')) {
                    $amt = (int) $values[1];    // El primer número es el monto (50)
                    $decade = $values[2];       // El segundo es la decena (7)
                } else {
                    // Si vino de "los 70 a 50"
                    $decade = $values[1];       // El primer número es la decena (7)
                    $amt = (int) end($values);  // El último es el monto (50)
                }

                for ($i = 0; $i <= 9; $i++) {
                    $bets->push(new DetectedBet('fixed', $decade.$i, $amt, originalLine: $line));
                }
                continue;
            }

            // 4. PARLET
            if (preg_match(self::PARLET, $line, $matches)) {
                $n1 = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $n2 = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $nums = [$n1, $n2]; sort($nums);
                $bets->push(new DetectedBet('parlet', $nums[0].'x'.$nums[1], (int)$matches[3], originalLine: $line));
                continue;
            }

            // 5. NORMAL / TRIPLETAS (Soporta 1 a 3 dígitos y prefijos t/p)
            if (preg_match(self::NORMAL, $line, $matches)) {
                $num = str_pad($matches[1], (strlen($matches[1]) > 2 ? 3 : 2), '0', STR_PAD_LEFT);
                $type = strlen($num) === 3 ? 'hundred' : 'fixed';
                $bets->push(new DetectedBet($type, $num, (int)$matches[2], (int)($matches[3] ?? 0), (int)($matches[4] ?? 0), $line));
                continue;
            }

            $bets->push(new DetectedBet('error',"ND",0,0,0,$line));
        }

        return $bets;
    }


}
