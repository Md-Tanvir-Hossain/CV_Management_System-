<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

final class DashboardRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array{cvLast24h:int, positions:int, candidates:int, recruiters:int, publishedCvs:int} */
    public function statistics(): array
    {
        return [
            'cvLast24h' => (int) $this->connection->fetchOne("SELECT COUNT(id) FROM cv WHERE created_at >= NOW() - INTERVAL '24 hours'"),
            'positions' => (int) $this->connection->fetchOne('SELECT COUNT(id) FROM position'),
            'candidates' => (int) $this->connection->fetchOne("SELECT COUNT(id) FROM app_user WHERE roles::jsonb @> '[\"ROLE_CANDIDATE\"]'::jsonb"),
            'recruiters' => (int) $this->connection->fetchOne("SELECT COUNT(id) FROM app_user WHERE roles::jsonb @> '[\"ROLE_RECRUITER\"]'::jsonb"),
            'publishedCvs' => (int) $this->connection->fetchOne("SELECT COUNT(id) FROM cv WHERE status = 'published'"),
        ];
    }

    /** @return list<array{tag:string,total:int}> */
    public function tagCloud(int $limit = 30): array
    {
        return $this->connection->fetchAllAssociative("SELECT tag, COUNT(*) AS total FROM project CROSS JOIN LATERAL jsonb_array_elements_text(project.tags::jsonb) AS tag GROUP BY tag ORDER BY total DESC, tag ASC LIMIT {$limit}");
    }
}