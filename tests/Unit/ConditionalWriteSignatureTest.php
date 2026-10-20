<?php

use App\Support\S3\ConditionalWriteSignatureProvider;
use Aws\S3\S3Client;

it('signs the if-none-match and content-length headers into presigned upload urls', function () {
    $client = new S3Client([
        'version' => 'latest',
        'region' => 'us-east-1',
        'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
        'endpoint' => 'http://127.0.0.1:9000',
        'use_path_style_endpoint' => true,
        'signature_provider' => [ConditionalWriteSignatureProvider::class, 'make'],
    ]);

    $command = $client->getCommand('PutObject', [
        'Bucket' => 'temp-bucket',
        'Key' => 'upload/file.pdf',
        'IfNoneMatch' => '*',
        'ContentLength' => 1024,
    ]);

    $request = $client->createPresignedRequest($command, '+5 minutes');

    expect($request->getHeaderLine('If-None-Match'))->toBe('*')
        ->and($request->getHeaderLine('Content-Length'))->toBe('1024')
        ->and((string) $request->getUri())->toContain('X-Amz-SignedHeaders=content-length%3Bhost%3Bif-none-match');
});
