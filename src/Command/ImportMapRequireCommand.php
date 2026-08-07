<?php

namespace PRR\MultiAssetMapperBundle\Command;

use PRR\MultiAssetMapperBundle\AssetCollectionToolkit;
use PRR\MultiAssetMapperBundle\AssetCollectionToolkitRegistry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\ImportMap\ImportMapVersionChecker;
use Symfony\Component\AssetMapper\ImportMap\PackageRequireOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 * @author Paulo Ribeiro <paulo@prr.dev.br>
 */
#[AsCommand(name: 'mam:importmap:require', description: 'Require JavaScript packages')]
final class ImportMapRequireCommand extends Command
{
    use VersionProblemCommandTrait;

    public function __construct(
        private readonly AssetCollectionToolkitRegistry $collectionToolkitRegistry,
        private readonly ImportMapVersionChecker $importMapVersionChecker,
    ) {
        parent::__construct();
    }

    /**
     * @param AssetCollectionToolkit $toolkit
     * @return void
     */
    public function installNewCollection(AssetCollectionToolkit $toolkit): void
    {
        if (!mkdir($concurrentDirectory = $toolkit->getImportMapDir(), 0777, true) && !is_dir(
                $concurrentDirectory
            )) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
        if (!mkdir($concurrentDirectory = $toolkit->getImportMapDir() . '/styles', 0777, true) && !is_dir(
                $concurrentDirectory
            )) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        $importmap = <<<PHP
<?php

/**
 * Returns the importmap for this asset collection.
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
        'path' => './asset-collections/{$toolkit->collectionName}/main.js',
        'entrypoint' => true,
    ],
];
PHP;
        file_put_contents($toolkit->getImportMapDir() . '/importmap.php', $importmap);


        $main = <<<JS
/*
 * Welcome to your {$toolkit->collectionName}'s main JavaScript file!
 *
 * This file will be included onto the page via the mam_importmap() Twig function,
 * which should be included in your base-{$toolkit->collectionName}.html.twig <head> tag:
 * 
 * {% block importmap %}{{ mam_importmap('{$toolkit->collectionName}', 'main') }}{% endblock %}
 */
import './styles/main.css';

console.log('This log comes from asset-collections/{$toolkit->collectionName}/main.js - welcome to MultiAssetMapperBundle! 🎉');

JS;
        file_put_contents($toolkit->getImportMapDir() . '/main.js', $main);

        $styles = <<<CSS
body {
    background-color: skyblue;
}
CSS;
        file_put_contents($toolkit->getImportMapDir() . '/styles/main.css', $styles);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('collection', InputArgument::REQUIRED, 'The asset collection (e.g.: admin, admin_v1, frontend_v3, etc) in which the javascript package is required')
            ->addArgument('packages', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The javascript packages to import')
            ->addOption('entrypoint', null, InputOption::VALUE_NONE, 'Make the javascript package(s) an entrypoint?')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'The local path where the javascript package lives relative to the project root')
            ->setHelp(<<<'EOT'
                The <info>%command.name%</info> command adds packages to <comment>importmap.php</comment> usually
                by finding a CDN URL for the given package and version.

                For example:

                    <info>php %command.full_name% admin lodash</info>
                    <info>php %command.full_name% admin "lodash@^4.15"</info>

                You can also require specific paths of a package:

                    <info>php %command.full_name% admin "chart.js/auto"</info>

                Or require one package/file, but alias its name in your import map:

                    <info>php %command.full_name% frontend_v1 "vue/dist/vue.esm-bundler.js=vue"</info>

                Sometimes, a package may require other packages and multiple new items may be added
                to the import map.

                You can also require multiple packages at once:

                    <info>php %command.full_name% frontend_v1 "lodash@^4.15" "@hotwired/stimulus"</info>

                To add an importmap entry pointing to a local file, use the <info>path</info> option:

                    <info>php %command.full_name% frontend_v1 "any_module_name" --path=./asset-collections/shared/some_file.js</info>

                EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $toolkit = $this->collectionToolkitRegistry->get($input->getArgument('collection'));

        if (!is_dir($toolkit->getImportMapDir())) {
            $this->installNewCollection($toolkit);
        }

        $io = new SymfonyStyle($input, $output);

        $packageList = $input->getArgument('packages');
        $path = null;
        if ($input->getOption('path')) {
            if (\count($packageList) > 1) {
                $io->error('The "--path" option can only be used when you require a single package.');

                return Command::FAILURE;
            }

            $path = $input->getOption('path');
        }

        $packages = [];
        foreach ($packageList as $packageName) {
            $parts = ImportMapManager::parsePackageName($packageName);
            if (null === $parts) {
                $io->error(\sprintf('Package "%s" is not a valid package name format. Use the format PACKAGE@VERSION - e.g. "lodash" or "lodash@^4"', $packageName));

                return Command::FAILURE;
            }

            $packages[] = new PackageRequireOptions(
                $parts['package'],
                $parts['version'] ?? null,
                $parts['alias'] ?? null,
                $path,
                $input->getOption('entrypoint'),
            );
        }

        $newPackages = $toolkit->importMapManager->require($packages);

        $this->renderVersionProblems($this->importMapVersionChecker, $output);

        if (1 === \count($newPackages)) {
            $newPackage = $newPackages[0];
            $message = \sprintf('Package "%s" added to %s', $newPackage->importName, $toolkit->getImportMapRelativePath());

            $message .= '.';
        } else {
            $names = array_map(static fn (ImportMapEntry $package) => $package->importName, $newPackages);
            $message = \sprintf('%d new items (%s) added to %s!', \count($newPackages), implode(', ', $names), $toolkit->getImportMapRelativePath());
        }

        $messages = [$message];

        if (1 === \count($newPackages)) {
            $messages[] = \sprintf('Use the new package normally by importing "%s".', $newPackages[0]->importName);
        }

        $io->success($messages);

        return Command::SUCCESS;
    }
}
