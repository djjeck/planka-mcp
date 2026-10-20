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
#[Description("Update a card's properties (name, description, due date, completion status, cover, stopwatch).")]
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
            'stopwatch' => $schema->object([
                'startedAt' => $schema->string()
                    ->nullable()
                    ->format('date-time')
                    ->description('When it was started (ISO 8601), or null while stopped'),
                'total' => $schema->integer()
                    ->description('Seconds it ran before startedAt (all of its time while stopped)'),
            ])
                ->nullable()
                ->description('The card\'s stopwatch, replaced whole: start it with startedAt now and total kept, stop it with startedAt null and the elapsed seconds added to total. An empty object {} removes it.'),
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
            $stopwatch = StopwatchArgument::from($request->get('stopwatch'));

            if ($name === null && $description === null && $dueDate === null && $isClosed === null && $coverAttachmentId === null && $stopwatch === null) {
                throw new ValidationException('At least one field (name, description, dueDate, isClosed, coverAttachmentId, or stopwatch) must be provided.');
            }

            $apiKey = $this->apiKeyProvider->getApiKey();

            return Response::json($this->cardService->updateCard($apiKey, $cardId, $name, $description, $dueDate, $isClosed, $coverAttachmentId, $stopwatch));
        } catch (\Throwable $e) {
            return Response::error($e->getMessage());
        }
    }
}
