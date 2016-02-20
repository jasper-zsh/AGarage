<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Console\Descriptor;

use Symfony\Component\Console\Descriptor\DescriptorInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
abstract class Descriptor implements DescriptorInterface
{
    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * {@inheritdoc}
     */
    public function describe(OutputInterface $output, $object, array $options = array())
    {
        $this->output = $output;

        switch (true) {
            case $object instanceof RouteCollection:
                $this->describeRouteCollection($object, $options);
                break;
            case $object instanceof Route:
                $this->describeRoute($object, $options);
                break;
            case $object instanceof ParameterBag:
                $this->describeContainerParameters($object, $options);
                break;
            case $object instanceof ContainerBuilder && isset($options['group_by']) && 'tags' === $options['group_by']:
                $this->describeContainerTags($object, $options);
                break;
            case $object instanceof ContainerBuilder && isset($options['id']):
                $this->describeContainerService($this->resolveServiceDefinition($object, $options['id']), $options);
                break;
            case $object instanceof ContainerBuilder && isset($options['parameter']):
                $this->describeContainerParameter($object->getParameter($options['parameter']), $options);
                break;
            case $object instanceof ContainerBuilder:
                $this->describeContainerServices($object, $options);
                break;
            case $object instanceof Definition:
                $this->describeContainerDefinition($object, $options);
                break;
            case $object instanceof Alias:
                $this->describeContainerAlias($object, $options);
                break;
            case $object instanceof EventDispatcherInterface:
                $this->describeEventDispatcherListeners($object, $options);
                break;
            case is_callable($object):
                $this->describeCallable($object, $options);
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Object of type "%s" is not describable.', get_class($object)));
        }
    }

    /**
     * Returns the output.
     *
     * @return OutputInterface The output
     */
    protected function getOutput()
    {
        return $this->output;
    }

    /**
     * Writes content to output.
     *
     * @param string $content
     * @param bool   $decorated
     */
    protected function write($content, $decorated = false)
    {
        $this->output->write($content, false, $decorated ? OutputInterface::OUTPUT_NORMAL : OutputInterface::OUTPUT_RAW);
    }

    /**
     * Describes an InputArgument instance.
     *
     * @param RouteCollection $routes
     * @param array           $options
     */
    abstract protected function describeRouteCollection(RouteCollection $routes, array $options = array());

    /**
     * Describes an InputOption instance.
     *
     * @param Route $route
     * @param array $options
     */
    abstract protected function describeRoute(Route $route, array $options = array());

    /**
     * Describes container parameters.
     *
     * @param ParameterBag $parameters
     * @param array        $options
     */
    abstract protected function describeContainerParameters(ParameterBag $parameters, array $options = array());

    /**
     * Describes container tags.
     *
     * @param ContainerBuilder $builder
     * @param array            $options
     */
    abstract protected function describeContainerTags(ContainerBuilder $builder, array $options = array());

    /**
     * Describes a container service by its name.
     *
     * Common options are:
     * * name: name of described service
     *
     * @param Definition|Alias|object $service
     * @param array                   $options
     */
    abstract protected function describeContainerService($service, array $options = array());

    /**
     * Describes container services.
     *
     * Common options are:
     * * tag: filters described services by given tag
     *
     * @param ContainerBuilder $builder
     * @param array            $options
     */
    abstract protected function describeContainerServices(ContainerBuilder $builder, array $options = array());

    /**
     * Describes a service definition.
     *
     * @param Definition $definition
     * @param array      $options
     */
    abstract protected function describeContainerDefinition(Definition $definition, array $options = array());

    /**
     * Describes a service alias.
     *
     * @param Alias $alias
     * @param array $options
     */
    abstract protected function describeContainerAlias(Alias $alias, array $options = array());

    /**
     * Describes a container parameter.
     *
     * @param string $parameter
     * @param array  $options
     */
    abstract protected function describeContainerParameter($parameter, array $options = array());

    /**
     * Describes event dispatcher listeners.
     *
     * Common options are:
     * * name: name of listened event
     *
     * @param EventDispatcherInterface $eventDispatcher
     * @param array                    $options
     */
    abstract protected function describeEventDispatcherListeners(EventDispatcherInterface $eventDispatcher, array $options = array());

    /**
     * Describes a callable.
     *
     * @param callable $callable
     * @param array    $options
     */
    abstract protected function describeCallable($callable, array $options = array());

    /**
     * Formats a value as string.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function formatValue($value)
    {
        if (is_object($value)) {
            return sprintf('object(%s)', get_class($value));
        }

        if (is_string($value)) {
            return $value;
        }

        return preg_replace("/\n\s*/s", '', var_export($value, true));
    }

    /**
     * Formats a parameter.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function formatParameter($value)
    {
        if (is_bool($value) || is_array($value) || (null === $value)) {
            $jsonString = json_encode($value);

            if (preg_match('/^(.{60})./us', $jsonString, $matches)) {
                return $matches[1].'...';
            }

            return $jsonString;
        }

        return (string) $value;
    }

    /**
     * @param ContainerBuilder $builder
     * @param string           $serviceId
     *
     * @return mixed
     */
    protected function resolveServiceDefinition(ContainerBuilder $builder, $serviceId)
    {
        if ($builder->hasDefinition($serviceId)) {
            return $builder->getDefinition($serviceId);
        }

        // Some service IDs don't have a Definition, they're simply an Alias
        if ($builder->hasAlias($serviceId)) {
            return $builder->getAlias($serviceId);
        }

        // the service has been injected in some special way, just return the service
        return $builder->get($serviceId);
    }

    /**
     * @param ContainerBuilder $builder
     * @param bool             $showPrivate
     *
     * @return array
     */
    protected function findDefinitionsByTag(ContainerBuilder $builder, $showPrivate)
    {
        $definitions = array();
        $tags = $builder->findTags();
        asort($tags);

        foreach ($tags as $tag) {
            foreach ($builder->findTaggedServiceIds($tag) as $serviceId => $attributes) {
                $definition = $this->resolveServiceDefinition($builder, $serviceId);

                if (!$definition instanceof Definition || !$showPrivate && !$definition->isPublic()) {
                    continue;
                }

                if (!isset($definitions[$tag])) {
                    $definitions[$tag] = array();
                }

                $definitions[$tag][$serviceId] = $definition;
            }
        }

        return $definitions;
    }

    protected function sortParameters(ParameterBag $parameters)
    {
        $parameters = $parameters->all();
        ksort($parameters);

        return $parameters;
    }

    protected function sortServiceIds(array $serviceIds)
    {
        asort($serviceIds);

        return $serviceIds;
    }
}
