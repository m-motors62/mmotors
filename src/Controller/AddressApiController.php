<?php

namespace App\Controller;

use App\Service\AddressApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AddressApiController extends AbstractController
{
    #[Route('/api/adresses', name: 'app_api_adresses')]
    public function search(Request $request, AddressApiClient $addressApiClient): Response
    {
        $text = $request->query->get('q', '');

        return $this->json($addressApiClient->search($text));
    }
}