<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test\Helpers\Controllers;

use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for Twig scenario tests.
 */
class TwigScenarioController
{
    public function render(MicroKernel $kernel): Response
    {
        $twig = $kernel->getTwig();

        return new Response($twig->render('scenario/hello.twig', ['name' => 'World']));
    }

    public function renderUndefined(MicroKernel $kernel): Response
    {
        $twig = $kernel->getTwig();

        return new Response($twig->render('scenario/undefined_var.twig', []));
    }
}
