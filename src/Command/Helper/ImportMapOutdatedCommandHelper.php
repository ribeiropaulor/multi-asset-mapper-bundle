<?php

namespace PRR\MultiAssetMapperBundle\Command\Helper;

use Symfony\Component\AssetMapper\ImportMap\ImportMapUpdateChecker;
use Symfony\Component\AssetMapper\ImportMap\PackageUpdateInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ImportMapOutdatedCommandHelper
{
    private const COLOR_MAPPING = [
        'update-possible' => 'yellow',
        'semver-safe-update' => 'red',
    ];

    public function __construct(
        private readonly ImportMapUpdateChecker $updateChecker,
    ) {
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $packages = $input->getArgument('packages');
        $packagesUpdateInfos = $this->updateChecker->getAvailableUpdates($packages);
        $packagesUpdateInfos = array_filter($packagesUpdateInfos, static fn ($packageUpdateInfo) => $packageUpdateInfo->hasUpdate());
        if (0 === \count($packagesUpdateInfos)) {
            return Command::SUCCESS;
        }

        $displayData = array_map(static fn (string $importName, PackageUpdateInfo $packageUpdateInfo) => [
            'name' => $importName,
            'current' => $packageUpdateInfo->currentVersion,
            'latest' => $packageUpdateInfo->latestVersion,
            'latest-status' => PackageUpdateInfo::UPDATE_TYPE_MAJOR === $packageUpdateInfo->updateType ? 'update-possible' : 'semver-safe-update',
        ], array_keys($packagesUpdateInfos), $packagesUpdateInfos);

        if ('json' === $input->getOption('format')) {
            $io->writeln(json_encode($displayData, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
        } else {
            $table = $io->createTable();
            $table->setHeaders(['Package', 'Current', 'Latest']);
            foreach ($displayData as $datum) {
                $color = self::COLOR_MAPPING[$datum['latest-status']] ?? 'default';
                $table->addRow([
                    \sprintf('<fg=%s>%s</>', $color, $datum['name']),
                    $datum['current'],
                    \sprintf('<fg=%s>%s</>', $color, $datum['latest']),
                ]);
            }
            $table->render();
        }

        return Command::FAILURE;
    }
}
