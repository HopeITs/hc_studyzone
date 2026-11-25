<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Roles;
use App\Enums\UserPermission;
use App\Models\RolePermission;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

class UserPermissions extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected string $view = 'filament.pages.user-permissions';

    public ?array $data = [];

    #[Locked]
    public string $targetMode = 'name';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->role === Roles::GeneralAdmin() && (bool) $user->is_active;
    }

    public static function getNavigationLabel(): string
    {
        return __('general.user_permissions.title');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            $this->targetField()->visible(fn (): bool => $this->targetMode === 'name'),
            $this->roleField()->visible(fn (): bool => $this->targetMode === 'role'),
            Text::make(__('general.user_permissions.read_only_role'))
                ->color('gray')
                ->visible(fn (): bool => $this->targetRole() !== null && ! $this->canEditTarget()),
            Text::make(__('general.user_permissions.view_required'))
                ->color('gray')
                ->visible(fn (): bool => $this->targetRole() !== null),
            Grid::make(['default' => 1, 'lg' => 2])
                ->visible(fn (): bool => $this->targetRole() !== null)
                ->schema([
                    $this->permissionCard('students', 'heroicon-o-academic-cap', [
                        UserPermission::ViewStudents, UserPermission::CreateStudent,
                        UserPermission::EditStudent, UserPermission::DeleteStudent,
                    ]),
                    $this->permissionCard('visits', 'heroicon-o-calendar-days', [
                        UserPermission::ViewEntries, UserPermission::CreateEntry,
                        UserPermission::EditEntry, UserPermission::DeleteEntry,
                    ]),
                ]),
            Section::make(__('general.user_permissions.catalog_title'))
                ->description(__('general.user_permissions.catalog_description'))
                ->visible(fn (): bool => $this->targetRole() !== null)
                ->schema([
                    Grid::make(['default' => 1, 'lg' => 2])->schema($this->catalogCards()),
                ]),
        ]);
    }

    public function switchTargetMode(string $mode): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(in_array($mode, ['name', 'role'], true), 422);

        if ($mode === $this->targetMode) {
            return;
        }

        $this->targetMode = $mode;
        unset($this->selectedUser);
        $this->form->fill();
    }

    private function roleField(): Select
    {
        return Select::make('role_id')
            ->label(__('general.labels.role'))
            ->helperText(__('general.user_permissions.roles_help'))
            ->options(__('general.roles'))
            ->required()
            ->live()
            ->afterStateUpdated(function (): void {
                $this->form->fill([
                    ...$this->targetState(),
                    ...$this->choicesFor(RolePermission::overridesFor((int) $this->targetRole())),
                ]);
            });
    }

    protected function targetField(): Select
    {
        return Select::make('user_id')
            ->label(__('general.labels.full_name'))
            ->placeholder(__('general.user_permissions.search_name'))
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => $this->eligibleUsers()
                ->where('name', 'like', '%'.trim($search).'%')
                ->orderBy('name')
                ->limit(50)
                ->get()
                ->mapWithKeys(fn (User $user): array => [$user->getKey() => $this->userLabel($user)])
                ->all())
            ->getOptionLabelUsing(function (int|string $value): ?string {
                $user = $this->eligibleUsers()->find($value);

                return $user ? $this->userLabel($user) : null;
            })
            ->required()
            ->live()
            ->afterStateUpdated(function (int|string|null $state): void {
                unset($this->selectedUser);
                $this->form->fill([
                    'user_id' => $state,
                    ...$this->formPermissions($this->eligibleUsers()->find($state)),
                ]);
            });
    }

    private function catalogCards(): array
    {
        $groups = __('general.user_permissions.catalog');

        return array_map(fn (string $key, array $group): Section => Section::make($group['title'])
            ->columnSpan(1)
            ->collapsed()
            ->schema(array_map(fn (string $label, int $index): ToggleButtons => ToggleButtons::make("catalog_{$key}_{$index}")
                ->label($label)
                ->options([
                    'inherit' => __('general.user_permissions.inherit'),
                    'allow' => __('general.user_permissions.allow'),
                    'deny' => __('general.user_permissions.deny'),
                ])
                ->grouped()
                ->fullWidth()
                ->disabled()
                ->dehydrated(false), $group['actions'], array_keys($group['actions']))), array_keys($groups), $groups);
    }

    private function permissionCard(string $group, string $icon, array $permissions): Section
    {
        return Section::make(__('general.user_permissions.'.$group))
            ->icon($icon)
            ->columnSpan(1)
            ->schema(array_map(fn (UserPermission $permission): ToggleButtons => ToggleButtons::make($permission->value)
                ->label(__('general.user_permissions.'.$permission->value))
                ->options([
                    'inherit' => __('general.user_permissions.inherit'),
                    'allow' => __('general.user_permissions.allow'),
                    'deny' => __('general.user_permissions.deny'),
                ])
                ->colors(['inherit' => 'primary', 'allow' => 'success', 'deny' => 'danger'])
                ->grouped()
                ->fullWidth()
                ->helperText(fn (): string => __(
                    'general.user_permissions.'.($this->inheritedPermission($permission)
                        ? 'role_allows'
                        : 'role_denies')
                ))
                ->default('inherit')
                ->disabled(fn (): bool => ! $this->canEditTarget())
                ->required(), $permissions));
    }

    public function setAllPermissions(string $choice): void
    {
        abort_unless(static::canAccess() && $this->canEditTarget(), 403);
        abort_unless(in_array($choice, ['inherit', 'allow', 'deny'], true), 422);

        $this->form->fill([
            ...$this->targetState(),
            ...array_fill_keys(array_column(UserPermission::cases(), 'value'), $choice),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        abort_unless($this->canEditTarget(), 403);

        $permissions = [];

        foreach (UserPermission::cases() as $permission) {
            $choice = $data[$permission->value];

            if ($choice !== 'inherit') {
                $permissions[$permission->value] = $choice === 'allow';
            }
        }

        $this->persistPermissions($data, $permissions);

        Notification::make()->success()->title(__('general.user_permissions.saved'))->send();
    }

    protected function persistPermissions(array $data, array $permissions): void
    {
        if ($this->targetMode === 'role') {
            $role = $this->targetRole();
            abort_unless($role !== null && UserPermission::supportsOverrides($role), 403);

            DB::transaction(fn () => RolePermission::query()->updateOrCreate(
                ['role' => $role],
                ['permissions' => $permissions],
            ));

            return;
        }

        DB::transaction(function () use ($data, $permissions): void {
            $user = $this->eligibleUsers()->lockForUpdate()->findOrFail($data['user_id']);
            abort_unless(UserPermission::supportsOverrides((int) $user->role), 403);
            $user->permission_overrides = $permissions === [] ? null : [
                'role' => (int) $user->role,
                'permissions' => $permissions,
            ];
            $user->save();
        });
    }

    public function targetRole(): ?int
    {
        if ($this->targetMode === 'role') {
            $role = filter_var($this->data['role_id'] ?? null, FILTER_VALIDATE_INT);

            return $role !== false && Roles::tryFrom($role) !== null ? $role : null;
        }

        return $this->selectedUser ? (int) $this->selectedUser->role : null;
    }

    public function canEditTarget(): bool
    {
        $role = $this->targetRole();

        return $role !== null && UserPermission::supportsOverrides($role);
    }

    protected function targetState(): array
    {
        return $this->targetMode === 'role'
            ? ['role_id' => $this->targetRole()]
            : ['user_id' => $this->selectedUser->getKey()];
    }

    protected function inheritedPermission(UserPermission $permission): bool
    {
        $role = $this->targetRole();
        if ($this->targetMode === 'role') {
            return $permission->defaultFor((int) $role);
        }

        $overrides = once(fn (): array => RolePermission::overridesFor((int) $this->targetRole()));

        return $overrides[$permission->value] ?? $permission->defaultFor((int) $role);
    }

    private function eligibleUsers(): Builder
    {
        abort_unless(static::canAccess(), 403);

        return User::query();
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        $id = $this->data['user_id'] ?? null;

        return is_int($id) || is_string($id) ? $this->eligibleUsers()->find($id) : null;
    }

    private function userLabel(User $user): string
    {
        return $user->name.' ('.$user->email.') · '.__('general.roles.'.$user->role);
    }

    private function formPermissions(?User $user): array
    {
        $overrides = $user?->permission_overrides ?? [];
        $permissions = ($overrides['role'] ?? null) === (int) $user?->role ? $overrides['permissions'] ?? [] : [];

        return $this->choicesFor($permissions);
    }

    protected function choicesFor(array $permissions): array
    {
        return collect(UserPermission::cases())->mapWithKeys(fn (UserPermission $permission): array => [
            $permission->value => match ($permissions[$permission->value] ?? null) {
                true => 'allow',
                false => 'deny',
                default => 'inherit',
            },
        ])->all();
    }
}
