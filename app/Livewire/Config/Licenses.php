<?php

namespace App\Livewire\Config;

use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Services\Api\LicenseApiService;
use App\Services\Auth\CurrentUserPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Licenses extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $licenses = [];

    /** @var array<int, array<string, mixed>> */
    public array $customers = [];

    public int $customerPage = 1;

    public int $customerLastPage = 1;

    public string $customerSearch = '';

    public string $filterCustomerId = '';

    #[Locked]
    public string $filterCustomerName = '';

    public string $customerId = '';

    #[Locked]
    public string $choiceCustomerName = '';

    public bool $showCustomerForm = false;

    #[Locked]
    public ?string $editingCustomerId = null;

    public string $customerName = '';

    public int $page = 1;

    public int $lastPage = 1;

    public string $search = '';

    public ?string $error = null;

    public string $message = '';

    public bool $showForm = false;

    #[Locked]
    public ?string $editingId = null;

    #[Locked]
    public ?string $detailId = null;

    /** @var array<string, mixed> */
    public array $details = [];

    public string $label = '';

    public string $product = 'shpos';

    public int $seats = 1;

    public string $expiresAt = '';

    #[Locked]
    public string $expectedExpiresAt = '';

    public int $termMonths = 1;

    public int $renewalMonths = 0;

    public bool $enabled = true;

    public string $generatedKey = '';

    #[Locked]
    public string $generationChallengeId = '';

    public string $generationCode = '';

    public int $devicesPage = 1;

    public int $eventsPage = 1;

    public bool $showKeyModal = false;

    #[Locked]
    public ?string $keyLicenseId = null;

    #[Locked]
    public string $keyLicenseLabel = '';

    #[Locked]
    public string $keyChallengeId = '';

    public string $keyCode = '';

    // Render the secret only in the confirmation response, never in Livewire's public snapshot.
    protected string $revealedKey = '';

    public function mount(): void
    {
        $this->loadPage();
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->loadPage();
    }

    public function changePage(int $delta): void
    {
        $this->page = max(1, min($this->lastPage, $this->page + $delta));
        $this->loadPage();
    }

    public function create(): void
    {
        $this->authorizeAbility('licenses.generate');
        $this->resetGenerationApproval();
        $this->resetValidation();
        $this->editingId = null;
        $this->label = '';
        $this->product = 'shpos';
        $this->customerId = $this->filterCustomerId ?: (count($this->customers) === 1 ? (string) $this->customers[0]['id'] : '');
        $this->choiceCustomerName = $this->filterCustomerName ?: (count($this->customers) === 1 ? (string) $this->customers[0]['name'] : '');
        $this->seats = 1;
        $this->expiresAt = '';
        $this->expectedExpiresAt = '';
        $this->termMonths = 1;
        $this->renewalMonths = 0;
        $this->enabled = true;
        $this->generatedKey = '';
        $this->showForm = true;
    }

    public function edit(string $id): void
    {
        $this->authorizeAbility('licenses.update');
        $this->resetGenerationApproval();
        $record = collect($this->licenses)->firstWhere('id', $id);
        abort_unless(is_array($record), 404);
        $this->resetValidation();
        $this->editingId = $id;
        $this->label = (string) $record['label'];
        $this->product = (string) $record['product'];
        $this->customerId = (string) $record['customer_id'];
        $this->choiceCustomerName = (string) $record['customer_name'];
        $this->seats = (int) $record['seats'];
        $this->expiresAt = CarbonImmutable::parse($record['expires_at'], 'UTC')->setTimezone('America/Tegucigalpa')->format('d/m/Y H:i');
        $this->expectedExpiresAt = CarbonImmutable::parse($record['expires_at'], 'UTC')->toIso8601String();
        $this->termMonths = (int) ($record['term_months'] ?? 1);
        $this->renewalMonths = 0;
        $this->enabled = (bool) $record['enabled'];
        $this->generatedKey = '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeAbility($this->editingId ? 'licenses.update' : 'licenses.generate');
        if (! $this->editingId && $this->generationChallengeId === '') {
            $this->requestGenerationCode();

            return;
        }
        $this->validateLicenseData();
        if (! $this->editingId) {
            $this->validate(['generationCode' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']]);
        }
        $this->run(function (): void {
            $payload = ['customer_id' => $this->customerId, 'label' => trim($this->label), 'seats' => $this->seats];
            if ($this->editingId) {
                if ($this->renewalMonths !== 0) {
                    $payload['renewal_months'] = $this->renewalMonths;
                    $payload['expected_expires_at'] = $this->expectedExpiresAt;
                }
                app(LicenseApiService::class)->update($this->editingId, $payload + ['enabled' => $this->enabled]);
                $this->showForm = false;
                $this->message = 'Licencia actualizada.';
            } else {
                try {
                    $result = app(LicenseApiService::class)->generate($payload + ['product' => $this->product, 'term_months' => $this->termMonths,
                        'challenge_id' => $this->generationChallengeId, 'code' => $this->generationCode]);
                } finally {
                    $this->generationCode = '';
                }
                $this->generatedKey = (string) ($result['license_key'] ?? '');
                $this->resetGenerationApproval();
                $this->showForm = false;
                $this->message = 'Licencia generada con autorización por correo.';
            }
            $this->loadPage();
        });
    }

    public function requestGenerationCode(): void
    {
        $this->authorizeAbility('licenses.generate');
        abort_unless($this->editingId === null, 422);
        $this->validateLicenseData();
        $this->generationCode = '';
        $this->run(function (): void {
            $result = app(LicenseApiService::class)->requestGenerationCode([
                'customer_id' => $this->customerId, 'label' => trim($this->label), 'product' => $this->product,
                'seats' => $this->seats, 'term_months' => $this->termMonths,
            ]);
            $this->generationChallengeId = (string) ($result['challenge_id'] ?? '');
            $this->resetValidation('generationCode');
        });
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['customerId', 'label', 'product', 'seats', 'termMonths'], true)) {
            $this->resetGenerationApproval();
        }
    }

    public function updatedShowForm(bool $value): void
    {
        if (! $value) {
            $this->resetGenerationApproval();
        }
    }

    private function resetGenerationApproval(): void
    {
        $this->generationChallengeId = $this->generationCode = '';
        $this->resetValidation('generationCode');
    }

    private function validateLicenseData(): void
    {
        $this->label = trim($this->label);
        $this->validate([
            'customerId' => ['required', 'uuid'],
            'label' => ['required', 'string', 'max:160'], 'product' => ['required', 'in:hq-client,shpos,shpos-administrator'],
            'seats' => ['required', 'integer', 'min:1', 'max:10000'],
            'termMonths' => ['required', 'integer', 'in:1,6,12'], 'renewalMonths' => ['required', 'integer', 'in:0,1,6,12'],
        ]);
    }

    public function selectCustomer(string $id = ''): void
    {
        $this->authorizeAbility('licenses.list');
        $this->closeKeyReveal();
        $customer = $id === '' ? null : collect($this->customers)->firstWhere('id', $id);
        abort_if($id !== '' && ! is_array($customer), 404);
        $this->filterCustomerId = $id;
        $this->filterCustomerName = is_array($customer) ? (string) $customer['name'] : '';
        $this->page = 1;
        $this->lastPage = 1;
        $this->search = '';
        $this->licenses = [];
        $this->closeDetails();
        $this->loadPage();
    }

    public function updatedCustomerSearch(): void
    {
        $this->authorizeAbility('licenses.list');
        $this->customerPage = 1;
        $this->run(fn () => $this->loadCustomerOptions());
    }

    public function changeCustomerPage(int $delta): void
    {
        $this->authorizeAbility('licenses.list');
        $this->customerPage = max(1, min($this->customerLastPage, $this->customerPage + $delta));
        $this->run(fn () => $this->loadCustomerOptions());
    }

    public function updatedCustomerId(): void
    {
        $customer = collect($this->customers)->firstWhere('id', $this->customerId);
        if (is_array($customer)) {
            $this->choiceCustomerName = (string) $customer['name'];
        }
    }

    public function createCustomer(): void
    {
        $this->authorizeAbility('licenses.generate');
        $this->resetValidation();
        $this->editingCustomerId = null;
        $this->customerName = '';
        $this->showCustomerForm = true;
    }

    public function editCustomer(string $id): void
    {
        $this->authorizeAbility('licenses.update');
        $customer = collect($this->customers)->firstWhere('id', $id);
        abort_unless(is_array($customer), 404);
        $this->resetValidation();
        $this->editingCustomerId = $id;
        $this->customerName = (string) $customer['name'];
        $this->showCustomerForm = true;
    }

    public function saveCustomer(): void
    {
        $this->authorizeAbility($this->editingCustomerId === null ? 'licenses.generate' : 'licenses.update');
        $this->validate(['customerName' => ['required', 'string', 'max:160']]);
        $this->run(function (): void {
            $customer = app(LicenseApiService::class)->saveCustomer($this->editingCustomerId, trim($this->customerName));
            $this->showCustomerForm = false;
            $this->filterCustomerId = (string) $customer['id'];
            $this->filterCustomerName = (string) $customer['name'];
            $this->customerSearch = '';
            $this->customerPage = 1;
            $this->page = 1;
            $this->message = 'Cliente de licencias guardado.';
            $this->closeDetails();
            $this->loadPage();
        });
    }

    public function show(string $id): void
    {
        $this->authorizeAbility('licenses.detail');
        $this->detailId = $id;
        $this->devicesPage = 1;
        $this->eventsPage = 1;
        $this->loadDetails();
    }

    public function closeDetails(): void
    {
        $this->detailId = null;
        $this->details = [];
    }

    public function detailPage(string $kind, int $delta): void
    {
        if ($kind === 'devices') {
            $this->devicesPage = max(1, min((int) data_get($this->details, 'devices.last_page', 1), $this->devicesPage + $delta));
        } else {
            $this->eventsPage = max(1, min((int) data_get($this->details, 'events.last_page', 1), $this->eventsPage + $delta));
        }
        $this->loadDetails();
    }

    public function unlink(string $activation): void
    {
        $this->authorizeAbility('licenses.unlink');
        $this->authorizeAbility('licenses.detail');
        abort_unless($this->detailId !== null, 422);
        $this->run(function () use ($activation): void {
            $this->message = app(LicenseApiService::class)->unlink($this->detailId, $activation);
            $this->loadDetails();
            $this->loadPage();
        });
    }

    public function delete(string $id): void
    {
        $this->authorizeAbility('licenses.delete');
        $this->run(function () use ($id): void {
            $this->message = app(LicenseApiService::class)->delete($id);
            $this->closeKeyReveal();
            if ($this->detailId === $id) {
                $this->closeDetails();
            }
            if ($this->editingId === $id) {
                $this->editingId = null;
                $this->showForm = false;
            }
            $this->generatedKey = '';
            if (count($this->licenses) === 1 && $this->page > 1) {
                $this->page--;
            }
            $this->loadPage();
        });
    }

    public function requestKeyCode(string $id): void
    {
        $this->authorizeAbility('licenses.detail');
        $record = collect($this->licenses)->firstWhere('id', $id);
        abort_unless(is_array($record), 404);
        if ($this->keyLicenseId !== $id) {
            $this->closeKeyReveal();
        }
        $this->keyCode = $this->revealedKey = '';
        $this->resetValidation('keyCode');
        $this->message = '';
        $this->run(function () use ($id, $record): void {
            $result = app(LicenseApiService::class)->requestKeyCode($id);
            $this->keyLicenseId = $id;
            $this->keyLicenseLabel = (string) $record['label'];
            $this->keyChallengeId = (string) ($result['challenge_id'] ?? '');
            $this->showKeyModal = true;
        });
    }

    public function revealKey(): void
    {
        $this->authorizeAbility('licenses.detail');
        abort_unless($this->keyLicenseId !== null && $this->keyChallengeId !== '', 422);
        $this->validate(['keyCode' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']]);
        $this->run(function (): void {
            $this->revealedKey = app(LicenseApiService::class)->revealKey($this->keyLicenseId, $this->keyChallengeId, $this->keyCode);
            $this->keyChallengeId = '';
        });
        $this->keyCode = '';
    }

    public function closeKeyReveal(): void
    {
        $this->showKeyModal = false;
        $this->keyLicenseId = null;
        $this->keyLicenseLabel = $this->keyChallengeId = $this->keyCode = $this->revealedKey = '';
        $this->resetValidation('keyCode');
    }

    public function updatedShowKeyModal(bool $value): void
    {
        if (! $value) {
            $this->closeKeyReveal();
        }
    }

    public function render(): View
    {
        $this->authorizeAbility('licenses.list');

        return view('livewire.config.licenses', ['access' => app(CurrentUserPermissions::class), 'revealedKey' => $this->revealedKey]);
    }

    private function loadPage(): void
    {
        $this->authorizeAbility('licenses.list');
        $this->run(function (): void {
            $this->loadCustomerOptions();
            if ($this->filterCustomerId === '') {
                $this->licenses = [];
                $this->page = 1;
                $this->lastPage = 1;

                return;
            }
            $result = app(LicenseApiService::class)->list($this->page, $this->search, $this->filterCustomerId);
            $this->licenses = $result->items;
            $this->page = $result->currentPage;
            $this->lastPage = $result->lastPage;
        });
    }

    private function loadCustomerOptions(): void
    {
        $result = app(LicenseApiService::class)->customers($this->customerPage, $this->customerSearch);
        $this->customers = $result->items;
        $this->customerPage = $result->currentPage;
        $this->customerLastPage = $result->lastPage;
        $selected = collect($this->customers)->firstWhere('id', $this->filterCustomerId);
        if (is_array($selected)) {
            $this->filterCustomerName = (string) $selected['name'];
        }
    }

    private function loadDetails(): void
    {
        $this->authorizeAbility('licenses.detail');
        if ($this->detailId) {
            $this->run(function (): void {
                $this->details = app(LicenseApiService::class)->detail($this->detailId, $this->devicesPage, $this->eventsPage);
            });
        }
    }

    private function authorizeAbility(string $ability): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, $ability);
    }

    private function run(callable $operation): void
    {
        $this->error = null;
        try {
            $operation();
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login', navigate: true);
        } catch (ApiOperationException $error) {
            $this->error = $error->getMessage();
            foreach (['name' => 'customerName', 'customer_id' => 'customerId'] as $field => $property) {
                $messages = $error->errors[$field] ?? [];
                $message = is_array($messages) ? reset($messages) : $messages;
                if (is_string($message) && $message !== '') {
                    $this->addError($property, $message);
                }
            }
        }
    }
}
