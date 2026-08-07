<?php

namespace PRR\MultiAssetMapperBundle;

readonly class AssetCollectionToolkitRegistry
{
    /**
     * @param iterable<AssetCollectionToolkit> $toolkits
     */
    public function __construct(
        public iterable $toolkits,
    ) {
        foreach ($toolkits as $toolkit) {
            if(!$toolkit instanceof AssetCollectionToolkit) {
                throw new \InvalidArgumentException(sprintf('Expected instance of %s as asset collection toolkit', AssetCollectionToolkit::class));
            }
        }
    }

    public function exists(string $packageName): bool
    {
        foreach ($this->toolkits as $toolkit) {
            if ($toolkit->collectionName === $packageName) {
                return true;
            }
        }
        return false;
    }

    public function get(string $collectionName): AssetCollectionToolkit
    {
        foreach ($this->toolkits as $toolkit) {
            if ($toolkit->collectionName === $collectionName) {
                return $toolkit;
            }
        }
        throw new \InvalidArgumentException(sprintf('Asset collection "%s" does not exist.', $collectionName));
    }
}