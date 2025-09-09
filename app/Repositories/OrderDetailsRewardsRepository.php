<?php

namespace App\Repositories;


use App\Contracts\Repositories\OrderDetailsRewardsRepositoryInterface;
use App\Models\OrderDetailsRewards;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;


class OrderDetailsRewardsRepository implements OrderDetailsRewardsRepositoryInterface
{
    public function __construct(
        private readonly OrderDetailsRewards $orderRewardDetails
    )
    {

    }

    public function add(array $data): string|object
    {
        // TODO: Implement add() method.
    }

    public function getFirstWhere(array $params, array $relations = []): ?Model
    {
        return  $this->orderRewardDetails->where($params)->first();
    }

    public function getList(array $orderBy = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, int $offset = null): Collection
    {
        // TODO: Implement getList() method.
    }

    public function getListWhere(array $orderBy = [], string $searchValue = null, array $filters = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, int $offset = null): Collection
    {
        // TODO: Implement getListWhere() method.
    }

    public function update(string $id, array $data): bool
    {
       return $this->orderRewardDetails->find($id)->update($data);
    }

    public function delete(array $params): bool
    {
        // TODO: Implement delete() method.
    }
}
