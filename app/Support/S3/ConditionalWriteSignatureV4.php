<?php

namespace App\Support\S3;

use Aws\Signature\S3SignatureV4;

/**
 * Sign conditional write headers into presigned S3 requests.
 *
 * The AWS SDK blacklists if-none-match when presigning because proxies are
 * allowed to rewrite conditional headers. A presigned upload URL depends on
 * that header being signed so the storage server rejects a second upload to
 * the same key, therefore the header must stay in the signature.
 */
final class ConditionalWriteSignatureV4 extends S3SignatureV4
{
    /**
     * @return array<string, bool>
     */
    protected function getHeaderBlacklist(): array
    {
        $blacklist = parent::getHeaderBlacklist();

        unset($blacklist['if-none-match']);

        return $blacklist;
    }
}
