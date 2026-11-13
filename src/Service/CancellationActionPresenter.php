<?php

namespace App\Service;

use App\Entity\CancellationUndo;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CancellationActionPresenter
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    /** @return array{id: string, record_type: string, record_id: int, expires_at: string, undo_url: string, undo_token: string, finalize_url: string, finalize_token: string} */
    public function present(CancellationUndo $action): array
    {
        return [
            'id' => $action->getId(),
            'record_type' => $action->getRecordType(),
            'record_id' => $action->getRecordId(),
            'expires_at' => $action->getExpiresAt()->format(DATE_ATOM),
            'server_now' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'undo_url' => $this->urlGenerator->generate('cancellation_undo', ['id' => $action->getId()]),
            'undo_token' => $this->csrfTokenManager->getToken('cancellation_undo')->getValue(),
            'finalize_url' => $this->urlGenerator->generate('cancellation_finalize', ['id' => $action->getId()]),
            'finalize_token' => $this->csrfTokenManager->getToken('cancellation_finalize')->getValue(),
        ];
    }
}
