<?php

namespace App\Http\Controllers;

use App\Dto\BankList\BankListFullResponseDto;
use App\Dto\BankList\BankListPartialResponseDto;
use App\Http\Requests\BankListIndexRequest;
use App\Http\Requests\BankListPreviewRequest;
use App\Http\Requests\ProcessListRequest;
use App\Http\Requests\ValidateListRequest;
use App\Models\BankList;
use App\Repositories\BankList\BankListRepository;
use App\Services\ListParserService;
use App\Services\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BankListController extends Controller
{
    protected ListParserService $listService;
    protected BankListRepository $repository;
    protected SettlementService $settlementService;

    public function __construct(ListParserService $listService, BankListRepository $repository, SettlementService $settlementService)
    {
        $this->listService = $listService;
        $this->repository = $repository;
        $this->settlementService = $settlementService;
    }

    public function index(BankListIndexRequest $request)
    {
        try {
            $filters = $request->validated();
            $perPage = $request->get('per_page', 15);

            $paginator = $this->repository->getPaginatedByUser($filters, auth()->id(), $perPage);
            $paginator->appends($request->query());
            $paginator->through(fn($model) => BankListPartialResponseDto::fromModel($model));
            $groupedItems = $paginator->getCollection()
                ->groupBy(function ($item) {
                    return \Carbon\Carbon::parse($item->created_at_raw)->format('Y-m-d');
                })
                ->map(function ($dateGroup) {
                    return $dateGroup->groupBy('creator_name');
                });

            $paginator->setCollection($groupedItems);

            return $this->successPaginated($paginator);

        } catch (\Throwable $th) {
            return $this->error('Error al obtener listas', 422, $th->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $bankListRepository = new BankListRepository();
            $model = $bankListRepository->getModelById($id);
            if (!auth()->user()->can('list.view_all') && $model->user_id !== auth()->id()) {
                return $this->error('No tienes permiso para ver esta lista', 403);
            }
            $model->load('user');
            return $this->success(BankListFullResponseDto::fromModel($model));

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('La lista no existe', 404);
        } catch (\Throwable $th) {
            return $this->error('Error al obtener el detalle', 422, $th->getMessage());
        }
    }

    public function process(ProcessListRequest $request)
    {
        try {
            $model = $this->listService->processAndStoreChat(
                auth()->user(),
                $request->all()
            );
            return $this->success([
                'id' => $model->id
            ], 'Procesado con éxito');

        } catch (\App\Exceptions\UnprocessedLinesException $e) {
            return $this->error(
                'Existe parte de la lista que no pudieron ser procesadas. Por favor revise',
                422,
                ['not_processed' => $e->getLines()]
            );
        } catch (\Throwable $th) {
            return $this->error('Error interno del servidor', 500, $th->getMessage());
        }
    }


    /**
     * Previsualizar procesamiento de lista.
     *
     * Permite ver el desglose de una lista antes de guardarla.
     * Si se proporciona fecha y horario, también calcula los premios contra el resultado de ese sorteo.
     */
    public function preview(BankListPreviewRequest $request)
    {
        $params = $request->validated();

        try {
            $cleanedWhatsAppText = $this->listService->cleanWhatsAppChat($params['text']);
            $extraction = $this->listService->extractBets($cleanedWhatsAppText);
            $bets = $extraction['bets'];
            $fullText = $extraction['full_text'];

            // Validaciones de errores técnicos
            $errorLines = $bets->where('type', 'error')->pluck('originalLine');
            if ($errorLines->isNotEmpty()) {
                throw new \App\Exceptions\UnprocessedLinesException($errorLines->toArray());
            }

            if ($bets->where('type', '!=', 'error')->isEmpty()) {
                throw new \Exception("La lista no contiene ninguna jugada válida.");
            }

            // 1. Totales de venta
            $data = $this->listService->calculateTotals($bets, $fullText);

            $searchDateAndHourly = $this->getSearchDateAndHourly($request, $params);
            $searchHourly = $searchDateAndHourly['searchHourly'];
            $searchDate = $searchDateAndHourly['searchDate'];


            if ($searchHourly) {
                $win = \App\Models\DailyNumber::whereDate('date', $searchDate)
                    ->where('hourly', $searchHourly)
                    ->first();

                if ($win) {
                    $rates = auth()->user()->getEffectiveRates();
                    $prizesData = $this->settlementService->calculateFromBets($bets, $win, $rates);

                    $data['prizes_preview'] = [
                        'found' => true,
                        'total_prizes' => $prizesData['total'],
                        'breakdown' => $prizesData['breakdown'],
                        'winning_number' => "{$win->hundred}-{$win->fixed}",
                        'draw_date' => $win->date->format('d/m/Y'),
                        'draw_hourly' => strtoupper($win->hourly)
                    ];
                } else {
                    $data['prizes_preview'] = [
                        'found' => false,
                        'message' => "No hay resultados registrados para el {$searchDate} ({$searchHourly})."
                    ];
                }
            }

            return $this->success($data);

        } catch (\App\Exceptions\UnprocessedLinesException $e) {
            return $this->error('Líneas con errores', 422, ['not_processed' => $e->getLines()]);
        } catch (\Throwable $th) {
            return $this->error($th->getMessage(), 422);
        }
    }


    public function previewById($id)
    {
        try {
            $bankListRepository = new BankListRepository();
            $model = $bankListRepository->getModelById($id);
            $data = $model->processed_text;
            return $this->success($data);
        } catch (\Throwable $th) {
            return $this->error('No se pudo previsualizar', 422, $th->getMessage());
        }
    }


    public function validate(ValidateListRequest $request, $id)
    {
        try {
            $bankListRepository = new BankListRepository();
            $approvedBy = $request->status == BankList::STATUS_APPROVED ? auth()->id() : null;
            $data = [
                'status' => $request->status,
                'bank_id' => $request->bank_id,
                'updated_by' => auth()->id(),
                'approved_by' => $approvedBy
            ];
            $bankListRepository->update($data, $id);
            return $this->success(['id' => $id], 'Validación de lista ejecutada correctamente');
        } catch (\Throwable $th) {
            return $this->error('No es posible la validación', 422, $th->getMessage());
        }

    }

    public function destroy($id)
    {
        try {
            $bankListRepository = new BankListRepository();
            $model = $bankListRepository->getModelById($id);
            // LLAMADA A LA POLICY: ¿Puede este usuario BORRAR este modelo?
            Gate::authorize('delete', $model);
            $bankListRepository->delete($id);
            return $this->success(['id' => $id], 'Lista eliminada correctamente');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->error('No tienes permiso para eliminar esta lista', 403);
        } catch (\Throwable $th) {
            return $this->error('Error al intentar eliminar', 422, $th->getMessage());
        }
    }

    private function getSearchDateAndHourly(BankListPreviewRequest $request, mixed $params)
    {
        $now = now(); // Carbon instance
        $currentTime = $now->format('H:i');
        if ($request->filled('hourly')) {
            // Si el usuario eligió uno manualmente, respetamos su elección
            $searchHourly = $params['hourly'];
        } else {
            // Entre la 1:00 PM (13:00) y las 9:30 PM (21:30) -> Sugerimos AM
            if ($currentTime >= '13:00' && $currentTime <= '21:30') {
                $searchHourly = 'am';
            } else {
                // Desde las 9:31 PM hasta las 12:59 PM del día siguiente -> Sugerimos PM
                $searchHourly = 'pm';
            }
        }

        // 3. Determinar fecha (Si es PM y estamos en la madrugada, quizás busca el PM de ayer)
        $searchDate = $params['date'] ?? now()->format('Y-m-d');

        // Ajuste extra de usabilidad:
        // Si son las 2:00 AM y el sistema sugiere 'pm', probablemente el usuario
        // busca el resultado de la noche de AYER, no de hoy (que aún no ha salido).
        if (!$request->filled('date') && $searchHourly === 'pm' && $currentTime < '13:00') {
            $searchDate = now()->subDay()->format('Y-m-d');
        }

        return [
            'searchDate' => $searchDate,
            'searchHourly' => $searchHourly
        ];

    }

}
