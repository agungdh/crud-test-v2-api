<?php

namespace App\Support\S3;

use Aws\Signature\SignatureInterface;
use Aws\Signature\SignatureProvider;

final class ConditionalWriteSignatureProvider
{
    /**
     * Build the signer used by the temporary upload disk.
     */
    public static function make(string $version, string $service, string $region): SignatureInterface
    {
        if (in_array($version, ['v4', 's3v4'], true)) {
            return new ConditionalWriteSignatureV4($service, $region);
        }

        return (SignatureProvider::defaultProvider())($version, $service, $region);
    }
}
