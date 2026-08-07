<?php

/*
 * This file is part of the multi-asset-mapper-bundle project.
 *
 * (c) Paulo Ribeiro <paulo@prr.dev.br>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PRR\MultiAssetMapperBundle\Tests;

use PHPUnit\Framework\TestCase;
use PRR\MultiAssetMapperBundle\Tests\Fixtures\FrameworkAppKernel;
use Symfony\Component\HttpKernel\Kernel;

class MultiAssetMapperBundleTest extends TestCase
{
    public static function provideKernels(): \Generator
    {
        yield 'framework' => [new FrameworkAppKernel('test', true)];
    }

    /**
     * @dataProvider provideKernels
     */
    public function testBootKernel(Kernel $kernel): void
    {
        $kernel->boot();
        $this->assertArrayHasKey('MultiAssetMapperBundle', $kernel->getBundles());
    }
}