<?php
/**
 * This file is part of Railt package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Railt\LaravelProvider\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Railt\Foundation\ApplicationInterface;
use Railt\Http\Exception\GraphQLException;
use Railt\Http\Response;
use Railt\Http\ResponseInterface;
use Railt\LaravelProvider\Config;
use Railt\LaravelProvider\Config\Endpoint;
use Railt\LaravelProvider\Http\LaravelProvider;
use Railt\Foundation\Config\RepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class GraphQLController
 */
class GraphQLController
{
    /**
     * @var ApplicationInterface
     */
    private $app;

    /**
     * @var Config
     */
    private $config;

    /**
     * GraphQLController constructor.
     * @param ApplicationInterface $app
     * @param Config $config
     */
    public function __construct(ApplicationInterface $app, Config $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function graphqlAction(Request $request): JsonResponse
    {
        $response = $this->execute($request);

        return new JsonResponse($response->toArray(), $response->getStatusCode(), []);
    }

    /**
     * @param Request $request
     * @return ResponseInterface
     */
    private function execute(Request $request): ResponseInterface
    {
        try {
            $endpoint = $this->getEndpointByRoute($request);

            $connection = $this->app->connect($endpoint->getSchema());

            $response = $connection->requests(new LaravelProvider($request));

            return $response;
        } catch (\Throwable $e) {
            $exception = new GraphQLException($e->getMessage(), $e->getCode(), $e);

            if ($this->app->make(RepositoryInterface::class)->get('debug')) {
                $exception->publish();
            }

            $response = new Response([]);
            $response->withException($exception);

            return $response;
        }
    }

    /**
     * @param Request $request
     * @return Endpoint
     * @throws NotFoundHttpException
     */
    private function getEndpointByRoute(Request $request): Endpoint
    {
        $route = $request->route()->getName();
        $endpoint = $this->config->findByRouteName($route);

        if ($endpoint === null) {
            $error = \sprintf('GraphQL endpoint for route "%s" was not registered', $route);
            throw new NotFoundHttpException($error);
        }

        return $endpoint;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function playgroundAction(Request $request)
    {
        return \view('railt::playground', [
            'endpoints' => $this->config->getEndpoints(),
            'route'     => $request->route(),
            'graphiql'  => $this->config->getPlayground(),
            'debug'     => $this->config->isDebug(),
        ]);
    }
}
