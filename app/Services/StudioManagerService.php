<?php

namespace App\Services;

use App\Models\Studio;
use App\Enums\UserRole;

class StudioManagerService
{
    public function __construct(
        private readonly StudioStaffService $studioStaffService,
    ) {
    }

    /**
     * @param  array{name:string,email:string,password?:string|null}  $attributes
     * @return array{user:\App\Models\User,studio_role:string,action:string}
     */
    public function createOrAttachManager(Studio $studio, array $attributes): array
    {
        return $this->studioStaffService->createOrAttach($studio, UserRole::Yonetici, $attributes);
    }
}
