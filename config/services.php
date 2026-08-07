<?php

use PRR\MultiAssetMapperBundle\Command\AssetMapperCompileCommand;
use PRR\MultiAssetMapperBundle\Command\DebugAssetMapperCommand;
use PRR\MultiAssetMapperBundle\Command\ImportMapAuditCommand;
use PRR\MultiAssetMapperBundle\Command\ImportMapInstallCommand;
use PRR\MultiAssetMapperBundle\Command\ImportMapOutdatedCommand;
use PRR\MultiAssetMapperBundle\Command\ImportMapRemoveCommand;
use PRR\MultiAssetMapperBundle\Command\ImportMapRequireCommand;
use PRR\MultiAssetMapperBundle\Command\ImportMapUpdateCommand;
use PRR\MultiAssetMapperBundle\AssetCollectionToolkitRegistry;
use PRR\MultiAssetMapperBundle\Twig\Extension\ImportMapExtension;
use PRR\MultiAssetMapperBundle\Twig\Extension\ImportMapRuntime;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $container->services()

        ->set(ImportMapExtension::class)
            ->tag('twig.extension')

        ->set(ImportMapRuntime::class)
            ->args([
                service(AssetCollectionToolkitRegistry::class)
            ])
            ->tag('twig.runtime')

        ->set(ImportMapRequireCommand::class)
            ->args([
                service(AssetCollectionToolkitRegistry::class),
                service('asset_mapper.importmap.version_checker'),
            ])
            ->tag('console.command')

        ->set(ImportMapInstallCommand::class)
            ->args([
                service(AssetCollectionToolkitRegistry::class),
                param('kernel.project_dir')
            ])
            ->tag('console.command')

        ->set(AssetCollectionToolkitRegistry::class)
            ->args([
                tagged_iterator('multi_asset_mapper.collection.toolkit'),
            ])

        ->set(AssetMapperCompileCommand::class)
            ->args([
                service(AssetCollectionToolkitRegistry::class),
                param('kernel.project_dir'),
                param('kernel.debug'),
                service('event_dispatcher')->nullOnInvalid(),
            ])
            ->tag('console.command')

        ->set(DebugAssetMapperCommand::class)
            ->args([
                service(AssetCollectionToolkitRegistry::class),
                param('kernel.project_dir'),
            ])
            ->tag('console.command')

        ->set(ImportMapAuditCommand::class)
            ->args([
                service(AssetCollectionToolkitRegistry::class),
                service('http_client')->nullOnInvalid(),
            ])
            ->tag('console.command')

        ->set(ImportMapOutdatedCommand::class)
            ->args([
                service(AssetCollectionToolkitRegistry::class),
            ])
            ->tag('console.command')

        ->set(ImportMapRemoveCommand::class)
            ->args([
                service(AssetCollectionToolkitRegistry::class),
            ])
            ->tag('console.command')

        ->set(ImportMapUpdateCommand::class)
            ->args([
                service(AssetCollectionToolkitRegistry::class),
            ])
            ->tag('console.command')
    ;
};
