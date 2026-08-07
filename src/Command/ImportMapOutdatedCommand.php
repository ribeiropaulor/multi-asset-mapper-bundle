<?php

namespace PRR\MultiAssetMapperBundle\Command;

use PRR\MultiAssetMapperBundle\AssetCollectionToolkitRegistry;
use PRR\MultiAssetMapperBundle\Command\Helper\ImportMapOutdatedCommandHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'mam:importmap:outdated', description: 'List outdated JavaScript packages and their latest versions')]
final class ImportMapOutdatedCommand extends Command
{
    public function __construct(
        private readonly AssetCollectionToolkitRegistry $collectionToolkitRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
                name: 'packages',
                mode: InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                description: 'A list of javascript packages to check',
            );
        $this->addOption(
                name: 'format',
                mode: InputOption::VALUE_REQUIRED,
                description: \sprintf('The output format ("%s")', implode(', ', $this->getAvailableFormatOptions())),
                default: 'txt',
            );
        $this->addOption(
            name: 'collection',
            mode: InputOption::VALUE_REQUIRED,
            description: 'The asset collection to check (if not specified, all asset collections will be checked)',
        );
        $this->setHelp(<<<'EOT'
                The <info>%command.name%</info> command will list the latest updates available for the 3rd party packages in <comment>importmap.php</comment>.
                Versions showing in <fg=red>red</> are semver compatible versions and you should upgrading.
                Versions showing in <fg=yellow>yellow</> are major updates that include backward compatibility breaks according to semver.

                   <info>php %command.full_name%</info>

                Or specific packages only:

                   <info>php %command.full_name% <packages></info>
                EOT
            );

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $collectionName = $input->getOption('collection');
        if ($collectionName && $this->collectionToolkitRegistry->exists($collectionName) === false) {
            $io->error(sprintf('Asset collection "%s" does not exist.', $collectionName));
            return Command::FAILURE;
        }

        $lastResult = Command::SUCCESS;
        foreach ($this->collectionToolkitRegistry->toolkits as $toolkit) {
            if ($collectionName && $toolkit->collectionName !== $collectionName) {
                continue;
            }
            $io->title(sprintf('Asset Collection "%s"', $toolkit->collectionName));
            $helper = new ImportMapOutdatedCommandHelper($toolkit->importMapUpdateChecker);
            $result = $helper->execute($input, $output);
            if ($result !== Command::SUCCESS) {
                $lastResult = $result;
            }
        }

        return $lastResult;
    }

    private function getAvailableFormatOptions(): array
    {
        return ['txt', 'json'];
    }
}
