<?php

namespace App\Console\Commands;

use App\Models\BerkasUpload;
use App\Services\BerkasUploadService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('berkas-upload:prune')]
#[Description('Delete expired temporary uploads and their objects')]
class PruneBerkasUploads extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BerkasUploadService $berkasUploadService): int
    {
        $pruned = 0;

        BerkasUpload::query()
            ->whereNull('consumed_at')
            ->where('purge_at', '<=', now())
            ->chunkById(100, function ($uploads) use ($berkasUploadService, &$pruned): void {
                foreach ($uploads as $upload) {
                    $berkasUploadService->deleteTemporary($upload);
                    $upload->delete();

                    $pruned++;
                }
            });

        $this->info("Pruned {$pruned} expired temporary upload(s).");

        return self::SUCCESS;
    }
}
