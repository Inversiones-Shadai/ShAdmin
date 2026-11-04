<?php

namespace Tests\Feature\Auth;

use App\Services\Auth\CurrentUserPermissions;
use Tests\TestCase;

class InventoryControlPermissionTest extends TestCase
{
    public function test_control_abilities_are_individual_and_use_their_own_portal_hierarchy(): void
    {
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api']);
        session()->put('auth.api.spermisos', [[
            'modulo_l' => ['codigo' => 'frm'],
            'vistas_l' => [['vistai' => ['codigo' => 'civ'], 'accionesi' => [['codigo' => 'civbtn02']]]],
        ]]);
        $permissions = app(CurrentUserPermissions::class);
        $this->assertTrue($permissions->allows('inventory-control.view'));
        $this->assertTrue($permissions->allows('inventory-control.show'));
        $this->assertFalse($permissions->allows('inventory-control.list'));
        $this->assertFalse($permissions->allows('inventory-control.filter'));
        $this->assertFalse($permissions->allows('inventory-matrix.list'));
    }

    public function test_catalog_matches_central_actions_without_exposing_an_unimplemented_route(): void
    {
        $view = config('api_permissions.catalog.frm.views.civ');
        $this->assertSame('Control de inventario', $view['label']);
        $this->assertSame(['civbtn01', 'civbtn02', 'civbtn03'], $view['actions']);
        $this->assertNull($view['route']);
        $this->assertSame(['frm', 'civ', 'civbtn01'], config('api_permissions.abilities.inventory-control.list'));
        $this->assertSame(['frm', 'civ', 'civbtn02'], config('api_permissions.abilities.inventory-control.show'));
        $this->assertSame(['frm', 'civ', 'civbtn03'], config('api_permissions.abilities.inventory-control.filter'));
    }
}
