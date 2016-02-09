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
 * "key=value" class to parse user-entered data into CAS sessions.
 *
 * @copyright  2012 University of Birmingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stack_cas_keyval {

    /** @var Holds the raw text as entered by a question author. */
    private $raw;

    /** @var stack_cas_session */
    private $session;

    /** @var bool */
    private $valid;

    /** @var bool has this been sent to the CAS yet? */
    private $instantiated;

    /** @var string HTML error message that can be displayed to the user. */
    private $errors;

    /** @var string 's' or 't' for student or teacher security level. */
    private $security;

    /** @var bool whether to insert *s where there are implied multipliations. */
    private $insertstars;

    /** @var bool if true, apply strict syntax checks. */
    private $syntax;

    public function __construct($raw, $options = null, $seed=null, $security='s', $syntax=true, $insertstars=0) {
        $this->raw          = $raw;
        $this->security     = $security;
        $this->syntax       = $syntax;
        $this->insertstars  = $insertstars;

        $this->session      = new stack_cas_session(null, $options, $seed);

        if (!is_string($raw)) {
            throw new stack_exception('stack_cas_keyval: raw must be a string.');
        }

        if (!('s' === $security || 't' === $security)) {
            throw new stack_exception('stack_cas_keyval: 2nd argument, security level, must be "s" or "t" only.');
        }

        if (!is_bool($syntax)) {
            throw new stack_exception('stack_cas_keyval: 5th argument, syntax, must be boolean.');
        }

        if (!is_int($insertstars)) {
            throw new stack_exception('stack_cas_keyval: 6th argument, stars, must be an integer.');
        }
    }

    private function validate() {
        if (empty($this->raw) or '' == trim($this->raw)) {
            $this->valid = true;
            return true;
        }

        // Subtle one: must protect things inside strings before we explode.
        $str = $this->raw;
        $strings = stack_utils::all_substring_strings($str);
        foreach ($strings as $key => $string) {
            $str = str_replace('"'.$string.'"', '[STR:'.$key.']', $str);
        }

        // CAS keyval may not contain @ or $. but strings sure can
        if (strpos($str, '@') !== false || strpos($str, '$') !== false) {
            $this->errors = stack_string('illegalcaschars');
            $this->valid = false;
            return false;
        }

        $str = str_replace("\n", ';', $str);
        $str = stack_utils::remove_comments($str);
        $str = str_replace(';', "\n", $str);

        $kvarray = explode("\n", $str);
        foreach ($strings as $key => $string) {
            foreach ($kvarray as $kkey => $kstr) {
                $kvarray[$kkey] = str_replace('[STR:'.$key.']', '"'.$string.'"', $kstr);
            }
        }

        // 23/4/12 - significant changes to the way keyvals are interpreted.  Use Maxima assignmentsm i.e. x:2.
        $errors  = '';
        $valid   = true;
        $vars = array();
        foreach ($kvarray as $kvs) {
            $kvs = trim($kvs);
            if ('' != $kvs) {
                $cs = new stack_cas_casstring($kvs);
                $cs->get_valid($this->security, $this->syntax, $this->insertstars);
                $vars[] = $cs;
            }
        }

        $this->session->add_vars($vars);
        $this->valid       = $this->session->get_valid();
        $this->errors      = $this->session->get_errors();
    }

    public function get_valid() {
        if (null === $this->valid) {
            $this->validate();
        }
        return $this->valid;
    }

    public function get_errors($casdebug=false) {
        if (null === $this->valid) {
            $this->validate();
        }
        if ($casdebug) {
            return $this->errors.$this->session->get_debuginfo();
        }
        return $this->errors;
    }

    public function instantiate() {
        if (null === $this->valid) {
            $this->validate();
        }
        if (!$this->valid) {
            return false;
        }
        $this->session->instantiate();
        $this->instantiated = true;
    }

    public function get_session() {
        if (null === $this->valid) {
            $this->validate();
        }
        return $this->session;
    }

    public function get_state_references($references = array()) {
        if (!array_key_exists('writes', $references)) {
            $references['writes'] = array('instance' => array(), 'global' => array());
        }
        if (strpos($this->raw, "stack_state_") !== false) {
            if (null === $this->valid) {
                $this->validate();
            }

            foreach ($this->session->get_session() as $cs) {
                if (strpos($cs->get_raw_casstring(), "stack_state") !== false) {
                    $str = $cs->get_raw_casstring();
                    $strings = stack_utils::all_substring_strings($str);
                    foreach ($strings as $key => $string) {
                        $str = str_replace('"'.$string.'"', '[STR:'.$key.']', $str);
                    }
                    $i = strpos($str, 'stack_state_');
                    while ($i !== false) {
                        $opening = -1;
                        $closing = $i + 14;
                        $count = 0;
                        $in = false;
                        while ($closing < strlen($str) - 1) {
                            $closing++;
                            $c = $str[$closing];
                            if ($c == '(') {
                                $count++;
                                if (!$in) {
                                    $opening = $closing;
                                }
                                $in = true;
                            }else if ($c == ')') {
                                $count--;
                                if ($count == 0 && $in) {
                                    break;
                                }
                            }
                        }
                        $fnc = substr($str, $i, $opening - $i);
                        $params = substr($str, $opening, $closing - $opening + 1);
                        $params = stack_utils::list_to_array($params, false);
                        foreach ($strings as $key => $string) {
                            foreach ($params as $ind => $param) {
                                if ($ind == 2 && strpos($param, '[STR:'.$key.']')) {
                                    // String parameters need to stay strings in this case
                                    $params[$ind] = str_replace('[STR:'.$key.']', '"'.$string.'"', $param);
                                } else {
                                    $params[$ind] = str_replace('[STR:'.$key.']', $string, $param);
                                }
                            }
                        }

                        $context = false;
                        $name = false;
                        $value = false;
                        if ($fnc == 'stack_state_declare') {
                            $access = $params[0];
                            $context = $params[1];
                            $name = $params[2];
                            $value = $params[3];
                            if (strpos(strtolower($access), 'w') !== false) {
                                if (!array_key_exists($context, $references['writes'])) {
                                    $references['writes'][$context] = array();
                                }
                                $references['writes'][$context][$name] = true;
                            }
                            if (count($params) != 4){
                                if (!array_key_exists('errors', $references)) {
                                    $references['errors'] = array();
                                }
                                $references['errors'][] = stack_string('functionwithwrongnumberofparameters',
                                        array('function' => $fnc, 'parameters' => implode(',', $params), 'correct' => 4));
                            }
                        } else if ($fnc == 'stack_state_get') {
                            $context = $params[0];
                            $name = $params[1];
                            if (count($params) != 2){
                                if (!array_key_exists('errors', $references)) {
                                    $references['errors'] = array();
                                }
                                $references['errors'][] = stack_string('functionwithwrongnumberofparameters',
                                        array('function' => $fnc, 'parameters' => implode(',', $params), 'correct' => 2));
                            }
                        } else if ($fnc == 'stack_state_set') {
                            $context = $params[0];
                            $name = $params[1];
                            $value = $params[2];
                            if (!array_key_exists($context, $references['writes'])) {
                                $references['writes'][$context] = array();
                            }
                            $references['writes'][$context][$name] = true;
                            if (count($params) != 3){
                                if (!array_key_exists('errors', $references)) {
                                    $references['errors'] = array();
                                }
                                $references['errors'][] = stack_string('functionwithwrongnumberofparameters',
                                        array('function' => $fnc, 'parameters' => implode(',', $params), 'correct' => 3));
                            }
                        } else if ($fnc == 'stack_state_increment_once' || $fnc == 'stack_state_decrement_once') {
                            $context = 'global';
                            $name = $params[0];
                            $value = 0;
                            $references['writes']['instance'][$name] = true;
                            if ($fnc == 'stack_state_increment_once') {
                                $references['writes']['instance']['[il]:' . $name] = true;
                            } else if ($fnc == 'stack_state_decrement_once') {
                                $references['writes']['instance']['[dl]:' . $name] = true;
                            }
                            $references['writes']['global'][$name] = true;
                            if (count($params) != 1){
                                if (!array_key_exists('errors', $references)) {
                                    $references['errors'] = array();
                                }
                                $references['errors'][] = stack_string('functionwithwrongnumberofparameters',
                                        array('function' => $fnc, 'parameters' => implode(',', $params), 'correct' => 1));
                            }
                        } else if ($fnc == 'stack_state_full_state') {
                            // A special function somoone might use to debug things.
                            if (count($params) != 1){
                                if (!array_key_exists('errors', $references)) {
                                    $references['errors'] = array();
                                }
                                $references['errors'][] = stack_string('functionwithwrongnumberofparameters',
                                        array('function' => $fnc, 'parameters' => implode(',', $params), 'correct' => 1));
                            }
                            $i = strpos($str, 'stack_state_', $i+1);
                            continue;
                        }

                        if (!array_key_exists($context, $references)) {
                            $references[$context] = array();
                        }
                        if (!array_key_exists($name, $references[$context])) {
                            $references[$context][$name] = $value;
                        }

                        $i = strpos($str, 'stack_state_', $i+1);
                    }
                }
            }
        }
        foreach ($references['writes'] as $context => $something) {
            if(!($context == 'global' || $context == 'instance')) {
                if (!array_key_exists('errors', $references)) {
                    $references['errors'] = array();
                }
                $references['errors'][] = stack_string('statevariablescopeaccesserror', array('context' => $context));
            }
        }

        return $references;
    }
}
