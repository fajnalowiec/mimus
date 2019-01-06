<?php
namespace mimus {

	class Rule {

		public function __construct(Mock $mock, string $method) {
			$this->mock = $mock;
			$this->method = $method;
		}

		public function expects(...$args) : Path {
			if (isset($this->path)) {
				throw new Exception(null,
					"expectation already set for %s", $this->name);
			}

			return $this->path = new Path(...$args);
		}

		public function match(?Exception $except, bool $count, ...$args) : ?Path {
			if ($this->path->try($except, $count, ...$args)) {
				return $this->path;
			}

			return null;
		}

		private $mock;
		private $method;
		private $path;
	}
}
