<?php

namespace App\Data\Api;

class PaginatedCollectionData
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $currentPage,
        public readonly int $lastPage,
        public readonly array $meta = [],
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>|null  $meta
     */
    public static function fromApi(array $items, ?array $meta): self
    {
        $currentPage = max(1, (int) ($meta['current_page'] ?? 1));
        $lastPage = max(1, (int) ($meta['last_page'] ?? 1));

        return new self(
            items: $items,
            total: max(0, (int) ($meta['total'] ?? count($items))),
            currentPage: min($currentPage, $lastPage),
            lastPage: $lastPage,
            meta: $meta ?? [],
        );
    }
}
