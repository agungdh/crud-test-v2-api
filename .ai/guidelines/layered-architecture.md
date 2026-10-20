# Layered Architecture

This project keeps HTTP concerns, business logic, and response shaping in separate layers. Every API feature follows `Route → Form Request → Controller → Service → Model`, and the response leaves through an API Resource.

## Layer responsibilities

| Layer | Owns | Must not do |
| --- | --- | --- |
| Form Request (`app/Http/Requests/{Domain}`) | Validation rules and authorization | Business logic, database writes |
| Controller (`app/Http/Controllers`) | Orchestration: resolve input, call one Service use-case, return the response | Queries, `DB::transaction()`, hashing, validation, business rules |
| Service (`app/Services`) | Business logic and orchestration | `Request`, `response()`, `abort()`, `session()`, `Auth`, Resources |
| API Resource (`app/Http/Resources`) | Response presentation | Queries, business logic, writes |
| Model (`app/Models`) | Persistence, relationships, casts, scopes | Orchestration, HTTP concerns |

Do not add repositories, action classes, or separate DTO folders unless the user explicitly asks for them.

## File layout (layered, requests grouped by domain)

- Controllers: `app/Http/Controllers/PostController.php` — one resource controller per model, flat.
- Requests: `app/Http/Requests/Post/StorePostRequest.php` and `UpdatePostRequest.php` — one folder per domain, one class per endpoint that accepts input. The namespace matches the folder, for example `App\Http\Requests\Post`.
- Services: `app/Services/PostService.php` — one per domain, flat, suffixed `Service`, with use-case method names (`paginate`, `create`, `update`, `delete`).
- Resources: `app/Http/Resources/PostResource.php` — one per model, flat.

Only Form Requests are grouped by domain. Controllers, Services, Models, and Resources stay in their flat layer folders.

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
- Opaque fields that cannot be replaced blindly use `sometimes|nullable` instead, and the Form Request says why. Two cases qualify today:
  - Password: omitted or `null` leaves the stored password unchanged.
  - File uploads: omitted keeps the stored file, a new file replaces it, and an explicit `null` removes it. Multipart clients cannot reliably send an empty file part, so `present` would reject a normal "attachment untouched" submission. Multipart uploads arrive as `POST` with `_method=PUT`, because PHP does not populate files for a real `PUT`.
- Do not stretch `sometimes|nullable` into a partial-update endpoint. Every other field stays `required` or `present|nullable`; the endpoint still replaces the record and only treats those opaque fields as keep-or-replace.
- The Service writes every validated key, including explicit `null` values, inside one `DB::transaction()`, and handles the file follow-up: delete the replaced or removed object after the database write commits.
- Feature tests assert that `PATCH` returns `405` and that a payload missing a nullable field returns `422`. For `sometimes` fields, cover all three states: omitted keeps the stored value, a value replaces it, `null` clears it.

The `layered-architecture` skill holds the full skeletons to copy and the review checklist.
