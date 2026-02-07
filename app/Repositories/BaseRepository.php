<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class BaseRepository implements RepositoryInterface
{
    // Guardamos el nombre de la clase, no la instancia de la consulta
    protected string $modelClass;

    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
    }

    /**
     * Genera una consulta nueva cada vez que se llama.
     */
    protected function query(): Builder
    {
        return $this->modelClass::query();
    }

    public function getModelById($id): Model
    {
        return $this->query()->findOrFail($id);
    }

    public function store(array $data): Model
    {
        return $this->query()->create($data);
    }

    public function update(array $data, $id): bool
    {
        return $this->query()->findOrFail($id)->update($data);
    }

    public function delete($id): bool
    {
        return $this->query()->findOrFail($id)->delete();
    }
}
