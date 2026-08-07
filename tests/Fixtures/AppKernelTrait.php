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

trait AppKernelTrait
{
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return $this->createTmpDir('cache');
    }

    public function getLogDir(): string
    {
        return $this->createTmpDir('logs');
    }

    private function createTmpDir(string $type): string
    {
        $dir = sys_get_temp_dir().'/multi_asset_mapper_bundle/'.uniqid($type.'_', true);

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }
}
