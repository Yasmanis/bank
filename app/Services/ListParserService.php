<?php

namespace App\Services;

use App\Dto\Bet\DetectedBet;
use App\Repositories\BankList\BankListRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListParserService
{
    private const PAREJAS = '/^(?:(?:las\s+)?parejas?|(?:del\s+)?00\s+al\s+99|00-99)\D+(\d+)$/i';
    private const TERMINALES = '/^(?:(?:del\s+)?\d?(\d)\s+al\s+\d?\1|ter(?:minal(?:es)?)?\s*\d?(\d)|t\s*\d?(\d)|\d?(\d)-\d?\4)\D+(\d+)$/i';
    private const LINEAS = '/^(?:(?:los|del)\s+(\d)0|(?:del\s+)?(\d)0\s+al\s+\2[9]|(\d)0-\3[9])\D+(\d+)$/i';
    private const PARLET = '/^(\d{1,2})[x\*](\d{1,2})\D+(\d+)$/i';
    private const NORMAL = '/^t?\s?(\d{2,3})\D+(\d+)(?:\D+(\d+))?(?:\D+(\d+))?$/i';

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
            if (empty($line) || !preg_match('/\d/', $line)) continue;

            // 1. Intentar Parejas
            if (preg_match(self::PAREJAS, $line, $matches)) {
                $amt = (int)$matches[1];
                for ($i = 0; $i <= 9; $i++) {
                    $bets->push(new DetectedBet('fixed', $i.$i, $amt, originalLine: $line));
                }
                continue;
            }

            // 2. Intentar Terminales
            if (preg_match(self::TERMINALES, $line, $matches)) {
                // Quitamos el primer elemento (la línea completa) y filtramos vacíos
                $values = array_values(array_filter(array_slice($matches, 1), fn($v) => $v !== ''));

                // El dígito es el primero, el monto el último
                $digit = $values[0];
                $amt = (int) end($values);

                for ($i = 0; $i <= 9; $i++) {
                    $num = $i . $digit;
                    $bets->push(new DetectedBet('fixed', $num, $amt, originalLine: $line));
                }
                continue;
            }

            // 3. Intentar Parlet
            if (preg_match(self::PARLET, $line, $matches)) {
                // Normalizamos a 2 dígitos (ej: 5 -> 05)
                $n1 = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $n2 = str_pad($matches[2], 2, '0', STR_PAD_LEFT);

                // Ordenamos para que el menor siempre vaya primero
                $nums = [$n1, $n2];
                sort($nums);
                $key = $nums[0] . 'x' . $nums[1]; // Resultado siempre será "05x10"

                $amt = (int)$matches[3];

                $bets->push(new DetectedBet('parlet', $key, $amt, originalLine: $line));
                continue;
            }

            // 4. Normal / Tripletas
            if (preg_match(self::NORMAL, $line, $matches)) {
                $num = str_pad($matches[1], (strlen($matches[1]) > 2 ? 3 : 2), '0', STR_PAD_LEFT);
                $type = strlen($num) === 3 ? 'hundred' : 'fixed';

                $bets->push(new DetectedBet(
                    type: $type,
                    number: $num,
                    amount: (int)$matches[2],
                    runner1: (int)($matches[3] ?? 0),
                    runner2: (int)($matches[4] ?? 0),
                    originalLine: $line
                ));
                continue;
            }

            // Aquí podríamos capturar las líneas que no coinciden con nada
        }

        return $bets;
    }


}
