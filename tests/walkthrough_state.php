<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains tests that walk Stack questions through various sequences
 * of student interaction with different behaviours.
 *
 * @package   qtype_stack
 * @copyright 2015 Aalto University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once(__DIR__ . '/test_base.php');


/**
 * Unit tests for the Stack question type with state-variable expansions.
 *
 * @copyright 2015 Aalto University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group qtype_stack
 */
class qtype_stack_walkthrough_state extends qtype_stack_walkthrough_test_base {

    public function test_state_test_initialisation_and_correct_scene_progression() {
        // Create the stack question 'test_state_1'. Correct answers are {-2,4,5,-3}.
        $q = test_question_maker::make_question('stack', 'test_state_1');
        $this->start_attempt_at_question($q, 'adaptive', 1);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->assertEquals('adaptivemultipart',
                $this->quba->get_question_attempt($this->slot)->get_behaviour_name());
        $this->render();
        $this->check_output_contains_text_input('ans1');
        $this->check_output_does_not_contain_text_input_with_class('ans2');
        $this->check_output_does_not_contain_text_input_with_class('ans3');
        $this->check_output_does_not_contain_input_validation();
        $this->check_output_does_not_contain_prt_feedback();
        $this->check_output_does_not_contain_stray_placeholders();
        $this->check_current_output(
                new question_pattern_expectation('/Guess the roots/'),
                new question_pattern_expectation('/Your quess for the first root is/'),
                new question_no_pattern_expectation('/Then the second one/'),
                new question_no_pattern_expectation('/Then the third one/'),
                new question_no_pattern_expectation('/Well done all the roots have been found/'),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_num_parts_correct(),
                $this->get_no_hint_visible_expectation()
        );

        // Process a validate request.
        $this->process_submission(array('ans1' => '4', '-submit' => 1));

        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_prt_score('prt1', null, null);
        $this->check_prt_score('prt2', null, null);
        $this->check_prt_score('prt3', null, null);
        $this->render();
        $this->check_output_contains_text_input('ans1', '4');
        $this->check_output_contains_input_validation('ans1');
        // Since the answer is a number there are no variables.
        $this->check_output_does_not_contain_lang_string('studentValidation_listofvariables', 'qtype_stack', '\( \left[ x \right]\)');
        $this->check_output_does_not_contain_prt_feedback();
        $this->check_output_does_not_contain_stray_placeholders();

        // Process a submit of the correct answer. To initiate scene transition.
        $this->process_submission(array('ans1' => '4', 'ans1_val' => '4', '-submit' => 1));

        // Verify. The next scene and the submission.
        $this->check_current_state(question_state::$todo);
        $this->check_prt_score('prt1', 1, 0);
        $this->check_prt_score('prt2', null, null);
        $this->check_prt_score('prt3', null, null);
        $this->render();
        $this->check_output_contains_text_input('ans2');
        $this->check_output_does_not_contain_text_input_with_class('ans1');
        $this->check_output_does_not_contain_text_input_with_class('ans3');
        $this->check_output_does_not_contain_input_validation();
        $this->check_output_does_not_contain_prt_feedback();
        $this->check_output_does_not_contain_stray_placeholders();
        $this->check_current_output(
                new question_pattern_expectation('/Guess the roots/'),
                new question_no_pattern_expectation('/Your quess for the first root is/'),
                new question_pattern_expectation('/Then the second one/'),
                new question_no_pattern_expectation('/Then the third one/'),
                new question_no_pattern_expectation('/Well done all the roots have been found/'),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_num_parts_correct(),
                $this->get_no_hint_visible_expectation()
        );

        // Do a direct submit to the second input to switch scenes.
        $this->process_submission(array('ans2' => '5', 'ans2_val' => '5', '-submit' => 1));

        // Verify. The next scene and the submission.
        $this->check_current_state(question_state::$todo);
        $this->check_prt_score('prt1', 1, 0);
        $this->check_prt_score('prt2', 1, 0);
        $this->check_prt_score('prt3', null, null);
        $this->render();
        $this->check_output_contains_text_input('ans3');
        $this->check_output_does_not_contain_text_input_with_class('ans1');
        $this->check_output_does_not_contain_text_input_with_class('ans2');
        $this->check_output_does_not_contain_input_validation();
        $this->check_output_does_not_contain_prt_feedback();
        $this->check_output_does_not_contain_stray_placeholders();
        $this->check_current_output(
                new question_pattern_expectation('/Guess the roots/'),
                new question_no_pattern_expectation('/Your quess for the first root is/'),
                new question_no_pattern_expectation('/Then the second one/'),
                new question_pattern_expectation('/Then the third one/'),
                new question_no_pattern_expectation('/Well done all the roots have been found/'),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_num_parts_correct(),
                $this->get_no_hint_visible_expectation()
        );

        // Do a direct submit for the third input.
        $this->process_submission(array('ans3' => '-2', 'ans3_val' => '-2', '-submit' => 1));

        // Verify. The next scene and the submission.
        $this->check_current_state(question_state::$todo);
        $this->check_prt_score('prt1', 1, 0);
        $this->check_prt_score('prt2', 1, 0);
        $this->check_prt_score('prt3', 1, 0);
        $this->check_current_mark(1);
        $this->render();
        $this->check_output_does_not_contain_text_input_with_class('ans1');
        $this->check_output_does_not_contain_text_input_with_class('ans2');
        $this->check_output_does_not_contain_text_input_with_class('ans3');
        $this->check_output_does_not_contain_input_validation();
        $this->check_output_does_not_contain_prt_feedback();
        $this->check_output_does_not_contain_stray_placeholders();
        $this->check_current_output(
                new question_pattern_expectation('/Guess the roots/'),
                new question_no_pattern_expectation('/Your quess for the first root is/'),
                new question_no_pattern_expectation('/Then the second one/'),
                new question_no_pattern_expectation('/Then the third one/'),
                new question_pattern_expectation('/Well done all the roots have been found/'),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_num_parts_correct(),
                $this->get_no_hint_visible_expectation()
        );
    }

    public function test_state_test_initialisation_and_incorrect_answers() {
        // In this test we will incorrectly give the same root as an aswer in multiple scenes.
        // Create the stack question 'test_state_1'. Correct answers are {-2,4,5,-3}.
        $q = test_question_maker::make_question('stack', 'test_state_1');
        $this->start_attempt_at_question($q, 'adaptive', 1);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->assertEquals('adaptivemultipart',
                $this->quba->get_question_attempt($this->slot)->get_behaviour_name());
        $this->render();
        $this->check_output_contains_text_input('ans1');
        $this->check_output_does_not_contain_text_input_with_class('ans2');
        $this->check_output_does_not_contain_text_input_with_class('ans3');
        $this->check_output_does_not_contain_input_validation();
        $this->check_output_does_not_contain_prt_feedback();
        $this->check_output_does_not_contain_stray_placeholders();
        $this->check_current_output(
                new question_pattern_expectation('/Guess the roots/'),
                new question_pattern_expectation('/Your quess for the first root is/'),
                new question_no_pattern_expectation('/Then the second one/'),
                new question_no_pattern_expectation('/Then the third one/'),
                new question_no_pattern_expectation('/Well done all the roots have been found/'),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_num_parts_correct(),
                $this->get_no_hint_visible_expectation()
        );

        // Process a submit of the correct answer. To initiate scene transition.
        $this->process_submission(array('ans1' => '5', 'ans1_val' => '5', '-submit' => 1));

        // Verify. The next scene and the submission.
        $this->check_current_state(question_state::$todo);
        $this->check_prt_score('prt1', 1, 0);
        $this->check_prt_score('prt2', null, null);
        $this->check_prt_score('prt3', null, null);
        $this->render();
        $this->check_output_contains_text_input('ans2');
        $this->check_output_does_not_contain_text_input_with_class('ans1');
        $this->check_output_does_not_contain_text_input_with_class('ans3');
        $this->check_output_does_not_contain_input_validation();
        $this->check_output_does_not_contain_prt_feedback();
        $this->check_output_does_not_contain_stray_placeholders();
        $this->check_current_output(
                new question_pattern_expectation('/Guess the roots/'),
                new question_no_pattern_expectation('/Your quess for the first root is/'),
                new question_pattern_expectation('/Then the second one/'),
                new question_no_pattern_expectation('/Then the third one/'),
                new question_no_pattern_expectation('/Well done all the roots have been found/'),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_num_parts_correct(),
                $this->get_no_hint_visible_expectation()
        );

        // Do a direct submit to the second input with the same value. PRT does not reference the 'ans1'-value,
        // it has to use state to identify this as a wrong answer.
        $this->process_submission(array('ans2' => '5', 'ans2_val' => '5', '-submit' => 1));

        // Verify. The scene is the same but there is feedback.
        $this->check_current_state(question_state::$todo);
        $this->check_prt_score('prt1', 1, 0);
        $this->check_prt_score('prt2', 0, 0.1);
        $this->check_prt_score('prt3', null, null);
        $this->render();
        $this->check_output_contains_text_input('ans2','5');
        $this->check_output_does_not_contain_text_input_with_class('ans1');
        $this->check_output_does_not_contain_text_input_with_class('ans3');
        $this->check_output_does_not_contain_input_validation();
        $this->check_output_does_not_contain_prt_feedback('prt1');
        $this->check_output_contains_prt_feedback('prt2');
        $this->check_output_does_not_contain_prt_feedback('prt3');
        $this->check_output_does_not_contain_stray_placeholders();
        $this->check_current_output(
                new question_pattern_expectation('/Guess the roots/'),
                new question_no_pattern_expectation('/Your quess for the first root is/'),
                new question_pattern_expectation('/Then the second one/'),
                new question_pattern_expectation('/You have already given that root/'),
                new question_no_pattern_expectation('/Then the third one/'),
                new question_no_pattern_expectation('/Well done all the roots have been found/'),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_num_parts_correct(),
                $this->get_no_hint_visible_expectation()
        );
    }
}
