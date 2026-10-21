<?php

declare(strict_types=1);

namespace App\Domain\Action;

use App\Planka\PlankaClientInterface;

final class ActionService implements ActionServiceInterface
{
    public function __construct(
        private readonly PlankaClientInterface $plankaClient,
    ) {}

    /** @return array<mixed> */
    public function getBoardActions(string $apiKey, string $boardId, ?string $beforeId = null): array
    {
        return $this->plankaClient->get($apiKey, '/api/boards/' . $boardId . '/actions', self::page($beforeId));
    }

    /** @return array<mixed> */
    public function getCardActions(string $apiKey, string $cardId, ?string $beforeId = null): array
    {
        return $this->plankaClient->get($apiKey, '/api/cards/' . $cardId . '/actions', self::page($beforeId));
    }

    /**
     * Planka returns the newest 50 actions; beforeId asks for the 50 before that action.
     *
     * @return array<string, string>
     */
    private static function page(?string $beforeId): array
    {
        return $beforeId === null || $beforeId === '' ? [] : ['beforeId' => $beforeId];
    }
}
