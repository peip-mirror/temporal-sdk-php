<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Marshaller;

use Temporal\Client\Internal\Marshaller\Mapper\MapperFactoryInterface;
use Temporal\Client\Internal\Marshaller\Mapper\MapperInterface;

/**
 * @psalm-import-type TypeMatcher from TypeFactory
 */
class Marshaller implements MarshallerInterface
{
    /**
     * @var array<string, MapperInterface>
     */
    private array $mappers = [];

    /**
     * @var TypeFactory
     */
    private TypeFactory $type;

    /**
     * @var MapperFactoryInterface
     */
    private MapperFactoryInterface $mapper;

    /**
     * @param MapperFactoryInterface $mapper
     * @param array<TypeMatcher> $matchers
     */
    public function __construct(MapperFactoryInterface $mapper, array $matchers = [])
    {
        $this->mapper = $mapper;
        $this->type = new TypeFactory($this, $matchers);
    }

    /**
     * @param class-string $class
     * @return MapperInterface
     * @throws \ReflectionException
     */
    private function factory(string $class): MapperInterface
    {
        $reflection = new \ReflectionClass($class);

        return $this->mapper->create($reflection, $this->type);
    }

    /**
     * @param class-string $class
     * @return MapperInterface
     * @throws \ReflectionException
     */
    private function getMapper(string $class): MapperInterface
    {
        return $this->mappers[$class] ??= $this->factory($class);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \ReflectionException
     */
    public function marshal(object $from): array
    {
        $mapper = $this->getMapper(\get_class($from));

        $result = [];

        foreach ($mapper->getGetters() as $field => $getter) {
            $result[$field] = $getter->call($from);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \ReflectionException
     */
    public function unmarshal(array $from, object $to): object
    {
        $mapper = $this->getMapper(\get_class($to));

        $result = $mapper->isCopyOnWrite() ? clone $to : $to;

        foreach ($mapper->getSetters() as $field => $setter) {
            if (! \array_key_exists($field, $from)) {
                continue;
            }

            $setter->call($result, $from[$field] ?? null);
        }

        return $result;
    }
}
