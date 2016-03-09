<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-03
 * Time: 22:22
 */

namespace Oasis\Mlib\Http\Views;

use Symfony\Component\HttpFoundation\Request;

abstract class AbstractSmartViewHandler
{
    protected function shouldHandle(Request $request)
    {
        $compatible    = $this->getCompatibleTypes();
        $acceptedTypes = $request->getAcceptableContentTypes();
        if (empty($acceptedTypes)) {
            $acceptedTypes = ['*/*'];
        }

        foreach ($acceptedTypes as $acceptedType) {
            if ($acceptedType == "*/*") {
                return true;
            }
            list($acceptedGroup, $acceptedSubtype) = explode("/", strtolower($acceptedType), 2);
            foreach ($compatible as $type) {
                list($group, $subtype) = explode("/", strtolower($type), 2);
                if ($acceptedGroup == "*" || $acceptedGroup == $group) {
                    if ($acceptedSubtype == "*" || $acceptedSubtype == $subtype) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return array
     */
    abstract protected function getCompatibleTypes();
}
