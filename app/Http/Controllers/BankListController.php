<?php

namespace App\Http\Controllers;

use App\Dto\BankList\BankListFullResponseDto;
use App\Dto\BankList\BankListPartialResponseDto;
use App\Dto\User\UserIndexResponseDto;
use App\Exceptions\UnprocessedLinesException;
use App\Http\Requests\BankListIndexRequest;
use App\Http\Requests\BankListPreviewRequest;
use App\Http\Requests\ManualValidationRequest;
use App\Http\Requests\ProcessListRequest;
use App\Http\Requests\ValidateListRequest;
use App\Models\BankList;
use App\Repositories\BankList\BankListRepository;
use App\Repositories\User\UserRepository;
use App\Services\BankListService;
use App\Services\ListParserService;
use App\Services\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class BankListController extends Controller
{
    protected ListParserService $listService;
    protected BankListRepository $repository;
    protected SettlementService $settlementService;

    protected BankListService $bankListService;

    protected UserRepository $userRepository;


    public function __construct(ListParserService $listService, BankListRepository $repository, SettlementService $settlementService, BankListService $bankListService, UserRepository $userRepository)
    {
        $this->listService = $listService;
        $this->repository = $repository;
        $this->settlementService = $settlementService;
        $this->bankListService = $bankListService;
        $this->userRepository = $userRepository;

    }

    /**
     * Ver Todas las listas.
     */

    public function index(BankListIndexRequest $request)
    {
        try {
            $filters = $request->validated();
            $perPage = $request->integer('per_page', 15);
            // Pasamos filtros y el ID del que consulta
            $paginator = $this->repository->getPaginatedByUser($filters, auth()->id(), $perPage);
            // Mantener los filtros en los links de paginación
            $paginator->appends($request->query());
            $paginator->through(fn($model) => BankListPartialResponseDto::fromModel($model));
            // Agrupamiento por fecha y luego por nombre
            $groupedItems = $paginator->getCollection()
                ->groupBy(function ($item) {
                    return \Carbon\Carbon::parse($item->created_at_raw)->format('Y-m-d');
                })
                ->map(function ($dateGroup) {
                    return $dateGroup->groupBy('creator_name');
                });
            $paginator->setCollection($groupedItems);

            if (!empty($filters['user_id'])){
                $userId = $filters['user_id'];
            } else {
                $userId = auth()->id();
            }
            $paginatorUser = $this->userRepository->getSubUsersPaginated(
                $userId,
                [],
                $perPage
            );
            $myListsMakers = $paginatorUser->through(fn($modelUser) => UserIndexResponseDto::fromModel($modelUser));
            $extraData = [
                'my_list_markers' => $myListsMakers->items()
            ];
            return $this->successPaginated($paginator,$extraData);

        } catch (\Throwable $th) {
            return $this->error('Error al obtener listas', 422, $th->getMessage());
        }
    }

    /**
     * Ver consolidado unificado de un turno.
     * Accesible para cualquier usuario (ve lo suyo y lo de sus hijos).
     * Por defecto toma la fecha de hoy y el horario sugerido según la hora actual.
     */
    public function unified(Request $request)
    {
        // 1. Validación: ahora date y hourly son opcionales
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'date'    => 'nullable|date',
            'hourly'  => 'nullable|in:am,pm'
        ]);

        try {
            $authUser = auth()->user();
            $targetUserId = $request->user_id ?? $authUser->id;

            // 2. Seguridad Jerárquica
            if (!$authUser->can('list.view_all')) {
                $managedIds = $authUser->getManagedUserIds();
                if (!in_array((int)$targetUserId, $managedIds)) {
                    return $this->error('No tienes permiso para ver a este usuario', 403);
                }
            }

            // 3. Resolución de Fecha y Horario Inteligente
            // Reutilizamos tu método privado para obtener la fecha y el turno sugerido
            $params = $request->only(['date', 'hourly']);
            // Pasamos un objeto vacío o simulamos el Request para que el helper funcione
            $searchParams = $this->getSearchDateAndHourly($request, $params);

            $date = $searchParams['searchDate'];
            $hourly = $searchParams['searchHourly'];

            // 4. Llamada al servicio con los datos resueltos
            $unifiedModel = $this->bankListService->getUnifiedTurnReport(
                (int)$targetUserId,
                $date,
                $hourly
            );

            if (!$unifiedModel) {
                return $this->error("No hay actividad para unificar el día {$date} ({$hourly})", 404);
            }

            // Cargamos la relación del usuario virtualmente para el DTO
            $unifiedModel->setRelation('user', User::find($targetUserId));

            // Retornamos el DTO de siempre (FullResponse)
            return $this->success(BankListFullResponseDto::fromModel($unifiedModel));

        } catch (\Throwable $th) {
            return $this->error('Error al generar el consolidado', 422, $th->getMessage());
        }
    }

    public function show($id)
    {
        try {
            // 1. Buscamos el modelo
            $model = $this->repository->getModelById($id);
            $this->authorize('view', $model);
            $model->load(['user', 'bank', 'validator']);
            return $this->success(BankListFullResponseDto::fromModel($model));
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->error('No tienes permiso para ver esta lista', 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('La lista no existe', 404);
        } catch (\Throwable $th) {
            return $this->error('Error al obtener el detalle', 422, $th->getMessage());
        }
    }

    public function process(ProcessListRequest $request)
    {
        try {
            $user = auth()->user();
            $files = $request->file('file');
            $results = [];

            if (is_array($files)) {
                foreach ($files as $index => $file) {
                    $data = $request->validated();
                    if ($index > 0) $data['text'] = null;
                    if ($index > 0) $data['client_uuid'] = null;

                    $model = $this->bankListService->createFromChat($user, $data, $file);
                    $results[] = ['id' => $model->id, 'type' => 'file'];
                }
            }
            else {
                $model = $this->bankListService->createFromChat($user, $request->validated(), $files);
                $results[] = ['id' => $model->id, 'type' => $files ? 'file' : 'text'];
            }

            return $this->success(['processed' => $results], 'Procesado con éxito');

        } catch (UnprocessedLinesException $e) {
            $this->logger()->listProcess(
                "Lista guardada con errores de formato. Revisar.",
                null,
                ['not_processed' => $e->getLines()]
            );
            return $this->success([
                'status' => 'error_parsing',
                'not_processed' => $e->getLines()
            ], 'La lista se guardó pero tiene líneas con errores.', 200);
        } catch (\Throwable $th) {
            $this->logger()->log(
                "Fallo en proceso de lista: " . $th->getMessage(),
                'system_error',
                null,
                ['detail' => $th->getMessage()]
            );

            return $this->success([
                'status' => 'error_system',
                'detail' => $th->getMessage()
            ], $th->getMessage(), 200);
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
            $extraction = $this->listService->extractBets($params['text']);
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

    private function getSearchDateAndHourly(Request $request, mixed $params)
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

    public function validateManual(ManualValidationRequest $request, $id)
    {
        try {
            $model = $this->repository->getModelById($id);
            $manualData = $request->validated();
            $bankId = $manualData['bank_id'];
            unset($manualData['bank_id']);

            $this->repository->update([
                'status' => BankList::STATUS_APPROVED,
                'bank_id' => $bankId,
                'manual_results' => $manualData,
                'validated_by' => auth()->id(),
                'validated_at' => now(),
            ], $id);

            return $this->success(null, 'Lista validada manualmente y asignada al banco.');
        } catch (\Throwable $th) {
            return $this->error('Error al procesar validación manual', 422, $th->getMessage());
        }
    }

}
