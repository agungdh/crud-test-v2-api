<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBerkasUploadRequest;
use App\Http\Resources\BerkasUploadResource;
use App\Models\BerkasUpload;
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
        $upload = $this->berkasUploadService->presign($request->user(), $request->validated());

        return BerkasUploadResource::make($upload)
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(BerkasUpload $berkasUpload): JsonResponse
    {
        $this->berkasUploadService->delete($berkasUpload);

        return response()->json(null, 204);
    }
}
