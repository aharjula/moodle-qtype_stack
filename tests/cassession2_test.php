<?php


defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../locallib.php');
require_once(__DIR__ . '/fixtures/test_base.php');
require_once(__DIR__ . '/../stack/cas/cassession2.class.php');
require_once(__DIR__ . '/../stack/cas/casstring.class.new.php');

class stack_cas_session2_test extends qtype_stack_testcase {


	public function test_get_valid() {
		$strings = array('foo', 'bar', 'sqrt(4)');

		$casstrings = array();

		foreach ($strings as $string) {
			$casstrings[] = stack_cas_casstring_new::make_casstring_from_teacher_source($string, 'test_get_valid()', new stack_cas_security());
		}

		$session = new stack_cas_session2($casstrings);

		$this->assertTrue($session->get_valid());
	}
}
