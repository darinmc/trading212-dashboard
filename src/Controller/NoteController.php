<?php

namespace App\Controller;

use App\Entity\Position;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class NoteController extends AbstractController
{
    #[Route('/ticker/{ticker}/update-note', name: 'app_update_ticker_note', methods: ['POST'])]
    public function updateTickerNote(Request $request, string $ticker, EntityManagerInterface $entityManager): JsonResponse
    {
        $note = $request->request->get('note', '');
        $important = $request->request->get('important', '0') === '1';
        $checkFlag = $request->request->get('check', '0') === '1';
        $financials = $request->request->get('financials', '0') === '1';
        
        // Find all positions with this ticker
        $positions = $entityManager->getRepository(Position::class)->findBy(['ticker' => $ticker]);
        
        // Update the note on all positions with this ticker
        foreach ($positions as $position) {
            $position->setNote($note);
            $position->setImportant($important);
            $position->setCheckFlag($checkFlag);
            $position->setFinancials($financials);
        }
        
        $entityManager->flush();
        
        return new JsonResponse([
            'success' => true,
            'note' => $note,
            'important' => $important,
            'check' => $checkFlag,
            'financials' => $financials
        ]);
    }
}
