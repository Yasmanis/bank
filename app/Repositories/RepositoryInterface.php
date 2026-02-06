<?php

namespace App\Repositories;

interface RepositoryInterface
{
    public function __construct();
    public function getModelById($id);

    public function store(array $data);

    public function update(array $data,$id);

    public function delete($id);



}
