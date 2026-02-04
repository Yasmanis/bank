<?php

namespace App\Http\Controllers;

use App\Dto\BankList\BankListFullResponseDto;
use App\Dto\BankList\BankListPartialResponseDto;
use App\Http\Requests\ProcessListRequest;
use App\Models\BankList;
use App\Repositories\BankList\BankListRepository;
use App\Services\ListParserService;
use Illuminate\Http\Request;

class BankListController extends Controller
{
    protected ListParserService $listService;

    public function __construct(ListParserService $listService)
    {
        $this->listService = $listService;
    }

    public function index(Request $request)
    {
        try {
            $bankListRepository = new BankListRepository();

            $paginator = $bankListRepository->getPaginatedByUser(
                auth()->id(),
                $request->get('per_page', 15)
            );

            $paginator->getCollection()->transform(function ($model) {
                return BankListPartialResponseDto::fromModel($model);
            });

            return $this->success($paginator, 'Listas obtenidas con éxito');

        } catch (\Throwable $th) {
            return $this->error('Error al obtener listas', 500, $th->getMessage());
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

    public function calculateWinner(Request $request)
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
            $f = substr($raw, -6, 2);        // Antepenúltimos 2
            $c = ($len === 7) ? substr($raw, 0, 1) : null; // Primero (solo si hay 7 cifras)
        }

        // 2. Preparar los números ganadores con normalización (str_pad)
        $fijoPadded = $f ? str_pad($f, 2, '0', STR_PAD_LEFT) : null;


        // 3. Limpiar el texto usando el servicio
        $cleanedWhatsAppText = $this->listService->cleanWhatsAppChat($request->text);


        $winningNumbers = [
            'fijo' => $fijoPadded,
            'hundred' => ($c !== null && $fijoPadded) ? $c . $fijoPadded : null,
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
            $data = $this->listService->calculateTotals($cleanedWhatsAppText);
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


    public function validate($id)
    {
        try {
            $bankListRepository = new BankListRepository();
            $data = [
                'status' => BankList::STATUS_APPROVED,
                'updated_by' => auth()->id(),
                'approved_by' => auth()->id()
            ];
            $bankListRepository->update($data,$id);
            return $this->success(['id' => $id],'Validación de lista ejecutada correctamente');
        } catch (\Throwable $th) {
            return $this->error('No es posible la validación', 422, $th->getMessage());
        }

    }
}
