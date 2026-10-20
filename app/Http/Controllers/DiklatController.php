<?php

namespace App\Http\Controllers;

use App\Http\Requests\Diklat\ListDiklatRequest;
use App\Http\Requests\Diklat\StoreDiklatRequest;
use App\Http\Requests\Diklat\UpdateDiklatRequest;
use App\Http\Resources\DiklatResource;
use App\Models\Diklat;
use App\Services\DiklatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DiklatController extends Controller
{
    public function __construct(private DiklatService $diklatService)
    {
        //
    }

    public function index(ListDiklatRequest $request): AnonymousResourceCollection
    {
        $page = $this->diklatService->paginate($request->user(), $request->validated());

        return DiklatResource::collection($page->items)
            ->additional(['meta' => ['next_cursor' => $page->nextCursor]]);
    }

    public function store(StoreDiklatRequest $request): JsonResponse
    {
        $diklat = $this->diklatService->create($request->user(), $request->validated());

        return DiklatResource::make($diklat)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Diklat $diklat): DiklatResource
    {
        return DiklatResource::make(
            $this->diklatService->show($request->user(), $diklat),
        );
    }

    public function update(UpdateDiklatRequest $request, Diklat $diklat): DiklatResource
    {
        return DiklatResource::make(
            $this->diklatService->update($request->user(), $diklat, $request->validated()),
        );
    }

    public function destroy(Request $request, Diklat $diklat): JsonResponse
    {
        $this->diklatService->delete($request->user(), $diklat);

        return response()->json(null, 204);
    }
}
