<?php

namespace App\Service;

use App\Entity\CV;
use App\Entity\Position;
use App\Entity\ProfileAttributeValue;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\PositionAttributeRepository;
use App\Repository\ProfileAttributeValueRepository;
use App\Repository\ProjectRepository;

final class CvProjection
{
    public function __construct(
        private readonly PositionAttributeRepository $positionAttributeRepository,
        private readonly ProfileAttributeValueRepository $profileValueRepository,
        private readonly ProjectRepository $projectRepository,
    ) {
    }

    /** @return array{attributes: list<array{attribute: \App\Entity\Attribute, value: ?ProfileAttributeValue}>, projects: list<Project>} */
    public function forCv(CV $cv): array
    {
        $position = $cv->getPosition();
        $values = [];
        foreach ($this->profileValueRepository->findForUser($cv->getCandidate()) as $profileValue) {
            $values[$profileValue->getAttribute()->getId()] = $profileValue;
        }

        $attributes = [];
        foreach ($this->positionAttributeRepository->findForPosition($position) as $positionAttribute) {
            $attribute = $positionAttribute->getAttribute();
            $attributes[] = ['attribute' => $attribute, 'value' => $values[$attribute->getId()] ?? null];
        }

        return [
            'attributes' => $attributes,
            'projects' => $this->matchingProjects($cv->getCandidate(), $position),
        ];
    }

    /** @return list<Project> */
    private function matchingProjects(User $candidate, Position $position): array
    {
        $requiredTags = array_map('strtolower', $position->getProjectTags());
        $projects = $this->projectRepository->findForOwner($candidate);
        if ($requiredTags === []) {
            return array_slice($projects, 0, $position->getMaxProjects());
        }

        $matching = array_values(array_filter($projects, static function (Project $project) use ($requiredTags): bool {
            return array_intersect($requiredTags, array_map('strtolower', $project->getTags())) !== [];
        }));

        return array_slice($matching, 0, $position->getMaxProjects());
    }

    /** @param list<array{attribute: \App\Entity\Attribute, value: ?ProfileAttributeValue}> $attributes */
    public function hasMissingValues(array $attributes): bool
    {
        foreach ($attributes as $item) {
            if (!$item['value'] instanceof ProfileAttributeValue || trim((string) $item['value']->getValue()) === '') {
                return true;
            }
        }

        return false;
    }
}