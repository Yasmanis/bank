<?php

namespace App\Http\Controllers;

use App\Services\ListParserService;
use Illuminate\Http\Request;

class ListController extends Controller
{
    public $listService;
    public function __construct(ListParserService $listService)
    {
        $this->listService = $listService;
    }

    public function process(Request $request)
    {
        $request->validate([
            'text' => 'required|string',
            'fijo' => 'nullable|string',
            'centena' => 'nullable|string',
            'corrido1' => 'nullable|string',
            'corrido2' => 'nullable|string',
            'completo' => 'nullable|string',
        ]);

        // Variables temporales para los números
        $f = $request->fijo;
        $c = $request->centena;
        $r1 = $request->corrido1;
        $r2 = $request->corrido2;

        // 1. Si llega el campo "completo", extraemos y sobrescribimos las variables
        if ($request->filled('completo')) {
            $raw = str_replace(' ', '', $request->completo);
            $len = strlen($raw);

            // Extraemos de atrás hacia adelante para asegurar posiciones fijas
            $r2 = substr($raw, -2);           // Últimos 2
            $r1 = substr($raw, -4, 2);        // Penúltimos 2
            $f  = substr($raw, -6, 2);        // Antepenúltimos 2
            $c  = ($len === 7) ? substr($raw, 0, 1) : null; // Primero (solo si hay 7 cifras)
        }

        // 2. Preparar los números ganadores con normalización (str_pad)
        $fijoPadded = $f ? str_pad($f, 2, '0', STR_PAD_LEFT) : null;


        // 3. Limpiar el texto usando el servicio
        $cleanedWhatsAppText = $this->listService->cleanWhatsAppChat($request->text);


        $winningNumbers = [
            'fijo'     => $fijoPadded,
            'hundred'  => ($c !== null && $fijoPadded) ? $c . $fijoPadded : null,
            'runners1' => $r1 ? [str_pad($r1, 2, '0', STR_PAD_LEFT)] : [],
            'runners2' => $r2 ? [str_pad($r2, 2, '0', STR_PAD_LEFT)] : [],
        ];

        // 3. Procesar resultados
        $data = $this->listService->calculateWinners($cleanedWhatsAppText, $winningNumbers);

        return response()->json($data);
    }


    public function preview(Request $request)
    {
        $request->validate([
            'text' => 'required|string',
            'fijo' => 'nullable|string',
            'centena' => 'nullable|string',
            'corrido1' => 'nullable|string',
            'corrido2' => 'nullable|string',
            'completo' => 'nullable|string',
        ]);

        // 1. Limpiar el texto usando el servicio
        $cleanedWhatsAppText = $this->listService->cleanWhatsAppChat($request->text);

        // 3. Procesar resultados
        $data = $this->listService->calculateTotals($cleanedWhatsAppText);

        return response()->json($data);
    }

    public function validate()
    {
        return response()->json([
            'message' => 'Validación de lista ejecutada correctamente',
        ]);

    }
}
