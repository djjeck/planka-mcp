<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Domain\Card\CardServiceInterface;
use App\Exception\ValidationException;
use App\Http\ApiKeyProviderInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('planka_update_card')]
#[Description("Update a card's properties (name, description, due date, completion status).")]
final class UpdateCardTool extends Tool
{
    public function __construct(
        private readonly CardServiceInterface $cardService,
        private readonly ApiKeyProviderInterface $apiKeyProvider,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'cardId' => $schema->string()
                ->required()
                ->description('The card ID (from planka_get_board or planka_get_card)'),
            'name' => $schema->string()
                ->nullable()
                ->description('New card title'),
            'description' => $schema->string()
                ->nullable()
                ->description('New description (empty string to clear)'),
            'dueDate' => $schema->string()
                ->nullable()
                ->description('New due date in ISO format (empty string to clear)')
                ->format('date-time'),
            'isClosed' => $schema->boolean()
                ->nullable()
                ->description('Close or reopen a card'),
            'coverAttachmentId' => $schema->string()
                ->nullable()
                ->description('Image attachment on this card to show as its cover (empty string to clear). An image uploaded to a card without a cover becomes its cover on its own.'),
        ];
    }

    public function handle(Request $request): Response
    {
        try {
            $cardId = (string) $request->get('cardId', '');
            /** @var ?string $name */
            $name = $request->get('name');
            /** @var ?string $description */
            $description = $request->get('description');
            /** @var ?string $dueDate */
            $dueDate = $request->get('dueDate');
            /** @var ?bool $isClosed */
            $isClosed = $request->get('isClosed');
            /** @var ?string $coverAttachmentId */
            $coverAttachmentId = $request->get('coverAttachmentId');

            if ($name === null && $description === null && $dueDate === null && $isClosed === null && $coverAttachmentId === null) {
                throw new ValidationException('At least one field (name, description, dueDate, isClosed, or coverAttachmentId) must be provided.');
            }

            $apiKey = $this->apiKeyProvider->getApiKey();

            return Response::json($this->cardService->updateCard($apiKey, $cardId, $name, $description, $dueDate, $isClosed, $coverAttachmentId));
        } catch (\Throwable $e) {
            return Response::error($e->getMessage());
        }
    }
}
