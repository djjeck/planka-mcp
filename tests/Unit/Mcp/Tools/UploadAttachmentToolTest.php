<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp\Tools;

use App\Domain\Attachment\AttachmentServiceInterface;
use App\Exception\ValidationException;
use App\Http\ApiKeyProviderInterface;
use App\Mcp\Tools\UploadAttachmentTool;
use Laravel\Mcp\Request;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UploadAttachmentToolTest extends TestCase
{
    private AttachmentServiceInterface&MockObject $attachmentService;
    private ApiKeyProviderInterface&MockObject $apiKeyProvider;
    private UploadAttachmentTool $tool;
    private string $uploadDir;

    protected function setUp(): void
    {
        $this->attachmentService = $this->createMock(AttachmentServiceInterface::class);
        $this->apiKeyProvider = $this->createMock(ApiKeyProviderInterface::class);
        $this->uploadDir = realpath(sys_get_temp_dir()) ?: sys_get_temp_dir();
        $this->tool = new UploadAttachmentTool($this->attachmentService, $this->apiKeyProvider, $this->uploadDir);
    }

    private function makeRequest(array $params = []): Request
    {
        return new Request(arguments: $params);
    }

    public function testHandleSuccess(): void
    {
        $tmpFile = tempnam($this->uploadDir, 'test_');
        file_put_contents($tmpFile, 'content');

        try {
            $this->apiKeyProvider->method('getApiKey')->willReturn('test-key');
            $this->attachmentService->method('uploadAttachment')->willReturn(['item' => ['id' => 'a1']]);

            $response = $this->tool->handle($this->makeRequest([
                'cardId' => 'c1',
                'filePath' => $tmpFile,
                'filename' => 'test.txt',
            ]));
            $this->assertFalse($response->isError());
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testHandleEmptyFilePath(): void
    {
        $response = $this->tool->handle($this->makeRequest(['cardId' => 'c1', 'filePath' => '', 'filename' => 'f.txt']));
        $this->assertTrue($response->isError());
        $this->assertStringContainsString('filePath cannot be empty', (string) $response->content());
    }

    public function testHandleEmptyFilename(): void
    {
        $response = $this->tool->handle($this->makeRequest(['cardId' => 'c1', 'filePath' => '/tmp/f', 'filename' => '']));
        $this->assertTrue($response->isError());
        $this->assertStringContainsString('filename cannot be empty', (string) $response->content());
    }

    public function testHandleFileNotFound(): void
    {
        $response = $this->tool->handle($this->makeRequest([
            'cardId' => 'c1',
            'filePath' => '/nonexistent/file.txt',
            'filename' => 'file.txt',
        ]));
        $this->assertTrue($response->isError());
        $this->assertStringContainsString('File not found', (string) $response->content());
    }

    public function testHandleFileOutsideUploadDir(): void
    {
        $tool = new UploadAttachmentTool($this->attachmentService, $this->apiKeyProvider, '/nonexistent/upload/dir');

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'content');

        try {
            $response = $tool->handle($this->makeRequest([
                'cardId' => 'c1',
                'filePath' => $tmpFile,
                'filename' => 'test.txt',
            ]));
            $this->assertTrue($response->isError());
            $this->assertStringContainsString('upload directory', (string) $response->content());
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testHandleAuthError(): void
    {
        $tmpFile = tempnam($this->uploadDir, 'test_');
        file_put_contents($tmpFile, 'content');

        try {
            $this->apiKeyProvider->method('getApiKey')->willThrowException(new ValidationException('No API key'));

            $response = $this->tool->handle($this->makeRequest([
                'cardId' => 'c1',
                'filePath' => $tmpFile,
                'filename' => 'test.txt',
            ]));
            $this->assertTrue($response->isError());
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testHandleUploadsFromAUrl(): void
    {
        $this->apiKeyProvider->method('getApiKey')->willReturn('test-key');
        $this->attachmentService
            ->expects($this->once())
            ->method('uploadAttachmentFromUrl')
            ->with('test-key', 'c1', 'http://human-dm:8900/files/abc.sig', 'photo.jpg')
            ->willReturn(['item' => ['id' => 'a1']]);
        $this->attachmentService->expects($this->never())->method('uploadAttachment');

        $response = $this->tool->handle($this->makeRequest([
            'cardId' => 'c1',
            'url' => 'http://human-dm:8900/files/abc.sig',
            'filename' => 'photo.jpg',
        ]));
        $this->assertFalse($response->isError());
    }

    public function testHandleReportsAUrlUploadFailure(): void
    {
        $this->attachmentService
            ->method('uploadAttachmentFromUrl')
            ->willThrowException(new ValidationException('Host "evil" is not allowed'));

        $response = $this->tool->handle($this->makeRequest([
            'cardId' => 'c1',
            'url' => 'http://evil/x.jpg',
            'filename' => 'x.jpg',
        ]));
        $this->assertTrue($response->isError());
        $this->assertStringContainsString('not allowed', (string) $response->content());
    }

    public function testHandleRefusesBothUrlAndFilePath(): void
    {
        $this->attachmentService->expects($this->never())->method('uploadAttachmentFromUrl');
        $this->attachmentService->expects($this->never())->method('uploadAttachment');

        $response = $this->tool->handle($this->makeRequest([
            'cardId' => 'c1',
            'url' => 'http://human-dm:8900/files/abc.sig',
            'filePath' => '/tmp/f.jpg',
            'filename' => 'f.jpg',
        ]));
        $this->assertTrue($response->isError());
        $this->assertStringContainsString('either url or filePath', (string) $response->content());
    }

    public function testSchemaReturnsArray(): void
    {
        $data = $this->tool->toArray();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('inputSchema', $data);
    }
}
