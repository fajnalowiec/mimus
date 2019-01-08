<?php
namespace mimus {

	use Componere\Definition;
	use Componere\Method;

	class Mock {
		public /* please */ static /* don't look at my shame */
			function of(string $class, bool $reset = true) {

			if (!class_exists($class)) {
				throw new \LogicException("{$class} does not exist, nothing to mock");
			}

			if (!isset(Mock::$mocks[$class])) {
				Mock::$mocks[$class] = new self($class);
			} else if ($reset) {
				Mock::$mocks[$class]->reset();
			}

			return Mock::$mocks[$class];
		}

		private function __construct(string $class) {
			$this->definition = new Definition($class);
			$this->reflector  = $this->definition->getReflector();

			foreach ($this->reflector->getMethods() as $prototype) {
				$name      = $prototype->getName();

				$this->table[$name] = [];

				$closure   = $this->definition->getClosure($name);
				$table     =& $this->table[$name];

				$this->definition->addMethod($name, $implementation = new Method(function(...$args) use($name, $closure, $prototype, &$table) {
					$except = null;
					$path    = null;

					if (!$table) {
						return;
					}

					foreach ($table as $idx => $rule) {
						try {
							$path = $rule->match($except, true, ...$args);
						} catch (Exception $ex) {
							$except = $ex;
						}

						if ($path) {
							$except = null;
							break;
						}
					}

					if ($except) {
						throw $except;
					}

					if ($prototype->isStatic()) {
						return $path->travel(null, $closure, ...$args);
					}

					return $path->travel($this, $closure, ...$args);
				}));

				if ($prototype) {
					if ($prototype->isStatic()) {
						$implementation->setStatic();
					}

					if ($prototype->isPrivate()) {
						$implementation->setPrivate();
					}

					if ($prototype->isProtected()) {
						$implementation->setProtected();
					}

					if ($prototype->isFinal()) {
						$implementation->setFinal();
					}
				}
			}
			
			$this->definition->register();
		}

		public function partialize($on) {
			if (!is_array($on) && !is_string($on)) {
				throw new \LogicException(
					"expected an array of method names or the name of a valid class");
			}

			if (is_array($on)) {
				foreach ($on as $method) {
					$rule = new Rule($this, $method);
					$rule->expects()
						->executes();

					$this->table[$method][] = $rule;
				}
			} else {
				try {
					$reflector = new \ReflectionClass($on);
				} catch (\ReflectionException $re) {
					throw new \LogicException(
						"expected a valid class name, {$on} cannot be loaded");
				}

				$on   = [];
				foreach ($reflector->getMethods() as $method) {
					$on[] = $method->getName();
				}

				$this->partialize($on);
			}
			
		}

		public function rule(string $name) : Rule {
			if (!isset($this->table[$name])) {
				throw new \LogicException(
					"method {$name} does not exist, or is whitelisted");
			}
			return $this->table[$name][] = new Rule($this, $name);
		}

		public function reset(string $name = null) {
			if ($name === null) {
				foreach ($this->table as $name => $rules) {
					$this->reset($name);
				}
			} else {
				foreach ($this->table[$name] as $idx => $rule) {
					unset($this->table[$name][$idx]);
				}		
			}
		}

		public function getInstance(...$args) {
			if (!func_num_args()) {
				return $this->reflector->newInstanceWithoutConstructor();
			}
			return $this->reflector->newInstanceArgs(...$args);
		}

		private $definition;
		private $reflector;
		private $table;

		private static $mocks;
	}

	function printable($value) {
		switch (gettype($value)) {
			case 'null':
				return 'null';
			case 'boolean':
				return $value ? "bool(true)" : "bool(false)";
			case 'integer':
				return sprintf("int(%d)", $value);
			case 'double':
				return sprintf("float(%s)", $value);
			case 'string': /* TODO limit length */
				if (class_exists($value, 0))
					return $value;
				return sprintf("string(%d) \"%s\"", strlen($value), $value);
			case 'array': /* TODO limit length */
				return sprintf("array(%d) [%s]", count($value), implode(',', $value));
			case 'object':
				return get_class($value);
			default:
				return 'unknown';
		}
	}
}
