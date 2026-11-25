<?php

declare(strict_types=1);

namespace App\Filament\Pages;

class RolePermissions extends UserPermissions
{
    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        parent::mount();

        $this->switchTargetMode('role');
    }
}
