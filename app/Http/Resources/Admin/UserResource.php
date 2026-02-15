<?php

namespace App\Http\Resources\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'permission_slugs' => $this->whenLoaded('roles', function (): array {
                return $this->roles
                    ->flatMap(function (Model $role): array {
                        if (! $role instanceof \App\Models\Role) {
                            return [];
                        }

                        return $role->permissions->pluck('slug')->all();
                    })
                    ->unique()
                    ->values()
                    ->all();
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
