<?php

declare(strict_types=1);

namespace App\Domain\Action;

interface ActionServiceInterface
{
    /** @return array<mixed> */
    public function getBoardActions(string $apiKey, string $boardId, ?string $beforeId = null): array;

    /** @return array<mixed> */
    public function getCardActions(string $apiKey, string $cardId, ?string $beforeId = null): array;
}
