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
        $dossiers = $this->dossierRepository->findBy(['statut' => 'en_cours']);
        $count = 0;

        foreach ($dossiers as $dossier) {
            $derniereActivite = $this->dossierRepository->getLastActivityDate($dossier);
            $derniereConsultation = $this->actionLogRepository->getLastConsultationDate($dossier->getId());

            if (!$derniereConsultation || $derniereActivite > $derniereConsultation) {
                $count++;
            }
        }

        return $count;
    }
}