<?php

declare(strict_types=1);

namespace PRR\MultiAssetMapperBundle\Command;

use PRR\MultiAssetMapperBundle\AssetCollectionToolkitRegistry;
use PRR\MultiAssetMapperBundle\Command\Helper\SetupHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'mam:asset-collection:install', description: 'Initiate files for an asset collection')]
class InstallAssetCollectionCommand extends Command
{
    public function __construct(private readonly AssetCollectionToolkitRegistry $toolkitRegistry, private readonly string $projectDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'collection',
            InputArgument::REQUIRED | InputArgument::IS_ARRAY,
            'The collection names to setup. If none is provided, all asset collections will be initiated.'
        );
        $this->setHelp(
            <<<'EOT'
                The <info>%command.name%</info> command sets up the initial files for an asset collection.

                This command is meant to be run right after you configure your asset collections in your `config/packages/multi_asset_mapper.yaml` file.
                EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        foreach ($input->getArgument('collection') as $collection) {
            $io->section("Setting up asset collection '$collection'...");
            $this->toolkitRegistry->exists($collection) && throw new \InvalidArgumentException("Asset collection '$collection' already exists.");

            if (!is_dir($this->getAssetCollectionDir($collection))) {
                if (!mkdir($concurrentDirectory = $this->getAssetCollectionDir($collection), 0777, true) && !is_dir(
                        $concurrentDirectory
                    )) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
                }
            }

            $this->createConfigYaml($collection, $io);
            $this->createMainJS($collection, $io);
            $this->createMainCSS($collection, $io);
            $this->createImportmap($collection, $io);

            $output->writeln("<info>Asset collection '$collection' has been set up.</info>");

        }

        return Command::SUCCESS;
    }

    private function createConfigYaml(string $collection, SymfonyStyle $io): void
    {
        $filepath = $this->projectDir.'/config/packages/multi_asset_mapper.yaml';
        if (!is_file($filepath)) {
            $content = <<<YAML
multi_asset_mapper:
    collections:
        - $collection
YAML;
            SetupHelper::writeFileIfNotExists($filepath, $content, $io);

            return;
        }

        $content = SetupHelper::addCollectionToYaml(file_get_contents($filepath), $collection, $io);
        file_put_contents($filepath, $content);
    }

    private function createMainJS(string $collection, SymfonyStyle $io): void
    {
        $content = <<<JS
/*
 * Welcome to your admin's main JavaScript file!
 *
 * This file will be included onto the page via the mam_importmap() Twig function,
 * which should be included in your base-{$collection}.html.twig <head> tag:
 * 
 * {% block importmap %}{{ mam_importmap('{$collection}', 'main') }}{% endblock %}
 */
import './styles/main.css';

console.log('This log comes from asset-collections/{$collection}/main.js - welcome to MultiAssetMapperBundle! 🎉');

JS;
        SetupHelper::writeFileIfNotExists($this->getAssetCollectionDir($collection).'/main.js', $content, $io);
    }

    private function createMainCSS(string $collection, SymfonyStyle $io): void
    {
        $content = <<<CSS
body {
    background-color: skyblue;
}
CSS;
        $filepath = $this->getAssetCollectionDir($collection).'/styles/main.css';
        if (!is_dir(dirname($filepath))) {
            if (!mkdir($concurrentDirectory = dirname($filepath), 0777, true) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }
        SetupHelper::writeFileIfNotExists($filepath, $content, $io);
    }

    private function createImportmap(string $collection, SymfonyStyle $io): void
    {
        $content = <<<PHP
<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "mam:debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the mam_importmap() Twig function).
 *
 * The "mam:importmap:require" command can be used to add new entries to this file.
 */
return [
    'main' => [
        'path' => './asset-collections/{$collection}/main.js',
        'entrypoint' => true,
    ]
];
PHP;
        SetupHelper::writeFileIfNotExists($this->getAssetCollectionDir($collection).'/importmap.php', $content, $io);
    }

    private function getAssetCollectionDir(string $collection): string
    {
        return sprintf('%s/asset-collections/%s', $this->projectDir, $collection);
    }
}
