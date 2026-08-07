<?php
declare(strict_types=1);

namespace PRR\MultiAssetMapperBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @param bool $debug Whether debugging is enabled or not
     */
    public function __construct(
        private bool $debug,
    ) {
    }

    /**
     * Generates the configuration tree builder.
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('multi_asset_mapper');
        $rootNode    = $treeBuilder->getRootNode();

        $this->addMappersSection($rootNode);

        return $treeBuilder;
    }


    /**
     * Add mappers section to configuration tree
     */
    private function addMappersSection(ArrayNodeDefinition $node): void
    {
        // Key that should not be rewritten to the package config
        $excludedKeys = ['default_package' => true];

        $node
            ->beforeNormalization()
                ->ifTrue(static function ($v) {
                    return is_array($v['collections'] ?? null);
                })
                ->then(static function ($v) {
                    $addDefaults = static function(string|int $collection, array $a) {
                        if (!isset($a['paths'])) {
                            $a['paths'] = [sprintf('asset-collections/%s/', $collection) => ''];
                        }
                        if (!isset($a['public_prefix'])) {
                            $a['public_prefix'] = sprintf('asset-collections/%s/', $collection);
                        }
                        if (!isset($a['importmap_path'])) {
                            $a['importmap_path'] = '%kernel.project_dir%' . sprintf('/asset-collections/%s/importmap.php', $collection);
                        }
                        if (!isset($a['vendor_dir'])) {
                            $a['vendor_dir'] = '%kernel.project_dir%' . sprintf('/asset-collections/%s/vendor', $collection);
                        }
                        return $a;
                    };
                    $result = [];
                    foreach ($v['collections'] as $key => $collection) {
                        if (is_string($collection)) {
                            $result[$collection] = $addDefaults($collection, []);
                            continue;
                        } elseif (is_array($collection) || is_null($collection)) {
                            $result[$key] = $addDefaults($collection['name'] ?? $key, $collection ?? []);
                            continue;
                        }
                        $result[$key] = $collection;
                    }
                    $v['collections'] = $result;

                    return $v;
                })
            ->end()
            ->children()
                ->arrayNode('collections', 'collection')
                    ->useAttributeAsKey('name')
                    ->requiresAtLeastOneElement()
                    ->prototype('array')
                    ->fixXmlConfig('path')
                    ->fixXmlConfig('excluded_pattern')
                    ->fixXmlConfig('extension')
                    ->fixXmlConfig('importmap_script_attribute')
                    ->children()
                        // add array node called "paths" that will be an array of strings
                        ->arrayNode('paths')
                            ->info('Directories that hold assets that should be in the mapper. Can be a simple array of an array of ["path/to/assets": "namespace"]')
                            ->example(['assets/'])
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('namespace')
                            ->beforeNormalization()
                                ->always()
                                ->then(static function ($v) {
                                    $result = [];
                                    foreach ($v as $key => $item) {
                                        // "dir" => "namespace"
                                        if (\is_string($key)) {
                                            $result[$key] = $item;

                                            continue;
                                        }

                                        if (\is_array($item)) {
                                            // $item = ["namespace" => "the/namespace", "value" => "the/dir"]
                                            $result[$item['value']] = $item['namespace'] ?? '';
                                        } else {
                                            // $item = "the/dir"
                                            $result[$item] = '';
                                        }
                                    }

                                    return $result;
                                })
                            ->end()
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('excluded_patterns')
                            ->info('Array of glob patterns of asset file paths that should not be in the asset mapper')
                            ->prototype('scalar')->end()
                            ->example(['*/assets/build/*', '*/*_.scss'])
                        ->end()
                        // boolean called defaulting to true
                        ->booleanNode('exclude_dotfiles')
                            ->info('If true, any files starting with "." will be excluded from the asset mapper')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('server')
                            ->info('If true, a "dev server" will return the assets from the public directory (true in "debug" mode only by default)')
                            ->defaultValue($this->debug)
                        ->end()
                        ->scalarNode('public_prefix')
                            ->info('The public path where the assets will be written to (and served from when "server" is true)')
                            ->defaultValue('/asset-collections/')
                        ->end()
                        ->enumNode('missing_import_mode')
                            ->values(['strict', 'warn', 'ignore'])
                            ->info('Behavior if an asset cannot be found when imported from JavaScript or CSS files - e.g. "import \'./non-existent.js\'". "strict" means an exception is thrown, "warn" means a warning is logged, "ignore" means the import is left as-is.')
                            ->defaultValue('warn')
                        ->end()
                        ->arrayNode('extensions')
                            ->info('Key-value pair of file extensions set to their mime type.')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('extension')
                            ->example(['.zip' => 'application/zip'])
                            ->prototype('scalar')->end()
                        ->end()
                        ->scalarNode('importmap_path')
                            ->info('The path of the importmap.php file.')
                            ->defaultValue('%kernel.project_dir%/importmap.php')
                        ->end()
                        ->scalarNode('importmap_polyfill')
                            ->info('The importmap name that will be used to load the polyfill. Set to false to disable.')
                            ->validate()
                                ->ifTrue()
                                ->thenInvalid('Invalid "importmap_polyfill" value. Must be either an importmap name or false.')
                            ->end()
                            ->defaultValue('es-module-shims')
                        ->end()
                        ->arrayNode('importmap_script_attributes')
                            ->info('Key-value pair of attributes to add to script tags output for the importmap.')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('key')
                            ->example(['data-turbo-track' => 'reload'])
                            ->prototype('scalar')->end()
                        ->end()
                        ->scalarNode('vendor_dir')
                            ->info('The directory to store JavaScript vendors.')
                            ->defaultValue('%kernel.project_dir%/asset-collections/vendor')
                        ->end()
                        ->scalarNode('provider')
                            ->setDeprecated('symfony/framework-bundle', '6.4', 'Option "%node%" at "%path%" is deprecated and does nothing. Remove it.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}