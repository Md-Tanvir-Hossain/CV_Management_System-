<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

final class SearchRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<int> */
    public function positionIds(string $query): array
    {
        return array_map('intval', $this->connection->fetchFirstColumn("SELECT id FROM position WHERE search_vector @@ plainto_tsquery('simple', :query) ORDER BY ts_rank(search_vector, plainto_tsquery('simple', :query)) DESC, updated_at DESC", ['query' => $query]));
    }

    /** @return list<int> */
    public function publishedCvIds(string $query): array
    {
        return array_map('intval', $this->connection->fetchFirstColumn("SELECT id FROM cv WHERE status = 'published' AND search_vector @@ plainto_tsquery('simple', :query) ORDER BY ts_rank(search_vector, plainto_tsquery('simple', :query)) DESC, updated_at DESC", ['query' => $query]));
    }
}