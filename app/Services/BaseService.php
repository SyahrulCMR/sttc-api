<?php

namespace App\Services;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

abstract class BaseService
{
    protected BaseRepositoryInterface $repository;

    public function list(array $columns = ['*']): Collection
    {
        return $this->repository->all($columns);
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function find(int|string $id): Model
    {
        return $this->repository->findOrFail($id);
    }

    public function create(array $data): Model
    {
        return $this->transaction(fn () => $this->repository->create($data));
    }

    public function update(int|string $id, array $data): Model
    {
        return $this->transaction(fn () => $this->repository->update($id, $data));
    }

    public function delete(int|string $id): bool
    {
        return $this->transaction(fn () => $this->repository->delete($id));
    }

    /**
     * Run a closure inside a database transaction. Re-throws on failure
     * so callers / global exception handler can map to a JSON response.
     */
    protected function transaction(callable $callback): mixed
    {
        try {
            return DB::transaction($callback);
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
