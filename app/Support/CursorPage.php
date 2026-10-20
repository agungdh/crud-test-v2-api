<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

final readonly class CursorPage
{
    /**
     * The fixed number of items returned per page.
     */
    public const PER_PAGE = 20;

    /**
     * @param  Collection<int, Model>  $items
     */
    public function __construct(
        public Collection $items,
        public ?string $nextCursor,
    ) {
        //
    }

    /**
     * Build a page by resolving the public UUID cursor to its numeric key.
     *
     * The cursor is looked up through the already filtered query so a cursor
     * from another search, scope, or owner is treated as missing.
     */
    public static function fromQuery(Builder $query, ?string $cursorUuid): self
    {
        $query->orderByDesc('id');

        if (filled($cursorUuid)) {
            $cursorId = (clone $query)->where('uuid', $cursorUuid)->value('id');

            if ($cursorId === null) {
                throw (new ModelNotFoundException)->setModel($query->getModel()::class, [$cursorUuid]);
            }

            $query->where('id', '<', $cursorId);
        }

        $items = $query->limit(self::PER_PAGE + 1)->get();
        $hasMore = $items->count() > self::PER_PAGE;

        $items = $items->take(self::PER_PAGE)->values();

        return new self($items, $hasMore ? $items->last()?->uuid : null);
    }
}
