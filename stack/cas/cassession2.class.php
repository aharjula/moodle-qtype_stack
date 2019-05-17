<?php
// This file is part of Stack - http://stack.maths.ed.ac.uk/
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

defined('MOODLE_INTERNAL') || die();


// Note that is a complete rewrite of cassession, in this we generate 
// no "caching" in the form of keyval representations as we do not 
// necessarily return enough information from the CAS to do that, for 
// that matter neither did the old one...


// @copyright  2019 Aalto University.
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.

require_once(__DIR__ . '/casstring.class.new.php');
require_once(__DIR__ . '/connectorhelper.class.php');
require_once(__DIR__ . '/../options.class.php');
require_once(__DIR__ . '/../utils.class.php');
require_once(__DIR__ . '/evaluatable_object.interfaces.php');

class stack_cas_session2 {
  
	private $statements;

	private $instantiated;

	private $options;

	private $seed;

	private $errors;

    public function __construct(array $statements, stack_options $options = null, $seed = null) {

    	$this->instantiated = false;
    	$this->errors = array();
    	$this->statements = $statements;

    	foreach ($statements as $statement) {
    		if (!is_subclass_of($statement, 'cas_evaluatable')) {
    			throw new stack_exception('stack_cas_session: items in $statements must be cas_evaluatable.');
    		}
    	}

        if ($options === null) {
            $this->options = new stack_options();
        } else if (is_a($options, 'stack_options')) {
            $this->options = $options;
        } else {
            throw new stack_exception('stack_cas_session: $options must be stack_options.');
        }

        if (!($seed === null)) {
            if (is_int($seed)) {
                $this->seed = $seed;
            } else {
                throw new stack_exception('stack_cas_session: $seed must be a number.  Got "'.$seed.'"');
            }
        } else {
            $this->seed = time();
        }
    }

    public function add_statement(cas_evaluatable $statement) {
    	$this->statements[] = $statement;
    	$this->instantiated = false;
    }

    public function is_instantiated(): bool {
    	return $this->instantiated;
    }

    public function get_valid(): bool {
    	foreach ($this->statements as $statement) {
    		if ($statement->get_valid() === false) {
    			return false;
    		}
    	}
    	// There is nothing wrong with an empty session.
    	return true;
    }

    /**
     * Returns all the errors related to evaluation. Naturally only call after
     * instanttiation.
     */
    public function get_errors(): array {
    	return $this->errors;
    }

    /**
     * Executes this session and returns the values to the statements that 
     * request them.
     * Returns true if everything went well.
     */
    public function instantiate(): bool {
    	if (count($this->statements) === 0 || $this->instantiated === true) {
    		$this->instantiated = true;
    		return true;
    	}
    	if (!$this->get_valid()) {
    		throw new stack_exception('stack_cas_session: cannot instantiate invalid session');
    	}

    	// Lets simply build the expression here.
    	// NOTE that we do not even bother trying to protect the global scope
    	// as we have not seen anyone using the same CAS process twice, should 
    	// that become necessary much more would need to be done. But the parser 
    	// can handle that if need be.
    	$collectvalues = array();
    	$collectlatex = array();

    	foreach ($this->statements as $statement) {
    		if ($statement instanceof cas_value_extractor) {
    			$key = $statement->get_key();
    			if ($key !== '') {
                    $collectvalues[$key] = $key;    				
    			}
    		}
    		if ($statement instanceof cas_latex_extractor) {
				$key = $statement->get_key();
    			if ($key !== '') {
                    $collectlatex[$key] = $key;
    			}
    		}
    	}

    	// We will build the whole command here, note that the preamble should
    	// go to the library.
    	$command = '_EC(ec,sco,sta):=if is(ec=[]) then (_ERR:append(_ERR,[[error,sco,sta]]),false) else true$';
    	// No protection in the block.
    	$command .= 'block([],stack_randseed(' . $this->seed . ')';
    	// The options.
    	$command .=  $this->options->get_cas_commands()['csvars'];
    	// Some parts of logic storage:
    	$command .= ',_ERR:[],_RESPONSE:["stack_map"]';
    	$command .= ',_RAW_VALUES:["stack_map"]';
    	if (count($collectlatex) > 0) {
    		$command .= ',_LATEX_VALUES:["stack_map"]';
    	}

    	// Set some values:
    	$command .= '_RAW_VALUES:stackmap_set(_RAW_VALUES,"__stackmaximaversion",stackmaximaversion)';

    	// Evaluate statements.
    	foreach ($this->statement as $num => $statement) {
    		// Num here is an order number in the array, hopefully no-one pushes
    		// other keys here.
    		$line = ',_EC(' . $statement->get_evaluationform() . ',';
    		$line .= stack_utils::php_string_to_maxima_string($statement->get_source_context());
    		$line .= ',' . $num . ')';
    		$command .= $line;
    	}

    	// Collect values if required.
    	foreach ($collectvalues as $key) {
    		$command .= ',_RAW_VALUES:stackmap_set(_RAW_VALUES,';
    		$command .= stack_utils::php_string_to_maxima_string($key);
    		$command .= ',string(' . $key . '))';
    	}
    	foreach ($collectlatex as $key) {
    		$command .= ',_LATEX_VALUES:stackmap_set(_LATEX_VALUES,';
    		$command .= stack_utils::php_string_to_maxima_string($key);
    		$command .= ',tex1(' . $key . '))';
    	}

    	// Pack values to the response.
    	$command .= ',_RESPONSE:stackmap_set(_RESPONSE,"timeout",false)';
		$command .= ',_RESPONSE:stackmap_set(_RESPONSE,"values",_RAW_VALUES)';
    	if (count($collectlatex) > 0) {
    		$command .= ',_RESPONSE:stackmap_set(_RESPONSE,"presentation",_LATEX_VALUES)';
    	}
    	$command .= ',if length(_ERR)>0 then _RESPONSE:stackmap_set(_RESPONSE,"errors",_ERR)';

    	// Then output them.
    	$command .= ',print("STACK-OUTPUT-BEGINS>")';
    	$command .= ',print(stackjson_stringify(_RESPONSE))';
		$command .= ',print("<STACK-OUTPUT-ENDS")';
    	$command .= ')$';

    	// Send it to cas.
    	$connection = stack_connection_helper::make();
        $results = $connection->json_compute($command);

        // Lets collect what we got.
        $asts = array();
        $latex = array();
        $ersby_statement = array();

        if ($results['timeout'] === true) {
        	foreach ($this->statement as $num => $statement) {
        		$statement->set_cas_status(array("TIMEDOUT"));
        	}

        } else {
        	if (array_key_exists('values', $results)) {
        		foreach ($results['values'] as $key => $value) {
        			if (is_string($value)) {
        				$ast = maxima_parser_utils::parse($value);
        				$asts[$key] = $ast;
        			}
        		}
        	}
        	if (array_key_exists('presentation', $results)) {
        		foreach ($results['presentation'] as $key => $value) {
        			if (is_string($value)) {
      					$latex[$key] = $value;
        			}
        		}
        	}
        	if (array_key_exists('errors', $results)) {
        		$this->errors = $results['errors'];
        		foreach ($results['errors'] as $key => $value) {
        			// [0] the list of errors
        			// [1] the context information
        			// [2] the statement number
        			$ersby_statement[$value[2]] = $value[0];
        		}
        	}

        	// Then push those to the objects we are handling.
	    	foreach ($this->statement as $num => $statement) {
	    		$err = array();
	    		if (array_key_exists($num, $ersby_statement)) {
	    			$err = $ersby_statement[$num];
	    		}
	    		$statement->set_cas_status($err);
	        	if ($statement instanceof cas_value_extractor && $statement->get_key() !== '') {
	    			$statement->set_cas_evaluated_value($asts[$statement->get_key()]);
	    		}
	    		if ($statement instanceof cas_latex_extractor && $statement->get_key() !== '') {
	    			$statement->set_cas_latex_value($latex[$statement->get_key()]);
	    		}
	    	}
	    	$this->instantiated = true;
        }
    }
}
