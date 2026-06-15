<?php

namespace App\Services;

class StoreAgentsSqlExporter
{
    public function __construct(private int $storeId = 1) {}

    public function export(): string
    {
        return (new StoreDataSqlExporter($this->storeId))->exportAgentsOnlySql();
    }
}
