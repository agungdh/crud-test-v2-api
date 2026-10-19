---
name: layered-architecture
description: "Use when adding or changing any API feature in this project: new endpoints, CRUD resources, controllers, Form Requests, Services, or API Resources. Defines the layer contract (Request validates, Controller orchestrates, Service holds logic, Resource shapes the response), the flat file layout and naming, the code skeletons to copy, error and authorization handling, and the review checklist before finishing."
---

# Layered Architecture

This project keeps HTTP concerns, business logic, and response shaping in separate layers. Follow this contract for every new endpoint and every change to an existing one. For exact framework syntax, verify with `search-docs`; this skill defines where code belongs, not the full Laravel API.

## Layer contract

A request flows `Route → Form Request → Controller → Service → Model`, and the response leaves through an API Resource.

| Layer | Owns | Must not do |
| --- | --- | --- |
| Form Request (`app/Http/Requests`) | Validation rules and authorization | Business logic, database writes |
| Controller (`app/Http/Controllers`) | Orchestration: resolve input, call one Service use-case, return the response | Queries, `DB::transaction()`, hashing, validation, business rules |
| Service (`app/Services`) | Business logic and orchestration | HTTP: `Request`, `response()`, `abort()`, `session()`, `Auth`, Resources |
| API Resource (`app/Http/Resources`) | Response presentation | Queries, business logic, writes |
| Model (`app/Models`) | Persistence, relationships, casts, scopes | Orchestration, HTTP concerns |

Do not introduce repositories, action classes, or separate DTO folders unless the user explicitly asks for them.

## Layout and naming (flat)

- Controllers: `app/Http/Controllers/PostController.php` — one resource controller per model, RESTful methods `index`, `show`, `store`, `update`, `destroy`.
- Requests: `app/Http/Requests/StorePostRequest.php`, `app/Http/Requests/UpdatePostRequest.php` — one class per endpoint that accepts input, flat, no domain subfolders.
- Services: `app/Services/PostService.php` — one service per domain, flat, suffixed `Service`. Method names are use-case verbs: `paginate`, `create`, `update`, `delete`; never a generic `handle` or `execute` catch-all.
- Resources: `app/Http/Resources/PostResource.php` — one per model.

Create files with Artisan, not by hand:

```bash
php artisan make:controller PostController --resource --model=Post --no-interaction
php artisan make:request StorePostRequest --no-interaction
php artisan make:request UpdatePostRequest --no-interaction
php artisan make:class Services/PostService --no-interaction
php artisan make:resource PostResource --no-interaction
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

## Form Request skeleton

Keep `rules()` and `authorize()` here. Authorization for a model ability delegates to its policy.

```php
<?php

namespace App\Http\Requests;

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

use App\Http\Requests\ListPostsRequest;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
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
        return PostResource::collection(
            $this->postService->paginate($request->validated()),
        );
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

Services query models, own transactions, and return models, collections, or paginators. Wrap multi-write work in `DB::transaction()`.

```php
<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PostService
{
    /**
     * @param  array{per_page?: int, search?: string}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Post::query()
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search): Builder => $query->where('title', 'like', "%{$search}%"),
            )
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
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
- Single resources are wrapped in `data` by default (`UserResource::make($user)`), and collections add pagination `links` and `meta`. Keep the default wrapping.
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

## Checklist before finishing

1. Each endpoint has a Form Request when it accepts input, and the Service receives `validated()`.
2. The controller only orchestrates; behavior changes are implemented in a Service method.
3. Responses go through a Resource, and no model escapes the controller unwrapped.
4. `DB::transaction()` wraps multi-write Service methods.
5. Failed validation returns `422`, unauthorized returns `403`, missing models return `404` without explicit handling.
6. Feature tests cover the new behavior; `vendor/bin/pint --dirty --format agent` and the relevant tests pass.
