<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service;

use TYPO3\CMS\Core\Core\Environment;

/**
 * Cheap local PHP/host checks for classic (non-Composer) activation.
 *
 * Intentionally no network I/O and no directory creation — only ini_get()
 * and a single is_dir/is_writable stat so the BE module stays light.
 */
final class ClassicActivationRequirementsService
{
    private const MIN_MEMORY_BYTES = 1024 * 1024 * 1024; // 1024M
    private const MIN_EXECUTION_SECONDS = 60;

    /** @var array<string, list<string>> */
    private array $failedChecksByPath = [];

    /**
     * @return list<string> Failure keys: memory_limit, max_execution_time, typo3temp
     */
    public function getFailedChecks(?string $publicPath = null): array
    {
        $cacheKey = $publicPath ?? '';
        if (isset($this->failedChecksByPath[$cacheKey])) {
            return $this->failedChecksByPath[$cacheKey];
        }

        $failed = [];

        // ini_get is in-memory; negligible cost.
        if (!$this->isMemoryLimitSufficient((string)ini_get('memory_limit'))) {
            $failed[] = 'memory_limit';
        }
        if (!$this->isMaxExecutionTimeSufficient((string)ini_get('max_execution_time'))) {
            $failed[] = 'max_execution_time';
        }
        if (!$this->isTypo3TempWritable($publicPath)) {
            $failed[] = 'typo3temp';
        }

        return $this->failedChecksByPath[$cacheKey] = $failed;
    }

    public function meetsRequirements(?string $publicPath = null): bool
    {
        return $this->getFailedChecks($publicPath) === [];
    }

    public function isMemoryLimitSufficient(string $memoryLimit): bool
    {
        $memoryLimit = trim($memoryLimit);
        if ($memoryLimit === '' || $memoryLimit === '-1') {
            return true; // unlimited
        }

        $bytes = $this->phpSizeToBytes($memoryLimit);
        if ($bytes === null) {
            return false;
        }

        return $bytes >= self::MIN_MEMORY_BYTES;
    }

    public function isMaxExecutionTimeSufficient(string $maxExecutionTime): bool
    {
        $maxExecutionTime = trim($maxExecutionTime);
        if ($maxExecutionTime === '' || $maxExecutionTime === '0') {
            return true; // unlimited / CLI-style
        }
        if (!is_numeric($maxExecutionTime)) {
            return false;
        }

        return (int)$maxExecutionTime >= self::MIN_EXECUTION_SECONDS;
    }

    /**
     * Read-only check — never mkdir (activation creates files when needed).
     */
    public function isTypo3TempWritable(?string $publicPath = null): bool
    {
        $base = $publicPath ?? (Environment::getPublicPath() . '/');
        $typo3temp = rtrim($base, '/') . '/typo3temp';

        return is_dir($typo3temp) && is_writable($typo3temp);
    }

    /**
     * Parse PHP ini size strings (e.g. 512M, 1G) to bytes.
     */
    public function phpSizeToBytes(string $size): ?int
    {
        $size = trim($size);
        if ($size === '') {
            return null;
        }

        // Fast path for typical "512M" / "1G" / plain bytes — avoids regex on hot path.
        $unit = strtoupper(substr($size, -1));
        if ($unit === 'G' || $unit === 'M' || $unit === 'K') {
            $number = substr($size, 0, -1);
            if ($number === '' || !is_numeric($number) || (float)$number < 0) {
                return null;
            }
            $value = (int)$number;
            return match ($unit) {
                'G' => $value * 1024 * 1024 * 1024,
                'M' => $value * 1024 * 1024,
                'K' => $value * 1024,
            };
        }

        if (!is_numeric($size) || (float)$size < 0) {
            return null;
        }

        return (int)$size;
    }
}
