<?php

namespace Tests\Feature;

use Tests\TestCase;

class SidebarMenuAnimationTest extends TestCase
{
    public function test_permission_menus_include_accessible_motion_aware_animations(): void
    {
        $sidebar = file_get_contents(resource_path('views/layouts/app/sidebar.blade.php'));
        $styles = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($sidebar);
        $this->assertIsString($styles);
        $this->assertStringContainsString('data-animated-menu', $sidebar);
        $this->assertStringContainsString('permission-menu-accordion', $sidebar);
        $this->assertStringContainsString('permission-submenu-accordion', $sidebar);
        $this->assertStringContainsString('permission-menu-mobile-group', $sidebar);
        $this->assertStringContainsString('permission-submenu-mobile-group', $sidebar);
        $this->assertStringContainsString('prefers-reduced-motion: no-preference', $styles);
        $this->assertStringContainsString(':is(.permission-menu-accordion, .permission-submenu-accordion)::details-content', $styles);
        $this->assertStringContainsString(':is(.permission-menu-mobile-group, .permission-submenu-mobile-group) > div[data-open]', $styles);
        $this->assertStringContainsString('.permission-menu-panel::before', $styles);
        $this->assertStringContainsString('@keyframes permission-menu-enter', $styles);
    }
}
