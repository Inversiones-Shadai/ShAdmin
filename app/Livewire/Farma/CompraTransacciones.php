<?php

namespace App\Livewire\Farma;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\PurchaseApiService;
use App\Services\Auth\CurrentUserPermissions;
use App\Services\Images\ImageSource;
use App\Services\Images\ScannedDocumentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class CompraTransacciones extends Component
{
    use RemembersSelectorChoice;
    use WithFileUploads;

    /** @var array<int, array<string, mixed>> */
    public array $transactions = [];

    /** @var array<string, mixed> */
    public array $options = [];

    public int $total = 0;

    public int $page = 1;

    public int $lastPage = 1;

    public string $search = '';

    public ?string $loadError = null;

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $compra = '';

    public string $tipo = '';

    public string $nro = '';

    public string $valor = '';

    public mixed $documentoArchivo = null;

    public ?string $documentoBase64 = null;

    public ?string $documentoMime = null;

    public bool $hasStoredDocument = false;

    public ?int $originalPurchaseId = null;

    public float $originalValue = 0.0;

    public function mount(): void
    {
        $this->loadPage();
    }

    public function openCreate(): void
    {
        $this->authorizeAbility('purchase-transactions.create');
        $this->authorizeAbility('purchase-transactions.options');
        $this->resetForm();
        if ($this->loadOptions()) {
            $this->showFormModal = true;
        }
    }

    public function openEdit(int $id): void
    {
        $this->authorizeAbility('purchase-transactions.search');
        $this->authorizeAbility('purchase-transactions.update');
        $this->authorizeAbility('purchase-transactions.options');
        $this->resetForm();
        try {
            $transaction = $this->api()->findTransaction($id);
            if (! $this->loadOptions()) {
                return;
            }
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->alert('No fue posible cargar la transacción', $exception->getMessage());

            return;
        }

        $this->editingId = $id;
        $this->compra = (string) ($transaction['compra_id'] ?? '');
        $this->tipo = (string) ($transaction['tipo_id'] ?? '');
        $this->nro = (string) ($transaction['nro'] ?? '');
        $this->valor = number_format((float) ($transaction['valor'] ?? 0), 2, '.', '');
        $this->originalPurchaseId = (int) ($transaction['compra_id'] ?? 0);
        $this->originalValue = (float) ($transaction['valor'] ?? 0);
        $this->hasStoredDocument = (bool) ($transaction['tiene_documento'] ?? false);
        $this->showFormModal = true;
    }

    public function updatedTipo(): void
    {
        $this->resetErrorBag(['documentoArchivo', 'valor']);
        $this->syncCashValue();

        if (! $this->transactionRequiresDocument()) {
            $this->documentoArchivo = null;
            $this->documentoBase64 = $this->documentoMime = null;
        }
    }

    public function updatedCompra(): void
    {
        $this->resetErrorBag('valor');
        $this->syncCashValue();
    }

    public function updatedDocumentoArchivo(): void
    {
        $this->resetErrorBag('documentoArchivo');
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
        $this->authorizeAbility($this->editingId === null ? 'purchase-transactions.create' : 'purchase-transactions.update');

        if ($this->transactionIsCash() && $this->cashHasPriorPayments()) {
            $this->addError('valor', 'El pago al contado no está disponible porque la factura ya tiene abonos o notas aplicadas.');

            return;
        }

        $cashTotal = $this->selectedPurchaseTotal();

        if ($this->transactionIsCash() && ($cashTotal === null || ! is_numeric($this->valor) || abs((float) $this->valor - $cashTotal) > 0.005)) {
            $this->addError('valor', 'El pago al contado debe ser exactamente por el total de la factura: L '.number_format((float) $cashTotal, 2).'.');

            return;
        }

        $documentRequired = $this->transactionRequiresDocument() && ! $this->hasStoredDocument;
        $validated = $this->validate([
            'compra' => ['required', 'integer', 'min:1'], 'tipo' => ['required', 'integer', 'min:1'],
            'nro' => ['nullable', 'string', 'max:100'], 'valor' => ['required', 'numeric', 'min:0.01'],
            'documentoArchivo' => [$documentRequired ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:12288'],
        ], [
            '*.required' => 'El campo :attribute es obligatorio.',
            'valor.min' => 'El valor debe ser mayor que cero.',
            'documentoArchivo.required' => 'El comprobante es obligatorio para abonos y notas de crédito.',
        ]);

        if ($this->documentoArchivo !== null && $this->documentoBase64 === null) {
            $this->updatedDocumentoArchivo();
            if ($this->getErrorBag()->has('documentoArchivo')) {
                return;
            }
        }
        $payload = [
            'compra' => (int) $validated['compra'], 'tipo' => (int) $validated['tipo'],
            'nro' => ($value = trim(strip_tags((string) ($validated['nro'] ?? '')))) === '' ? null : mb_substr($value, 0, 100),
            'valor' => (float) $validated['valor'],
        ];
        if ($this->documentoBase64 !== null) {
            $payload['img'] = $this->documentoBase64;
        }

        try {
            $message = $this->editingId === null ? $this->api()->createTransaction($payload) : $this->api()->updateTransaction($this->editingId, $payload);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->alert('No fue posible guardar la transacción', $exception->getMessage());

            return;
        }

        $this->showFormModal = false;
        $this->toast($message ?: 'Transacción guardada correctamente.');
        $this->resetForm();
        $this->loadPage();
    }

    public function viewDocument(int $id): void
    {
        $this->authorizeAbility('purchase-transactions.document');
        try {
            $src = app(ImageSource::class)->forRecord(collect($this->transactions)->firstWhere('id', $id), fn () => $this->api()->transactionDocument($id), 'documento_url');
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException|InvalidArgumentException $exception) {
            $this->alert('No fue posible cargar el documento', $exception->getMessage());

            return;
        }
        $this->dispatch('image-viewer:show', key: "purchase-transactions:{$id}", name: "Comprobante de transacción #{$id}", src: $src);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeAbility('purchase-transactions.delete');
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        $this->authorizeAbility('purchase-transactions.delete');
        if ($this->deletingId === null) {
            return;
        }
        try {
            $message = $this->api()->deleteTransaction($this->deletingId);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->showDeleteModal = false;
            $this->alert('No fue posible eliminar la transacción', $exception->getMessage());

            return;
        }
        $this->showDeleteModal = false;
        $this->toast($message ?: 'Transacción eliminada correctamente.');
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
        $visible = $this->transactions;
        $permissions = app(CurrentUserPermissions::class);

        return view('livewire.farma.compra-transacciones', [
            'visibleTransactions' => $visible,
            'documentRequired' => $this->transactionRequiresDocument(),
            'cashPayment' => $this->transactionIsCash(),
            'cashHasPriorPayments' => $this->cashHasPriorPayments(),
            'selectedPurchaseTotal' => $this->selectedPurchaseTotal(),
            'access' => [
                'create' => $permissions->allowsAll(['purchase-transactions.create', 'purchase-transactions.options']),
                'edit' => $permissions->allowsAll(['purchase-transactions.search', 'purchase-transactions.update', 'purchase-transactions.options']),
                'delete' => $permissions->allows('purchase-transactions.delete'), 'document' => $permissions->allows('purchase-transactions.document'),
            ],
        ]);
    }

    private function loadPage(int $page = 1): void
    {
        $this->authorizeAbility('purchase-transactions.list');
        $this->loadError = null;
        try {
            $this->applyPage($this->api()->listTransactions($page, search: $this->search));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->loadError = $exception->getMessage();
        }
    }

    private function applyPage(PaginatedCollectionData $page): void
    {
        $this->transactions = $page->items;
        $this->total = $page->total;
        $this->page = $page->currentPage;
        $this->lastPage = $page->lastPage;
    }

    private function loadOptions(): bool
    {
        return true;
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->compra = $this->tipo = $this->nro = $this->valor = '';
        $this->documentoArchivo = null;
        $this->documentoBase64 = $this->documentoMime = null;
        $this->hasStoredDocument = false;
        $this->originalPurchaseId = null;
        $this->originalValue = 0.0;
    }

    private function transactionRequiresDocument(): bool
    {
        foreach ($this->options['tipos'] ?? [] as $option) {
            if (! is_array($option) || (string) ($option['id'] ?? '') !== $this->tipo) {
                continue;
            }

            return Str::contains(Str::lower((string) ($option['label'] ?? '')), ['abono', 'nota de crédito', 'nota de credito']);
        }

        return false;
    }

    private function transactionIsCash(): bool
    {
        foreach ($this->options['tipos'] ?? [] as $option) {
            if (! is_array($option) || (string) ($option['id'] ?? '') !== $this->tipo) {
                continue;
            }

            return Str::contains(Str::lower((string) ($option['label'] ?? '')), 'pago al contado');
        }

        return false;
    }

    /** @return array<string, mixed>|null */
    private function selectedPurchase(): ?array
    {
        foreach ($this->options['compras'] ?? [] as $purchase) {
            if (is_array($purchase) && (string) ($purchase['id'] ?? '') === $this->compra) {
                return $purchase;
            }
        }

        return null;
    }

    private function selectedPurchaseTotal(): ?float
    {
        $purchase = $this->selectedPurchase();

        return $purchase === null ? null : (float) ($purchase['total'] ?? 0);
    }

    private function cashHasPriorPayments(): bool
    {
        if (! $this->transactionIsCash()) {
            return false;
        }

        $purchase = $this->selectedPurchase();

        if ($purchase === null) {
            return false;
        }

        $applied = (float) ($purchase['total_aplicado'] ?? max(0, (float) ($purchase['total'] ?? 0) - (float) ($purchase['saldo'] ?? 0)));

        if ($this->editingId !== null && $this->originalPurchaseId === (int) $this->compra) {
            $applied = max(0, $applied - $this->originalValue);
        }

        return $applied > 0.005;
    }

    private function syncCashValue(): void
    {
        if (! $this->transactionIsCash()) {
            return;
        }

        $total = $this->selectedPurchaseTotal();

        if ($total !== null) {
            $this->valor = number_format($total, 2, '.', '');
        }

        if ($this->cashHasPriorPayments()) {
            $this->addError('valor', 'La factura ya tiene abonos o notas aplicadas; utiliza un abono por el saldo restante.');
        }
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
}
