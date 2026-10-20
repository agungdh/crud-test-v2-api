---
name: layered-architecture
description: "Use when adding or changing any API feature in this project: new endpoints, CRUD resources, controllers, Form Requests, Services, or API Resources. Defines the layer contract (Request validates, Controller orchestrates, Service holds logic, Resource shapes the response), the domain-grouped Form Request layout, the code skeletons to copy, error and authorization handling, and the review checklist before finishing."
---

# Layered Architecture

This project keeps HTTP concerns, business logic, and response shaping in separate layers. Follow this contract for every new endpoint and every change to an existing one. For exact framework syntax, verify with `search-docs`; this skill defines where code belongs, not the full Laravel API.

## Layer contract

A request flows `Route → Form Request → Controller → Service → Model`, and the response leaves through an API Resource.

| Layer | Owns | Must not do |
| --- | --- | --- |
| Form Request (`app/Http/Requests/{Domain}`) | Validation rules and authorization | Business logic, database writes |
| Controller (`app/Http/Controllers`) | Orchestration: resolve input, call one Service use-case, return the response | Queries, `DB::transaction()`, hashing, validation, business rules |
| Service (`app/Services`) | Business logic and orchestration | HTTP: `Request`, `response()`, `abort()`, `session()`, `Auth`, Resources |
| API Resource (`app/Http/Resources`) | Response presentation | Queries, business logic, writes |
| Model (`app/Models`) | Persistence, relationships, casts, scopes | Orchestration, HTTP concerns |

Do not introduce repositories, action classes, or separate DTO folders unless the user explicitly asks for them.

## Layout and naming (requests grouped by domain)

- Controllers: `app/Http/Controllers/PostController.php` — one resource controller per model, RESTful methods `index`, `show`, `store`, `update`, `destroy`, flat.
- Requests: `app/Http/Requests/Post/StorePostRequest.php`, `app/Http/Requests/Post/UpdatePostRequest.php` — one folder per domain, one class per endpoint that accepts input. The namespace matches the folder, for example `App\Http\Requests\Post`.
- Services: `app/Services/PostService.php` — one service per domain, flat, suffixed `Service`. Method names are use-case verbs: `paginate`, `create`, `update`, `delete`; never a generic `handle` or `execute` catch-all.
- Resources: `app/Http/Resources/PostResource.php` — one per model, flat.

Only Form Requests are grouped by domain. Controllers, Services, Models, and Resources stay in their flat layer folders.

Create files with Artisan, not by hand:

```bash
php artisan make:controller PostController --resource --model=Post --no-interaction
php artisan make:request Post/StorePostRequest --no-interaction
php artisan make:request Post/UpdatePostRequest --no-interaction
php artisan make:class Services/PostService --no-interaction
php artisan make:resource PostResource --no-interaction
```

Updates use `PUT` only. `Route::apiResource()` maps `update` to `PUT|PATCH`, so exclude it and register the PUT route explicitly:

```php
Route::apiResource('posts', PostController::class)->except('update');
Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
```

## Input contract

The Service receives the output of `validated()`, never the `Request` object. Document the expected shape with an array shape in the PHPDoc so the type survives the array boundary.

```php
// Controller
$post = $this->postService->create($request->validated());

// Service
/**
 * @param  array{title: string, body: string}  $data
 */
public function create(array $data): Post
```

Every endpoint that accepts input gets its own Form Request, including list endpoints with filters. Endpoints that only use route-model binding (like `show`) do not need one.

## Cursor pagination (list endpoints)

Collection endpoints use keyset pagination. Never use `LengthAwarePaginator`, `simplePaginate`, `cursorPaginate`, or a `per_page` input.

- Validate `cursor` as `nullable|uuid`. A malformed value returns `422`.
- The page size is fixed at `CursorPage::PER_PAGE` (20) and cannot be changed by the client.
- The cursor is the public UUID of the last item on the current page, never an encoded Laravel cursor and never the numeric `id`.
- Order `id` descending. The default and fallback is `orderByDesc('id')`, and `CursorPage` keysets on the primary key only. An endpoint that genuinely needs another business order must add a matching keyset implementation, never just an `orderBy` on top of the id keyset.
- Resolve the cursor through the same filtered and scoped query the list uses, then apply `where('id', '<', $cursorId)`. A UUID that is missing, soft-deleted, outside the search/scope, or owned by another user returns `404`.
- Use `App\Support\CursorPage::fromQuery($query, $filters['cursor'] ?? null)`; it fetches `PER_PAGE + 1` rows to detect the next page.
- Return `data` plus `meta.next_cursor` only, with no `links`, `meta.total`, `prev_cursor`, or previous URL. A full page ends with the last item's UUID; the final page returns `null`.

```php
// Form Request
return [
    'search' => ['nullable', 'string', 'max:255'],
    'cursor' => ['nullable', 'uuid'],
];

// Service
$query = Post::query()
    ->when($filters['search'] ?? null, ...);

return CursorPage::fromQuery($query, $filters['cursor'] ?? null);

// Controller
$page = $this->postService->paginate($request->validated());

return PostResource::collection($page->items)
    ->additional(['meta' => ['next_cursor' => $page->nextCursor]]);
```

Cover the first page, the next page via `cursor`, the final page (`meta.next_cursor` is `null`), a fixed size of 20 even when `per_page` is sent, an invalid cursor (`422`), and an unknown or out-of-scope cursor (`404`).

## Update semantics (PUT only)

`PUT` is a full replacement, not a partial one:

- Mandatory fields use `required`.
- Nullable fields use `present|nullable`, so the key must exist but the value may be `null`. A missing key fails with `422` instead of silently keeping the old value.
- Opaque fields that cannot be replaced blindly use `sometimes|nullable` instead: password and file uploads.
  - Password: omitted or `null` leaves the stored password unchanged.
  - File upload: omitted keeps the stored file, a new file replaces it, and `null` removes it. Multipart clients cannot reliably send an empty file part, so `present` would reject a normal "attachment untouched" submission. Multipart uploads arrive as `POST` with `_method=PUT`, because PHP does not populate files for a real `PUT`.
- Only these keep-or-replace fields skip the full-replacement rule. Everything else stays `required` or `present|nullable`, so the endpoint still replaces the record.
- The Service writes every validated key, including explicit `null` values, inside one `DB::transaction()`, and for files deletes the replaced or removed object after the database write commits.

```php
// UpdatePostRequest
return [
    'title' => ['required', 'string', 'max:255'],
    'body' => ['required', 'string'],
    'published_at' => ['present', 'nullable', 'date'],
    // keep-or-replace: omitted keeps the current file, a value replaces it, null removes it
    'attachment' => ['sometimes', 'nullable', 'file', 'max:10240'],
];
```

Add a feature test asserting `PATCH` returns `405`, that omitting a nullable field returns `422`, and that a `sometimes` field keeps its value when omitted, replaces it when sent, and clears it when `null`.

## Form Request skeleton

Keep `rules()` and `authorize()` here. Authorization for a model ability delegates to its policy.

```php
<?php

namespace App\Http\Requests\Post;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Post::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ];
    }
}
```

## Controller skeleton

The controller reads validated input, calls exactly one Service method per action, and wraps the result in a Resource.

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\ListPostsRequest;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    public function __construct(private PostService $postService)
    {
        //
    }

    public function index(ListPostsRequest $request): AnonymousResourceCollection
    {
        $page = $this->postService->paginate($request->validated());

        return PostResource::collection($page->items)
            ->additional(['meta' => ['next_cursor' => $page->nextCursor]]);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->postService->create($request->validated());

        return PostResource::make($post)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Post $post): PostResource
    {
        return PostResource::make($post);
    }

    public function update(UpdatePostRequest $request, Post $post): PostResource
    {
        return PostResource::make(
            $this->postService->update($post, $request->validated()),
        );
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->postService->delete($post);

        return response()->json(null, 204);
    }
}
```

## Service skeleton

Services query models, own transactions, and return models, collections, or `CursorPage` results. Wrap multi-write work in `DB::transaction()`.

```php
<?php

namespace App\Services;

use App\Models\Post;
use App\Support\CursorPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PostService
{
    /**
     * @param  array{search?: string, cursor?: string}  $filters
     */
    public function paginate(array $filters): CursorPage
    {
        $query = Post::query()
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search): Builder => $query->where('title', 'like', "%{$search}%"),
            );

        return CursorPage::fromQuery($query, $filters['cursor'] ?? null);
    }

    /**
     * @param  array{title: string, body: string}  $data
     */
    public function create(array $data): Post
    {
        return Post::create($data);
    }

    /**
     * @param  array{title?: string, body?: string}  $data
     */
    public function update(Post $post, array $data): Post
    {
        return DB::transaction(function () use ($post, $data): Post {
            $post->update($data);

            return $post->refresh();
        });
    }

    public function delete(Post $post): void
    {
        $post->delete();
    }
}
```

## Resource skeleton

`toArray()` only presents already-loaded attributes and relations. Use `whenLoaded()` for relations so a missing eager load does not trigger a query.

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'author' => UserResource::make($this->whenLoaded('user')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

## Responses

- Every API response is shaped by a Resource. Controllers never return models, arrays, or paginators directly.
- Single resources are wrapped in `data` by default (`UserResource::make($user)`). Collection endpoints return `data` plus `meta.next_cursor`; do not add `links`, `meta.total`, or a previous cursor.
- Endpoints with a bespoke payload (like the login response) may use `response()->json([...])` while still embedding Resources for the model parts.
- Follow the existing `/api` prefix; the app does not version its routes.

## Errors and authorization

- Validation failures become `422` automatically through the Form Request; do not catch them.
- Authorization lives in the Form Request `authorize()` or a Policy, and returns `403`.
- Missing route-bound models become `404` automatically; do not add `findOrFail` in the controller.
- Throw `ValidationException` from a Service for domain rule failures that map to a field (the auth service does this for `auth.failed`).
- Do not wrap controller bodies in `try/catch` for the cases above.

## Testing

- Cover endpoints with feature tests that assert the HTTP contract (status, validation errors, JSON shape) and the persisted state.
- Reach Service behavior through the endpoint in most cases; add a focused test only for Service logic with distinct branches.

## Anti-patterns

- Querying Eloquent, checking credentials, or opening transactions in a controller.
- Reading `$request->all()` or `$request->input()` instead of `validated()`.
- Passing the `Request` object into a Service.
- Returning a model or raw array from a controller action.
- Putting `response()`, `abort()`, or Resource calls inside a Service.
- Adding a repository layer that only forwards to Eloquent.
- Registering `PATCH`, or using `sometimes` rules for anything other than the password and file upload fields.

## Checklist before finishing

1. Each endpoint has a Form Request when it accepts input, and the Service receives `validated()`.
2. The controller only orchestrates; behavior changes are implemented in a Service method.
3. Responses go through a Resource, and no model escapes the controller unwrapped.
4. `DB::transaction()` wraps multi-write Service methods.
5. Update routes accept `PUT` only; `PATCH` returns `405`, and `sometimes|nullable` appears only on password and file upload fields.
6. Failed validation returns `422`, unauthorized returns `403`, missing models return `404` without explicit handling.
7. List endpoints use `CursorPage` with a fixed 20 items, a UUID `cursor`, `orderByDesc('id')` fallback, and only `meta.next_cursor` in the response.
8. Feature tests cover the new behavior; `vendor/bin/pint --dirty --format agent` and the relevant tests pass.
