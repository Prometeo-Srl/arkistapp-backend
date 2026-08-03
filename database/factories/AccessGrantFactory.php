<?php

namespace Database\Factories;

use App\Enums\AccessPermission;
use App\Enums\GranteeType;
use App\Models\AccessGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessGrant>
 */
class AccessGrantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grantable_type' => 'file',
            'grantable_id' => FileFactory::new(),
            'grantee_type' => GranteeType::User,
            'grantee_id' => User::factory(),
            'permission' => AccessPermission::Viewer,
            'granted_by_id' => User::factory(),
            'expires_at' => null,
        ];
    }
}
