<?php

declare(strict_types=1);

namespace App\Enums;

enum UserPermission: string
{
    case ViewStudents = 'view_students';
    case CreateStudent = 'create_student';
    case EditStudent = 'edit_student';
    case DeleteStudent = 'delete_student';
    case ViewEntries = 'view_entries';
    case CreateEntry = 'create_entry';
    case EditEntry = 'edit_entry';
    case DeleteEntry = 'delete_entry';

    public static function supportsOverrides(int $role): bool
    {
        return in_array($role, [Roles::CityAdmin(), Roles::CenterAdmin()], true);
    }

    public function defaultFor(int $role): bool
    {
        return match ($this) {
            self::ViewStudents, self::ViewEntries => in_array($role, [
                Roles::GeneralAdmin(), Roles::CityAdmin(), Roles::CenterAdmin(), Roles::MealAdmin(), Roles::Observer(),
            ], true),
            self::CreateStudent, self::DeleteStudent => in_array($role, [Roles::GeneralAdmin(), Roles::CenterAdmin()], true),
            default => $role === Roles::GeneralAdmin() || self::supportsOverrides($role),
        };
    }
}
