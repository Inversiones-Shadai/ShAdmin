<?php

namespace App\Livewire\Farma;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\PurchaseApiService;
use App\Services\Api\SelectorApiService;
use App\Services\Auth\CurrentUserPermissions;
use App\Services\Images\ImageSource;
use App\Services\Images\ScannedDocumentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

use function array_key_exists;
use function is_array;

class Compras extends Component
{
    use RemembersSelectorChoice;
    use WithFileUploads;

    /** @var array<int, array<string, mixed>> */
    public array $purchases = [];

    /** @var array<string, mixed> */
    public array $options = [];

    /** @var array<int, array<string, mixed>> */
    public array $details = [];

    public int $total = 0;

    public int $page = 1;

    public int $lastPage = 1;

    public string $search = '';

    public ?string $loadError = null;

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public bool $showReportModal = false;

    public bool $showKardexModal = false;

    /** @var array<string, mixed> */
    public array $reportPurchase = [];

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public ?int $kardexPurchaseId = null;

    public string $deletingName = '';

    public string $kardexPurchaseName = '';

    public string $tipo = '';

    public string $proveedor = '';

    public string $providerName = '';

    public bool $providerHasImage = false;

    public bool $showProviderPicker = false;

    public string $providerSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $providerOptions = [];

    public int $providerPage = 1;

    public int $providerLastPage = 1;

    public int $providerTotal = 0;

    public ?string $providerLoadError = null;

    /** @var array<int, array<string, mixed>> */
    public array $selectedProducts = [];

    /** @var array<int, ?string> */
    #[Locked]
    public array $latestProductCosts = [];

    /** @var array<int, string> */
    #[Locked]
    public array $productCostErrors = [];

    public ?string $providerImageSrc = null;

    public string $plazo = '0';

    public string $nro = '';

    public string $nota = '';

    public mixed $documentoArchivo = null;

    public ?string $documentoBase64 = null;

    public ?string $documentoMime = null;

    public bool $showPaymentModal = false;

    public bool $showPaymentsModal = false;

    /** @var array<int, array<string, mixed>> */
    public array $paymentTransactions = [];

    /** @var array<string, mixed> */
    public array $paymentOptions = [];

    public ?int $paymentPurchaseId = null;

    public string $paymentPurchaseName = '';

    public string $paymentType = '';

    public string $paymentNumber = '';

    public string $paymentValue = '';

    public float $paymentBalance = 0.0;

    public float $paymentTotal = 0.0;

    public float $paymentApplied = 0.0;

    public float $paymentsTotal = 0.0;

    public mixed $paymentDocumentFile = null;

    public ?string $paymentDocumentBase64 = null;

    public ?string $paymentDocumentMime = null;

    public function mount(): void
    {
        $this->loadPage();
    }

    public function openCreate(): void
    {
        $this->authorizeAbility('purchases.create');
        $this->authorizeAbility('purchases.options');
        $this->resetForm();

        if ($this->loadOptions()) {
            $this->addDetail();
            $this->showFormModal = true;
        }
    }

    public function openEdit(int $id): void
    {
        $this->authorizeAbility('purchases.search');
        $this->authorizeAbility('purchases.update');
        $this->authorizeAbility('purchases.options');
        $this->resetForm();

        try {
            $purchase = $this->api()->find($id);

            if (! $this->loadOptions()) {
                return;
            }
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->alert('No fue posible cargar la compra', $exception->getMessage());

            return;
        }

        $this->editingId = $id;
        $this->tipo = (string) ($purchase['tipo_id'] ?? '');
        $this->proveedor = (string) ($purchase['proveedor_id'] ?? '');
        $this->providerName = (string) data_get($purchase, 'proveedor.nombre', '');
        $this->providerHasImage = (bool) data_get($purchase, 'proveedor.tiene_imagen', true);
        $this->providerImageSrc = data_get($purchase, 'proveedor.imagen_url');
        $this->plazo = (string) (int) ($purchase['plazo'] ?? 0);
        $this->nro = (string) ($purchase['nro'] ?? '');
        $this->nota = (string) ($purchase['nota'] ?? '');
        $purchaseDetails = is_array($purchase['detalles'] ?? null)
            ? array_values(array_filter($purchase['detalles'], is_array(...)))
            : [];
        $this->details = collect($purchaseDetails)->map(fn (array $detail): array => [
            'id' => (int) ($detail['id'] ?? 0),
            'producto' => (string) ($detail['producto_id'] ?? ''),
            'es_bonificacion' => (bool) ($detail['es_bonificacion'] ?? false),
            'cantidad' => (int) ($detail['cantidad'] ?? 1),
            'lote' => (string) ($detail['lote'] ?? ''),
            'fecha_elaboracion' => substr((string) ($detail['fecha_elaboracion'] ?? ''), 0, 10),
            'fecha_expiracion' => substr((string) ($detail['fecha_expiracion'] ?? ''), 0, 10),
            'costo' => number_format((float) ($detail['costo'] ?? 0), 2, '.', ''),
            'isv' => (bool) ($detail['isv'] ?? false),
            'descuento' => number_format((float) ($detail['descuento'] ?? 0), 2, '.', ''),
        ])->values()->all();
        $this->selectedProducts = collect($purchaseDetails)
            ->filter(fn (array $detail): bool => is_array($detail['producto'] ?? null))
            ->mapWithKeys(fn (array $detail): array => [(int) ($detail['producto_id'] ?? 0) => $detail['producto']])
            ->all();

        if ($this->details === []) {
            $this->addDetail();
        }
        $this->loadLatestProductCosts(array_column($this->details, 'producto'));
        $this->loadProviderImage();
        $this->showFormModal = true;
    }

    public function addDetail(): void
    {
        $this->details[] = [
            'id' => null,
            'producto' => '',
            'es_bonificacion' => false,
            'cantidad' => 1,
            'lote' => '',
            'fecha_elaboracion' => '',
            'fecha_expiracion' => '',
            'costo' => '0.00',
            'isv' => false,
            'descuento' => '0.00',
        ];
    }

    public function removeDetail(int $index): void
    {
        if (! array_key_exists($index, $this->details)) {
            return;
        }

        unset($this->details[$index]);
        $this->details = array_values($this->details);

        if ($this->details === []) {
            $this->addDetail();
        }
    }

    public function openProductSearch(int $index): void
    {
        if (! array_key_exists($index, $this->details)) {
            return;
        }

        $this->dispatch(
            event: 'advanced-product-search:open',
            context: "purchase-detail-{$index}",
            excludedIds: [],
        );
    }

    /** @param array<string, mixed> $product */
    #[On('advanced-product-search:selected')]
    public function selectProductFromSearch(string $context, array $product): void
    {
        if (preg_match('/^purchase-detail-(\d+)$/', $context, $matches) !== 1) {
            return;
        }

        $index = (int) $matches[1];
        $id = (int) ($product['id'] ?? 0);

        if ($id < 1 || ! array_key_exists($index, $this->details)) {
            return;
        }

        $this->details[$index]['producto'] = (string) $id;
        $this->selectedProducts[$id] = $product;
        $this->loadLatestProductCosts([$id]);
        $this->details[$index]['costo'] = ! empty($this->details[$index]['es_bonificacion']) ? '0.00' : ($this->latestProductCosts[$id] ?? '0.00');
        $this->resetErrorBag("details.{$index}.producto");
    }

    public function openProviderPicker(): void
    {
        $this->authorizeAbility('purchases.options');
        $this->providerSearch = '';
        $this->showProviderPicker = true;
        $this->loadProviderPage();
    }

    public function closeProviderPicker(): void
    {
        $this->showProviderPicker = false;
    }

    public function updatedProviderSearch(): void
    {
        $this->loadProviderPage();
    }

    public function previousProviderPage(): void
    {
        $this->loadProviderPage(max(1, $this->providerPage - 1));
    }

    public function nextProviderPage(): void
    {
        $this->loadProviderPage(min($this->providerLastPage, $this->providerPage + 1));
    }

    public function selectProvider(int $id): void
    {
        $provider = collect($this->providerOptions)->first(fn (array $option): bool => (int) ($option['id'] ?? 0) === $id);
        if (! is_array($provider)) {
            return;
        }

        $this->proveedor = (string) $id;
        $this->providerName = (string) ($provider['nombre'] ?? $provider['label'] ?? '');
        $this->providerHasImage = (bool) ($provider['tiene_imagen'] ?? false);
        $this->providerImageSrc = $provider['imagen_url'] ?? null;
        $this->showProviderPicker = false;
        $this->resetErrorBag('proveedor');
        $this->loadProviderImage();
    }

    public function updatedDocumentoArchivo(): void
    {
        $this->documentoBase64 = $this->documentoMime = null;
        $this->resetErrorBag('documentoArchivo');

        if ($this->documentoArchivo !== null) {
            $this->prepareDocument();
        }
    }

    public function prepareDocument(): void
    {
        $this->validateOnly('documentoArchivo', ['documentoArchivo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:12288']]);

        if ($this->documentoArchivo === null) {
            $this->documentoBase64 = $this->documentoMime = null;

            return;
        }

        try {
            $scan = app(ScannedDocumentService::class)->scan($this->documentoArchivo);
            $this->documentoBase64 = $scan['base64'];
            $this->documentoMime = $scan['mime'];
        } catch (InvalidArgumentException $exception) {
            $this->documentoBase64 = $this->documentoMime = null;
            $this->addError('documentoArchivo', $exception->getMessage());
        }
    }

    public function save(): void
    {
        $this->authorizeAbility($this->editingId === null ? 'purchases.create' : 'purchases.update');
        $validated = $this->validate([
            'tipo' => ['required', 'integer', 'min:1'], 'proveedor' => ['required', 'integer', 'min:1'],
            'plazo' => ['required', 'integer', 'min:0', 'max:3650'], 'nro' => ['required', 'string', 'max:255'],
            'nota' => ['nullable', 'string', 'max:5000'],
            'details' => ['required', 'array', 'min:1', 'max:200'],
            'details.*.producto' => ['required', 'integer', 'min:1'],
            'details.*.es_bonificacion' => ['sometimes', 'boolean'],
            'details.*.cantidad' => ['required', 'integer', 'min:1'],
            'details.*.lote' => ['required', 'string', 'max:100'],
            'details.*.fecha_elaboracion' => ['required', 'date_format:Y-m-d'],
            'details.*.fecha_expiracion' => ['required', 'date_format:Y-m-d', 'after_or_equal:details.*.fecha_elaboracion'],
            'details.*.costo' => ['required', 'numeric', 'min:0'],
            'details.*.isv' => ['boolean'],
            'details.*.descuento' => ['required', 'numeric', 'min:0'],
            'documentoArchivo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:12288'],
        ], [
            '*.required' => 'El campo :attribute es obligatorio.',
            'details.min' => 'Agrega al menos un producto.',
        ]);

        if ($this->documentoArchivo !== null && $this->documentoBase64 === null) {
            $this->prepareDocument();
            if ($this->getErrorBag()->has('documentoArchivo')) {
                return;
            }
        }

        $validatedDetails = is_array($validated['details'] ?? null)
            ? array_values(array_filter($validated['details'], is_array(...)))
            : [];

        foreach ($validatedDetails as $index => $detail) {
            if ((float) $detail['costo'] < 0.01 && empty($detail['es_bonificacion'])) {
                $this->addError("details.{$index}.costo", 'El costo debe ser mayor que cero si el producto no es bonificación.');

                return;
            }
        }

        if (! $this->discountsAreValid($validatedDetails)) {
            return;
        }

        $payload = [
            'tipo' => (int) $validated['tipo'],
            'proveedor' => (int) $validated['proveedor'],
            'plazo' => (int) $validated['plazo'],
            'nro' => $this->text((string) $validated['nro'], 255),
            'nota' => $this->nullableText((string) ($validated['nota'] ?? ''), 5000),
            'detalles' => collect($validatedDetails)->map(fn (array $detail): array => [
                'producto' => (int) $detail['producto'],
                'es_bonificacion' => (bool) ($detail['es_bonificacion'] ?? false),
                'cantidad' => (int) $detail['cantidad'],
                'lote' => $this->text((string) $detail['lote'], 100),
                'fecha_elaboracion' => $detail['fecha_elaboracion'],
                'fecha_expiracion' => $detail['fecha_expiracion'],
                'costo' => (float) $detail['costo'],
                'isv' => (bool) ($detail['isv'] ?? false),
                'descuento' => (float) $detail['descuento'],
            ])->all(),
        ];
        if ($this->documentoBase64 !== null) {
            $payload['img'] = $this->documentoBase64;
        }

        try {
            if ($this->editingId === null) {
                $this->api()->create($payload);
            } else {
                $this->api()->update($this->editingId, $payload);
            }
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->applyApiErrors($exception);

            return;
        }

        $this->showFormModal = false;
        $this->toast('Compra guardada y pendiente de pago.');
        $this->resetForm();
        $this->loadPage($this->page);
    }

    public function viewDocument(int $id): void
    {
        $this->authorizeAbility('purchases.document');
        try {
            $src = app(ImageSource::class)->forRecord(collect($this->purchases)->firstWhere('id', $id), fn () => $this->api()->document($id), 'documento_url');
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException|InvalidArgumentException $exception) {
            $this->alert('No fue posible cargar el documento', $exception->getMessage());

            return;
        }
        $purchase = collect($this->purchases)->first(fn (array $item): bool => (int) $item['id'] === $id);
        $this->dispatch('image-viewer:show', key: "purchases:{$id}", name: 'Factura '.($purchase['nro'] ?? "#{$id}"), src: $src);
    }

    public function openReport(int $id): void
    {
        $this->authorizeAbility('purchases.report');

        try {
            $this->reportPurchase = $this->api()->report($id);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->alert('No fue posible cargar el reporte', $exception->getMessage());

            return;
        }

        $this->showReportModal = true;
    }

    public function closeReport(): void
    {
        $this->showReportModal = false;
        $this->reportPurchase = [];
    }

    public function confirmKardex(int $id): void
    {
        $this->authorizeAbility('purchases.kardex');
        $purchase = $this->purchaseFromList($id);

        if ($purchase === null) {
            $this->alert('No se puede enviar al Kardex', 'La compra ya no se encuentra en la lista actual.');

            return;
        }

        if (! empty($purchase['kardex_enviado'])) {
            $this->alert('Compra ya procesada', 'Esta compra ya fue enviada al Kardex.');

            return;
        }

        if (($purchase['estado'] ?? '') === 'borrador') {
            $this->alert('Finaliza la compra', 'Los borradores no pueden enviarse al Kardex.');

            return;
        }

        $this->kardexPurchaseId = $id;
        $this->kardexPurchaseName = (string) ($purchase['nro'] ?? "#{$id}");
        $this->showKardexModal = true;
    }

    public function sendToKardex(): void
    {
        $this->authorizeAbility('purchases.kardex');

        if ($this->kardexPurchaseId === null) {
            return;
        }

        try {
            $purchase = $this->api()->sendToKardex($this->kardexPurchaseId);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->showKardexModal = false;
            $this->alert('No fue posible enviar la compra al Kardex', $exception->getMessage());

            return;
        }

        $this->replacePurchaseInList($purchase);
        $this->cancelKardex();
        $this->toast('Compra enviada al Kardex correctamente.');
    }

    public function cancelKardex(): void
    {
        $this->showKardexModal = false;
        $this->kardexPurchaseId = null;
        $this->kardexPurchaseName = '';
    }

    public function openPayment(int $id, bool $fullBalance = false): void
    {
        $this->authorizeAbility('purchase-transactions.create');
        $this->authorizeAbility('purchase-transactions.options');
        $purchase = $this->purchaseFromList($id);

        if ($purchase === null || (float) ($purchase['saldo'] ?? 0) <= 0) {
            $this->alert('No se puede registrar el pago', 'La compra no tiene saldo pendiente.');

            return;
        }

        try {
            $preferred = $fullBalance ? 'pago al contado' : 'abono';
            $result = app(SelectorApiService::class)->page('payment-types', ['search' => $preferred]);
            $this->paymentOptions = ['tipos' => array_map(fn ($item) => $item['record'], $result['data'])];
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->alert('No fue posible cargar los tipos de pago', $exception->getMessage());

            return;
        }

        $preferred = $fullBalance ? 'pago al contado' : 'abono';
        $type = null;

        foreach ($this->paymentOptions['tipos'] ?? [] as $option) {
            if (is_array($option) && Str::contains(Str::lower((string) ($option['label'] ?? '')), $preferred)) {
                $type = $option;

                break;
            }
        }
        $this->paymentPurchaseId = $id;
        $this->paymentPurchaseName = (string) ($purchase['nro'] ?? "#{$id}");
        $this->paymentType = (string) (is_array($type) ? ($type['id'] ?? '') : '');
        $this->paymentNumber = '';
        $this->paymentBalance = (float) $purchase['saldo'];
        $this->paymentTotal = (float) $purchase['total'];
        $this->paymentApplied = (float) ($purchase['total_aplicado'] ?? max(0, $this->paymentTotal - $this->paymentBalance));

        if ($fullBalance && $this->paymentApplied > 0.005) {
            $this->alert('No se puede registrar pago al contado', 'La factura ya tiene abonos o notas aplicadas. Registra un abono por el saldo restante.');
            $this->resetPaymentForm();

            return;
        }

        $this->paymentValue = $fullBalance ? number_format($this->paymentTotal, 2, '.', '') : '';
        $this->showPaymentModal = true;
    }

    public function savePayment(): void
    {
        $this->authorizeAbility('purchase-transactions.create');

        if ($this->paymentIsCash() && $this->paymentApplied > 0.005) {
            $this->addError('paymentValue', 'El pago al contado no está disponible porque la factura ya tiene abonos o notas aplicadas.');

            return;
        }

        if ($this->paymentIsCash() && (! is_numeric($this->paymentValue) || abs((float) $this->paymentValue - $this->paymentTotal) > 0.005)) {
            $this->addError('paymentValue', 'El pago al contado debe ser exactamente por el total de la factura: L '.number_format($this->paymentTotal, 2).'.');

            return;
        }

        $validated = $this->validate([
            'paymentPurchaseId' => ['required', 'integer', 'min:1'],
            'paymentType' => ['required', 'integer', 'min:1'],
            'paymentNumber' => ['nullable', 'string', 'max:100'],
            'paymentValue' => ['required', 'numeric', 'min:0.01', 'max:'.$this->paymentBalance],
            'paymentDocumentFile' => [$this->paymentRequiresDocument() ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:12288'],
        ], [
            '*.required' => 'El campo :attribute es obligatorio.',
            'paymentValue.min' => 'El pago debe ser mayor que cero.',
            'paymentValue.max' => 'El pago no puede superar el saldo disponible de L '.number_format($this->paymentBalance, 2).'.',
            'paymentDocumentFile.required' => 'El comprobante es obligatorio para abonos y notas de crédito.',
        ]);

        if ($this->paymentDocumentFile !== null && $this->paymentDocumentBase64 === null) {
            $this->updatedPaymentDocumentFile();

            if ($this->getErrorBag()->has('paymentDocumentFile')) {
                return;
            }
        }

        $payload = [
            'compra' => (int) $validated['paymentPurchaseId'],
            'tipo' => (int) $validated['paymentType'],
            'nro' => $this->nullableText((string) ($validated['paymentNumber'] ?? ''), 100),
            'valor' => (float) $validated['paymentValue'],
        ];

        if ($this->paymentDocumentBase64 !== null) {
            $payload['img'] = $this->paymentDocumentBase64;
        }

        try {
            $message = $this->api()->createTransaction($payload);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->alert('No fue posible registrar el pago', $exception->getMessage());

            return;
        }

        $this->showPaymentModal = false;
        $this->toast($message ?: 'Pago registrado correctamente.');
        $this->resetPaymentForm();
        $this->loadPage($this->page);
    }

    public function updatedPaymentType(): void
    {
        $this->resetErrorBag(['paymentDocumentFile', 'paymentValue']);

        if ($this->paymentIsCash()) {
            $this->paymentValue = number_format($this->paymentTotal, 2, '.', '');

            if ($this->paymentApplied > 0.005) {
                $this->addError('paymentValue', 'La factura ya tiene abonos o notas aplicadas; utiliza un abono por el saldo restante.');
            }
        }

        if (! $this->paymentRequiresDocument()) {
            $this->paymentDocumentFile = null;
            $this->paymentDocumentBase64 = $this->paymentDocumentMime = null;
        }
    }

    public function updatedPaymentDocumentFile(): void
    {
        $this->resetErrorBag('paymentDocumentFile');
        $this->validateOnly('paymentDocumentFile', [
            'paymentDocumentFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:12288'],
        ]);

        if ($this->paymentDocumentFile === null) {
            $this->paymentDocumentBase64 = $this->paymentDocumentMime = null;

            return;
        }

        try {
            $scan = app(ScannedDocumentService::class)->scan($this->paymentDocumentFile);
            $this->paymentDocumentBase64 = $scan['base64'];
            $this->paymentDocumentMime = $scan['mime'];
        } catch (InvalidArgumentException $exception) {
            $this->paymentDocumentBase64 = $this->paymentDocumentMime = null;
            $this->addError('paymentDocumentFile', $exception->getMessage());
        }
    }

    public function viewPayments(int $id): void
    {
        $this->authorizeAbility('purchase-transactions.list');
        $purchase = $this->purchaseFromList($id);

        try {
            $page = $this->api()->listTransactions(1, $id);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->alert('No fue posible cargar los pagos', $exception->getMessage());

            return;
        }

        $this->paymentPurchaseId = $id;
        $this->paymentPurchaseName = (string) ($purchase['nro'] ?? "#{$id}");
        $this->paymentTransactions = $page->items;
        $this->paymentsTotal = (float) ($purchase['total_aplicado'] ?? collect($page->items)->sum('valor'));
        $this->showPaymentsModal = true;
    }

    public function viewPaymentDocument(int $id): void
    {
        $this->authorizeAbility('purchase-transactions.document');

        try {
            $src = app(ImageSource::class)->forRecord(collect($this->paymentTransactions)->firstWhere('id', $id), fn () => $this->api()->transactionDocument($id), 'documento_url');
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException|InvalidArgumentException $exception) {
            $this->alert('No fue posible cargar el comprobante', $exception->getMessage());

            return;
        }

        $this->dispatch('image-viewer:show', key: "purchase-payments:{$id}", name: "Comprobante de pago #{$id}", src: $src);
    }

    public function closePayment(): void
    {
        $this->showPaymentModal = false;
        $this->resetPaymentForm();
    }

    public function closePayments(): void
    {
        $this->showPaymentsModal = false;
        $this->paymentTransactions = [];
        $this->paymentsTotal = 0.0;
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeAbility('purchases.delete');
        $purchase = collect($this->purchases)->first(fn (array $item): bool => (int) $item['id'] === $id);
        $this->deletingId = $id;
        $this->deletingName = (string) ($purchase['nro'] ?? "#{$id}");
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        $this->authorizeAbility('purchases.delete');
        if ($this->deletingId === null) {
            return;
        }
        try {
            $message = $this->api()->delete($this->deletingId);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->showDeleteModal = false;
            $this->alert('No fue posible eliminar la compra', $exception->getMessage());

            return;
        }
        $this->showDeleteModal = false;
        $this->toast($message ?: 'Compra eliminada correctamente.');
        $this->loadPage();
    }

    public function closeForm(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
    }

    public function previousPage(): void
    {
        $this->loadPage(max(1, $this->page - 1));
    }

    public function nextPage(): void
    {
        $this->loadPage(min($this->lastPage, $this->page + 1));
    }

    public function updatedSearch(): void
    {
        $this->loadPage(1);
    }

    public function render(): View
    {
        $permissions = app(CurrentUserPermissions::class);
        $purchaseTotals = $this->calculatedTotals();

        return view('livewire.farma.compras', [
            'visiblePurchases' => $this->purchases,
            'subtotalCalculado' => $purchaseTotals['subtotal'],
            'purchaseTotals' => $purchaseTotals,
            'productCostChanges' => $this->productCostChanges(),
            'paymentDocumentRequired' => $this->paymentRequiresDocument(),
            'paymentCash' => $this->paymentIsCash(),
            'detailProducts' => collect($this->details)
                ->map(fn (array $detail): array => $this->productDisplay($detail['producto'] ?? null))
                ->all(),
            'access' => [
                'create' => $permissions->allowsAll(['purchases.create', 'purchases.options']),
                'edit' => $permissions->allowsAll(['purchases.search', 'purchases.update', 'purchases.options']),
                'delete' => $permissions->allows('purchases.delete'), 'document' => $permissions->allows('purchases.document'),
                'report' => $permissions->allows('purchases.report'),
                'kardex' => $permissions->allows('purchases.kardex'),
                'pay' => $permissions->allowsAll(['purchase-transactions.create', 'purchase-transactions.options']),
                'viewPayments' => $permissions->allows('purchase-transactions.list'),
                'paymentDocument' => $permissions->allows('purchase-transactions.document'),
            ],
        ]);
    }

    private function loadPage(int $page = 1): void
    {
        $this->authorizeAbility('purchases.list');
        $this->loadError = null;
        try {
            $this->applyPage($this->api()->list($page, $this->search));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->loadError = $exception->getMessage();
        }
    }

    /** @param array<string, mixed> $purchase */
    private function replacePurchaseInList(array $purchase): void
    {
        $id = (int) ($purchase['id'] ?? 0);

        if ($id < 1) {
            return;
        }

        foreach ($this->purchases as $index => $existing) {
            if ((int) ($existing['id'] ?? 0) === $id) {
                $this->purchases[$index] = $purchase;

                return;
            }
        }

        array_unshift($this->purchases, $purchase);
        $this->total++;
    }

    /** @return array<string, mixed>|null */
    private function purchaseFromList(int $id): ?array
    {
        $purchase = collect($this->purchases)->first(fn (array $item): bool => (int) ($item['id'] ?? 0) === $id);

        return is_array($purchase) ? $purchase : null;
    }

    private function resetPaymentForm(): void
    {
        $this->resetValidation(['paymentPurchaseId', 'paymentType', 'paymentNumber', 'paymentValue']);
        $this->paymentPurchaseId = null;
        $this->paymentPurchaseName = $this->paymentType = $this->paymentNumber = $this->paymentValue = '';
        $this->paymentBalance = 0.0;
        $this->paymentTotal = $this->paymentApplied = 0.0;
        $this->paymentDocumentFile = null;
        $this->paymentDocumentBase64 = $this->paymentDocumentMime = null;
        $this->paymentOptions = [];
    }

    private function paymentRequiresDocument(): bool
    {
        foreach ($this->paymentOptions['tipos'] ?? [] as $option) {
            if (! is_array($option) || (string) ($option['id'] ?? '') !== $this->paymentType) {
                continue;
            }

            return Str::contains(Str::lower((string) ($option['label'] ?? '')), ['abono', 'nota de crédito', 'nota de credito']);
        }

        return false;
    }

    private function paymentIsCash(): bool
    {
        foreach ($this->paymentOptions['tipos'] ?? [] as $option) {
            if (! is_array($option) || (string) ($option['id'] ?? '') !== $this->paymentType) {
                continue;
            }

            return Str::contains(Str::lower((string) ($option['label'] ?? '')), 'pago al contado');
        }

        return false;
    }

    /** @return array{subtotal: float, isv: float, descuento: float, total: float, unitTaxes: array<int, float>, lines: array<int, float>} */
    private function calculatedTotals(): array
    {
        $totals = ['subtotal' => 0.0, 'isv' => 0.0, 'descuento' => 0.0, 'total' => 0.0, 'unitTaxes' => [], 'lines' => []];

        foreach ($this->details as $index => $detail) {
            $line = $this->lineTotals($detail);
            $totals['subtotal'] += $line['subtotal'];
            $totals['isv'] += $line['isv'];
            $totals['descuento'] += $line['descuento'];
            $totals['total'] += $line['total'];
            $totals['unitTaxes'][$index] = $line['isv_unitario'];
            $totals['lines'][$index] = $line['total'];
        }

        $totals['subtotal'] = round($totals['subtotal'], 2);
        $totals['isv'] = round($totals['isv'], 2);
        $totals['descuento'] = round($totals['descuento'], 2);
        $totals['total'] = round($totals['total'], 2);

        return $totals;
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array{subtotal: float, isv_unitario: float, isv: float, descuento: float, total: float}
     */
    private function lineTotals(array $detail): array
    {
        $quantity = is_numeric($detail['cantidad'] ?? null) ? max(0, (int) $detail['cantidad']) : 0;
        $cost = is_numeric($detail['costo'] ?? null) ? max(0, (float) $detail['costo']) : 0.0;
        $unitDiscount = is_numeric($detail['descuento'] ?? null) ? max(0, (float) $detail['descuento']) : 0.0;
        $subtotal = round($quantity * $cost, 2);
        $unitTax = filter_var($detail['isv'] ?? false, FILTER_VALIDATE_BOOL)
            ? round($cost * $this->isvRate(), 2)
            : 0.0;
        $tax = round($quantity * $unitTax, 2);
        $discount = round($quantity * $unitDiscount, 2);

        return [
            'subtotal' => $subtotal,
            'isv_unitario' => $unitTax,
            'isv' => $tax,
            'descuento' => round($discount, 2),
            'total' => round(max(0, $subtotal + $tax - $discount), 2),
        ];
    }

    /** @param array<int, array<string, mixed>> $details */
    private function discountsAreValid(array $details): bool
    {
        $valid = true;

        foreach ($details as $index => $detail) {
            $line = $this->lineTotals($detail);

            if ($line['descuento'] <= ($line['subtotal'] + $line['isv'])) {
                continue;
            }

            $this->addError(
                "details.{$index}.descuento",
                'El descuento no puede superar el subtotal más el ISV de la línea.',
            );
            $valid = false;
        }

        return $valid;
    }

    private function isvRate(): float
    {
        return max(0, min(1, (float) config('farma.purchase_isv_rate', 0.15)));
    }

    /** @param array<int, int|string> $productIds */
    private function loadLatestProductCosts(array $productIds): void
    {
        $this->authorizeAbility('purchases.options');
        $missing = array_values(array_filter(array_unique(array_map('intval', $productIds)),
            fn (int $id): bool => $id > 0 && ! array_key_exists($id, $this->latestProductCosts)));

        if ($missing === []) {
            return;
        }

        try {
            foreach (array_chunk($missing, 200) as $batch) {
                foreach ($this->api()->latestCosts($batch, $this->editingId) as $id => $cost) {
                    $this->latestProductCosts[$id] = $cost;
                    unset($this->productCostErrors[$id]);
                }
            }
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException) {
            foreach ($missing as $id) {
                if (! array_key_exists($id, $this->latestProductCosts)) {
                    $this->productCostErrors[$id] = 'No se pudo consultar el último costo.';
                }
            }
        }
    }

    /** @return array<int, array{previous: string, difference: float}> */
    private function productCostChanges(): array
    {
        $changes = [];
        foreach ($this->details as $index => $detail) {
            if (! empty($detail['es_bonificacion'])) {
                continue;
            }
            $previous = $this->latestProductCosts[(int) ($detail['producto'] ?? 0)] ?? null;
            $current = $detail['costo'] ?? null;
            if ($previous === null || ! is_numeric($current) || (float) $current < 0) {
                continue;
            }

            $difference = (int) round((float) $current * 100) - (int) round((float) $previous * 100);
            if ($difference !== 0) {
                $changes[$index] = ['previous' => $previous, 'difference' => $difference / 100];
            }
        }

        return $changes;
    }

    /** @return array{name: string, code: string, laboratory: string, presentation: string, purchaseUnit: string} */
    private function productDisplay(mixed $id): array
    {
        $id = (int) $id;

        if ($id < 1) {
            return ['name' => 'Buscar producto', 'code' => '', 'laboratory' => '', 'presentation' => '—', 'purchaseUnit' => '—'];
        }

        $product = $this->selectedProducts[$id] ?? null;
        if (is_array($product)) {
            return [
                'name' => trim((string) ($product['descripcion'] ?? $product['label'] ?? "Producto #{$id}")),
                'code' => trim((string) ($product['codigo'] ?? '')),
                'laboratory' => trim((string) ($product['laboratorio'] ?? '')),
                'presentation' => trim((string) ($product['presentacion'] ?? '')) ?: '—',
                'purchaseUnit' => trim((string) ($product['unidad_compra'] ?? '')) ?: '—',
            ];
        }

        return ['name' => "Producto #{$id}", 'code' => '', 'laboratory' => '', 'presentation' => '—', 'purchaseUnit' => '—'];
    }

    private function applyPage(PaginatedCollectionData $page): void
    {
        $this->purchases = $page->items;
        $this->total = $page->total;
        $this->page = $page->currentPage;
        $this->lastPage = $page->lastPage;
    }

    private function loadOptions(): bool
    {
        return true;
    }

    private function loadProviderPage(int $page = 1): void
    {
        $this->providerLoadError = null;

        try {
            $result = $this->api()->providers($page, $this->providerSearch);
            $this->providerOptions = $result->items;
            $this->providerPage = $result->currentPage;
            $this->providerLastPage = $result->lastPage;
            $this->providerTotal = $result->total;
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->providerLoadError = $exception->getMessage();
            $this->providerOptions = [];
        }
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->tipo = $this->proveedor = $this->nro = $this->nota = '';
        $this->providerName = '';
        $this->providerHasImage = false;
        $this->showProviderPicker = false;
        $this->providerSearch = '';
        $this->providerOptions = [];
        $this->providerPage = $this->providerLastPage = 1;
        $this->providerTotal = 0;
        $this->providerLoadError = null;
        $this->selectedProducts = [];
        $this->latestProductCosts = [];
        $this->productCostErrors = [];
        $this->plazo = '0';
        $this->details = [];
        $this->documentoArchivo = null;
        $this->documentoBase64 = $this->documentoMime = null;
        $this->providerImageSrc = null;
    }

    private function loadProviderImage(): void
    {
        if ($this->providerImageSrc !== null) {
            return;
        }

        if (! ctype_digit($this->proveedor) || (int) $this->proveedor < 1 || ! $this->providerHasImage) {
            return;
        }

        try {
            $this->providerImageSrc = app(ImageSource::class)->source($this->api()->providerImage((int) $this->proveedor));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException|InvalidArgumentException) {
            $this->providerImageSrc = null;
        }
    }

    private function text(string $value, int $max): string
    {
        return mb_substr(trim(strip_tags($value)), 0, $max);
    }

    private function nullableText(string $value, int $max): ?string
    {
        $value = $this->text($value, $max);

        return $value === '' ? null : $value;
    }

    private function api(): PurchaseApiService
    {
        return app(PurchaseApiService::class);
    }

    private function authorizeAbility(string $ability): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, $ability);
    }

    private function redirectToLogin(): void
    {
        $this->redirectRoute('login');
    }

    private function toast(string $message): void
    {
        $this->dispatch('sweetalert:toast', icon: 'success', title: $message);
    }

    private function alert(string $title, string $message): void
    {
        $this->dispatch('sweetalert:alert', icon: 'error', title: $title, text: $message);
    }

    private function applyApiErrors(ApiOperationException $exception): void
    {
        foreach ($exception->errors as $field => $messages) {
            if (str_starts_with($field, 'detalles')) {
                $field = 'details'.substr($field, strlen('detalles'));
            }

            foreach ((array) $messages as $message) {
                $this->addError($field === 'detalles' ? 'details' : $field, (string) $message);
            }
        }
        if ($exception->errors === []) {
            $this->alert('No fue posible guardar la compra', $exception->getMessage());
        }
    }
}
