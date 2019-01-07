<?php
namespace mimus\tests {

	class Rule extends \PHPUnit\Framework\TestCase {

		public function testExpectationAlreadySet() {
			$mock = \mimus\Mock::of(\mimus\tests\classes\Foo::class);

			$rule = $mock->rule("publicMethod");
			
			$rule->expects();

			$this->expectException(\mimus\Exception::class);

			$rule->expects();
		}
	}
}