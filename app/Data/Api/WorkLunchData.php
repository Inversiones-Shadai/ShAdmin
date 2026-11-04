<?php

namespace App\Data\Api;

use App\Exceptions\ApiOperationException;

class WorkLunchData
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $workDate,
        public readonly string $workStartTime,
        public readonly ?string $workEndTime,
        public readonly ?string $lunchStartTime,
        public readonly ?string $lunchEndTime,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApi(array $data): self
    {
        $id = (int) ($data['id'] ?? 0);
        $workStartTime = trim((string) ($data['wkstart_time'] ?? ''));

        if ($id <= 0 || $workStartTime === '') {
            throw new ApiOperationException('La API no devolvió un registro de jornada válido.');
        }

        return new self(
            id: $id,
            workDate: self::nullableString($data['work_date'] ?? null),
            workStartTime: $workStartTime,
            workEndTime: self::nullableString($data['wkend_time'] ?? null),
            lunchStartTime: self::nullableString($data['lunch_start_time'] ?? null),
            lunchEndTime: self::nullableString($data['lunch_end_time'] ?? null),
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'work_date' => $this->workDate,
            'wkstart_time' => $this->workStartTime,
            'wkend_time' => $this->workEndTime,
            'lunch_start_time' => $this->lunchStartTime,
            'lunch_end_time' => $this->lunchEndTime,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
