<?php

namespace App\Http\Controllers;

use App\Http\Requests\Berkas\StoreBerkasUploadRequest;
use App\Http\Resources\BerkasUploadResource;
use App\Services\BerkasUploadService;
use Illuminate\Http\JsonResponse;

class BerkasUploadController extends Controller
{
    public function __construct(private BerkasUploadService $berkasUploadService)
    {
        //
    }

    public function store(StoreBerkasUploadRequest $request): JsonResponse
    {
        $upload = $this->berkasUploadService->presign($request->validated());

        return BerkasUploadResource::make($upload)
            ->response()
            ->setStatusCode(201);
    }
}
