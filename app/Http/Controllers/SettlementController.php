<?php

namespace App\Http\Controllers;

use App\Services\SettlementService;
use Illuminate\Http\Request;
/**
 * @group Liquidación (Cierre de Turno)
 */
class SettlementController extends Controller
{
    protected $service;

    public function __construct(SettlementService $service)
    {
        $this->service = $service;
    }

    /**
     * Previsualizar Liquidación.
     * Calcula cuánto debe el usuario sin afectar el balance.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'bank_id' => 'required|exists:banks,id', // Requerido
            'date'    => 'required|date',
            'hourly'  => 'required|in:am,pm'
        ]);

        try {
            $result = $this->service->calculate(
                $request->user_id,
                $request->bank_id,
                $request->date,
                $request->hourly
            );
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
