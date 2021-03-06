<?php

/**
 * This file is part of the Pathfinder package.
 *
 * (c) bitExpert AG
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace bitExpert\Pathfinder;

use bitExpert\Slf4PsrLog\LoggerFactory;

abstract class AbstractRouter implements Router
{
    /**
     * @var \Psr\Log\LoggerInterface the logger instance.
     */
    protected $logger;
    /**
     * @var string
     */
    protected $baseURL;
    /**
     * @var mixed|null
     */
    protected $defaultTarget;
    /**
     * @var Route[][]
     */
    protected $routes;

    /**
     * Creates a new {@link \bitExpert\Pathfinder\AbstractRouter}.
     *
     * @param string $baseURL
     */
    public function __construct($baseURL)
    {
        // completes the base url with a / if not set in configuration
        $this->baseURL = rtrim($baseURL, '/') . '/';
        $this->defaultTarget = null;
        $this->routes = [];

        $this->logger = LoggerFactory::getLogger(__CLASS__);
    }

    /**
     * {@inheritDoc}
     */
    public function setDefaultTarget($defaultTarget)
    {
        $this->defaultTarget = $defaultTarget;
    }

    /**
     * Matches given variables against given matchers and returns
     * if all vars pass all matchers
     *
     * @param Route $route The route to test the values against
     * @param array $params The names variables and values
     * @return bool
     */
    protected function matchParams(Route $route, $params)
    {
        $matchers = $route->getMatchers();

        foreach ($params as $name => $value) {
            if (!isset($matchers[$name])) {
                continue;
            }

            $valueMatchers = $matchers[$name];
            foreach ($valueMatchers as $matcher) {
                if (!$matcher($value)) {
                    $this->logger->debug(sprintf(
                        'Value "%s" for param "%s" did not match criteria of matcher "%s"',
                        $value,
                        $name,
                        get_class($matcher)
                    ));
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Validates given params against the required ones and checks for matcher violations afterwards
     *
     * @param Route $route
     * @param array $params
     * @param array $requiredParams
     */
    protected function validateParams(Route $route, array $params, array $requiredParams)
    {
        $identifier = $this->getRouteIdentifier($route);
        $givenParams = array_keys($params);

        $missingParams = array_diff($requiredParams, $givenParams);

        if (count($missingParams) > 0) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Error while validating params "%s": Required parameters "%s" are missing',
                    $identifier,
                    implode(', ', $missingParams)
                )
            );
        }

        if (!$this->matchParams($route, $params)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Error while validing params for target "%s": Params don\'t fulfill their matcher\'s criteria',
                    $identifier
                )
            );
        }
    }

    /**
     * {@inheritDocs}
     */
    public function addRoute(Route $route)
    {
        $this->validateRoute($route);
        // get the specific path matcher for this route
        $pathMatcher = $this->getPathMatcherForRoute($route);

        $methods = $route->getMethods();

        foreach ($methods as $method) {
            if (!isset($this->routes[$method])) {
                $this->routes[$method] = [];
            }

            $this->routes[$method][] = [
                'pathMatcher' => $pathMatcher,
                'route' => $route
            ];
        }
    }

    /**
     * Sets the routes.
     *
     * @param Route[] $routes
     */
    public function setRoutes(array $routes)
    {
        $this->routes = [];
        foreach ($routes as $route) {
            $this->addRoute($route);
        }
    }

    /**
     * Validates given route for configuration correctness and throws \ConfigurationException
     * if any required configuration is missing. Returns true if everything's fine
     *
     * @param Route $route
     * @throws \ConfigurationException
     */
    protected function validateRoute(Route $route)
    {
        if (null === $route->getPath()) {
            throw new \ConfigurationException('Route must have defined a path');
        }

        if (null === $route->getTarget()) {
            throw new \ConfigurationException('Route must have defined a target');
        }

        if (0 === count($route->getMethods())) {
            throw new \ConfigurationException('Route must at least accept one request method');
        }

        if (!is_string($route->getTarget()) && (null === $route->getName())) {
            throw new \ConfigurationException('If defined route target is not a string a name has to be set');
        }
    }

    /**
     * Returns the identifier string for given route
     *
     * @param Route $route
     * @return string
     */
    protected function getRouteIdentifier(Route $route)
    {
        return empty($route->getName()) ? $route->getTarget() : $route->getName();
    }

    /**
     * Returns the internal identifier of the given route used for matching the request
     *
     * @param Route $route
     * @return mixed
     */
    abstract protected function getPathMatcherForRoute(Route $route);
}
