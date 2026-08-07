<?php

namespace PRR\MultiAssetMapperBundle\Command\Helper;

use Symfony\Component\AssetMapper\ImportMap\ImportMapAuditor;
use Symfony\Component\AssetMapper\ImportMap\ImportMapPackageAuditVulnerability;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ImportMapAuditCommandHelper
{
    private const SEVERITY_COLORS = [
        'critical' => 'red',
        'high' => 'red',
        'medium' => 'yellow',
        'low' => 'default',
        'unknown' => 'default',
    ];

    public function __construct(
        private readonly ImportMapAuditor $importMapAuditor,
    ) {
    }

    private SymfonyStyle $io;

    public function execute(InputInterface $input, SymfonyStyle $io): int
    {
        $this->io = $io;
        $format = $input->getOption('format');

        $audit = $this->importMapAuditor->audit();

        return match ($format) {
            'txt' => $this->displayTxt($audit),
            'json' => $this->displayJson($audit),
            default => throw new \InvalidArgumentException(\sprintf('Supported formats are "%s".', implode('", "', $this->getAvailableFormatOptions()))),
        };
    }

    private function displayTxt(array $audit): int
    {
        $rows = [];

        $packagesWithoutVersion = [];
        $vulnerabilitiesCount = array_map(static fn () => 0, self::SEVERITY_COLORS);
        foreach ($audit as $packageAudit) {
            if (!$packageAudit->version) {
                $packagesWithoutVersion[] = $packageAudit->package;
            }
            foreach ($packageAudit->vulnerabilities as $vulnerability) {
                $rows[] = [
                    \sprintf('<fg=%s>%s</>', self::SEVERITY_COLORS[$vulnerability->severity] ?? 'default', ucfirst($vulnerability->severity)),
                    $vulnerability->summary,
                    $packageAudit->package,
                    $packageAudit->version ?? 'n/a',
                    $vulnerability->firstPatchedVersion ?? 'n/a',
                    $vulnerability->url,
                ];
                ++$vulnerabilitiesCount[$vulnerability->severity];
            }
        }
        $packagesCount = \count($audit);
        $packagesWithoutVersionCount = \count($packagesWithoutVersion);

        if (!$rows && !$packagesWithoutVersionCount) {
            $this->io->info('No vulnerabilities found.');

            return Command::SUCCESS;
        }

        if ($rows) {
            $table = $this->io->createTable();
            $table->setHeaders([
                'Severity',
                'Title',
                'Package',
                'Version',
                'Patched in',
                'More info',
            ]);
            $table->addRows($rows);
            $table->render();
            $this->io->newLine();
        }

        $this->io->text(\sprintf('%d package%s found: %d audited / %d skipped',
            $packagesCount,
            1 === $packagesCount ? '' : 's',
            $packagesCount - $packagesWithoutVersionCount,
            $packagesWithoutVersionCount,
        ));

        if (0 < $packagesWithoutVersionCount) {
            $this->io->warning(\sprintf('Unable to retrieve versions for package%s: %s',
                1 === $packagesWithoutVersionCount ? '' : 's',
                implode(', ', $packagesWithoutVersion)
            ));
        }

        if ([] !== $rows) {
            $vulnerabilityCount = 0;
            $vulnerabilitySummary = [];
            foreach ($vulnerabilitiesCount as $severity => $count) {
                if (!$count) {
                    continue;
                }
                $vulnerabilitySummary[] = \sprintf('%d %s', $count, ucfirst($severity));
                $vulnerabilityCount += $count;
            }
            $this->io->text(\sprintf('%d vulnerabilit%s found: %s',
                $vulnerabilityCount,
                1 === $vulnerabilityCount ? 'y' : 'ies',
                implode(' / ', $vulnerabilitySummary),
            ));
        }

        return Command::FAILURE;
    }

    private function displayJson(array $audit): int
    {
        $vulnerabilitiesCount = array_map(static fn () => 0, self::SEVERITY_COLORS);

        $json = [
            'packages' => [],
            'summary' => $vulnerabilitiesCount,
        ];

        foreach ($audit as $packageAudit) {
            $json['packages'][] = [
                'package' => $packageAudit->package,
                'version' => $packageAudit->version,
                'vulnerabilities' => array_map(static fn (ImportMapPackageAuditVulnerability $v) => [
                    'ghsa_id' => $v->ghsaId,
                    'cve_id' => $v->cveId,
                    'url' => $v->url,
                    'summary' => $v->summary,
                    'severity' => $v->severity,
                    'vulnerable_version_range' => $v->vulnerableVersionRange,
                    'first_patched_version' => $v->firstPatchedVersion,
                ], $packageAudit->vulnerabilities),
            ];
            foreach ($packageAudit->vulnerabilities as $vulnerability) {
                ++$json['summary'][$vulnerability->severity];
            }
        }

        $this->io->write(json_encode($json));

        return 0 < array_sum($json['summary']) ? Command::FAILURE : Command::SUCCESS;
    }

    private function getAvailableFormatOptions(): array
    {
        return ['txt', 'json'];
    }
}
