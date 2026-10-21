<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Domain\Action\ActionServiceInterface;
use App\Exception\ValidationException;
use App\Http\ApiKeyProviderInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('planka_get_actions')]
#[Description('Get the activity log (actions) for a board or card, newest first, 50 at a time. To page back, pass the id of the oldest action received as beforeId.')]
final class GetActionsTool extends Tool
{
    public function __construct(
        private readonly ActionServiceInterface $actionService,
        private readonly ApiKeyProviderInterface $apiKeyProvider,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->required()
                ->description('Whether to get actions for a board or a card')
                ->enum(['board', 'card']),
            'id' => $schema->string()
                ->required()
                ->description('The board ID or card ID'),
            'beforeId' => $schema->string()
                ->nullable()
                ->description('Return the actions before this action ID (the oldest one from the previous page). Default: the newest'),
        ];
    }

    public function handle(Request $request): Response
    {
        try {
            $type = (string) $request->get('type', '');
            $id = (string) $request->get('id', '');
            $beforeId = $request->get('beforeId');
            $beforeId = $beforeId === null ? null : (string) $beforeId;
            $apiKey = $this->apiKeyProvider->getApiKey();

            $result = match ($type) {
                'board' => $this->actionService->getBoardActions($apiKey, $id, $beforeId),
                'card' => $this->actionService->getCardActions($apiKey, $id, $beforeId),
                default => throw new ValidationException(sprintf('Invalid type "%s". Must be: board, card', $type)),
            };

            return Response::json($result);
        } catch (\Throwable $e) {
            return Response::error($e->getMessage());
        }
    }
}
