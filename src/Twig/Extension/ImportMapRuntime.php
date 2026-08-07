<?php

namespace PRR\MultiAssetMapperBundle\Twig\Extension;

use PRR\MultiAssetMapperBundle\AssetCollectionToolkitRegistry;
use Twig\Attribute\AsTwigFunction;
use Twig\Extension\RuntimeExtensionInterface;

class ImportMapRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly AssetCollectionToolkitRegistry $collectionToolkitRegistry,
    )
    {
    }

    #[AsTwigFunction("multi_importmap")]
    public function importmap(string $collection, string|array $entryPoint = 'main', array $attributes = []): string
    {
        $importMapRenderer = $this->collectionToolkitRegistry->get($collection)->importMapRenderer;
        return $importMapRenderer->render($entryPoint, $attributes);
    }
}