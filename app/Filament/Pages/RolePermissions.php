<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Roles;
use App\Enums\UserPermission;
use App\Models\RolePermission;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\DB;

class RolePermissions extends UserPermissions
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    public static function getNavigationLabel(): string
    {
        return __('general.user_permissions.roles_title');
    }

    protected function targetField(): Select
    {
        return Select::make('role_id')
            ->label(__('general.labels.role'))
            ->helperText(__('general.user_permissions.roles_help'))
            ->options([
                Roles::CityAdmin() => __('general.roles.'.Roles::CityAdmin()),
                Roles::CenterAdmin() => __('general.roles.'.Roles::CenterAdmin()),
            ])
            ->required()
            ->live()
            ->afterStateUpdated(function (): void {
                $this->form->fill([
                    ...$this->targetState(),
                    ...$this->choicesFor(RolePermission::overridesFor((int) $this->targetRole())),
                ]);
            });
    }

    public function targetRole(): ?int
    {
        $role = filter_var($this->data['role_id'] ?? null, FILTER_VALIDATE_INT);

        return $role !== false && UserPermission::supportsOverrides($role) ? $role : null;
    }

    protected function targetState(): array
    {
        return ['role_id' => $this->targetRole()];
    }

    protected function inheritedPermission(UserPermission $permission): bool
    {
        return $permission->defaultFor((int) $this->targetRole());
    }

    protected function persistPermissions(array $data, array $permissions): void
    {
        $role = $this->targetRole();
        abort_if($role === null, 422);

        DB::transaction(fn () => RolePermission::query()->updateOrCreate(
            ['role' => $role],
            ['permissions' => $permissions],
        ));
    }
}
