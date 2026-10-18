<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Domain\Attachment\AttachmentServiceInterface;
use App\Exception\ValidationException;
use App\Http\ApiKeyProviderInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('planka_upload_attachment')]
#[Description('Upload a file attachment to a card, from a URL (allowed hosts only) or from a file in the configured upload directory. An image uploaded to a card without a cover becomes its cover.')]
final class UploadAttachmentTool extends Tool
{
    public function __construct(
        private readonly AttachmentServiceInterface $attachmentService,
        private readonly ApiKeyProviderInterface $apiKeyProvider,
        private readonly string $uploadDir,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'cardId' => $schema->string()
                ->required()
                ->description('The card ID to attach the file to (from planka_get_board or planka_get_card)'),
            'url' => $schema->string()
                ->description('URL to download the file from; its host must be allowed by the server. Give either url or filePath.'),
            'filePath' => $schema->string()
                ->description('Absolute path to the file on the MCP server filesystem, inside its upload directory. Give either url or filePath.'),
            'filename' => $schema->string()
                ->required()
                ->description('Filename to use for the attachment (e.g. report.pdf)'),
        ];
    }

    public function handle(Request $request): Response
    {
        try {
            $cardId = (string) $request->get('cardId', '');
            $url = trim((string) $request->get('url', ''));
            $filePath = (string) $request->get('filePath', '');
            $filename = (string) $request->get('filename', '');

            if (trim($filename) === '') {
                throw new ValidationException('filename cannot be empty.');
            }
            if ($url !== '' && trim($filePath) !== '') {
                throw new ValidationException('Give either url or filePath, not both.');
            }

            if ($url !== '') {
                $result = $this->attachmentService->uploadAttachmentFromUrl(
                    $this->apiKeyProvider->getApiKey(),
                    $cardId,
                    $url,
                    $filename,
                );

                return Response::json($result);
            }

            if (trim($filePath) === '') {
                throw new ValidationException('filePath cannot be empty (or give a url).');
            }

            $realPath = realpath($filePath);
            if ($realPath === false) {
                throw new ValidationException('File not found or inaccessible: ' . $filePath);
            }
            $allowedBase = rtrim($this->uploadDir, '/\\') . \DIRECTORY_SEPARATOR;
            if (!str_starts_with($realPath . \DIRECTORY_SEPARATOR, $allowedBase)) {
                throw new ValidationException('File path must be within the configured upload directory.');
            }

            $apiKey = $this->apiKeyProvider->getApiKey();

            $result = $this->attachmentService->uploadAttachment($apiKey, $cardId, $filePath, $filename);

            return Response::json($result);
        } catch (\Throwable $e) {
            return Response::error($e->getMessage());
        }
    }
}
