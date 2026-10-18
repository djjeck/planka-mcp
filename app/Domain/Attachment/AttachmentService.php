<?php

declare(strict_types=1);

namespace App\Domain\Attachment;

use App\Http\RemoteFileFetcherInterface;
use App\Planka\PlankaClientInterface;

final class AttachmentService implements AttachmentServiceInterface
{
    public function __construct(
        private readonly PlankaClientInterface $plankaClient,
        private readonly RemoteFileFetcherInterface $remoteFileFetcher,
    ) {}

    /** @return array<mixed> */
    public function uploadAttachment(string $apiKey, string $cardId, string $filePath, string $filename): array
    {
        return $this->plankaClient->postMultipart($apiKey, '/api/cards/' . $cardId . '/attachments', [], $filePath, $filename);
    }

    /** @return array<mixed> */
    public function uploadAttachmentFromUrl(string $apiKey, string $cardId, string $url, string $filename): array
    {
        $contents = $this->remoteFileFetcher->fetch($url);

        return $this->plankaClient->postMultipartContents($apiKey, '/api/cards/' . $cardId . '/attachments', [], $contents, $filename);
    }

    /** @return array<mixed> */
    public function updateAttachment(string $apiKey, string $attachmentId, ?string $name = null): array
    {
        $body = [];
        if ($name !== null) {
            $body['name'] = $name;
        }

        return $this->plankaClient->patch($apiKey, '/api/attachments/' . $attachmentId, $body);
    }

    /** @return array<mixed> */
    public function deleteAttachment(string $apiKey, string $attachmentId): array
    {
        return $this->plankaClient->delete($apiKey, '/api/attachments/' . $attachmentId);
    }
}
