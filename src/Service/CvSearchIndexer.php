<?php

namespace App\Service;

use App\Entity\CV;
use App\Entity\User;
use Doctrine\DBAL\Connection;

final class CvSearchIndexer
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function refresh(CV $cv): void
    {
        $this->connection->executeStatement("UPDATE cv SET search_vector = to_tsvector('simple', :content) WHERE id = :id", ['content' => $this->contentSqlValue($cv), 'id' => $cv->getId()]);
    }

    public function refreshCandidate(User $candidate): void
    {
        $this->connection->executeStatement("UPDATE cv SET search_vector = to_tsvector('simple', concat_ws(' ', position.title, position.company, (SELECT string_agg(pav.value, ' ') FROM profile_attribute_value pav JOIN position_attribute pa ON pa.attribute_id = pav.attribute_id WHERE pav.user_id = cv.candidate_id AND pa.position_id = cv.position_id), (SELECT string_agg(project.name || ' ' || (SELECT string_agg(tag, ' ') FROM jsonb_array_elements_text(project.tags::jsonb) AS tag), ' ') FROM project WHERE project.owner_id = cv.candidate_id))) FROM position WHERE position.id = cv.position_id AND cv.candidate_id = :candidate AND cv.status = 'published'", ['candidate' => $candidate->getId()]);
    }

    private function contentSqlValue(CV $cv): string
    {
        return (string) $this->connection->fetchOne("SELECT concat_ws(' ', position.title, position.company, (SELECT string_agg(pav.value, ' ') FROM profile_attribute_value pav JOIN position_attribute pa ON pa.attribute_id = pav.attribute_id WHERE pav.user_id = :candidate AND pa.position_id = :position), (SELECT string_agg(project.name || ' ' || (SELECT string_agg(tag, ' ') FROM jsonb_array_elements_text(project.tags::jsonb) AS tag), ' ') FROM project WHERE project.owner_id = :candidate)) FROM position WHERE position.id = :position", ['candidate' => $cv->getCandidate()->getId(), 'position' => $cv->getPosition()->getId()]);
    }
}