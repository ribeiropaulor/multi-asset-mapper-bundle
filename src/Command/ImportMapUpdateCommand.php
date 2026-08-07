<?php

namespace PRR\MultiAssetMapperBundle\Command;

use PRR\MultiAssetMapperBundle\AssetCollectionToolkitRegistry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 * @author Paulo Ribeiro <paulo@prr.dev.br>
 */
#[AsCommand(name: 'mam:importmap:update', description: 'Update JavaScript packages to their latest versions')]
final class ImportMapUpdateCommand extends Command
{
    use VersionProblemCommandTrait;

    public function __construct(
        private readonly AssetCollectionToolkitRegistry $collectionToolkitRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('collection', InputArgument::REQUIRED, 'The asset collection (e.g.: admin, admin_v1, frontend_v3, etc) that has the JavaScript package(s) to be updated')
            ->addArgument('packages', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'The JavaScript packages to update')
            ->setHelp(<<<'EOT'
                The <info>%command.name%</info> command will update all from the 3rd part packages
                in <comment>importmap.php</comment> to their latest version, including downloaded packages.

                   <info>php %command.full_name%</info>

                Or specific packages only:

                    <info>php %command.full_name% <collection> <packages></info>
                EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // check if the asset package exists
        $collectionName = $input->getArgument('collection');
        if ($collectionName && $this->collectionToolkitRegistry->exists($collectionName) === false) {
            $io->error(sprintf('Asset collection "%s" does not exist.', $collectionName));
            return Command::FAILURE;
        }

        $toolkit = $this->collectionToolkitRegistry->get($collectionName);

        $packages = $input->getArgument('packages');

        $updatedPackages = $toolkit->importMapManager->update($packages);

        $this->renderVersionProblems($toolkit->importMapVersionChecker, $output);

        if (0 < \count($packages)) {
            $io->success(\sprintf(
                'Updated %s package%s in %s.',
                implode(', ', array_map(static fn (ImportMapEntry $entry): string => $entry->importName, $updatedPackages)),
                1 < \count($updatedPackages) ? 's' : '',
                $toolkit->getImportMapRelativePath()
            ));
        } else {
            $io->success(\sprintf('Updated all packages in %s.', $toolkit->getImportMapRelativePath()));
        }

        return Command::SUCCESS;
    }
}
