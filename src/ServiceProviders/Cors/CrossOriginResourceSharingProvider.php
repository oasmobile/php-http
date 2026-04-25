<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 20:16
 */

namespace Oasis\Mlib\Http\ServiceProviders\Cors;

use Oasis\Mlib\Http\Views\PrefilightResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class CrossOriginResourceSharingProvider implements EventSubscriberInterface
{
    const HEADER_REQUEST_ORIGIN  = "Origin";
    const HEADER_REQUEST_METHOD  = "Access-Control-Request-Method";
    const HEADER_REQUEST_HEADERS = "Access-Control-Request-Headers";

    const HEADER_ALLOW_ORIGIN      = "Access-Control-Allow-Origin";
    const HEADER_VARY              = "Vary";
    const HEADER_ALLOW_METHODS     = "Access-Control-Allow-Methods";
    const HEADER_ALLOW_HEADERS     = "Access-Control-Allow-Headers";
    const HEADER_EXPOSE_HEADERS    = "Access-Control-Expose-Headers";
    const HEADER_ALLOW_CREDENTIALS = "Access-Control-Allow-Credentials";
    const HEADER_MAX_AGE           = "Access-Control-Max-Age";

    const SIMPLE_METHODS = [
        'HEAD',
        'POST',
        'GET',
    ];

    /** @var CrossOriginResourceSharingStrategy[] */
    protected $strategies = [];
    /** @var CrossOriginResourceSharingStrategy|null */
    protected $activeStrategy = null;
    /** @var PrefilightResponse|null */
    protected $preFlightResponse = null;

    /**
     * @param array<CrossOriginResourceSharingStrategy|array<string, mixed>> $strategies
     */
    public function __construct(array $strategies = [])
    {
        /** @var CrossOriginResourceSharingStrategy[] $resolved */
        $resolved = [];
        foreach ($strategies as $strategy) {
            if (\is_array($strategy)) {
                $resolved[] = new CrossOriginResourceSharingStrategy($strategy);
            } elseif ($strategy instanceof CrossOriginResourceSharingStrategy) {
                $resolved[] = $strategy;
            } else {
                throw new \InvalidArgumentException(
                    static::class . " must be constructed with array of " . CrossOriginResourceSharingStrategy::class
                );
            }
        }
        $this->strategies = $resolved;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onPreRouting', 33],   // BEFORE_PRIORITY_ROUTING + 1
                ['onPostRouting', 20],  // BEFORE_PRIORITY_CORS_PREFLIGHT
            ],
            KernelEvents::RESPONSE => [
                ['onResponse', -512],   // AFTER_PRIORITY_LATEST
            ],
            KernelEvents::EXCEPTION => [
                ['onException', 512],   // AFTER_PRIORITY_EARLIEST
            ],
        ];
    }

    /**
     * Finds out active CORS strategy for the request.
     * Also decides if the request is a Pre-Flight request.
     */
    public function onPreRouting(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $this->activeStrategy    = null;
        $this->preFlightResponse = null;

        if (!$request->headers->has(static::HEADER_REQUEST_ORIGIN)) {
            return;
        }

        foreach ($this->strategies as $strategy) {
            if ($strategy->matches($request)) {
                $this->activeStrategy = $strategy;
                break;
            }
        }

        if (!$this->activeStrategy) {
            return;
        }

        if ($request->getMethod() === "OPTIONS"
            && $request->headers->has(static::HEADER_REQUEST_METHOD)
        ) {
            $this->preFlightResponse = new PrefilightResponse();
        }
    }

    public function onPostRouting(RequestEvent $event): void
    {
        if ($this->preFlightResponse) {
            $request = $event->getRequest();
            $this->preFlightResponse->addAllowedMethod($request->headers->get(static::HEADER_REQUEST_METHOD) ?? '');

            $event->setResponse($this->preFlightResponse);
        }
    }

    /**
     * Handle MethodNotAllowedHttpException during preflight.
     * Replaces the old onMethodNotAllowedHttp() Silex error handler.
     */
    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof MethodNotAllowedHttpException) {
            return;
        }

        if ($this->preFlightResponse) {
            foreach (explode(', ', $exception->getHeaders()['Allow']) as $method) {
                $this->preFlightResponse->addAllowedMethod($method);
            }

            $event->allowCustomResponseCode();
            $event->setResponse($this->preFlightResponse);
            $event->stopPropagation();
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request  = $event->getRequest();
        $response = $event->getResponse();

        if ($this->activeStrategy) {
            // This function will process according to spec https://www.w3.org/TR/cors/#resource-processing-model

            if ($response instanceof PrefilightResponse) {
                // PREFLIGHT REQUEST STEPS:

                // 1. skip setting access control headersAllowed if no 'origin' header is provided in request
                if (!$request->headers->has(static::HEADER_REQUEST_ORIGIN)) {
                    return;
                }

                // 2. skip if origin is not allowed
                $requestOrigin = $request->headers->get(static::HEADER_REQUEST_ORIGIN);
                if ($requestOrigin === null || !$this->activeStrategy->isOriginAllowed($requestOrigin)) {
                    return;
                }

                // 3. terminate if no request-method header is set
                if (!$request->headers->has(static::HEADER_REQUEST_METHOD)) {
                    return;
                }
                $requestMethod = strtoupper((string) $request->headers->get(static::HEADER_REQUEST_METHOD));

                // 4. prepare request headers
                if ($request->headers->has(static::HEADER_REQUEST_HEADERS)) {
                    $requestHeaders = explode(",", (string) $request->headers->get(static::HEADER_REQUEST_HEADERS));
                } else {
                    $requestHeaders = [];
                }

                // 5. terminate if method is not allowed
                if (empty($methodsAllowed = $response->getAllowedMethods())) {
                    return;
                }
                if (!\in_array($requestMethod, $methodsAllowed)) {
                    return;
                }

                // 6. terminate if header is not allowed
                foreach ($requestHeaders as $header) {
                    $header = trim($header);
                    if (!$this->activeStrategy->isHeaderAllowed($header)) {
                        return;
                    }
                }

                // 7. set allow-origin header
                if ($this->activeStrategy->isCredentialsAllowed()) {
                    $response->headers->add([static::HEADER_ALLOW_CREDENTIALS => 'true']);
                    $response->headers->add([static::HEADER_ALLOW_ORIGIN => $requestOrigin]);
                } else {
                    if ($this->activeStrategy->isWildcardOriginAllowed()) {
                        $response->headers->add([static::HEADER_ALLOW_ORIGIN => "*"]);
                    } else {
                        $response->headers->add([static::HEADER_ALLOW_ORIGIN => $requestOrigin]);
                        $response->headers->add([static::HEADER_VARY => 'Origin']);
                    }
                }

                // 8. set max age
                $response->headers->add([static::HEADER_MAX_AGE => $this->activeStrategy->getMaxAge()]);

                // 9. set allow methods if method is not simple method
                if (!\in_array($requestMethod, static::SIMPLE_METHODS)) {
                    $response->headers->add(
                        [static::HEADER_ALLOW_METHODS => strtoupper(implode(', ', $methodsAllowed))]
                    );
                }

                // 10. set allow headers
                if ($headersAllwed = $this->activeStrategy->getAllowedHeaders()) {
                    $response->headers->add([static::HEADER_ALLOW_HEADERS => $headersAllwed]);
                }
            } else {
                // NORMAL REQUEST STEPS:

                // 1. skip setting access control headersAllowed if no 'origin' header is provided in request
                if (!$request->headers->has(static::HEADER_REQUEST_ORIGIN)) {
                    return;
                }

                // 2. skip if origin is not allowed
                $requestOrigin = $request->headers->get(static::HEADER_REQUEST_ORIGIN);
                if ($requestOrigin === null || !$this->activeStrategy->isOriginAllowed($requestOrigin)) {
                    return;
                }

                // 3. set allow-origin header
                if ($this->activeStrategy->isCredentialsAllowed()) {
                    $response->headers->add([static::HEADER_ALLOW_CREDENTIALS => 'true']);
                    $response->headers->add([static::HEADER_ALLOW_ORIGIN => $requestOrigin]);
                } else {
                    if ($this->activeStrategy->isWildcardOriginAllowed()) {
                        $response->headers->add([static::HEADER_ALLOW_ORIGIN => "*"]);
                    } else {
                        $response->headers->add([static::HEADER_ALLOW_ORIGIN => $requestOrigin]);
                        $response->headers->add([static::HEADER_VARY => 'Origin']);
                    }
                }

                // 4. list exposed headers
                if ($headersExposed = $this->activeStrategy->getExposedHeaders()) {
                    $response->headers->add([static::HEADER_EXPOSE_HEADERS => $headersExposed]);
                }
            }
        }
    }
}
