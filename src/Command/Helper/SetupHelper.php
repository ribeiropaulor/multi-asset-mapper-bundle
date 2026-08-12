<?php

namespace PRR\MultiAssetMapperBundle\Command\Helper;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 *
 * @author Paulo Riberiro <paulo@prr.dev.br>
 */
class SetupHelper
{
    public static function insertLineIfNotExists(string $filePath, string $line, ?string $toInsert = null, ?SymfonyStyle $io = null): void
    {
        if (!file_exists($filePath)) {
            file_put_contents($filePath, ($toInsert ?? $line) . PHP_EOL);
            $io?->writeln("File '$filePath' has been created.");
            return;
        }

        $content = file($filePath, FILE_IGNORE_NEW_LINES);
        if (!in_array($line, $content, true)) {
            array_unshift($content, $toInsert ?? $line);
            file_put_contents($filePath, implode(PHP_EOL, $content) . PHP_EOL);
            $io?->writeln("File '$filePath' has been updated.");
        }
    }

    public static function writeFileIfNotExists(string $filepath, string $content, ?SymfonyStyle $io = null): void
    {
        if (!file_exists($filepath)) {
            file_put_contents($filepath, $content . PHP_EOL);
            $io?->writeln("File '$filepath' has been created.");
        }
    }

    public static function addCollectionToYaml(string $yaml, string $collectionName, ?SymfonyStyle $io = null): string
    {
        $array = Yaml::parse($yaml);
        if (!array_key_exists('multi_asset_mapper', $array)) {
            $array['multi_asset_mapper'] = [];
        }
        if (!array_key_exists('collections', $array['multi_asset_mapper'])) {
            $array['multi_asset_mapper']['collections'] = [];
        }
        if (!in_array($collectionName, $array['multi_asset_mapper']['collections'], true) && !isset($array['multi_asset_mapper']['collections'][$collectionName])) {
            $array['multi_asset_mapper']['collections'][] = $collectionName;
            $io?->writeln("Collection '$collectionName' has been added to the multi_asset_mapper.yaml config.");
        }

        return Yaml::dump($array, 4, 2);
    }
}