<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\CvLikeRepository;
use App\Repository\CvRepository;
use App\Repository\ProjectRepository;

final class BadgeService
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly CvRepository $cvRepository,
        private readonly CvLikeRepository $likeRepository,
    ) {
    }

    /** @return list<array{name: string, description: string}> */
    public function earnedBy(User $candidate): array
    {
        $counts = [
            'projects' => $this->projectRepository->countForOwner($candidate),
            'cvs' => $this->cvRepository->countForCandidate($candidate),
            'likes' => $this->likeRepository->countReceivedByCandidate($candidate),
        ];
        $badges = [];
        if ($counts['projects'] >= 10) {
            $badges[] = ['name' => 'Project Builder', 'description' => 'Created 10 projects'];
        }
        if ($counts['cvs'] >= 5) {
            $badges[] = ['name' => 'CV Creator', 'description' => 'Created 5 CVs'];
        }
        if ($counts['likes'] >= 25) {
            $badges[] = ['name' => 'Candidate Spotlight', 'description' => 'Received 25 CV likes'];
        }

        return $badges;
    }

    /** @param list<array{name: string, description: string}> $badges */
    public function svg(array $badges): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="720" height="'.($badges === [] ? 180 : 220).'" viewBox="0 0 720 '.($badges === [] ? 180 : 220).'">';
        $svg .= '<rect width="720" height="100%" fill="#f8f9fa"/><text x="32" y="44" font-family="Arial,sans-serif" font-size="26" font-weight="bold" fill="#212529">CV Management badges</text>';
        if ($badges === []) {
            $svg .= '<text x="32" y="96" font-family="Arial,sans-serif" font-size="16" fill="#6c757d">No badges earned yet</text>';
        } else {
            foreach ($badges as $index => $badge) {
                $x = 32 + ($index * 220);
                $name = htmlspecialchars($badge['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $description = htmlspecialchars($badge['description'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $svg .= '<circle cx="'.($x + 32).'" cy="96" r="28" fill="#0d6efd"/><path d="M'.($x + 20).' 96l8 8 16-18" stroke="#fff" stroke-width="5" fill="none"/>';
                $svg .= '<text x="'.($x + 72).'" y="92" font-family="Arial,sans-serif" font-size="16" font-weight="bold" fill="#212529">'.$name.'</text><text x="'.($x + 72).'" y="116" font-family="Arial,sans-serif" font-size="12" fill="#6c757d">'.$description.'</text>';
            }
        }
        return $svg.'</svg>';
    }
}