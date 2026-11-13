<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\CancellationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class CancellationController extends AbstractController
{
    #[Route('/cancellations/{id}/undo', name: 'cancellation_undo', requirements: ['id' => '[a-f0-9]{48}'], methods: ['POST'])]
    public function undo(string $id, Request $request, CancellationManager $cancellations): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getRole() !== 'customer') {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('cancellation_undo', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.'], 403);
        }

        try {
            if (!$cancellations->undo($id, $user)) {
                return $this->json([
                    'success' => false,
                    'expired' => true,
                    'status' => 'Cancelled',
                    'message' => 'The 3-second Undo period has expired.',
                ], 409);
            }
        } catch (\DomainException $exception) {
            return $this->json(['success' => false, 'message' => $exception->getMessage()], 409);
        }

        return $this->json(['success' => true, 'status' => 'Pending', 'message' => 'Cancellation undone.']);
    }

    #[Route('/cancellations/{id}/finalize', name: 'cancellation_finalize', requirements: ['id' => '[a-f0-9]{48}'], methods: ['POST'])]
    public function finalize(string $id, Request $request, CancellationManager $cancellations): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getRole() !== 'customer') {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('cancellation_finalize', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.'], 403);
        }

        try {
            if (!$cancellations->finalize($id, $user)) {
                return $this->json([
                    'success' => false,
                    'message' => 'The cancellation is not ready to be finalized.',
                ], 409);
            }
        } catch (\DomainException $exception) {
            return $this->json(['success' => false, 'message' => $exception->getMessage()], 409);
        }

        return $this->json(['success' => true, 'status' => 'Cancelled', 'message' => 'Cancellation finalized.']);
    }
}
