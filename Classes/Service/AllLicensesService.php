<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Service;

use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Orchestrates All Licenses portfolio OTP, API fetch, and short-lived BE cache.
 */
final class AllLicensesService
{
    public function __construct(
        private readonly LicenseService $licenseService,
        private readonly AllLicensesCacheService $allLicensesCacheService,
    ) {}

    /**
     * View assigns for the All Licenses tab pane.
     *
     * @return array{
     *     allLicensesVerified: bool,
     *     allLicensesEmail: string,
     *     allLicensesSummary: array{total:int,active:int,expiringSoon:int},
     *     allLicenses: list<array<string,mixed>>
     * }
     */
    public function getViewAssigns(int $beUserId): array
    {
        $emptySummary = ['total' => 0, 'active' => 0, 'expiringSoon' => 0];
        $cached = $beUserId > 0 ? $this->allLicensesCacheService->getForBackendUser($beUserId) : null;

        return [
            'allLicensesVerified' => $cached !== null,
            'allLicensesEmail' => $cached['email'] ?? '',
            'allLicensesSummary' => $cached['data']['summary'] ?? $emptySummary,
            'allLicenses' => $cached['data']['licenses'] ?? [],
        ];
    }

    /**
     * @return array{success:bool,message?:string,error_code?:string,expires_in?:int,retry_after?:int,http_status:int}
     */
    public function sendOtp(string $email, string $language = 'en'): array
    {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail(
                'invalid_email',
                LocalizationUtility::translate('license-all-licenses.error.email', 'NsLicense')
                    ?: 'Please enter a valid email address.',
                400
            );
        }

        try {
            $result = $this->licenseService->sendLicenseEmailOtp([
                'email' => $email,
                'language' => $language !== '' ? $language : 'en',
            ]);
            $result['http_status'] = !empty($result['success']) ? 200 : 400;
            return $result;
        } catch (\Throwable $e) {
            return $this->fail('error', 'Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verify OTP, fetch portfolio, store cache for the BE user.
     *
     * @return array<string,mixed>
     */
    public function verifyAndFetch(int $beUserId, string $email, string $otp): array
    {
        $email = trim($email);
        $otp = trim($otp);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail(
                'invalid_email',
                LocalizationUtility::translate('license-all-licenses.error.email', 'NsLicense')
                    ?: 'Please enter a valid email address.',
                400
            );
        }
        if ($otp === '' || !preg_match('/^\d{6}$/', $otp)) {
            return $this->fail(
                'invalid_otp',
                LocalizationUtility::translate('license-all-licenses.error.otp', 'NsLicense')
                    ?: 'Please enter the 6-digit code.',
                400
            );
        }

        try {
            $verify = $this->licenseService->verifyLicenseEmailOtp([
                'email' => $email,
                'otp' => $otp,
            ]);
            if (empty($verify['success'])) {
                $verify['http_status'] = 400;
                return $verify;
            }

            $token = trim((string) ($verify['verification_token'] ?? ''));
            if ($token === '') {
                return $this->fail(
                    'error',
                    LocalizationUtility::translate('license-all-licenses.error.verify_failed', 'NsLicense')
                        ?: 'Verification failed. Please try again.',
                    500
                );
            }

            $licensesResult = $this->licenseService->getLicensesByEmail([
                'email' => $email,
                'verification_token' => $token,
            ]);
            if (empty($licensesResult['success'])) {
                $licensesResult['http_status'] = 400;
                return $licensesResult;
            }

            $payload = [
                'email' => $licensesResult['email'] ?? $email,
                'summary' => $licensesResult['summary'] ?? ['total' => 0, 'active' => 0, 'expiringSoon' => 0],
                'licenses' => $licensesResult['licenses'] ?? [],
            ];

            if ($beUserId > 0) {
                $this->allLicensesCacheService->set($beUserId, (string) $payload['email'], $token, $payload);
            }

            return [
                'success' => true,
                'message' => $verify['message'] ?? 'Email verified successfully.',
                'email' => $payload['email'],
                'summary' => $payload['summary'],
                'licenses' => $payload['licenses'],
                'expires_in' => AllLicensesCacheService::TTL_SECONDS,
                'http_status' => 200,
            ];
        } catch (\Throwable $e) {
            return $this->fail('error', 'Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Return cached portfolio or refresh from API when requested.
     *
     * @return array<string,mixed>
     */
    public function getCachedOrRefresh(int $beUserId, string $email = '', bool $forceRefresh = false): array
    {
        if ($beUserId <= 0) {
            return $this->fail('unauthorized', 'Backend user required.', 401);
        }

        $cached = null;
        if ($email !== '') {
            $cached = $this->allLicensesCacheService->get($beUserId, $email);
        }
        if ($cached === null) {
            $cached = $this->allLicensesCacheService->getForBackendUser($beUserId);
        }

        if ($cached === null) {
            return $this->fail(
                'email_not_verified',
                LocalizationUtility::translate('license-all-licenses.error.session_expired', 'NsLicense')
                    ?: 'Your verification session has expired. Please verify your email again.',
                401
            );
        }

        if (!$forceRefresh) {
            return [
                'success' => true,
                'email' => $cached['email'],
                'summary' => $cached['data']['summary'] ?? ['total' => 0, 'active' => 0, 'expiringSoon' => 0],
                'licenses' => $cached['data']['licenses'] ?? [],
                'from_cache' => true,
                'http_status' => 200,
            ];
        }

        try {
            $licensesResult = $this->licenseService->getLicensesByEmail([
                'email' => $cached['email'],
                'verification_token' => $cached['verificationToken'],
            ]);
            if (empty($licensesResult['success'])) {
                if (($licensesResult['error_code'] ?? '') === 'email_not_verified') {
                    $this->allLicensesCacheService->remove($beUserId, $cached['email']);
                }
                $licensesResult['http_status'] = 400;
                return $licensesResult;
            }

            $payload = [
                'email' => $licensesResult['email'] ?? $cached['email'],
                'summary' => $licensesResult['summary'] ?? ['total' => 0, 'active' => 0, 'expiringSoon' => 0],
                'licenses' => $licensesResult['licenses'] ?? [],
            ];
            $this->allLicensesCacheService->set(
                $beUserId,
                (string) $payload['email'],
                $cached['verificationToken'],
                $payload
            );

            return [
                'success' => true,
                'email' => $payload['email'],
                'summary' => $payload['summary'],
                'licenses' => $payload['licenses'],
                'from_cache' => false,
                'http_status' => 200,
            ];
        } catch (\Throwable $e) {
            return $this->fail('error', 'Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @return array{success:true,message:string,http_status:int}
     */
    public function clearSession(int $beUserId, string $email = ''): array
    {
        if ($beUserId > 0) {
            $this->allLicensesCacheService->remove($beUserId, $email);
        }

        return [
            'success' => true,
            'message' => LocalizationUtility::translate('license-all-licenses.session_cleared', 'NsLicense')
                ?: 'Session cleared.',
            'http_status' => 200,
        ];
    }

    public function getBackendUserId(): int
    {
        $beUser = $GLOBALS['BE_USER'] ?? null;
        if (!is_object($beUser) || !isset($beUser->user['uid'])) {
            return 0;
        }
        return (int) $beUser->user['uid'];
    }

    /**
     * @return array{success:false,error_code:string,message:string,http_status:int}
     */
    private function fail(string $errorCode, string $message, int $httpStatus): array
    {
        return [
            'success' => false,
            'error_code' => $errorCode,
            'message' => $message,
            'http_status' => $httpStatus,
        ];
    }
}
