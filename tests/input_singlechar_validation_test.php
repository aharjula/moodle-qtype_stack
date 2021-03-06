<?php
// This file is part of Stack - http://stack.bham.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unit tests for the stack_singlechar_input class.
 *
 * @copyright  2012 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/test_base.php');
require_once(__DIR__ . '/../stack/input/factory.class.php');


/**
 * Unit tests for stack_boolean_input_test.
 *
 * @copyright  2012 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group qtype_stack
 */
class stack_singlechar_input_validation_test extends qtype_stack_testcase {
    public function test_validate_student_response_true() {
        $options = new stack_options();
        $el = stack_input_factory::make('singleChar', 'sans1', 'true');
        $state = $el->validate_student_response(array('sans1' => 'x'), $options, 'true', null);
        $this->assertEquals(stack_input::SCORE, $state->status);
    }

    public function test_validate_student_response_false() {
        $options = new stack_options();
        $el = stack_input_factory::make('singleChar', 'sans1', 'true');
        $state = $el->validate_student_response(array('sans1' => ''), $options, 'true', null);
        $this->assertEquals(stack_input::BLANK, $state->status);
    }

    public function test_validate_student_response_na() {
        $options = new stack_options();
        $el = stack_input_factory::make('singlechar', 'sans1', 'true');
        $state = $el->validate_student_response(array('sans1' => 'xx'), $options, 'true', null);
        $this->assertEquals(stack_input::INVALID, $state->status);
    }
}
