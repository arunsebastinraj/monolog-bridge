<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Processor;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Adds the current route information to the log entry.
 *
 * @author Piotr Stankowski <git@trakos.pl>
 */
class RouteProcessor implements EventSubscriberInterface, ResetInterface
{
    private $routeData;
    private $includeParams;

    public function __construct(bool $includeParams = true)
    {
        $this->includeParams = $includeParams;
        $this->reset();
    }

    public function __invoke(array $records)
    {
        if ($this->routeData && !isset($records['extra']['requests'])) {
            $records['extra']['requests'] = array_values($this->routeData);
        }

        return $records;
    }

    public function reset()
    {
        $this->routeData = array();
    }

    public function addRouteData(GetResponseEvent $event)
    {
        if ($event->isMasterRequest()) {
            $this->reset();
        }

        $request = $event->getRequest();
        if (!$request->attributes->has('_controller')) {
            return;
        }

        $currentRequestData = array(
            'controller' => $request->attributes->get('_controller'),
            'route' => $request->attributes->get('_route'),
        );

        if ($this->includeParams) {
            $currentRequestData['route_params'] = $request->attributes->get('_route_params');
        }

        $this->routeData[spl_object_id($request)] = $currentRequestData;
    }

    public function removeRouteData(FinishRequestEvent $event)
    {
        $requestId = spl_object_id($event->getRequest());
        unset($this->routeData[$requestId]);
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array('addRouteData', 1),
            KernelEvents::FINISH_REQUEST => array('removeRouteData', 1),
        );
    }
}
