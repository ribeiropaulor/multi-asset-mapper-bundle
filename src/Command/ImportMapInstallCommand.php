<?php

namespace PRR\MultiAssetMapperBundle\Command;

use PRR\MultiAssetMapperBundle\AssetCollectionToolkit;
use PRR\MultiAssetMapperBundle\AssetCollectionToolkitRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Downloads all assets that should be downloaded.
 *
 * @author Jonathan Scheiber <contact@jmsche.fr>
 * @author Paulo Ribeiro <paulo@prr.dev.br>
 */
#[AsCommand(name: 'mam:importmap:install', description: 'Download all assets that should be downloaded')]
final class ImportMapInstallCommand extends Command
{
    public function __construct(
        private readonly AssetCollectionToolkitRegistry $collectionToolkitRegistry,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        foreach ($this->collectionToolkitRegistry->toolkits as $toolkit) {
            $this->downloadPackagesFrom($toolkit, $io, $output);
        }

        return Command::SUCCESS;
    }

    private function downloadPackagesFrom(AssetCollectionToolkit $toolkit, SymfonyStyle $io, OutputInterface $output): void
    {
        $io->section(sprintf('Downloading packages of collection "%s"', $toolkit->collectionName));
        $finishedCount = 0;
        $progressBar = new ProgressBar($output);
        $progressBar->setFormat('<info>%current%/%max%</info> %bar% %url%');
        $downloadedPackages = $toolkit->packageDownloader->downloadPackages(static function (string $package, string $event, ResponseInterface $response, int $totalPackages) use (&$finishedCount, $progressBar) {
            $progressBar->setMessage($response->getInfo('url'), 'url');
            if (0 === $progressBar->getMaxSteps()) {
                $progressBar->setMaxSteps($totalPackages);
                $progressBar->start();
            }

            if ('finished' === $event) {
                ++$finishedCount;
                $progressBar->advance();
            }
        });
        $progressBar->finish();
        $progressBar->clear();

        if (!$downloadedPackages) {
            $io->success('No assets to install.');
            return;
        }

        $io->success(\sprintf(
            'Downloaded %d package%s into %s.',
            \count($downloadedPackages),
            1 === \count($downloadedPackages) ? '' : 's',
            str_replace($this->projectDir.'/', '', $toolkit->packageDownloader->getVendorDir()),
        ));
    }
}
