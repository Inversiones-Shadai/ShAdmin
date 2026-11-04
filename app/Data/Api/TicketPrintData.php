<?php

namespace App\Data\Api;

use App\Exceptions\ApiOperationException;
use Carbon\CarbonImmutable;
use Throwable;

use function is_array;
use function is_numeric;

class TicketPrintData
{
    public const SUPPORTED_TEMPLATE_VERSION = 1;

    public function __construct(
        public readonly int $id,
        public readonly int $number,
        public readonly float $value,
        public readonly string $issuedAt,
        public readonly int $templateVersion,
        public readonly int $promotionId,
        public readonly string $promotionName,
        public readonly int $clientId,
        public readonly string $clientName,
        public readonly string $clientDocument,
        public readonly string $clientPhone,
        public readonly int $stockId,
        public readonly string $stockName,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApi(array $data): self
    {
        // La API actual entrega los textos de impresión en campos planos.
        if (is_string($data['promocion'] ?? null)) {
            $data['promocion'] = ['id' => $data['promocion_id'] ?? null, 'nombre' => $data['promocion']];
            $data['cliente'] = [
                'id' => $data['cliente_id'] ?? null,
                'nombre' => $data['cliente_n'] ?? null,
                'identidad' => $data['cliente_i'] ?? null,
                'telefono' => $data['cliente_t'] ?? null,
            ];
            $data['stock'] = ['id' => $data['stock_id'] ?? null, 'descripcion' => $data['stock'] ?? null];
        }

        $promotion = is_array($data['promocion'] ?? null) ? $data['promocion'] : [];
        $client = is_array($data['cliente'] ?? null) ? $data['cliente'] : [];
        $stock = is_array($data['stock'] ?? null) ? $data['stock'] : [];

        $id = (int) ($data['id'] ?? 0);
        $number = (int) ($data['numero'] ?? 0);
        $templateVersion = (int) ($data['template_version'] ?? 0);
        $issuedAt = trim((string) ($data['fecha'] ?? ''));
        $promotionId = (int) ($promotion['id'] ?? 0);
        $promotionName = trim((string) ($promotion['nombre'] ?? ''));
        $clientId = (int) ($client['id'] ?? 0);
        $clientName = trim((string) ($client['nombre'] ?? ''));
        $clientDocument = trim((string) ($client['identidad'] ?? ''));
        $clientPhone = trim((string) ($client['telefono'] ?? ''));
        $stockId = (int) ($stock['id'] ?? 0);
        $stockName = trim((string) ($stock['descripcion'] ?? ''));

        if (
            $id <= 0
            || $number <= 0
            || ! is_numeric($data['valor'] ?? null)
            || $issuedAt === ''
            || $promotionId <= 0
            || $promotionName === ''
            || $clientId <= 0
            || $clientName === ''
            || $clientDocument === ''
            || $clientPhone === ''
            || $stockId <= 0
            || $stockName === ''
        ) {
            throw new ApiOperationException('El servidor no devolvió datos válidos para imprimir el ticket.');
        }

        if ($templateVersion !== self::SUPPORTED_TEMPLATE_VERSION) {
            throw new ApiOperationException('La versión del formato del ticket no es compatible con esta aplicación.');
        }

        try {
            $issuedAt = CarbonImmutable::parse($issuedAt)->toIso8601String();
        } catch (Throwable) {
            throw new ApiOperationException('La fecha del ticket enviada por el servidor no es válida.');
        }

        return new self(
            id: $id,
            number: $number,
            value: (float) $data['valor'],
            issuedAt: $issuedAt,
            templateVersion: $templateVersion,
            promotionId: $promotionId,
            promotionName: $promotionName,
            clientId: $clientId,
            clientName: $clientName,
            clientDocument: $clientDocument,
            clientPhone: $clientPhone,
            stockId: $stockId,
            stockName: $stockName,
        );
    }

    public function formattedIssuedAt(): string
    {
        return CarbonImmutable::parse($this->issuedAt)->format('d/m/Y H:i');
    }

    public function formattedDocument(): string
    {
        if (mb_strlen($this->clientDocument) < 13) {
            return $this->clientDocument;
        }

        return mb_substr($this->clientDocument, 0, 4)
            .'-'.mb_substr($this->clientDocument, 4, 4)
            .'-'.mb_substr($this->clientDocument, 8, 5);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->number,
            'valor' => $this->value,
            'fecha' => $this->issuedAt,
            'template_version' => $this->templateVersion,
            'promocion' => [
                'id' => $this->promotionId,
                'nombre' => $this->promotionName,
            ],
            'cliente' => [
                'id' => $this->clientId,
                'nombre' => $this->clientName,
                'identidad' => $this->clientDocument,
                'telefono' => $this->clientPhone,
            ],
            'stock' => [
                'id' => $this->stockId,
                'descripcion' => $this->stockName,
            ],
        ];
    }
}
