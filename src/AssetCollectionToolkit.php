<?php

namespace PRR\MultiAssetMapperBundle;

use PRR\MultiAssetMapperBundle\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\AssetMapperRepository;
use Symfony\Component\AssetMapper\CompiledAssetMapperConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapGenerator;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\ImportMap\ImportMapRenderer;
use Symfony\Component\AssetMapper\ImportMap\ImportMapUpdateChecker;
use Symfony\Component\AssetMapper\ImportMap\ImportMapVersionChecker;
use Symfony\Component\AssetMapper\ImportMap\RemotePackageDownloader;
use Symfony\Component\AssetMapper\Path\PublicAssetsFilesystemInterface;
use Symfony\Component\Filesystem\Filesystem;

readonly class AssetCollectionToolkit
{
    public function __construct(
        public string $collectionName,
        public string $importMapPath,
        private string $projectDir,
        public ImportMapManager $importMapManager,
        public RemotePackageDownloader $packageDownloader,
        public CompiledAssetMapperConfigReader $compiledConfigReader,
        public AssetMapperInterface $assetMapper,
        public ImportMapGenerator $importMapGenerator,
        public PublicAssetsFilesystemInterface $assetsFilesystem,
        public AssetMapperRepository $assetMapperRepository,
        public ImportMapConfigReader $importMapConfigReader,
        public ImportMapUpdateChecker $importMapUpdateChecker,
        public ImportMapVersionChecker $importMapVersionChecker,
        public ImportMapRenderer $importMapRenderer,
    )
    {
    }

    public function getImportMapRelativePath(): string
    {
        return (new Filesystem())->makePathRelative($this->importMapPath, $this->projectDir);
    }

    public function getImportMapDir(): string
    {
        return \dirname($this->importMapPath);
    }
}