<?php

namespace PRR\MultiAssetMapperBundle\Command;

use PRR\MultiAssetMapperBundle\AssetCollectionToolkitRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Paulo Ribeiro <paulo@prr.dev.br>
 */
#[AsCommand(name: 'mam:importmap:remove', description: 'Remove JavaScript packages')]
final class ImportMapRemoveCommand extends Command
{
    public function __construct(
        private readonly AssetCollectionToolkitRegistry $collectionToolkitRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('collection', InputArgument::REQUIRED, 'The asset collection (e.g.: admin, admin_v1, frontend_v3, etc) that has the JavaScript package(s) to be removed')
            ->addArgument('packages', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The JavaScript packages to remove')
            ->setHelp(<<<'EOT'
                The <info>%command.name%</info> command removes JavaScript packages from the <comment>importmap.php</comment>.
                If a package was downloaded into your app, the downloaded file will also be removed.

                For example:

                    <info>php %command.full_name% admin_v1 lodash</info>
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
        $io->title(sprintf('Asset Collection "%s"', $toolkit->collectionName));

        $packageList = $input->getArgument('packages');
        $toolkit->importMapManager->remove($packageList);

        if (1 === \count($packageList)) {
            $io->success(\sprintf('Removed "%s" from importmap.php in asset collection "%s".', $packageList[0], $toolkit->collectionName));
        } else {
            $io->success(\sprintf('Removed %d items from importmap.php in asset collection "%s".', \count($packageList), $toolkit->collectionName));
        }

        return Command::SUCCESS;
    }
}
