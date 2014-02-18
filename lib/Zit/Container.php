<?php

namespace Zit;

/**
 * Dependency Injection Container object
 *
 * @package Zit
 */
class Container
{
	protected $objectCache = array();
	protected $callbacks = array();
	/** @var \SplObjectStorage */
	protected $factories;

	/**
	 * Instantiate the container
	 */
	public function __construct() {
		$this->factories = new \SplObjectStorage();
	}

	/**
	 * Magic method to support object syntax for getting and setting dependencys
	 *
	 * @param string $name      Function name
	 * @param array  $arguments Function arguments
	 * @return mixed
	 * @throws \InvalidArgumentException If the method doesn't exist
	 */
	public function __call($name, $arguments = array()) {
		// Parse function name
		preg_match_all('/_?([A-Z][a-z0-9]*|[a-z0-9]+)/', $name, $parts);
		$parts = $parts[1];
		
		// Determine method
		$method = array_shift($parts);		
		if ('new' == $method) {
			$method = 'fresh';
		}
		
		// Determine object key
		$key = strtolower(implode('_', $parts));
		array_unshift($arguments, $key);
		
		// Call method if exists
		if (method_exists($this, $method)) {
			return call_user_func_array(array($this, $method), $arguments);
		}
		
		// Throw exception on miss
		throw new \InvalidArgumentException(sprintf('Methood "%s" does not exist.', $method));
	}
	
	/**
	 * Define a Closure as a dependency factory. {@link get()} method will always invoke the callable.
	 *
	 * @param \Closure $callable An invokable that returns an object
	 * @return \Closure
	 */
	public function factory(\Closure $callable) {
		$this->factories->attach($callable);

		return $callable;
	}

	/**
	 * Define a dependency
	 *
	 * @param string   $name     The dependency name. Must be lowercase to support magic methods.
	 * @param \Closure $callable A Closure that creates the dependency object
	 */
	public function set($name, \Closure $callable) {
		$this->callbacks[$name] = $callable;
	}

	/**
	 * Define a parameter rather than a dependency
	 *
	 * @param string $name  Parameter name
	 * @param mixed  $param Parameter value
	 */
	public function setParam($name, $param) {
		$this->set(
			 $name, function () use ($param) {
					 return $param;
				 }
		);
	}

	/**
	 * Check if a dependency or parameter is defined
	 *
	 * @param string $name Dependency or parameter name
	 * @return bool
	 */
	public function has($name) {
		return isset($this->callbacks[$name]);
	}

	/**
	 * Get a dependency object or parameter value
	 *
	 * Returns a cached dependency object if the dependency has already been instantiated
	 * unless the dependency was defined with the {@link factory()} method
	 *
	 * @param string $name Dependency name
	 * @return mixed
	 */
	public function get($name) {
		// Return object if it's already instantiated
		if (isset($this->objectCache[$name])) {
			$args = func_get_args();
			array_shift($args);

			$key = $this->keyForArguments($args);
			if ('_no_arguments' == $key && !isset($this->objectCache[$name][$key]) && !empty($this->objectCache[$name])) {
				$key = key($this->objectCache[$name]);
			}
			if (isset($this->objectCache[$name][$key])) {
				return $this->objectCache[$name][$key];
			}
		}
		
		// Otherwise create a new one
		return call_user_func_array(array($this, 'fresh'), func_get_args());
	}

	/**
	 * Create a new instance of the dependency
	 *
	 * @param string $name Dependency name
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	public function fresh($name) {
		if (!isset($this->callbacks[$name])) {
			throw new \InvalidArgumentException(sprintf('Callback for "%s" does not exist.', $name));
		}
		
		$arguments = func_get_args();
		$arguments[0] = $this;
		$object = call_user_func_array($this->callbacks[$name], $arguments);
		if (!isset($this->factories[$this->callbacks[$name]])) {
			// Cache the result if the dependency isn't a factory
			$key = $this->keyForArguments($arguments);
			$this->objectCache[$name][$key] = $object;
		}
		return $object;
	}

	/**
	 * Delete all cached copies of the dependency
	 *
	 * @param string $name Dependency name
	 * @return bool
	 */
	public function delete($name) {
		// TODO: Should this also delete the callback?
		if (isset($this->objectCache[$name])) {
			unset($this->objectCache[$name]);
			return true;
		}
		
		return false;
	}

	/**
	 * Creates a hash based on the function call arguments
	 *
	 * Allows the caching of objects based on constructor parameters
	 * and creation of new objects if the constructor parameters are different
	 *
	 * @param array $arguments
	 * @return string
	 */
	protected function keyForArguments(array $arguments) {
		if (count($arguments) && $this === $arguments[0]) {
			array_shift($arguments);
		}
		
		if (0 == count($arguments)) {
			return '_no_arguments';
		}
		
		return md5(serialize($arguments));
	}
}


