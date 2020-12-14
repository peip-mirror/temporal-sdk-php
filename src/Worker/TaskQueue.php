<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

use React\Promise\PromiseInterface;
use Temporal\Client\Internal\Declaration\Reader\ActivityReader;
use Temporal\Client\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Client\Internal\Events\EventEmitterTrait;
use Temporal\Client\Internal\Repository\RepositoryInterface;
use Temporal\Client\Internal\ServiceContainer;
use Temporal\Client\Internal\Transport\Router;
use Temporal\Client\Internal\Transport\RouterInterface;
use Temporal\Client\Worker;
use Temporal\Client\Worker\Command\RequestInterface;

class TaskQueue implements TaskQueueInterface
{
    use EventEmitterTrait;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var WorkflowReader
     */
    private WorkflowReader $workflowReader;

    /**
     * @var ActivityReader
     */
    private ActivityReader $activityReader;

    /**
     * @var RouterInterface
     */
    private RouterInterface $router;

    /**
     * @var ServiceContainer
     */
    private ServiceContainer $services;

    /**
     * @param string $name
     * @param Worker $worker
     */
    public function __construct(string $name, Worker $worker)
    {
        $this->name = $name;
        $this->services = ServiceContainer::fromWorker($worker);

        $this->boot();
    }

    /**
     * @return void
     */
    private function boot(): void
    {
        $this->workflowReader = new WorkflowReader($this->services->reader);
        $this->activityReader = new ActivityReader($this->services->reader);

        $this->router = $this->createRouter();
    }

    /**
     * @return RouterInterface
     */
    protected function createRouter(): RouterInterface
    {
        $router = new Router();

        // Activity routes
        $router->add(new Router\InvokeActivity($this->services));

        // Workflow routes
        $router->add(new Router\StartWorkflow($this->services));
        $router->add(new Router\InvokeQuery($this->services->running));
        $router->add(new Router\InvokeSignal($this->services->running, $this->services->loop));
        $router->add(new Router\DestroyWorkflow($this->services->running, $this->services->client));
        $router->add(new Router\StackTrace($this->services->running));

        return $router;
    }

    /**
     * @param RequestInterface $request
     * @param array $headers
     * @return PromiseInterface
     */
    public function dispatch(RequestInterface $request, array $headers): PromiseInterface
    {
        $this->services->env->update($headers);

        return $this->router->dispatch($request, $headers);
    }

    /**
     * {@inheritDoc}
     */
    public function getId(): string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function addWorkflow(string $class, bool $overwrite = false): TaskQueueInterface
    {
        foreach ($this->workflowReader->fromClass($class) as $workflow) {
            $this->services->workflows->add($workflow, $overwrite);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getWorkflows(): RepositoryInterface
    {
        return $this->services->workflows;
    }

    /**
     * {@inheritDoc}
     */
    public function addActivity(string $class, bool $overwrite = false): TaskQueueInterface
    {
        foreach ($this->activityReader->fromClass($class) as $activity) {
            $this->services->activities->add($activity, $overwrite);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getActivities(): RepositoryInterface
    {
        return $this->services->activities;
    }
}
