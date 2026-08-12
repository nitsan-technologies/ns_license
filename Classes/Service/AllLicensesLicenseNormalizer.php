<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service;

/**
 * Normalizes All Licenses API/cache rows for Fluid + JS (missing fields, legacy payloads).
 */
final class AllLicensesLicenseNormalizer
{
    /**
     * @param list<array<string,mixed>> $licenses
     * @return list<array<string,mixed>>
     */
    public function normalizeList(array $licenses): array
    {
        $normalized = [];
        foreach ($licenses as $license) {
            if (!is_array($license)) {
                continue;
            }
            $normalized[] = $this->normalize($license);
        }
        return $normalized;
    }

    /**
     * @param array<string,mixed> $license
     * @return array<string,mixed>
     */
    public function normalize(array $license): array
    {
        if (trim((string) ($license['composerUsername'] ?? '')) === '') {
            $license['composerUsername'] = trim((string) ($license['user_name'] ?? ''));
        }

        if (trim((string) ($license['primaryDomain'] ?? '')) === '') {
            $license['primaryDomain'] = $this->firstProductionDomain((string) ($license['domains'] ?? ''));
        }

        $maxLabel = trim((string) ($license['domainsMaxLabel'] ?? ''));
        if ($maxLabel === '' || $maxLabel === '—') {
            $license['domainsMaxLabel'] = $this->formatLicenseType((string) ($license['license_type'] ?? ''));
        }

        if (trim((string) ($license['latestVersion'] ?? '')) === '') {
            $license['latestVersion'] = '—';
        }
        if (trim((string) ($license['installedVersion'] ?? '')) === '') {
            $license['installedVersion'] = '—';
        }

        return $license;
    }

    private function firstProductionDomain(string $domainsCsv): string
    {
        foreach (explode(',', $domainsCsv) as $part) {
            $domain = trim($part);
            if ($domain !== '') {
                return $domain;
            }
        }
        return '';
    }

    private function formatLicenseType(string $licenseType): string
    {
        $licenseType = trim($licenseType);
        if ($licenseType === '') {
            return '—';
        }
        if (strcasecmp($licenseType, 'X') === 0) {
            return '∞';
        }

        return $licenseType;
    }
}
