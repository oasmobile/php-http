<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Kernel;

use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves and sets CloudFront IP ranges as trusted proxies.
 */
class CloudfrontTrustedProxyResolver
{
    private ?string $cacheDir;

    public function __construct(?string $cacheDir)
    {
        $this->cacheDir = $cacheDir;
    }

    public function resolve(): void
    {
        try {
            $awsIps = [];
            if ($this->cacheDir) {
                $cacheFilename = $this->cacheDir . "/aws.ips";
                if (\file_exists($cacheFilename)) {
                    $content = \file_get_contents($cacheFilename);
                    if ($content === false) {
                        $content = '';
                    }
                    try {
                        $awsIps = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                        if (isset($awsIps['expire_at']) && time() > $awsIps['expire_at']) {
                            $awsIps = [];
                        }
                    } catch (\Throwable $throwable) {
                        \merror("Error while processing cached ip file, exception = %s, file content = %s", $throwable->getMessage(), $content);
                        $awsIps = [];
                    }
                }
            }

            if (!\array_key_exists('prefixes', $awsIps)) {
                $guzzleClient = $this->createAwsIpRangesClient();
                $awsResponse = $guzzleClient->request('GET', 'ip-ranges.json');
                if ($awsResponse->getStatusCode() !== Response::HTTP_OK) {
                    \merror("Cannot get ip-ranges from aws server, response = %s %s, %s", $awsResponse->getStatusCode(), $awsResponse->getReasonPhrase(), $awsResponse->getBody()->getContents());
                } else {
                    $content = $awsResponse->getBody()->getContents();
                    $awsIps  = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                    if ($this->cacheDir && \is_writable($this->cacheDir)) {
                        $cacheFilename       = $this->cacheDir . "/aws.ips";
                        $awsIps['expire_at'] = time() + 86400;
                        \file_put_contents($cacheFilename, \json_encode($awsIps, \JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), \LOCK_EX);
                    }
                }
            }

            if (\is_array($awsIps) && \array_key_exists('prefixes', $awsIps)) {
                $trustedCloudfrontIps = [];
                foreach ($awsIps['prefixes'] as $info) {
                    if (\array_key_exists('ip_prefix', $info) && $info['service'] === "CLOUDFRONT") {
                        $trustedCloudfrontIps[] = $info['ip_prefix'];
                    }
                }
                Request::setTrustedProxies(
                    \array_merge(Request::getTrustedProxies(), $trustedCloudfrontIps),
                    Request::HEADER_X_FORWARDED_AWS_ELB
                );
            }
        } catch (\Throwable $throwable) {
            \merror("Error while setting aws trusted proxies, exception = %s", $throwable->getMessage());
        }
    }

    protected function createAwsIpRangesClient(): Client
    {
        return new Client([
            'base_uri' => 'https://ip-ranges.amazonaws.com/',
            'timeout'  => 5.0,
        ]);
    }
}
