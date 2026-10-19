# Layered Architecture

This project keeps HTTP concerns, business logic, and response shaping in separate layers. Every API feature follows `Route → Form Request → Controller → Service → Model`, and the response leaves through an API Resource.

## Layer responsibilities

| Layer | Owns | Must not do |
| --- | --- | --- |
| Form Request (`app/Http/Requests`) | Validation rules and authorization | Business logic, database writes |
| Controller (`app/Http/Controllers`) | Orchestration: resolve input, call one Service use-case, return the response | Queries, `DB::transaction()`, hashing, validation, business rules |
| Service (`app/Services`) | Business logic and orchestration | `Request`, `response()`, `abort()`, `session()`, `Auth`, Resources |
| API Resource (`app/Http/Resources`) | Response presentation | Queries, business logic, writes |
| Model (`app/Models`) | Persistence, relationships, casts, scopes | Orchestration, HTTP concerns |

Do not add repositories, action classes, or separate DTO folders unless the user explicitly asks for them.

## File layout (flat)

- Controllers: `app/Http/Controllers/PostController.php` — one resource controller per model.
- Requests: `app/Http/Requests/StorePostRequest.php` and `UpdatePostRequest.php` — one class per endpoint that accepts input.
- Services: `app/Services/PostService.php` — one per domain, suffixed `Service`, with use-case method names (`paginate`, `create`, `update`, `delete`).
- Resources: `app/Http/Resources/PostResource.php` — one per model.

## Rules

- Controllers stay thin: inject the Service through the constructor, call one use-case method, and return a Resource or `JsonResponse`. No queries, transactions, hashing, validation, or business rules here.
- Every endpoint that accepts input has its own Form Request holding `rules()` and `authorize()`. Read input with `validated()` only, never `all()` or `input()`, and pass that array to the Service. No inline `$request->validate()`.
- Services receive the `validated()` array, own business logic, wrap multi-write work in `DB::transaction()`, and return models, collections, or paginators. Type the expected array shape in the PHPDoc.
- Responses always go through a Resource; controllers never return models, arrays, or paginators directly. Keep the default `data` wrapping.
- Validation failures return `422`, authorization returns `403` (Policy or Form Request `authorize()`), and missing route-bound models return `404`. Do not catch these in the controller.
- Follow the existing `/api` prefix; the app does not version its routes.

## Update endpoints (PUT only)

- Updates use `PUT`, never `PATCH`. `Route::apiResource()` registers `PUT|PATCH` for `update`, so exclude it and register the PUT route explicitly:

```php
Route::apiResource('posts', PostController::class)->except('update');
Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
```

- `PUT` is a full replacement. Every mutable field must be present in the payload: `required` for mandatory fields and `present|nullable` for nullable fields. An omitted field must fail validation with `422`, never silently keep its previous value.
- Password is the exception: `sometimes|nullable`; when omitted or `null` the existing password stays unchanged.
- The Service writes every validated key, including explicit `null` values, inside one `DB::transaction()`.
- Feature tests assert that `PATCH` returns `405` and that a payload missing a nullable field returns `422`.

The `layered-architecture` skill holds the full skeletons to copy and the review checklist.
