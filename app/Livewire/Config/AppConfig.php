<?php

namespace App\Livewire\Config;

use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Services\Api\AppConfigApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AppConfig extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $records = [];

    public int $page = 1;

    public int $lastPage = 1;

    public int $total = 0;

    public ?string $loadError = null;

    public ?string $saveError = null;

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $appv = '';

    public string $mail_alert = '';

    public string $mail_cc1 = '';

    public string $mail_cc2 = '';

    public string $mail_cc3 = '';

    public function mount(): void
    {
        $this->loadPage();
    }

    public function loadPage(int $page = 1): void
    {
        $this->authorizeAbility('appconfig.list');
        $this->loadError = null;
        try {
            $result = $this->api()->page(max(1, $page));
            $this->records = $result->items;
            $this->page = $result->currentPage;
            $this->lastPage = $result->lastPage;
            $this->total = $result->total;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->loadError = $exception->getMessage();
        }
    }

    public function openCreate(): void
    {
        $this->authorizeAbility('appconfig.list');
        $this->authorizeAbility('appconfig.create');
        $this->closeForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorizeAbility('appconfig.list');
        $this->authorizeAbility('appconfig.update');
        $this->closeForm();
        try {
            $record = $this->api()->find($id);
            $this->editingId = $id;
            foreach (['appv', 'mail_alert', 'mail_cc1', 'mail_cc2', 'mail_cc3'] as $field) {
                $this->{$field} = (string) ($record[$field] ?? '');
            }
            $this->showForm = true;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->dispatch('sweetalert:alert', icon: 'error', title: 'No fue posible cargar la configuración', text: $exception->getMessage());
        }
    }

    public function closeForm(): void
    {
        $this->reset('showForm', 'editingId', 'appv', 'mail_alert', 'mail_cc1', 'mail_cc2', 'mail_cc3', 'saveError');
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorizeAbility('appconfig.list');
        $this->authorizeAbility($this->editingId === null ? 'appconfig.create' : 'appconfig.update');
        $this->saveError = null;
        $validated = $this->validate([
            'appv' => ['nullable', 'string', 'max:65535'],
            'mail_alert' => ['required', 'email:rfc', 'max:255'],
            'mail_cc1' => ['required', 'email:rfc', 'max:255'],
            'mail_cc2' => ['nullable', 'email:rfc', 'max:255'],
            'mail_cc3' => ['nullable', 'email:rfc', 'max:255'],
        ], [
            'required' => 'Este campo es obligatorio.',
            'email' => 'Ingresa un correo electrónico válido.',
            'max' => 'El campo no puede exceder :max caracteres.',
        ]);

        $payload = [];
        foreach ($validated as $field => $value) {
            $value = trim((string) $value);
            $payload[$field] = $value === '' ? null : $value;
        }

        try {
            $message = $this->api()->save($this->editingId, $payload);
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');

            return;
        } catch (ApiOperationException $exception) {
            $this->saveError = $exception->getMessage();
            foreach ($exception->errors as $field => $messages) {
                foreach ((array) $messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        }

        $page = $this->editingId === null ? 1 : $this->page;
        $this->closeForm();
        $this->dispatch('sweetalert:toast', icon: 'success', title: $message ?: 'Configuración guardada correctamente.');
        $this->loadPage($page);
    }

    public function render(): View
    {
        return view('livewire.config.app-config', ['access' => app(CurrentUserPermissions::class)]);
    }

    private function authorizeAbility(string $ability): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, $ability);
    }

    private function api(): AppConfigApiService
    {
        return app(AppConfigApiService::class);
    }
}
