<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\Subuser;

class SubuserTransformer extends BaseClientTransformer
{
    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return Subuser::RESOURCE_NAME;
    }

    /**
     * Transforms a subuser into a model that can be shown to a front-end user.
     *
     * @throws \Pterodactyl\Exceptions\Transformer\InvalidTransformerLevelException
     */
    public function transform(Subuser $model): array
    {
        // Hydra: other users only see the username, never the email address.
        return array_merge(
            collect($this->makeTransformer(UserTransformer::class)->transform($model->user))->except('email')->all(),
            ['permissions' => $model->permissions, 'allowed_paths' => $model->allowed_paths ?? [], 'role_id' => $model->hydra_role_id]
        );
    }
}
