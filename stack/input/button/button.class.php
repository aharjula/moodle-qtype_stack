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
 * A basic button input. Primarily, for use with the state-system.
 *
 * @copyright  2016 Aalto University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stack_button_input extends stack_input {

    public function render(stack_input_state $state, $fieldname, $readonly) {
        $attributes = array(
            'type'  => 'button',
            'name'  => $fieldname . '_button',
            'id'    => $fieldname . '_button',
        );

        // If the teachersanswer is a string we unwrap it before using it as the text.
        if (strpos($this->teacheranswer, '"') === 0) {
            $attributes['value'] = stack_utils::maxima_string_to_php_string($this->teacheranswer);
        } else {
            $attributes['value'] = $this->teacheranswer;
        }

        if ($readonly) {
            $attributes['readonly'] = 'readonly';
        }


        // Due to the way the check action is being identified we can't just use a sybmit button we need to actually fire the
        // submission using the relevant submit button. And for this reason we need to run some scripts on the browser side.
        $fulltext = html_writer::empty_tag('input', $attributes);

        $attributes = array(
            'type'  => 'hidden',
            'name'  => $fieldname,
            'id'    => $fieldname,
            'value' => 'false',
        );

        if ($readonly) {
            $attributes['readonly'] = 'readonly';
        }

        $fulltext .= html_writer::empty_tag('input', $attributes);

        return $fulltext;
    }

    public function add_to_moodleform_testinput(MoodleQuickForm $mform) {
        $mform->addElement('text', $this->name, $this->name, array('size' => $this->parameters['boxWidth']));
        $mform->setDefault($this->name, $this->parameters['syntaxHint']);
        $mform->setType($this->name, PARAM_RAW);
    }

    public function adapt_to_model_answer($teacheranswer) {
        $this->teacheranswer = $teacheranswer;
    }

    public function requires_validation() {
        return false;
    }

    /**
     * Return the default values for the parameters.
     * @return array parameters` => default value.
     */
    public static function get_parameters_defaults() {
        return array(
            'mustVerify'     => false,
            'showValidation' => 0,
            'strictSyntax'   => false,
            'insertStars'    => 0,
            'syntaxHint'     => '',
            'forbidWords'    => '',
            'allowWords'     => '',
            'forbidFloats'   => true,
            'lowestTerms'    => true,
            'sameType'       => true);
    }

    /**
     * Each actual extension of this base class must decide what parameter values are valid
     * @return array of parameters names.
     */
    public function internal_validate_parameter($parameter, $value) {
        $valid = true;
        switch($parameter) {
            case 'showValidation':
                $valid = is_int($value) && $value == 0;
                break;
            case 'showValidation':
                $valid = is_bool($value) && $value == false;
                break;
        }
        return $valid;
    }

    /**
     * @return string the teacher's answer, displayed to the student in the general feedback.
     */
    public function get_teacher_answer_display($value, $display) {
        // Hidden inputs do not display such things.
        return '';
    }


    protected function response_to_contents($response) {
        $contents = array();
        if (array_key_exists($this->name, $response)) {
            if ($response[$this->name] == 'true') {
                $contents = array('true');
            }
        }

        return $contents;
    }

}
