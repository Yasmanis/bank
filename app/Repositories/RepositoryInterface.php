<?php

namespace App\Repositories;

interface RepositoryInterface
{
    public function getModelById($id);
    public function store(array $data);
    public function update(array $data, $id): bool;
    public function delete($id);

}
