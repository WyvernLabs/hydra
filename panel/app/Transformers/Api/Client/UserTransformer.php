<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\User;

class UserTransformer extends BaseClientTransformer
{
    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return User::RESOURCE_NAME;
    }

    /**
     * Transforms a User model into a representation that can be shown to regular
     * users of the API.
     */
    public function transform(User $model): array
    {
        // Hydra: the email address is only shown to the user themselves and to
        // staff, the picture is the uploaded one instead of a Gravatar (whose
        // hash would reveal the email), and the online status follows the
        // user's own setting.
        $viewer = isset($this->request) ? $this->request->user() : null;
        $private = !$viewer || (!$viewer->is($model) && !$viewer->canAccessAllServers());

        return array_merge([
            'uuid' => $model->uuid,
            'identifier' => $model->identifier,
            'username' => $model->username,
        ], $private ? [] : ['email' => $model->email], [
            'image' => $model->avatarUrl(),
            'online' => $model->show_online || !$private ? $model->isOnline() : null,
            '2fa_enabled' => $model->use_totp,
            'created_at' => $model->created_at->toAtomString(),
        ]);
    }
}
