<?php

namespace App\Http\Controllers;

use App\Dto\BankList\BankListFullResponseDto;
use App\Dto\BankList\BankListPartialResponseDto;
use App\Http\Requests\BankListIndexRequest;
use App\Http\Requests\ProcessListRequest;
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

            $paginator = $this->repository->getPaginated($filters, auth()->id(), $perPage);
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

        } catch (\Throwable $th) {
            return $this->error('No se pudo procesar', 422, $th->getMessage());
        }
    }


    public function preview(Request $request)
    {
        $request->validate([
            'text' => 'required|string'
        ]);
        $userAgent = $request->userAgent();
        $source = str_contains($userAgent, 'Postman') ? 'Postman' : 'Web Frontend';
        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'source' => $source,
                'agent' => $userAgent,
                'route' => $request->route()->getName()
            ])
            ->log($request->text);
        try {
            $cleanedWhatsAppText = $this->listService->cleanWhatsAppChat($request->text);
            $bets = $this->listService->extractBets($cleanedWhatsAppText);
            $data = $this->listService->calculateTotals($bets);
            return $this->success($data);
        } catch (\Throwable $th) {
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


    public function validate(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,denied'
        ]);
        try {
            $bankListRepository = new BankListRepository();
            $approvedBy = $request->status == BankList::STATUS_APPROVED ? auth()->id() : null;
            $data = [
                'status' => $request->status,
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
