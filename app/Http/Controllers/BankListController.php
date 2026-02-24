<?php

namespace App\Http\Controllers;

use App\Dto\BankList\BankListFullResponseDto;
use App\Dto\BankList\BankListPartialResponseDto;
use App\Http\Requests\BankListIndexRequest;
use App\Http\Requests\ProcessListRequest;
use App\Http\Requests\ValidateListRequest;
use App\Models\BankList;
use App\Repositories\BankList\BankListRepository;
use App\Services\ListParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BankListController extends Controller
{
    protected ListParserService $listService;
    protected BankListRepository $repository;

    public function __construct(ListParserService $listService,BankListRepository $repository)
    {
        $this->listService = $listService;
        $this->repository = $repository;
    }

    public function index(BankListIndexRequest $request)
    {
        try {
            $filters = $request->validated();
            $perPage = $request->get('per_page', 15);

            $paginator = $this->repository->getPaginatedByUser($filters, auth()->id(), $perPage);
            $paginator->appends($request->query());
            $paginator->through(fn ($model) => BankListPartialResponseDto::fromModel($model));
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


    public function preview(Request $request)
    {
        $request->validate([
            'text' => 'required|string'
        ]);
        try {
            $cleanedWhatsAppText = $this->listService->cleanWhatsAppChat($request->text);
            $extraction  = $this->listService->extractBets($cleanedWhatsAppText);
            $bets = $extraction['bets'];
            $fullText = $extraction['full_text'];
            $errorLines = $bets->where('type', 'error')->pluck('originalLine');
            if ($errorLines->isNotEmpty()) {
                throw new \App\Exceptions\UnprocessedLinesException($errorLines->toArray());
            }
            $validBets = $bets->where('type', '!=', 'error');
            if ($validBets->isEmpty()) {
                throw new \Exception("La lista no contiene ninguna jugada válida (Ej: 25-10).");
            }
            $data = $this->listService->calculateTotals($bets, $fullText);
            return $this->success($data);
        } catch (\App\Exceptions\UnprocessedLinesException $e) {
            return $this->error(
                'Existe parte de la lista que no pudieron ser procesadas. Por favor revise',
                422,
                ['not_processed' => $e->getLines()]
            );
        }
        catch (\Throwable $th) {
            return $this->error('No se pudo previsualizar', 422, $th->getMessage());
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
            $bankListRepository->update($data,$id);
            return $this->success(['id' => $id],'Validación de lista ejecutada correctamente');
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

}
