<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Kernel;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment as TwigEnvironment;

/**
 * Silex-compatible convenience methods for MicroKernel.
 *
 * Provides abort(), redirect(), json(), stream(), sendFile(),
 * render(), renderView(), path(), url().
 */
trait ConvenienceTrait
{
    abstract public function getTwig(): ?TwigEnvironment;

    /**
     * @param array<string, mixed> $parameters
     */
    public function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        if ($this->twigEnvironment === null) {
            throw new \LogicException('Cannot call render() when Twig is not configured.');
        }

        $twig = $this->twigEnvironment;

        if ($response instanceof StreamedResponse) {
            $response->setCallback(function () use ($twig, $view, $parameters): void {
                $twig->display($view, $parameters);
            });
        } else {
            if ($response === null) {
                $response = new Response();
            }
            $response->setContent($twig->render($view, $parameters));
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function renderView(string $view, array $parameters = []): string
    {
        if ($this->twigEnvironment === null) {
            throw new \LogicException('Cannot call renderView() when Twig is not configured.');
        }

        return $this->twigEnvironment->render($view, $parameters);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function path(string $route, array $parameters = []): string
    {
        if ($this->urlGenerator === null) {
            throw new \LogicException('Cannot call path() when routing is not configured.');
        }

        return $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function url(string $route, array $parameters = []): string
    {
        if ($this->urlGenerator === null) {
            throw new \LogicException('Cannot call url() when routing is not configured.');
        }

        return $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * @param array<string, string> $headers
     */
    public function abort(int $statusCode, string $message = '', array $headers = []): never
    {
        throw new HttpException($statusCode, $message, null, $headers);
    }

    public function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    /**
     * @param array<string, string> $headers
     */
    public function json(mixed $data = [], int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public function stream(callable $callback, int $status = 200, array $headers = []): StreamedResponse
    {
        return new StreamedResponse($callback, $status, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public function sendFile(
        string|\SplFileInfo $file,
        int $status = 200,
        array $headers = [],
        ?string $contentDisposition = null,
    ): BinaryFileResponse {
        $response = new BinaryFileResponse($file, $status, $headers);
        if ($contentDisposition !== null) {
            $filename = $file instanceof \SplFileInfo ? $file->getFilename() : basename($file);
            $response->setContentDisposition($contentDisposition, $filename);
        }

        return $response;
    }
}
