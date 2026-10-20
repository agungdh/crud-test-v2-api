<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pegawai\ListPegawaiRequest;
use App\Http\Requests\Pegawai\StorePegawaiRequest;
use App\Http\Requests\Pegawai\UpdatePegawaiRequest;
use App\Http\Resources\PegawaiResource;
use App\Models\Pegawai;
use App\Services\PegawaiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PegawaiController extends Controller
{
    public function __construct(private PegawaiService $pegawaiService)
    {
        //
    }

    public function index(ListPegawaiRequest $request): AnonymousResourceCollection
    {
        $page = $this->pegawaiService->paginate($request->validated());

        return PegawaiResource::collection($page->items)
            ->additional(['meta' => ['next_cursor' => $page->nextCursor]]);
    }

    public function store(StorePegawaiRequest $request): JsonResponse
    {
        $pegawai = $this->pegawaiService->create($request->validated());

        return PegawaiResource::make($pegawai)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Pegawai $pegawai): PegawaiResource
    {
        return PegawaiResource::make(
            $this->pegawaiService->show($pegawai),
        );
    }

    public function update(UpdatePegawaiRequest $request, Pegawai $pegawai): PegawaiResource
    {
        return PegawaiResource::make(
            $this->pegawaiService->update($pegawai, $request->validated()),
        );
    }

    public function destroy(Pegawai $pegawai): JsonResponse
    {
        $this->pegawaiService->delete($pegawai);

        return response()->json(null, 204);
    }
}
