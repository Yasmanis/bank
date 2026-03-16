<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLoggerService
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Registro de actividad genérico
     */
    public function log(
        string $description,
        string $logName = 'default',
        ?Model $subject = null,
        array $properties = []
    ): void {
        $payload = array_merge($properties, $this->getAutoMetadata());

        $activity = activity($logName)
            ->causedBy(auth()->user())
            ->withProperties($payload);

        if ($subject) {
            $activity->performedOn($subject);
        }

        $activity->log($description);
    }

    /**
     * Atajo para logs financieros (Transacciones, Liquidaciones)
     */
    public function finance(string $message, Model $subject, array $data = []): void
    {
        $this->log($message, 'financial', $subject, $data);
    }

    /**
     * Atajo para logs de seguridad (Login, Refresh, Fallos)
     */
    public function security(string $message, array $data = []): void
    {
        $this->log($message, 'security', null, $data);
    }

    /**
     * Atajo para procesos de listas (Parsing, Errores)
     */
    public function listProcess(string $message, ?Model $subject = null, array $data = []): void
    {
        $this->log($message, 'list_processing', $subject, $data);
    }

    /**
     * Genera metadatos automáticos de la petición
     */
    private function getAutoMetadata(): array
    {
        $ua = $this->request->userAgent();

        return [
            'ip' => $this->request->ip(),
            'source' => $this->request->header('X-Device-Type') ?? (str_contains($ua, 'Postman') ? 'Postman' : 'Web'),
            'url' => $this->request->fullUrl(),
            'method' => $this->request->method(),
        ];
    }
}
