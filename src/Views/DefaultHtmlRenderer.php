<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2017-01-17
 * Time: 16:54
 */

namespace Oasis\Mlib\Http\Views;

use Oasis\Mlib\Http\ErrorHandlers\WrappedExceptionInfo;
use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\HttpFoundation\Response;

class DefaultHtmlRenderer implements ResponseRendererInterface
{
    
    public function renderOnSuccess(mixed $result, MicroKernel $kernel): Response
    {
        if (is_object($result) && method_exists($result, '__toString')) {
            $result = (string)$result;
        }
        elseif (is_bool($result)) {
            $result = $result ? "true" : "false";
        }
        elseif (is_scalar($result)) {
            $result = (string)$result;
        }
        elseif (is_array($result)) {
            $result = nl2br(str_replace(' ', '&nbsp;', json_encode($result, JSON_PRETTY_PRINT)));
        }
        elseif (!is_string($result)) {
            return $this->renderOnException(
                new WrappedExceptionInfo(
                    new \RuntimeException("Unsupported type of result: " . print_r($result, true)),
                    Response::HTTP_INTERNAL_SERVER_ERROR
                ),
                $kernel
            );
        }
        
        return new Response($result);
    }
    
    public function renderOnException(WrappedExceptionInfo $exceptionInfo, MicroKernel $kernel): Response
    {
        $twig = $kernel->getTwig();
        if (!$twig) {
            $response = $this->renderOnSuccess($exceptionInfo->jsonSerialize(), $kernel);
        }
        else {
            try {
                $templateName = sprintf("%d.twig", $exceptionInfo->getCode());
                
                $response = new Response(
                    $twig->render($templateName, $exceptionInfo->toArray($kernel->isDebug()))
                );
            } catch (\Twig\Error\LoaderError $e) {
                $response = $this->renderOnSuccess($exceptionInfo->jsonSerialize(), $kernel);
            }
        }
        
        return $response;
    }
}
