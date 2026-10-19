<?php

namespace Pterodactyl\Services\Subusers;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subuser;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Repositories\Eloquent\SubuserRepository;
use Pterodactyl\Contracts\Repository\UserRepositoryInterface;
use Pterodactyl\Exceptions\Repository\RecordNotFoundException;
use Pterodactyl\Exceptions\Service\Subuser\UserNotFoundException;
use Pterodactyl\Exceptions\Service\Subuser\UserIsServerOwnerException;
use Pterodactyl\Exceptions\Service\Subuser\ServerSubuserExistsException;

class SubuserCreationService
{
    /**
     * SubuserCreationService constructor.
     */
    public function __construct(
        private ConnectionInterface $connection,
        private SubuserRepository $subuserRepository,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Assigns an existing user, found by their username, to the provided server.
     * Hydra: unlike Pterodactyl no account is created for unknown users.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws ServerSubuserExistsException
     * @throws UserIsServerOwnerException
     * @throws UserNotFoundException
     * @throws \Throwable
     */
    public function handle(Server $server, string $username, array $permissions): Subuser
    {
        return $this->connection->transaction(function () use ($server, $username, $permissions) {
            try {
                // Usernames are stored in lower case.
                $user = $this->userRepository->findFirstWhere([['username', '=', mb_strtolower(trim($username))]]);
            } catch (RecordNotFoundException) {
                throw new UserNotFoundException(trans('exceptions.subusers.user_not_found', ['username' => $username]));
            }

            if ($server->owner_id === $user->id) {
                throw new UserIsServerOwnerException(trans('exceptions.subusers.user_is_owner'));
            }

            $subuserCount = $this->subuserRepository->findCountWhere([['user_id', '=', $user->id], ['server_id', '=', $server->id]]);
            if ($subuserCount !== 0) {
                throw new ServerSubuserExistsException(trans('exceptions.subusers.subuser_exists'));
            }

            return $this->subuserRepository->create([
                'user_id' => $user->id,
                'server_id' => $server->id,
                'permissions' => array_unique($permissions),
            ]);
        });
    }
}
