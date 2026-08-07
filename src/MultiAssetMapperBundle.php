<?php

namespace PRR\MultiAssetMapperBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @author Paulo Ribeiro <ribeiro.paulor@gmail.com>
 */
final class MultiAssetMapperBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}