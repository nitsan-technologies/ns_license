<?php

declare(strict_types=1);

namespace NITSAN\NsLicense\Controller;

use NITSAN\NsLicense\Service\AllLicensesService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;

/**
 * AJAX endpoints for the All Licenses portfolio tab (OTP + listing).
 */
final class AllLicensesAjaxController
{
    public function __construct(
        private readonly AllLicensesService $allLicensesService,
    ) {}

    public function sendLicenseEmailOtpAction(ServerRequestInterface $request): JsonResponse
    {
        $params = $this->parsedBody($request);
        $result = $this->allLicensesService->sendOtp(
            trim((string) ($params['email'] ?? '')),
            trim((string) ($params['language'] ?? 'en'))
        );

        return $this->json($result);
    }

    public function verifyLicenseEmailOtpAction(ServerRequestInterface $request): JsonResponse
    {
        $params = $this->parsedBody($request);
        $result = $this->allLicensesService->verifyAndFetch(
            $this->allLicensesService->getBackendUserId(),
            trim((string) ($params['email'] ?? '')),
            trim((string) ($params['otp'] ?? ''))
        );

        return $this->json($result);
    }

    public function getLicensesByEmailAction(ServerRequestInterface $request): JsonResponse
    {
        $params = $this->parsedBody($request);
        $result = $this->allLicensesService->getCachedOrRefresh(
            $this->allLicensesService->getBackendUserId(),
            trim((string) ($params['email'] ?? '')),
            !empty($params['refresh'])
        );

        return $this->json($result);
    }

    public function clearAllLicensesSessionAction(ServerRequestInterface $request): JsonResponse
    {
        $params = $this->parsedBody($request);
        $result = $this->allLicensesService->clearSession(
            $this->allLicensesService->getBackendUserId(),
            trim((string) ($params['email'] ?? ''))
        );

        return $this->json($result);
    }

    /**
     * @return array<string,mixed>
     */
    private function parsedBody(ServerRequestInterface $request): array
    {
        $params = $request->getParsedBody() ?? [];
        return is_array($params) ? $params : [];
    }

    /**
     * @param array<string,mixed> $result
     */
    private function json(array $result): JsonResponse
    {
        $status = (int) ($result['http_status'] ?? (!empty($result['success']) ? 200 : 400));
        unset($result['http_status']);
        return new JsonResponse($result, $status);
    }
}
