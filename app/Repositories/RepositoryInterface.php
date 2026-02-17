<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RepositoryInterface
{
    public function getModelById($id);
    public function store(array $data);
    public function update(array $data, $id): bool;
    public function delete($id);

    public function getPaginated(array $filters, int $perPage = 15): LengthAwarePaginator;
}
