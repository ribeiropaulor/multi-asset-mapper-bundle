<?php
namespace PRR\MultiAssetMapperBundle\Command;

use PRR\MultiAssetMapperBundle\AssetCollectionToolkitRegistry;
use PRR\MultiAssetMapperBundle\Command\Helper\DebugAssetMapperCommandHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Outputs all the assets in the asset mapper.
 *
 * @author Paulo Ribeiro <paulo@prr.dev.br>
 */
#[AsCommand(name: 'mam:debug:asset-map', description: 'Output all mapped assets')]
final class DebugAssetMapperCommand extends Command
{
    public function __construct(
        private readonly AssetCollectionToolkitRegistry $collectionToolkitRegistry,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('collection', InputArgument::OPTIONAL, 'The asset collection (e.g.: admin, admin_v1, frontend_v3, etc) to debug (if not specified, all collections will be considered)')
            ->addOption('full', null, null, 'Whether to show the full paths')
            ->setHelp(<<<'EOT'
                The <info>%command.name%</info> command outputs all the assets in
                asset mapper for debugging purposes.
                EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $collectionName = $input->getArgument('collection');

        foreach ($this->collectionToolkitRegistry->toolkits as $toolkit) {
            if ($collectionName && $toolkit->collectionName !== $collectionName) {
                continue;
            }
            $io->title(sprintf('Asset Collection "%s"', $toolkit->collectionName));
            $helper = new DebugAssetMapperCommandHelper(
                assetMapper: $toolkit->assetMapper,
                assetMapperRepository: $toolkit->assetMapperRepository,
                projectDir: $this->projectDir,
            );
            $helper->execute($input, $io);
        }

        return Command::SUCCESS;
    }
}
