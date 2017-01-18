<?php

namespace ForceHttpsModuleSpec;

use ForceHttpsModule\Listener\ForceHttps;
use Kahlan\Plugin\Double;
use Zend\Console\Console;
use Zend\EventManager\EventManagerInterface;
use Zend\Http\PhpEnvironment\Request;
use Zend\Http\PhpEnvironment\Response;
use Zend\Mvc\MvcEvent;
use Zend\Uri\Uri;

describe('ForceHttps', function () {

    describe('->attach()', function () {

        it('attach on route event on non-console', function () {

            Console::overrideIsConsole(false);
            $listener = new ForceHttps([
                'enable'                => true,
                'force_all_routes'      => true,
                'force_specific_routes' => [],
            ]);

            $eventManager = Double::instance(['implements' => EventManagerInterface::class]);
            expect($eventManager)->toReceive('attach')->with(MvcEvent::EVENT_ROUTE, [$listener, 'forceHttpsScheme']);

            $listener->attach($eventManager);

        });

        it('not attach on route on console', function () {

            Console::overrideIsConsole(true);
            $listener = new ForceHttps([
                'enable'                => true,
                'force_all_routes'      => true,
                'force_specific_routes' => [],
            ]);

            $eventManager = Double::instance(['implements' => EventManagerInterface::class]);
            expect($eventManager)->not->toReceive('attach')->with(MvcEvent::EVENT_ROUTE, [$listener, 'forceHttpsScheme']);

            $listener->attach($eventManager);

        });

        it('not attach on route on enable = false', function () {

            Console::overrideIsConsole(true);
            $listener = new ForceHttps([
                'enable'                => false,
            ]);

            $eventManager = Double::instance(['implements' => EventManagerInterface::class]);
            expect($eventManager)->not->toReceive('attach')->with(MvcEvent::EVENT_ROUTE, [$listener, 'forceHttpsScheme']);

            $listener->attach($eventManager);

        });

    });

    describe('->forceHttpsScheme()', function () {

        it('not redirect if uri already has https scheme', function () {

            Console::overrideIsConsole(false);
            $listener = new ForceHttps([
                'enable'                => true,
                'force_all_routes'      => true,
                'force_specific_routes' => [],
            ]);

            $mvcEvent = Double::instance(['extends' => MvcEvent::class, 'methods' => '__construct']);
            allow($mvcEvent)->toReceive('getRequest', 'getUri', 'getScheme')->andReturn('https');
            expect($mvcEvent)->not->toReceive('getResponse');

            $listener->forceHttpsScheme($mvcEvent);

        });

        it('not redirect if force_all_routes is false and route name not in force_specific_routes config', function () {

            Console::overrideIsConsole(false);
            $listener = new ForceHttps([
                'enable'                => true,
                'force_all_routes'      => false,
                'force_specific_routes' => [
                    'checkout'
                ],
            ]);

            $mvcEvent = Double::instance(['extends' => MvcEvent::class, 'methods' => '__construct']);
            allow($mvcEvent)->toReceive('getRequest', 'getUri', 'getScheme')->andReturn('http');
            allow($mvcEvent)->toReceive('getRouteMatch', 'getMatchedRouteName')->andReturn('about');
            expect($mvcEvent)->not->toReceive('getResponse');

            $listener->forceHttpsScheme($mvcEvent);

        });

        it('redirect if force_all_routes is true', function () {

            Console::overrideIsConsole(false);
            $listener = new ForceHttps([
                'enable'                => true,
                'force_all_routes'      => true,
                'force_specific_routes' => [],
            ]);

            $mvcEvent = Double::instance(['extends' => MvcEvent::class, 'methods' => '__construct']);
            $response = Double::instance(['extends' => Response::class]);
            $request  = Double::instance(['extends' => Request::class]);
            $uri      = Double::instance(['extends' => Uri::class]);

            allow($mvcEvent)->toReceive('getRequest')->andReturn($request);
            allow($request)->toReceive('getUri')->andReturn($uri);
            allow($uri)->toReceive('getScheme')->andReturn('http');
            allow($mvcEvent)->toReceive('getRouteMatch', 'getMatchedRouteName')->andReturn('about');
            allow($uri)->toReceive('setScheme')->with('https')->andReturn($uri);
            allow($uri)->toReceive('toString')->andReturn('https://example.com/about');
            allow($mvcEvent)->toReceive('getResponse')->andReturn($response);
            allow($response)->toReceive('setStatusCode')->with(302)->andReturn($response);
            allow($response)->toReceive('getHeaders', 'addHeaderLine')->with('Location', 'https://example.com/about');
            allow($response)->toReceive('send');

            expect($mvcEvent)->toReceive('getResponse');

            $listener->forceHttpsScheme($mvcEvent);

        });

    });

});