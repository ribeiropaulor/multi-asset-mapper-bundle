<?php

namespace PRR\MultiAssetMapperBundle\Command;

use PRR\MultiAssetMapperBundle\AssetCollectionToolkitRegistry;
use PRR\MultiAssetMapperBundle\Command\Helper\AssetMapperCompileCommandHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Compiles the assets in the asset mapper to the final output directory.
 *
 * This command is intended to be used during deployment.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
#[AsCommand(name: 'mam:asset-map:compile', description: 'Compile all mapped assets and writes them to the final public output directory')]
final class AssetMapperCompileCommand extends Command
{
    public function __construct(
        private readonly AssetCollectionToolkitRegistry $collectionToolkitRegistry,
        private readonly string $projectDir,
        private readonly bool $isDebug,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(<<<'EOT'
                The <info>%command.name%</info> command compiles and dumps all the assets in
                the asset mapper into the final public directory (usually <comment>public/asset-collections</comment>).

                This command is meant to be run during deployment.
                EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        foreach ($this->collectionToolkitRegistry->toolkits as $toolkit) {
            $io->section(sprintf('Compiling package "%s"', $toolkit->collectionName));
            $helper = new AssetMapperCompileCommandHelper(
                compiledConfigReader: $toolkit->compiledConfigReader,
                assetMapper: $toolkit->assetMapper,
                importMapGenerator: $toolkit->importMapGenerator,
                assetsFilesystem: $toolkit->assetsFilesystem,
                projectDir: $this->projectDir,
                isDebug: $this->isDebug,
                eventDispatcher: $this->eventDispatcher,
            );
            $helper->execute($input, $output, $io);
        }

        return Command::SUCCESS;
    }
}
