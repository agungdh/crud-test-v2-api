<?php

use App\Support\S3\ConditionalWriteSignatureProvider;
use Aws\S3\S3Client;

it('signs the if-none-match header into presigned upload urls', function () {
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
    ]);

    $request = $client->createPresignedRequest($command, '+5 minutes');

    expect($request->getHeaderLine('If-None-Match'))->toBe('*')
        ->and((string) $request->getUri())->toContain('X-Amz-SignedHeaders=host%3Bif-none-match');
});
