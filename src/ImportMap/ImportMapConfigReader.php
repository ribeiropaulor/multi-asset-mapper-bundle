<?php

namespace PRR\MultiAssetMapperBundle\ImportMap;

use Symfony\Component\AssetMapper\ImportMap\RemotePackageStorage;
use Symfony\Component\Filesystem\Path;

class ImportMapConfigReader extends \Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader
{
    public function __construct(string $importMapConfigPath, RemotePackageStorage $remotePackageStorage, private string $projectDir)
    {
        parent::__construct($importMapConfigPath, $remotePackageStorage);
    }

    /**
     * The original version of this method uses `\dirname($this->importMapConfigPath)` instead of
     * `projectDir`. This is a problem when the importmap.php file is not in the project root,
     * because the paths will be resolved incorrectly. And having multiple `importmap.php` files is the whole reason
     * of this bundle.
     */
    public function convertPathToFilesystemPath(string $path): string
    {
        if (!str_starts_with($path, '.')) {
            return $path;
        }

        return Path::join($this->projectDir, $path);
    }
}