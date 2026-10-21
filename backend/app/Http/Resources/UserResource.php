<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $household = $this->currentHousehold;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'timezone' => $this->timezone,
            // Configurações › Avisos: cada tipo ligado ou não.
            'alert_settings' => $this->alertSettings(),
            'current_household' => $household ? [
                'id' => $household->id,
                'name' => $household->name,
                'role' => $this->roleIn($household),
            ] : null,
        ];
    }
}
