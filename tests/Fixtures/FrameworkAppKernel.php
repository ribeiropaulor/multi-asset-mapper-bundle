<?php

/*
 * This file is part of the multi-asset-mapper-bundle project.
 *
 * (c) Paulo Ribeiro <paulo@prr.dev.br>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PRR\MultiAssetMapperBundle\Tests\Fixtures;

use PRR\MultiAssetMapperBundle\MultiAssetMapperBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class FrameworkAppKernel extends Kernel
{
    use AppKernelTrait;

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new MultiAssetMapperBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', ['secret' => '$ecret', 'test' => true, 'http_method_override' => false]);
        });

        $loader->load(function (ContainerBuilder $container) {
            $container->loadFromExtension('multi_asset_mapper', [
                'collections' => [
                    'frontend',
                    'admin'
                ],
            ]);
        });
    }
}
