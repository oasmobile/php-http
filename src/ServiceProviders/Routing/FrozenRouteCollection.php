<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\ServiceProviders\Routing;

use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * A read-only wrapper around a RouteCollection that prevents modifications after boot.
 *
 * All write operations (add, addCollection, remove, addResource) throw a LogicException.
 * Read-only operations (get, all, count, getIterator, getResources) are inherited from
 * the parent class and work normally.
 */
class FrozenRouteCollection extends RouteCollection
{
    private const FROZEN_MESSAGE = 'Route collection is frozen after boot. Routes cannot be modified at this point.';

    private bool $frozen = false;

    public function __construct(RouteCollection $wrapped)
    {
        parent::addCollection($wrapped);
        $this->frozen = true;
    }

    public function add(string $name, Route $route, int $priority = 0): void
    {
        throw new \LogicException(self::FROZEN_MESSAGE);
    }

    public function addCollection(RouteCollection $collection): void
    {
        if ($this->frozen) {
            throw new \LogicException(self::FROZEN_MESSAGE);
        }

        parent::addCollection($collection);
    }

    /**
     * @param string|string[] $name
     */
    public function remove(string|array $name): void
    {
        throw new \LogicException(self::FROZEN_MESSAGE);
    }

    public function addResource(ResourceInterface $resource): void
    {
        if ($this->frozen) {
            throw new \LogicException(self::FROZEN_MESSAGE);
        }

        parent::addResource($resource);
    }
}
