<?php

namespace App\Support\S3;

use Aws\Signature\S3SignatureV4;

/**
 * Sign conditional write and content-length headers into presigned S3 requests.
 *
 * The AWS SDK blacklists if-none-match and content-length when presigning
 * because proxies are allowed to rewrite conditional headers. Both headers
 * must stay in the signature: if-none-match makes the storage server reject a
 * second upload to the same key, and content-length makes it reject a body
 * whose size differs from the size approved when the URL was created.
 */
final class ConditionalWriteSignatureV4 extends S3SignatureV4
{
    /**
     * @return array<string, bool>
     */
    protected function getHeaderBlacklist(): array
    {
        $blacklist = parent::getHeaderBlacklist();

        unset($blacklist['if-none-match'], $blacklist['content-length']);

        return $blacklist;
    }
}
