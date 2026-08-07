<?php

namespace PRR\MultiAssetMapperBundle\Command;

use PRR\MultiAssetMapperBundle\AssetCollectionToolkitRegistry;
use PRR\MultiAssetMapperBundle\Command\Helper\ImportMapAuditCommandHelper;
use Symfony\Component\AssetMapper\ImportMap\ImportMapAuditor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'mam:importmap:audit', description: 'Check for security vulnerability advisories for dependencies')]
class ImportMapAuditCommand extends Command
{
    public function __construct(
        private readonly AssetCollectionToolkitRegistry $collectionToolkitRegistry,
        private readonly ?HttpClientInterface $httpClient = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            name: 'format',
            mode: InputOption::VALUE_REQUIRED,
            description: 'The output format ("txt", "json")',
            default: 'txt',
        );
        $this->addOption(
            name: 'collection',
            mode: InputOption::VALUE_REQUIRED,
            description: 'The asset collection (e.g.: admin, admin_v1, frontend_v3, etc) to audit (if not specified, all collections will be audited)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $collection = $input->getOption('collection');

        foreach ($this->collectionToolkitRegistry->toolkits as $toolkit) {
            if ($collection && $toolkit->collectionName !== $collection) {
                continue;
            }
            $io->title(sprintf('Asset Collection "%s"', $toolkit->collectionName));

            $auditor = new ImportMapAuditor($toolkit->importMapConfigReader, $this->httpClient);
            $command = new ImportMapAuditCommandHelper($auditor);
            $command->execute($input, $io);
        }

        return Command::SUCCESS;
    }
}
