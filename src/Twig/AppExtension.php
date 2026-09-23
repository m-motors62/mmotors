<?php

namespace App\Twig;

use App\Repository\ActionLogRepository;
use App\Repository\DossierRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private DossierRepository $dossierRepository,
        private ActionLogRepository $actionLogRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nb_dossiers_non_consultes', [$this, 'countUnconsultedDossiers']),
        ];
    }

    public function countUnconsultedDossiers(): int
    {
        $consultedIds = $this->actionLogRepository->getConsultedDossierIds();

        return $this->dossierRepository->count([
            'statut' => 'en_cours',
        ]) - $this->dossierRepository->count([
            'statut' => 'en_cours',
            'id' => $consultedIds ?: [0],
        ]);
    }
}