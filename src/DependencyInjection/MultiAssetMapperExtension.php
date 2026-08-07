<?php

namespace PRR\MultiAssetMapperBundle\DependencyInjection;

use PRR\MultiAssetMapperBundle\ImportMap\ImportMapConfigReader;
use PRR\MultiAssetMapperBundle\AssetCollectionToolkit;
use Symfony\Component\AssetMapper\AssetMapper;
use Symfony\Component\AssetMapper\AssetMapperCompiler;
use Symfony\Component\AssetMapper\AssetMapperDevServerSubscriber;
use Symfony\Component\AssetMapper\AssetMapperRepository;
use Symfony\Component\AssetMapper\CompiledAssetMapperConfigReader;
use Symfony\Component\AssetMapper\Compiler\CssAssetUrlCompiler;
use Symfony\Component\AssetMapper\Compiler\JavaScriptImportPathCompiler;
use Symfony\Component\AssetMapper\Compiler\SourceMappingUrlsCompiler;
use Symfony\Component\AssetMapper\Factory\CachedMappedAssetFactory;
use Symfony\Component\AssetMapper\Factory\MappedAssetFactory;
use Symfony\Component\AssetMapper\ImportMap\ImportMapGenerator;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\ImportMap\ImportMapRenderer;
use Symfony\Component\AssetMapper\ImportMap\ImportMapUpdateChecker;
use Symfony\Component\AssetMapper\ImportMap\ImportMapVersionChecker;
use Symfony\Component\AssetMapper\ImportMap\RemotePackageDownloader;
use Symfony\Component\AssetMapper\ImportMap\RemotePackageStorage;
use Symfony\Component\AssetMapper\MapperAwareAssetPackage;
use Symfony\Component\AssetMapper\Path\LocalPublicAssetsFilesystem;
use Symfony\Component\AssetMapper\Path\PublicAssetsPathResolver;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Finder\Glob;

class MultiAssetMapperExtension extends Extension
{

    public function load(array $configs, ContainerBuilder $container): void
    {
        if (!class_exists(AssetMapper::class)) {
            throw new \LogicException('AssetMapper is required to use this bundle (MultiAssetMapperBundle). Try running "composer require symfony/asset-mapper".');
        }

        $loader = new Loader\PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');

        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $this->registerAssetMapperConfiguration($config, $container, $loader);
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ?ConfigurationInterface
    {
        return new Configuration($container->getParameter('kernel.debug'));
    }

    private function registerAssetMapperConfiguration(array $allConfig, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('services.php');

        /*if (!$assetEnabled) {
            $container->removeDefinition('asset_mapper.asset_package');
        }

        if (!$httpClientEnabled) {
            $container->register('asset_mapper.http_client', HttpClientInterface::class)
                ->addTag('container.error')
                ->addError('You cannot use the AssetMapper integration since the HttpClient component is not enabled. Try enabling the "framework.http_client" config option.');
        }*/

        $container->setParameter('multi_asset_mapper.package_names', array_keys($allConfig['collections']));
        $container->setParameter('multi_asset_mapper.package_configs', $allConfig['collections']);

        foreach ($allConfig['collections'] as $collectionName => $config) {
            $as = static fn (string $name) => sprintf('multi_%s.%s', $name, $collectionName);

            $paths = $config['paths'];
            foreach ($container->getParameter('kernel.bundles_metadata') as $name => $bundle) {
                if ($container->fileExists($dir = $bundle['path'].'/Resources/public') || $container->fileExists($dir = $bundle['path'].'/public')) {
                    $paths[$dir] = \sprintf('bundles/%s', preg_replace('/bundle$/', '', strtolower($name)));
                }
            }
            $excludedPathPatterns = [];
            foreach ($config['excluded_patterns'] as $path) {
                $excludedPathPatterns[] = Glob::toRegex($path, true, false);
            }

            $container->register($as('asset_mapper'), AssetMapper::class)
                ->setArguments([
                    new Reference($as('asset_mapper.repository')),
                    new Reference($as('asset_mapper.mapped_asset_factory')),
                    new Reference($as('asset_mapper.compiled_asset_mapper_config_reader')),
                ]);

            $container->register($as('asset_mapper.mapped_asset_factory'), MappedAssetFactory::class)
                ->setArguments([
                    new Reference($as('asset_mapper.public_assets_path_resolver')),
                    new Reference($as('asset_mapper_compiler')),
                    $config['vendor_dir']
                ]);

            $container->register($as('asset_mapper.cached_mapped_asset_factory'), CachedMappedAssetFactory::class)
                ->setArguments([
                    new Reference('.inner'),
                    $container->getParameter('kernel.cache_dir').'/multi_asset_mapper/'.$collectionName,
                    $container->getParameter('kernel.debug'),
                ])
                ->setDecoratedService(new Reference($as('asset_mapper.mapped_asset_factory')));

            $container->register($as('asset_mapper.repository'), AssetMapperRepository::class)
                ->setArguments([
                    $paths,
                    $container->getParameter('kernel.project_dir'),
                    $excludedPathPatterns,
                    $config['exclude_dotfiles'],
                    $container->getParameter('kernel.debug')
                ]);

            $container->register($as('asset_mapper.public_assets_path_resolver'), PublicAssetsPathResolver::class)
                ->setArguments([$config['public_prefix']]);


            $publicDirectory = $this->getPublicDirectory($container);
            $publicAssetsDirectory = rtrim($publicDirectory.'/'.ltrim($config['public_prefix'], '/'), '/');

            $container->register($as('asset_mapper.local_public_assets_filesystem'), LocalPublicAssetsFilesystem::class)
                ->setArguments([$publicDirectory]);

            $container->register($as('asset_mapper.compiled_asset_mapper_config_reader'), CompiledAssetMapperConfigReader::class)
                ->setArguments([$publicAssetsDirectory]);

            $container->register($as('asset_mapper.asset_package'), MapperAwareAssetPackage::class)
                //->setDecoratedService(new Reference('assets._default_package'))
                ->setArguments([
                    new Reference('assets._default_package'),
                    new Reference($as('asset_mapper')),
                ])
                ->addTag('assets.package', ['package' => $collectionName]);

            $container->register($as('asset_mapper.importmap.remote_package_storage'), RemotePackageStorage::class)
                ->setArguments([$config['vendor_dir']]);

            $container->register($as('asset_mapper.importmap.remote_package_downloader'), RemotePackageDownloader::class)
                ->setArguments([
                    new Reference($as('asset_mapper.importmap.remote_package_storage')),
                    new Reference($as('asset_mapper.importmap.config_reader')),
                    new Reference('asset_mapper.importmap.resolver'),
                ]);

            /*if (!is_dir(dirname($config['importmap_path']))) {
                if (!mkdir($concurrentDirectory = dirname($config['importmap_path']), 0777, true) && !is_dir(
                        $concurrentDirectory
                    )) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
                }
            }*/
            $container->register($as('asset_mapper.importmap.config_reader'), ImportMapConfigReader::class)
                ->setArguments([
                    $config['importmap_path'],
                    new Reference($as('asset_mapper.importmap.remote_package_storage')),
                    $container->getParameter('kernel.project_dir'),
                ]);

            $container->register($as('asset_mapper.importmap.manager'), ImportMapManager::class)
                ->setArguments([
                    new Reference($as('asset_mapper')),
                    new Reference($as('asset_mapper.importmap.config_reader')),
                    new Reference($as('asset_mapper.importmap.remote_package_downloader')),
                    new Reference('asset_mapper.importmap.resolver'),
                ]);

            //region: add compilers
            $container->register($as('asset_mapper.compiler.css_asset_url_compiler'), CssAssetUrlCompiler::class)
                ->setArguments([
                    $config['missing_import_mode'],
                    new Reference('logger')
                ])
                ->addTag($as('asset_mapper.compiler'));

            $container->register($as('asset_mapper.compiler.source_mapping_urls_compiler'), SourceMappingUrlsCompiler::class)
                ->addTag($as('asset_mapper.compiler'));

            $container->register($as('asset_mapper.compiler.javascript_import_path_compiler'), JavaScriptImportPathCompiler::class)
                ->setArguments([
                    new Reference($as('asset_mapper.importmap.config_reader')),
                    $config['missing_import_mode'],
                    new Reference('logger')
                ])
                ->addTag('monolog.logger', ['channel' => 'asset_mapper'])
                ->addTag($as('asset_mapper.compiler'));

            $container->register($as('asset_mapper_compiler'), AssetMapperCompiler::class)
                ->setArguments([
                    new TaggedIteratorArgument($as('asset_mapper.compiler')),
                    new ServiceClosureArgument(new Reference($as('asset_mapper')))
                ]);
            //endregion: add compilers

            $container->register($as('asset_mapper.importmap.generator'), ImportMapGenerator::class)
                ->setArguments([
                    new Reference($as('asset_mapper')),
                    new Reference($as('asset_mapper.compiled_asset_mapper_config_reader')),
                    new Reference($as('asset_mapper.importmap.config_reader')),
                ])
            ;

            $container->register($as('asset_mapper.importmap.renderer'), ImportMapRenderer::class)
                ->setArguments([
                    new Reference($as('asset_mapper.importmap.generator')),
                    new Reference('assets.packages', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    $container->getParameter('kernel.charset'),
                    $config['importmap_polyfill'],
                    $config['importmap_script_attributes'],
                    new Reference('request_stack')
                ]);

            $definition = new ChildDefinition(new Reference('cache.asset_mapper'));
            $definition->setParent(new Reference('cache.system'));
            $definition->setPublic(false);
            $definition->addTag('cache.pool');
            $container->setDefinition($as('cache.asset_mapper'), $definition);

            $container->register($as('asset_mapper.dev_server_subscriber'), AssetMapperDevServerSubscriber::class)
                ->setArguments([
                    new Reference($as('asset_mapper')),
                    $config['public_prefix'],
                    $config['extensions'],
                    new Reference($as('cache.asset_mapper')),
                    new Reference('profiler', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
                ->addTag('kernel.event_listener', ['event' => 'kernel.request', 'priority' => 36, 'method' => 'onKernelRequest']) // little above the default AssetMapperDevServerSubscriber
                ->addTag('kernel.event_listener', ['event' => 'kernel.response', 'priority' => 2049, 'method' => 'onKernelResponse'])
                ;

            $container->register($as('asset_mapper.importmap.update_checker'), ImportMapUpdateChecker::class)
                ->setArguments([
                    new Reference($as('asset_mapper.importmap.config_reader')),
                    new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ]);

            $container->register($as('asset_mapper.importmap.version_checker'), ImportMapVersionChecker::class)
                ->setArguments([
                    new Reference($as('asset_mapper.importmap.config_reader')),
                    new Reference($as('asset_mapper.importmap.remote_package_downloader')),
                    new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ]);

            $container->register($as('asset_mapper.collection.toolkit'), AssetCollectionToolkit::class)
                ->setArguments([
                    $collectionName,
                    $config['importmap_path'],
                    new Parameter('kernel.project_dir'),
                    new Reference($as('asset_mapper.importmap.manager')),
                    new Reference($as('asset_mapper.importmap.remote_package_downloader')),
                    new Reference($as('asset_mapper.compiled_asset_mapper_config_reader')),
                    new Reference($as('asset_mapper')),
                    new Reference($as('asset_mapper.importmap.generator')),
                    new Reference($as('asset_mapper.local_public_assets_filesystem')),
                    new Reference($as('asset_mapper.repository')),
                    new Reference($as('asset_mapper.importmap.config_reader')),
                    new Reference($as('asset_mapper.importmap.update_checker')),
                    new Reference($as('asset_mapper.importmap.version_checker')),
                    new Reference($as('asset_mapper.importmap.renderer')),
                ])
                ->addTag('multi_asset_mapper.collection.toolkit');

        }

    }


    private function getPublicDirectory(ContainerBuilder $container): string
    {
        $projectDir = $container->getParameter('kernel.project_dir');
        $defaultPublicDir = $projectDir.'/public';

        $composerFilePath = $projectDir.'/composer.json';

        if (!file_exists($composerFilePath)) {
            return $defaultPublicDir;
        }

        $composerConfig = json_decode(file_get_contents($composerFilePath), true);

        return isset($composerConfig['extra']['public-dir']) ? $projectDir.'/'.$composerConfig['extra']['public-dir'] : $defaultPublicDir;
    }
}