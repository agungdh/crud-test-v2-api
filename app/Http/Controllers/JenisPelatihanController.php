<?php

namespace App\Http\Controllers;

use App\Http\Requests\JenisPelatihan\ListJenisPelatihanRequest;
use App\Http\Requests\JenisPelatihan\StoreJenisPelatihanRequest;
use App\Http\Requests\JenisPelatihan\UpdateJenisPelatihanRequest;
use App\Http\Resources\JenisPelatihanResource;
use App\Models\JenisPelatihan;
use App\Services\JenisPelatihanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JenisPelatihanController extends Controller
{
    public function __construct(private JenisPelatihanService $jenisPelatihanService)
    {
        //
    }

    public function index(ListJenisPelatihanRequest $request): AnonymousResourceCollection
    {
        return JenisPelatihanResource::collection(
            $this->jenisPelatihanService->paginate($request->validated()),
        );
    }

    public function store(StoreJenisPelatihanRequest $request): JsonResponse
    {
        $jenisPelatihan = $this->jenisPelatihanService->create($request->validated());

        return JenisPelatihanResource::make($jenisPelatihan)
            ->response()
            ->setStatusCode(201);
    }

    public function show(JenisPelatihan $jenisPelatihan): JenisPelatihanResource
    {
        return JenisPelatihanResource::make($jenisPelatihan);
    }

    public function update(UpdateJenisPelatihanRequest $request, JenisPelatihan $jenisPelatihan): JenisPelatihanResource
    {
        return JenisPelatihanResource::make(
            $this->jenisPelatihanService->update($jenisPelatihan, $request->validated()),
        );
    }

    public function destroy(JenisPelatihan $jenisPelatihan): JsonResponse
    {
        $this->jenisPelatihanService->delete($jenisPelatihan);

        return response()->json(null, 204);
    }
}
