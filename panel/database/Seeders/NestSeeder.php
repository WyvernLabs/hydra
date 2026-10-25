<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Pterodactyl\Services\Nests\NestCreationService;
use Pterodactyl\Contracts\Repository\NestRepositoryInterface;

class NestSeeder extends Seeder
{
    /**
     * The default nests of Hydra in the order they are created (and so get
     * their IDs). The folder of their eggs in Seeders/eggs is the kebab-case name.
     *
     * @var array<string, string>
     */
    public static array $nests = [
        'Minecraft' => 'Minecraft - the classic game from Mojang. Java Edition with Vanilla, Paper, Fabric, Forge and many others, and Bedrock Edition.',
        'Standalone' => 'Game servers that are downloaded directly from the developer, such as Hytale and Terraria.',
        'Steam Games' => 'Game servers that are installed through SteamCMD.',
        'Voice Servers' => 'Voice servers such as TeamSpeak 3.',
        'Applications' => 'Bots and applications such as music bots, AFK bots and workflow automation.',
        'Environments' => 'Generic runtimes for your own Java, Node.js and Python applications.',
    ];

    /**
     * NestSeeder constructor.
     */
    public function __construct(
        private NestCreationService $creationService,
        private NestRepositoryInterface $repository,
    ) {
    }

    /**
     * Run the seeder to add missing nests to the Panel.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function run()
    {
        $items = $this->repository->findWhere([
            'author' => 'support@pterodactyl.io',
        ])->keyBy('name')->toArray();

        foreach (static::$nests as $name => $description) {
            if (is_null(array_get($items, $name))) {
                $this->creationService->handle([
                    'name' => $name,
                    'description' => $description,
                ], 'support@pterodactyl.io');
            }
        }
    }
}
