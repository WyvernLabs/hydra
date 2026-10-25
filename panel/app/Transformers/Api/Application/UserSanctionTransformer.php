<?php

namespace Pterodactyl\Transformers\Api\Application;

use Pterodactyl\Models\UserSanction;

/**
 * Hydra: a warning or a ban of a user account.
 */
class UserSanctionTransformer extends BaseTransformer
{
    public function getResourceName(): string
    {
        return UserSanction::RESOURCE_NAME;
    }

    public function transform(UserSanction $sanction): array
    {
        return [
            'id' => $sanction->id,
            'user' => $sanction->user_id,
            'type' => $sanction->type,
            'reason' => $sanction->reason,
            'active' => $sanction->isActive(),
            'permanent' => $sanction->isBan() && is_null($sanction->expires_at),
            'issued_by' => $sanction->issued_by,
            'expires_at' => $sanction->expires_at ? $this->formatTimestamp($sanction->expires_at) : null,
            'lifted_at' => $sanction->lifted_at ? $this->formatTimestamp($sanction->lifted_at) : null,
            'lifted_by' => $sanction->lifted_by,
            'acknowledged_at' => $sanction->acknowledged_at ? $this->formatTimestamp($sanction->acknowledged_at) : null,
            'created_at' => $this->formatTimestamp($sanction->created_at),
        ];
    }
}
