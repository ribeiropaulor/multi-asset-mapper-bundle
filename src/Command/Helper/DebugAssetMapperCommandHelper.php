<?php

namespace PRR\MultiAssetMapperBundle\Command\Helper;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\AssetMapperRepository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Outputs all the assets in the asset mapper.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class DebugAssetMapperCommandHelper
{
    private bool $didShortenPaths = false;

    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
        private readonly AssetMapperRepository $assetMapperRepository,
        private readonly string $projectDir,
    ) {
    }

    public function execute(InputInterface $input, SymfonyStyle $io): int
    {
        $allAssets = $this->assetMapper->allAssets();

        $pathRows = [];
        foreach ($this->assetMapperRepository->allDirectories() as $path => $namespace) {
            $path = $this->relativizePath($path);
            if (!$input->getOption('full')) {
                $path = $this->shortenPath($path);
            }

            $pathRows[] = [$path, $namespace];
        }
        $io->section('Asset Mapper Paths');
        $io->table(['Path', 'Namespace prefix'], $pathRows);

        $rows = [];
        foreach ($allAssets as $asset) {
            $logicalPath = $asset->logicalPath;
            $sourcePath = $this->relativizePath($asset->sourcePath);

            if (!$input->getOption('full')) {
                $logicalPath = $this->shortenPath($logicalPath);
                $sourcePath = $this->shortenPath($sourcePath);
            }

            $rows[] = [
                $logicalPath,
                $sourcePath,
            ];
        }
        $io->section('Mapped Assets');
        $io->table(['Logical Path', 'Filesystem Path'], $rows);

        if ($this->didShortenPaths) {
            $io->note('To see the full paths, re-run with the --full option.');
        }

        return 0;
    }

    private function relativizePath(string $path): string
    {
        return str_replace($this->projectDir.'/', '', $path);
    }

    private function shortenPath(string $path): string
    {
        $limit = 50;

        if (\strlen($path) <= $limit) {
            return $path;
        }

        $this->didShortenPaths = true;
        $limit = floor(($limit - 3) / 2);

        return substr($path, 0, $limit).'...'.substr($path, -$limit);
    }
}
