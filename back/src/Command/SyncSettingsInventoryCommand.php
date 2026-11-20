<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Command;

use SymSvel\Module\Settings\Service\SettingInventorySynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'symsvel:settings:sync', description: 'Synchronize installed module parameter definitions')]
final class SyncSettingsInventoryCommand extends Command
{
    public function __construct(
        private readonly SettingInventorySynchronizer $synchronizer,
        #[Autowire('%kernel.project_dir%')] private readonly string $backend,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $changes = $this->synchronizer->sync($this->backend);
        $output->writeln(sprintf(
            'Settings synchronized: %d added, %d removed, %d metadata changed.',
            count($changes['added']), count($changes['removed']), count($changes['changed']),
        ));
        return Command::SUCCESS;
    }
}
