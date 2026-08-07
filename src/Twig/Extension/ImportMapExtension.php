<?php

namespace PRR\MultiAssetMapperBundle\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @author Paulo Ribeiro <ribeiro.paulor@gmail.com>
 */
final class ImportMapExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('mam_importmap', [ImportMapRuntime::class, 'importmap'], ['is_safe' => ['html']]),
        ];
    }
}
