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
 * Stack question definition class.
 *
 * @package   qtype_stack
 * @copyright 2012 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/stack/input/factory.class.php');
require_once(__DIR__ . '/stack/cas/keyval.class.php');
require_once(__DIR__ . '/stack/cas/castext.class.php');
require_once(__DIR__ . '/stack/potentialresponsetree.class.php');
require_once($CFG->dirroot . '/question/behaviour/adaptivemultipart/behaviour.php');
require_once(__DIR__ . '/locallib.php');


/**
 * Represents a Stack question.
 *
 * @copyright 2012 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_stack_question extends question_graded_automatically_with_countback
        implements question_automatically_gradable_with_multiple_parts {

    /**
     * The name of the input that stores the state sequence number.
     */
    const SEQN_NAME = '_seqn';

    /**
     * @var string STACK specific: (state)variables, as defined by the teacher.
     */
    public $variabledefinitions;

    /**
     * @var string STACK specific: variables, as authored by the teacher.
     */
    public $questionvariables;

    /**
     * @var string STACK specific: variables, as authored by the teacher.
     */
    public $questionnote;

    /**
     * @var string Any specific feedback for this question. This is displayed
     * in the 'yellow' feedback area of the question. It can contain PRTfeedback
     * tags, but not IEfeedback.
     */
    public $specificfeedback;

    /** @var int one of the FORMAT_... constants */
    public $specificfeedbackformat;

    /** @var Feedback that is displayed for any PRT that returns a score of 1. */
    public $prtcorrect;

    /** @var int one of the FORMAT_... constants */
    public $prtcorrectformat;

    /** @var Feedback that is displayed for any PRT that returns a score between 0 and 1. */
    public $prtpartiallycorrect;

    /** @var int one of the FORMAT_... constants */
    public $prtpartiallycorrectformat;

    /** @var Feedback that is displayed for any PRT that returns a score of 0. */
    public $prtincorrect;

    /** @var int one of the FORMAT_... constants */
    public $prtincorrectformat;

    /** @var string if set, this is used to control the pseudo-random generation of the seed. */
    public $variantsselectionseed;

    /**
     * @var array STACK specific: string name as it appears in the question text => stack_input
     */
    public $inputs = array();

    /**
     * @var array stack_potentialresponse_tree STACK specific: respones tree number => ...
     */
    public $prts = array();

    /**
     * @var stack_options STACK specific: question-level options.
     */
    public $options;

    /**
     * @var array of seed values that have been deployed.
     */
    public $deployedseeds;

    /**
     * @var int STACK specific: seeds Maxima's random number generator.
     */
    public $seed = null;

    /**
     * @var array stack_cas_session STACK specific: session of variables.
     */
    protected $session = null;

    /**
     * @var array stack_cas_session STACK specific: session of variables.
     */
    protected $questionnoteinstantiated;

    /**
     * @var string instantiated version of questiontext.
     * Initialised in start_attempt / apply_attempt_state.
     */
    public $questiontextinstantiated = null;

    /**
     * @var string instantiated version of specificfeedback.
     * Initialised in start_attempt / apply_attempt_state.
     */
    public $specificfeedbackinstantiated;

    /**
     * @var string instantiated version of prtcorrect.
     * Initialised in start_attempt / apply_attempt_state.
     */
    public $prtcorrectinstantiated;

    /**
     * @var string instantiated version of prtpartiallycorrect.
     * Initialised in start_attempt / apply_attempt_state.
     */
    public $prtpartiallycorrectinstantiated;

    /**
     * @var string instantiated version of prtincorrect.
     * Initialised in start_attempt / apply_attempt_state.
     */
    public $prtincorrectinstantiated;

    /**
     * The next three fields cache the results of some expensive computations.
     * The chache is only vaid for a particular response, so we store the current
     * response, so that we can clearn the cached information in the result changes.
     * See {@link validate_cache()}.
     * @var array
     */
    protected $lastresponse = null;

    /**
     * @var bool like $lastresponse, but for the $acceptvalid argument to {@link validate_cache()}.
     */
    protected $lastacceptvalid = null;

    /**
     * @var array input name => stack_input_state.
     * This caches the results of validate_student_response for $lastresponse.
     */
    protected $inputstates = array();

    /**
     * @var array prt name => result of evaluate_response, if known.
     */
    protected $prtresults = array();

    /**
     * @var tracks the step number for state selection.
     */
    private $sequencenumber = 'unknown';

    /**
     * @var array containing information about the state variables.
     */
    private $statevariables = null;

    /**
     * @var question_attempt_step the step storing the initial values
     */
    private $initialstep = null;

    /**
     * @var boolean block the state writing if we know that this is a review of a previous step
     */
    private $stateconflict = true;

    /**
     * @var boolean requires question text rerendering. We could do this automatically, but as this should not be done when we are
     * processing a review request we leave this to the render.php that may actually be the more sensible place to identify if that
     * is the case.
     */
    private $renderrequired = false;

    /**
     * @var int the offest for state loading, used to define if we are loading the stored end state of this step or the entry state.
     * Primarilly only used in the context of rendering review views.
     */
    private $stateoffset = 0;

    /**
     * @var array a cache for PRT required variable listings.
     */
    private $prtrequired = array();


    /**
     * Make sure the cache is valid for the current response. If not, clear it.
     */
    protected function validate_cache($response, $acceptvalid = null) {
        if (is_null($this->lastresponse)) {
            // Nothing cached yet. No worries.
            $this->lastresponse = $response;
            $this->lastacceptvalid = $acceptvalid;
            return;
        }

        if ($this->lastresponse == $response && (
                $this->lastacceptvalid === null || $acceptvalid === null || $this->lastacceptvalid === $acceptvalid)) {
            if ($this->lastacceptvalid === null) {
                $this->lastacceptvalid = $acceptvalid;
            }
            return; // Cache is good.
        }

        // Clear the cache.
        $this->lastresponse = $response;
        $this->lastacceptvalid = $acceptvalid;
        $this->inputstates = array();
        $this->prtresults = array();
    }

    /**
     * @return bool do any of the inputs in this question require the student
     *      validat the input.
     */
    protected function any_inputs_require_validation() {
        foreach ($this->inputs as $name => $input) {
            if ($input->requires_validation()) {
                return true;
            }
        }
        return false;
    }

    public function make_behaviour(question_attempt $qa, $preferredbehaviour) {
        if (empty($this->inputs)) {
            return question_engine::make_behaviour('informationitem', $qa, $preferredbehaviour);
        }

        if (empty($this->prts)) {
            return question_engine::make_behaviour('manualgraded', $qa, $preferredbehaviour);
        }

        if ($preferredbehaviour == 'adaptive' || $preferredbehaviour == 'adaptivenopenalty') {
            return question_engine::make_behaviour('adaptivemultipart', $qa, $preferredbehaviour);
        }

        // State variable based questions cannot work with deferredfeedback as they tend to have inputs that are not show and can't
        // be filled and that behaviour requires that they are filled. So we force 'adaptive' to allow single state variable using
        // questions to exist in quizzes using those behaviours. In theory you could have a stateful question where all the inputs
        // are given on the first step but such would be an edge case.
        if (($preferredbehaviour == 'deferredfeedback' || $preferredbehaviour == 'deferredcbm') &&
                $this->has_writable_state_variables()) {
            return question_engine::make_behaviour('adaptivemultipart', $qa, 'adaptive');
        }

        if ($preferredbehaviour == 'deferredfeedback' && $this->any_inputs_require_validation()) {
            return question_engine::make_behaviour('dfexplicitvaildate', $qa, $preferredbehaviour);
        }

        if ($preferredbehaviour == 'deferredcbm' && $this->any_inputs_require_validation()) {
            return question_engine::make_behaviour('dfcbmexplicitvaildate', $qa, $preferredbehaviour);
        }

        return parent::make_behaviour($qa, $preferredbehaviour);
    }

    public function start_attempt(question_attempt_step $step, $variant) {
        $this->initialstep = $step;
        $this->stateconflict = false;

        // Work out the right seed to use.
        if (!is_null($this->seed)) {
            // Nasty hack, but if seed has already been set, then use that. This is
            // used by the questiontestrun.php script to allow non-deployed
            // variants to be browsed.
        } else if (!$this->has_random_variants()) {
            // Randomisation not used.
            $this->seed = 1;
        } else if (!empty($this->deployedseeds)) {
            // Question has a fixed number of variants.
            $this->seed = $this->deployedseeds[$variant - 1] + 0;
            // Don't know why this is coming out as a string. + 0 converts to int.
        } else {
            // This question uses completely free randomisation.
            $this->seed = $variant;
        }
        $step->set_qt_var('_seed', $this->seed);

        if ($this->has_writable_state_variables()) {
            $this->sequencenumber = 'initial';
        } else {
            $this->sequencenumber = 'ignored';
        }

        $this->initialise_question_from_seed();
    }

    /**
     * Once we know the random seed, we can initialise all the other parts of the question.
     */
    public function initialise_question_from_seed() {
        // We do not need to intialise twice.
        if ($this->session !== null) {
            return;
        }

        // Build up the question session out of all the bits that need to go into it.
        $session = null;
        // 0. question variable definitions.
        $variabledefs = new stack_cas_keyval($this->variabledefinitions, $this->options, $this->seed, 't');
        if ($this->has_state_variables()) {
            // Load identified state variables and all instance variables.
            if ($this->sequencenumber != 'unknown') {
                $this->load_state_variables();
            }

            // Inject them to the session.
            $session = new stack_cas_session($this->generate_state_load_commands(), $this->options, $this->seed);
            $session->merge_session($variabledefs->get_session());
        } else {
            $session = $variabledefs->get_session();
        }
        $sessionpreamblelength = count($session->get_session());

        // 1. question variables.
        $questionvars = new stack_cas_keyval($this->questionvariables, $this->options, $this->seed, 't');
        $session->merge_session($questionvars->get_session());

        // 2. correct answer for all inputs.
        $response = array();
        foreach ($this->inputs as $name => $input) {
            $cs = new stack_cas_casstring($input->get_teacher_answer());
            $cs->get_valid('t');
            $cs->set_key($name);
            $response[$name] = $cs;
        }
        $session->add_vars($response);
        $sessionlength = count($session->get_session());

        // 3. CAS bits inside the question text.
        $questiontext = $this->prepare_cas_text($this->questiontext, $session);

        // 4. CAS bits inside the specific feedback.
        $feedbacktext = $this->prepare_cas_text($this->specificfeedback, $session);

        // 5. CAS bits inside the question note.
        $notetext = $this->prepare_cas_text($this->questionnote, $session);

        // 6. The standard PRT feedback.
        $prtcorrect          = $this->prepare_cas_text($this->prtcorrect, $session);
        $prtpartiallycorrect = $this->prepare_cas_text($this->prtpartiallycorrect, $session);
        $prtincorrect        = $this->prepare_cas_text($this->prtincorrect, $session);

        // If state variables are present add the retrival command.
        if ($this->has_state_variables()) {
            // Actually stored in an variable named 'stackstatevars' but if we access it directly we would
            // need to add an special case elsewhere.
            $cs = new stack_cas_casstring('stack_state_full_state(false)');
            $cs->get_valid('t');
            $cs->set_key('stackstateexport');
            $session->add_vars(array($cs));
        }

        // Now instantiate the session.
        $session->instantiate();
        if ($session->get_errors()) {
            // We throw an exception here because any problems with the CAS code
            // up to this point should have been caught during validation when
            // the question was edited or deployed.
            throw new stack_exception('qtype_stack_question : CAS error when instantiating the session: ' .
                    $session->get_errors($this->user_can_edit()));
        }

        // If state variables are present store state changes.
        if ($this->has_writable_state_variables() || $this->sequencenumber == 'initial') {
            $this->store_state_variables($session->get_value_key('stackstateexport'), true);
        }

        // Finally, store only those values really needed for later.
        // Questiontext is only updated if it has not been initilaised already.
        if ($this->questiontextinstantiated === null) {
            $this->questiontextinstantiated        = $questiontext->get_display_castext();
        }
        $this->specificfeedbackinstantiated    = $feedbacktext->get_display_castext();
        $this->questionnoteinstantiated        = $notetext->get_display_castext();
        $this->prtcorrectinstantiated          = $prtcorrect->get_display_castext();
        $this->prtpartiallycorrectinstantiated = $prtpartiallycorrect->get_display_castext();
        $this->prtincorrectinstantiated        = $prtincorrect->get_display_castext();

        $session->prune_session($sessionlength - $sessionpreamblelength + 1, $sessionpreamblelength);
        $this->session = $session;

        // Allow inputs to update themselves based on the model answers.
        $this->adapt_inputs();
    }

    /**
     * Loads identified state variables from stores and other sources.
     */
    protected function load_state_variables() {
        global $USER, $DB;

        if (!$this->has_state_variables()) {
            return;
        }

        if ($this->sequencenumber == 'initial') {
            $this->sequencenumber = 0;
        }

        if (!array_key_exists('lock', $this->statevariables)) {
            $this->statevariables['lock'] = $this->sequencenumber;
        } else if ($this->sequencenumber < $this->statevariables['lock']){
            // We will need to forget stuff if we go back in time. So we do a regen from start.
            $this->statevariables = null;
            $this->has_state_variables();

            $this->statevariables['lock'] = $this->sequencenumber;
        } else if ($this->sequencenumber == $this->statevariables['lock']) {
            // If the state to be loaded is for the same state as already loaded we do not reload.This allows the state changes
            // within the question processing to propagate to the future actions during the processing i.e. from PRT to PRT.
            return;
        }

        // Always read the initial state, the cost of overwriting it with the real one is minimal.
        $vars = $this->initialstep->get_qt_data();
        foreach ($vars as $name => $value) {
            if (strpos($name, "_isv_") === 0) {
                $this->statevariables['instance'][substr($name, 5)] = $value;
            }
        }

        // Also the global state.
        if (array_key_exists('global', $this->statevariables) && count($this->statevariables['global']) > 0) {
            if (!array_key_exists('instance', $this->statevariables)) {
                $this->statevariables['instance'] = array();
            }
            list($insql, $inparams) = $DB->get_in_or_equal(array_keys($this->statevariables['global']));
            $params = array_merge(array($this->initialstep->get_user_id()), $inparams);
            $sql = "SELECT * FROM {qtype_stack_shared_state} WHERE userid = ? AND name $insql";
            $states = $DB->get_records_sql($sql, $params);
            foreach ($states as $state) {
                $this->statevariables['global'][$state->name] = $state->value;
            }
            // If this is the first load cycle then the global state has not been frozen yet and we need to store it.
            foreach ($this->statevariables['global'] as $key => $value) {
                if (!array_key_exists($key, $this->statevariables['instance'])) {
                    $this->statevariables['instance'][$key] = $value;
                }
            }
        }

        // Then go and get the state of the previous step(s).
        if ($this->sequencenumber > 0) {
            $params = array($this->initialstep->get_user_id(), $this->sequencenumber + $this->stateoffset,
                    $this->initialstep->get_id());
            // TODO: Write as a join so that we only pick the row with the highest sequencenumber for each name.
            $sql = "SELECT * FROM {qtype_stack_instance_state} WHERE userid = ? AND NOT sequencenumber > ? AND attemptstepid = ? " .
                    "ORDER BY sequencenumber";
            $states = $DB->get_records_sql($sql, $params);

            foreach ($states as $state) {
                $this->statevariables['instance'][$state->name] = $state->value;
            }
        }

        // Inject the user related details if needed.
        if (array_key_exists('user', $this->statevariables) && count($this->statevariables['user']) > 0) {
            $user = $USER;
            if ($user->id != $this->initialstep->get_user_id()) {
                // When reviewing a different users work we naturally want to see that users data as it was show to the user.
                $user = core_user::get_user($this->initialstep->get_user_id());
            }

            if (array_key_exists('id', $this->statevariables['user'])) {
                $this->statevariables['user']['id'] = $user->id;
            }
            if (array_key_exists('username', $this->statevariables['user'])) {
                $this->statevariables['user']['username'] = stack_utils::php_string_to_maxima_string($user->username);
            }
            if (array_key_exists('firstname', $this->statevariables['user'])) {
                $this->statevariables['user']['firstname'] = stack_utils::php_string_to_maxima_string($user->firstname);
            }
            if (array_key_exists('lastname', $this->statevariables['user'])) {
                $this->statevariables['user']['lastname'] = stack_utils::php_string_to_maxima_string($user->lastname);
            }
            if (array_key_exists('idnumber', $this->statevariables['user'])) {
                $this->statevariables['user']['idnumber'] = stack_utils::php_string_to_maxima_string($user->idnumber);
            }
        }

        // Inject the structure related details if needed.
        if ((array_key_exists('structure', $this->statevariables) && count($this->statevariables['structure']) > 0) ||
                array_key_exists('prt',$this->statevariables)) {
            if (array_key_exists('structure', $this->statevariables) &&
                array_key_exists('inputs', $this->statevariables['structure'])) {
                $this->statevariables['structure']['inputs'] = "[";
                $first = true;
                foreach ($this->inputs as $name => $input) {
                    if ($first) {
                        $first = false;
                    } else {
                        $this->statevariables['structure']['inputs'] .= ',';
                    }
                    $this->statevariables['structure']['inputs'] .= stack_utils::php_string_to_maxima_string($name);
                }
                $this->statevariables['structure']['inputs'] .= "]";
            }

            if (array_key_exists('prt',$this->statevariables) || array_key_exists('prts', $this->statevariables['structure'])) {
                if (!array_key_exists('structure', $this->statevariables)) {
                    $this->statevariables['structure'] = array();
                }

                $this->statevariables['structure']['prts'] = "[";
                $first = true;
                foreach ($this->prts as $name => $prt) {
                    if ($first) {
                        $first = false;
                    } else {
                        $this->statevariables['structure']['prts'] .= ',';
                    }
                    $this->statevariables['structure']['prts'] .= stack_utils::php_string_to_maxima_string($name);
                }
                $this->statevariables['structure']['prts'] .= "]";
            }

            if (array_key_exists('prt',$this->statevariables) ||
                array_key_exists('prt-inputs', $this->statevariables['structure'])) {
                if (!array_key_exists('structure', $this->statevariables)) {
                    $this->statevariables['structure'] = array();
                }

                $this->statevariables['structure']['prt-inputs'] = "[";
                $first = true;
                foreach ($this->prts as $name => $prt) {
                    if (!$first) {
                        $this->statevariables['structure']['prt-inputs'] .= ',';
                    }
                    if (!array_key_exists($name, $this->prtrequired)) {
                        $inputs = array_keys($this->inputs);
                        if ($this->has_writable_state_variables()) {
                            unset($inputs[self::SEQN_NAME]);
                        }
                        $this->prtrequired[$name] = $prt->get_required_variables($inputs);
                    }
                    $first = true;
                    $this->statevariables['structure']['prt-inputs'] .= "[";
                    foreach ($this->prtrequired[$name] as $key => $value) {
                        if (!$first) {
                            $this->statevariables['structure']['prt-inputs'] .= ',';
                        } else {
                            $first = false;
                        }
                        $this->statevariables['structure']['prt-inputs'] .= stack_utils::php_string_to_maxima_string($value);
                    }
                    $this->statevariables['structure']['prt-inputs'] .= "]";
                    $first = false;
                }
                $this->statevariables['structure']['prt-inputs'] .= "]";
            }
        }
    }

    /**
     * Generates the load commands for state variables for injection to a cassession.
     * @return an array of casstrings.
     */
    protected function generate_state_load_commands() {
        $loadcommands = array();
        if (!$this->has_state_variables()) {
            return $loadcommands;
        }
        $i = 0;
        foreach ($this->statevariables as $context => $vars) {
            if ($context != 'writes' && $context != 'lock' && $context != 'prt') {
                foreach ($vars as $name => $value) {
                    if ($name != '***active_step') {
                        $val = $value;
                        if ($value == '' || $value == NULL) {
                            $val = 'false';
                        }
                        $cs = new stack_cas_casstring("stack_state_load(\"$context\",\"$name\",$val)");
                        $cs->get_valid('t');
                        $cs->set_key("statevalueload$i");
                        $i++;
                        $loadcommands[] = $cs;
                    }
                }
            }
        }

        if (array_key_exists('prt', $this->statevariables)) {
            foreach ($this->prts as $index => $prt) {
                if (array_key_exists($prt->get_name(), $this->statevariables['prt'])) {
                    $cs = new stack_cas_casstring("stack_state_load_prt(" . stack_utils::php_string_to_maxima_string($prt->get_name()) . ")");
                    $cs->get_valid('t');
                    $cs->set_key("statevalueload$i");
                    $i++;
                    $loadcommands[] = $cs;
                }
            }
        }

        return $loadcommands;
    }

    /**
     * Re-renders the questiontext-used when the state changes. Or renders it based on previous state.
     * @param int state offset.
     */
    public function update_questiontext($offset = 0) {
        if (!$this->has_writable_state_variables()||(!$this->renderrequired&&$offset==0)) {
            // No need to do this.
            return;
        }
        $backupstate = null;
        $backupseqn = $this->sequencenumber;
        if ($offset != 0) {
            $backupstate = $this->statevariables;
            $this->statevariables = null;
            $this->sequencenumber = $this->sequencenumber + $offset;
            $this->load_state_variables();
        }

        $worksession = new stack_cas_session($this->generate_state_load_commands(), $this->options, $this->seed);

        $variabledefs = new stack_cas_keyval($this->variabledefinitions, $this->options, $this->seed, 't');
        $worksession->merge_session($variabledefs->get_session());

        $questionvars = new stack_cas_keyval($this->questionvariables, $this->options, $this->seed, 't');
        $worksession->merge_session($questionvars->get_session());

        $questiontext = $this->prepare_cas_text($this->questiontext, $worksession);

        // Now instantiate the session.
        $worksession->instantiate();
        if ($worksession->get_errors()) {
            // We throw an exception here because any problems with the CAS code
            // up to this point should have been caught during validation when
            // the question was edited or deployed.
            throw new stack_exception('qtype_stack_question : CAS error when instantiating the session: ' .
                    $worksession->get_errors($this->user_can_edit()));
        }

        $this->questiontextinstantiated = $questiontext->get_display_castext();

        if ($offset !== 0) {
            $this->statevariables = $backupstate;
            $this->sequencenumber = $backupseqn;
        }
    }

    /**
     * Extracts the values of statevariables from a value returned by maxima and stores them.
     * @param string from maxima
     * @param bool skip the automatic questiontext update, used when the questiontext is already being updated.
     */
    protected function store_state_variables($maximavalue,$skipupdatecquestiontext = false) {
        global $DB;

        // Parse the state returning from CAS. But first transfer the details that are not returned by CAS.
        $vars = array('writes' => $this->statevariables['writes']);
        if (array_key_exists('lock', $this->statevariables)) {
            $vars['lock'] = $this->statevariables['lock'];
        }
        if (array_key_exists('prt', $this->statevariables)) {
            $vars['prt'] = $this->statevariables['prt'];
        }
        $changes = array();
        if (strpos($maximavalue, "stackstatevar(") !== false) {
            $str = $maximavalue;
            $strings = stack_utils::all_substring_strings($str);
            foreach ($strings as $key => $string) {
                $str = str_replace('"'.$string.'"', '[STR:'.$key.']', $str);
            }
            $vl = stack_utils::list_to_array($str, false);
            foreach ($vl as $item) {
                $params = "[".substr($item, strlen("stackstatevar("), -1)."]";
                $params = stack_utils::list_to_array($params, false);
                foreach ($strings as $key => $string) {
                    foreach ($params as $ind => $param) {
                        if ($ind == 2) {
                            // String parameters need to stay strings in this case.
                            $params[$ind] = str_replace('[STR:'.$key.']', '"'.$string.'"', $param);
                        } else {
                            $params[$ind] = str_replace('[STR:'.$key.']', $string, $param);
                        }
                    }
                }

                $context = $params[0];
                $name = $params[1];
                $value = $params[2];
                $changed = ($params[3] == 'true');
                if (!array_key_exists($context, $vars)) {
                    $vars[$context] = array();
                    $changes[$context] = array();
                }
                $vars[$context][$name] = $value;
                if ($changed) {
                    $changes[$context][$name] = $changed;
                }
            }
        }

        // Switch the local state to the updated one.
        $this->statevariables = $vars;

        // We will not store these changes if this is not the step at the end of the sequence.
        if ($this->stateconflict) {
            return;
        }

        if (!$skipupdatecquestiontext && ((array_key_exists('global', $changes) && count($changes['global']) > 0) ||
                (array_key_exists('instance', $changes) && count($changes['instance']) > 0))) {
            $this->renderrequired = true;
        }

        // Changes to the global state.
        if (array_key_exists('global', $changes) && count($changes['global']) > 0) {
            list($insql, $inparams) = $DB->get_in_or_equal(array_keys($changes['global']));
            $params = array_merge(array($this->initialstep->get_user_id()), $inparams);
            $sql = "SELECT * FROM {qtype_stack_shared_state} WHERE userid = ? AND name $insql";
            $states = $DB->get_records_sql($sql, $params);
            foreach ($states as $state) {
                if ($vars['global'][$state->name] !== $state->value) {
                    $state->value = $vars['global'][$state->name];
                    $DB->update_record('qtype_stack_shared_state', $state);
                }
                unset($changes['global'][$state->name]);
            }
            // After all the known ones have been removed we can insert the remaining new ones.
            if (count($changes['global']) > 0) {
                $newrecords = array();
                foreach ($changes['global'] as $name => $value) {
                    $record = new stdClass();
                    $record->name = $name;
                    $record->value = $vars['global'][$name];
                    $record->userid = $this->initialstep->get_user_id();
                    $newrecords[] = $record;
                }
                $DB->insert_records('qtype_stack_shared_state', $newrecords);
            }
        }

        if (!array_key_exists('instance', $vars)) {
            $vars['instance'] = array();
        }
        if (!array_key_exists('instance', $changes)) {
            $changes['instance'] = array();
        }

        // Add a magical identifier. Tracks input steps.
        if (!($this->sequencenumber <= 0 || $this->sequencenumber == 'initial') &&
                !array_key_exists('***active_step', $vars['instance'])) {
            $vars['instance']['***active_step'] = 'true';
            $changes['instance']['***active_step'] = true;
            $this->sequencenumber = -$this->sequencenumber;
        }

        if (($this->sequencenumber == 'initial') && count($vars['instance']) > 0) {
            foreach ($vars['instance'] as $key => $val) {
                $this->initialstep->set_qt_var("_isv_$key", $val);
            }
        } else if (count($changes['instance']) > 0) {
            $sn = $this->sequencenumber;
            if ($sn < 0) {
                $sn = -$sn;
            }

            if (count($changes['instance']) > 0) {
                $newrecords = array();
                foreach ($changes['instance'] as $name => $value) {
                    $record = new stdClass();
                    $record->name = $name;
                    $record->value = $vars['instance'][$name];
                    $record->userid = $this->initialstep->get_user_id();
                    $record->sequencenumber = $sn;
                    $record->attemptstepid = $this->initialstep->get_id();
                    $newrecords[] = $record;
                }
                $DB->insert_records('qtype_stack_instance_state', $newrecords);
            }
        }
    }

    /**
     * Extracts the sequence number from the input if needed and initialises the system using it if not already initialised.
     * Basically, loads the state for that sequencenumber if the number is different from the current, also ensures that the
     * questiontext gets re-rendered for those values, even though it will probably be pointless. This function has to be called
     * everywhere we see the $response array as we cannot be certain about the order of processing with all the possible behaviours.
     * @param array the input values for the question
     */
    public function identify_sequence_number($response) {
        global $DB;

        $declared = 0; // 'initial'-step. Basically, the step with the 'unknown' value as we cannot receive something we have not
        // sent out.
        if (array_key_exists(self::SEQN_NAME, $response)) {
            $declared = $response[self::SEQN_NAME];
        }

        if ($this->sequencenumber != $declared && $this->has_writable_state_variables()) {
            $this->sequencenumber = $declared;
            $count = $DB->count_records('qtype_stack_instance_state', array('userid' => $this->initialstep->get_user_id(),
                    'attemptstepid' => $this->initialstep->get_id(), 'sequencenumber' => $this->sequencenumber,
                    'name' => '***active_step'));

            // Obviously, we are not going to write on top of a state already there. This must be a normal review or active
            // interference from the user side.
            $this->stateconflict = $count > 0;

            // Load the stored state for the declared value.
            $this->load_state_variables();

            // Update the question-text to match the entry state.
            $this->renderrequired = true;
            $this->update_questiontext();
            $this->renderrequired = false;
        }
    }

    /**
     * Helper method used by initialise_question_from_seed.
     * @param string $text a textual part of the question that is CAS text.
     * @param stack_cas_session $session the question's CAS session.
     * @return stack_cas_text the CAS text version of $text.
     */
    protected function prepare_cas_text($text, $session) {
        $castext = new stack_cas_text($text, $session, $this->seed, 't', false, 1);
        if ($castext->get_errors()) {
            throw new stack_exception('qtype_stack_question : Error part of the question: ' .
                    $castext->get_errors());
        }
        return $castext;
    }

    public function apply_attempt_state(question_attempt_step $step) {
        $this->seed = (int) $step->get_qt_var('_seed');
        $this->initialstep = $step;

        if ($this->has_writable_state_variables() && $this->sequencenumber == 'initial') {
            $this->stateconflict = false;
        }

        $this->initialise_question_from_seed();
    }

    /**
     * Give all the input elements a chance to configure themselves given the
     * teacher's model answers.
     */
    protected function adapt_inputs() {
        foreach ($this->inputs as $name => $input) {
            $teacheranswer = $this->session->get_value_key($name);
            $input->adapt_to_model_answer($teacheranswer);
        }
    }

    /**
     * Get the cattext for a hint, instantiated within the question's session.
     * @param question_hint $hint the hint.
     * @return stack_cas_text the castext.
     */
    public function get_hint_castext(question_hint $hint) {
        $hinttext = new stack_cas_text($hint->hint, $this->session, $this->seed, 't', false, 1);

        if ($hinttext->get_errors()) {
            throw new stack_exception('Error rendering the hint text: ' . $gftext->get_errors());
        }

        return $hinttext;
    }

    /**
     * Get the cattext for the general feedback, instantiated within the question's session.
     * @return stack_cas_text the castext.
     */
    public function get_generalfeedback_castext() {
        $gftext = new stack_cas_text($this->generalfeedback, $this->session, $this->seed, 't', false, 1);

        if ($gftext->get_errors()) {
            throw new stack_exception('Error rendering the general feedback text: ' . $gftext->get_errors());
        }

        return $gftext;
    }

    /**
     * We need to make sure the inputs are displayed in the order in which they
     * occur in the question text. This is not necessarily the order in which they
     * are listed in the array $this->inputs.
     */
    public function format_correct_response($qa) {
        $feedback = '';
        $inputs = stack_utils::extract_placeholders($this->questiontextinstantiated, 'input');
        foreach ($inputs as $name) {
            if ($name !== self::SEQN_NAME) {
                $input = $this->inputs[$name];
                $feedback .= html_writer::tag('p', $input->get_teacher_answer_display($this->session->get_value_key($name),
                        $this->session->get_display_key($name)));
            }
        }
        return stack_ouput_castext($feedback);
    }

    public function get_expected_data() {
        $expected = array();
        foreach ($this->inputs as $input) {
            $expected += $input->get_expected_data();
        }
        // Force additional type restriction for the state sequence parameter. So that we do not need to create a special hidden-
        // input-class that only accepts integers. In addition to the special hidden hidden-input-class we already created.
        if ($this->has_writable_state_variables()) {
            $expected[self::SEQN_NAME] = PARAM_INT;
        }
        return $expected;
    }

    public function get_question_summary() {
        if ('' !== $this->questionnoteinstantiated) {
            return $this->questionnoteinstantiated;
        }
        return parent::get_question_summary();
    }

    public function summarise_response(array $response) {
        $bits = array();
        foreach ($this->inputs as $name => $input) {
            if ($name != self::SEQN_NAME) {
                $state = $this->get_input_state($name, $response);
                if (stack_input::BLANK != $state->status) {
                    $bits[] = $name . ': ' . $input->contents_to_maxima($state->contents) . ' [' . $state->status . ']';
                }
            }
        }
        return implode('; ', $bits);
    }

    // Used in reporting - needs to return an array.
    public function summarise_response_data(array $response) {
        $bits = array();
        foreach ($this->inputs as $name => $input) {
            $state = $this->get_input_state($name, $response);
            $bits[$name] = $state->status;
        }
        return $bits;
    }

    public function get_correct_response() {
        $teacheranswer = array();
        foreach ($this->inputs as $name => $input) {
            $teacheranswer = array_merge($teacheranswer,
                    $input->maxima_to_response_array($this->session->get_value_key($name)));
        }

        return $teacheranswer;
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        foreach ($this->get_expected_data() as $name => $notused) {
            if (!question_utils::arrays_same_at_key_missing_is_blank(
                    $prevresponse, $newresponse, $name)) {
                return false;
            }
        }
        return true;
    }

    public function is_same_response_for_part($index, array $prevresponse, array $newresponse) {
        $previnput = $this->get_prt_input($index, $prevresponse, true);
        $newinput = $this->get_prt_input($index, $newresponse, true);

        return $this->is_same_prt_input($index, $previnput, $newinput);
    }

    /**
     * Get the results of validating one of the input elements.
     * @param string $name the name of one of the input elements.
     * @param array $response the response.
     * @return stack_input_state the result of calling validate_student_response() on the input.
     */
    public function get_input_state($name, $response) {
        $this->validate_cache($response, null);

        if (array_key_exists($name, $this->inputstates)) {
            return $this->inputstates[$name];
        }

        // The state sequence number is a special case. We automatically move it forward to ensure that the next submission has
        // a new sewuence number.
        if ($name == self::SEQN_NAME) {
            $sn = $this->sequencenumber;
            if ($sn == 'initial') {
                $sn = 1;
            } else if ($sn == 'unknown'){
                if (array_key_exists(self::SEQN_NAME, $response)) {
                    $sn = $response[self::SEQN_NAME];
                } else {
                    $sn = 0;
                }
                $sn = 1 + $sn;
            } else {
                $sn = 1 + $sn;
            }

            $this->inputstates[$name] = new stack_input_state(stack_input::SCORE, array($sn), $sn, $sn, '', '', '');
            return $this->inputstates[$name];
        }

        // The student's answer may not contain any of the variable names with which
        // the teacher has defined question variables.   Otherwise when it is evaluated
        // in a PRT, the student's answer will take these values.   If the teacher defines
        // 'ta' to be the answer, the student could type in 'ta'!  We forbid this.

        $forbiddenkeys = $this->session->get_all_keys();
        $teacheranswer = $this->session->get_value_key($name);
        if (array_key_exists($name, $this->inputs)) {
            $variabledefs = new stack_cas_keyval($this->variabledefinitions, $this->options, $this->seed, 't');
            $this->inputs[$name]->set_vardefsession($variabledefs->get_session());

            $this->inputstates[$name] = $this->inputs[$name]->validate_student_response(
                $response, $this->options, $teacheranswer, $forbiddenkeys);
            return $this->inputstates[$name];
        }
        return '';
    }

    /**
     * @param array $response the current response being processed.
     * @return boolean whether any of the inputs are blank.
     */
    public function is_any_input_blank(array $response) {
        $this->identify_sequence_number($response);
        foreach ($this->inputs as $name => $input) {
            if (stack_input::BLANK == $this->get_input_state($name, $response)->status) {
                return true;
            }
        }
        return false;
    }

    public function is_any_part_invalid(array $response) {
        $this->identify_sequence_number($response);
        // Invalid if any input is invalid, ...
        foreach ($this->inputs as $name => $input) {
            if (stack_input::INVALID == $this->get_input_state($name, $response)->status) {
                return true;
            }
        }

        // ... or any PRT gives an error.
        foreach ($this->prts as $index => $prt) {
            $result = $this->get_prt_result($index, $response, false);
            if ($result->errors) {
                return true;
            }
        }

        return false;
    }

    public function is_complete_response(array $response) {
        $this->identify_sequence_number($response);
        // If all PRTs are gradable, then the question is complete. (Optional inputs may be blank.)
        foreach ($this->prts as $index => $prt) {
            if (!$this->can_execute_prt($prt, $response, false)) {
                return false;
            }
        }

        // If there are no PRTs, then check that all inputs are complete.
        if (!$this->prts) {
            foreach ($this->inputs as $name => $notused) {
                if (stack_input::SCORE != $this->get_input_state($name, $response)->status) {
                    return false;
                }
            }
        }

        return true;
    }

    public function is_gradable_response(array $response) {
        $this->identify_sequence_number($response);
        // If any PRT is gradable, then we can grade the question.
        foreach ($this->prts as $index => $prt) {
            if ($this->can_execute_prt($prt, $response, true)) {
                return true;
            }
        }
        return false;
    }

    public function get_validation_error(array $response) {
        $this->identify_sequence_number($response);
        if ($this->is_any_part_invalid($response)) {
            // There will already be a more specific validation error displayed.
            return '';

        } else if ($this->is_any_input_blank($response)) {
            return stack_string('pleaseananswerallparts');

        } else {
            return stack_string('pleasecheckyourinputs');
        }
    }

    public function grade_response(array $response) {
        $this->identify_sequence_number($response);
        $fraction = 0;

        foreach ($this->prts as $index => $prt) {
            $results = $this->get_prt_result($index, $response, true);
            $fraction += $results->fraction;
        }
        return array($fraction, question_state::graded_state_for_fraction($fraction));
    }

    protected function is_same_prt_input($index, $prtinput1, $prtinput2) {
        foreach ($this->prts[$index]->get_required_variables(array_keys($this->inputs)) as $name) {
            if (!question_utils::arrays_same_at_key_missing_is_blank($prtinput1, $prtinput2, $name)) {
                return false;
            }
        }
        return true;
    }

    public function get_parts_and_weights() {
        $weights = array();
        foreach ($this->prts as $index => $prt) {
            $weights[$index] = $prt->get_value();
        }
        return $weights;
    }

    public function grade_parts_that_can_be_graded(array $response, array $lastgradedresponses, $finalsubmit) {
        $this->identify_sequence_number($response);
        $partresults = array();

        // At the moment, this method is not written as efficiently as it might
        // be in terms of caching. For now I will be happy it computes the right score.
        // Once we are confident enough, we can try to optimise.

        foreach ($this->prts as $index => $prt) {

            $results = $this->get_prt_result($index, $response, $finalsubmit);

            if ($results->valid === null) {
                continue;
            }

            if ($results->errors) {
                $partresults[$index] = new qbehaviour_adaptivemultipart_part_result($index, null, null, true);
                continue;
            }

            if (array_key_exists($index, $lastgradedresponses)) {
                $lastresponse = $lastgradedresponses[$index];
            } else {
                $lastresponse = array();
            }

            $lastinput = $this->get_prt_input($index, $lastresponse, $finalsubmit);
            $prtinput = $this->get_prt_input($index, $response, $finalsubmit);

            if ($this->is_same_prt_input($index, $lastinput, $prtinput)) {
                continue;
            }

            $partresults[$index] = new qbehaviour_adaptivemultipart_part_result(
                    $index, $results->score, $results->penalty);
        }

        return $partresults;
    }

    public function compute_final_grade($responses, $totaltries) {
        // This method is used by the interactive behaviour to compute the final
        // grade after all the tries are done.

        // At the moment, this method is not written as efficiently as it might
        // be in terms of caching. For now I am happy it computes the right score.
        // Once we are confident enough, we could try switching the nesting
        // of the loops to increase efficiency.

        // TODO: find if this breaks state and if we can rewrite this to work more efficiently.

        $fraction = 0;
        foreach ($this->prts as $index => $prt) {
            $accumulatedpenalty = 0;
            $lastinput = array();
            $penaltytoapply = null;
            $results = new stdClass();
            $results->fraction = 0;

            foreach ($responses as $response) {
                $prtinput = $this->get_prt_input($index, $response, true);

                if (!$this->is_same_prt_input($index, $lastinput, $prtinput)) {
                    $penaltytoapply = $accumulatedpenalty;
                    $lastinput = $prtinput;
                }

                if ($this->can_execute_prt($this->prts[$index], $response, true)) {
                    $results = $this->prts[$index]->evaluate_response($this->session,
                            $this->options, $prtinput, $this->seed);

                    $accumulatedpenalty += $results->fractionalpenalty;
                }
            }

            $fraction += max($results->fraction - $penaltytoapply, 0);
        }

        return $fraction;
    }

    /**
     * Do we have all the necessary inputs to execute one of the potential response trees?
     * @param stack_potentialresponse_tree $prt the tree in question.
     * @param array $response the response.
     * @param bool $acceptvalid if this is true, then we will grade things even
     *      if the corresponding inputs are only VALID, and not SCORE.
     * @return bool can this PRT be executed for that response.
     */
    protected function has_necessary_prt_inputs(stack_potentialresponse_tree $prt, $response, $acceptvalid) {
        $this->identify_sequence_number($response);

        if (!array_key_exists($prt->get_name(), $this->prtrequired)) {
            $inputs = array_keys($this->inputs);
            if ($this->has_writable_state_variables()) {
                unset($inputs[self::SEQN_NAME]);
            }
            $this->prtrequired[$prt->get_name()] = $prt->get_required_variables($inputs);
        }

        foreach ($this->prtrequired[$prt->get_name()] as $name) {
            $status = $this->get_input_state($name, $response)->status;
            if (!(stack_input::SCORE == $status || ($acceptvalid && stack_input::VALID == $status))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Do we have all the necessary inputs to execute one of the potential response trees?
     * @param stack_potentialresponse_tree $prt the tree in question.
     * @param array $response the response.
     * @param bool $acceptvalid if this is true, then we will grade things even
     *      if the corresponding inputs are only VALID, and not SCORE.
     * @return bool can this PRT be executed for that response.
     */
    protected function can_execute_prt(stack_potentialresponse_tree $prt, $response, $acceptvalid) {
        $this->identify_sequence_number($response);
        // The only way to find out is to actually try evaluating it. This calls
        // has_necessary_prt_inputs, and then does the computation, which ensures
        // there are no CAS errors.
        $result = $this->get_prt_result($prt->get_name(), $response, $acceptvalid);
        return null !== $result->valid && !$result->errors;
    }

    /**
     * Extract the input for a given PRT from a full response.
     * @param string $index the name of the PRT.
     * @param array $response the full response data.
     * @param bool $acceptvalid if this is true, then we will grade things even
     *      if the corresponding inputs are only VALID, and not SCORE.
     * @return array the input required by that PRT.
     */
    protected function get_prt_input($index, $response, $acceptvalid) {
        $prt = $this->prts[$index];

        if (!array_key_exists($index, $this->prtrequired)) {
            $inputs = array_keys($this->inputs);
            if ($this->has_writable_state_variables()) {
                unset($inputs[self::SEQN_NAME]);
            }
            $this->prtrequired[$index] = $prt->get_required_variables($inputs);
        }

        $prtinput = array();
        foreach ($this->prtrequired[$index] as $name) {
            $state = $this->get_input_state($name, $response);
            if (stack_input::SCORE == $state->status || ($acceptvalid && stack_input::VALID == $state->status)) {
                $prtinput[$name] = $state->contentsmodified;
            }
        }

        return $prtinput;
    }

    /**
     * Evaluate a PRT for a particular response.
     * @param string $index the index of the PRT to evaluate.
     * @param array $response the response to process.
     * @param bool $acceptvalid if this is true, then we will grade things even
     *      if the corresponding inputs are only VALID, and not SCORE.
     * @return stack_potentialresponse_tree_state the result from $prt->evaluate_response(),
     *      or a fake state object if the tree cannot be executed.
     */
    public function get_prt_result($index, $response, $acceptvalid) {
        $this->identify_sequence_number($response);
        $this->validate_cache($response, $acceptvalid);

        if (array_key_exists($index, $this->prtresults)) {
            return $this->prtresults[$index];
        }

        $prt = $this->prts[$index];

        if (!$this->has_necessary_prt_inputs($prt, $response, $acceptvalid)) {
            $this->prtresults[$index] = new stack_potentialresponse_tree_state(
                    $prt->get_value(), null, null, null);
            return $this->prtresults[$index];
        }

        $prtinput = $this->get_prt_input($index, $response, $acceptvalid);

        $variabledefs = new stack_cas_keyval($this->variabledefinitions, $this->options, $this->seed, 't');
        $variabledefs = $variabledefs->get_session();
        $variabledefs->merge_session($this->session);
        $session = $variabledefs;

        if ($this->has_state_variables()) {
            // Construct the statefull session for this.
            $loadsession = new stack_cas_session($this->generate_state_load_commands(), $this->options, $this->seed);
            if (array_key_exists('prt', $this->statevariables) && array_key_exists($prt->get_name(), $this->statevariables['prt'])) {
                // If we are using history features we need to update that history.
                $newdata = '[';
                $first = true;
                $strings = '';
                $maxima = '';
                foreach ($this->prtrequired[$prt->get_name()] as $name) {
                    if ($first) {
                        $first = false;
                    } else {
                        $strings .= ',';
                        $maxima .= ',';
                    }
                    $inputstate = $this->get_input_state($name, $response);
                    $val = $this->inputs[$name]->contents_to_maxima($inputstate->contents);
                    $strings .=  stack_utils::php_string_to_maxima_string($val);
                    $maxima .=  $val;
                }

                $newdata .= '[' . $maxima . '],[' . $strings . ']]';
                $prtname = stack_utils::php_string_to_maxima_string($prt->get_name());
                $cs = new stack_cas_casstring("stack_state_update_prt($prtname,$newdata)");
                $cs->get_valid('t');
                $cs->set_key("prthistoryvar");
                $loadsession->merge_session(new stack_cas_session(array($cs),$this->options, $this->seed));
            }
            $loadsession->merge_session($session);
            $session = $loadsession;
        }

        $this->prtresults[$index] = $prt->evaluate_response($session, $this->options, $prtinput, $this->seed);

        if ($this->has_writable_state_variables()) {
            $this->store_state_variables($this->prtresults[$index]->get_cas_context()->get_value_key('stackstateexport'));
        }

        return $this->prtresults[$index];
    }

    /**
     * Control the state to be used when working with historical data. Primarilly used for review.
     * @param the offset to set.
     */
    public function set_stateoffset($offset) {
        $this->stateoffset = $offset;
    }

    /**
     * For a possibly nested array, replace all the values with $newvalue.
     * @param array $array input array.
     * @param mixed $newvalue the new value to set.
     * @return modified array.
     */
    protected function set_value_in_nested_arrays($arrayorscalar, $newvalue) {
        if (!is_array($arrayorscalar)) {
            return $newvalue;
        }

        $newarray = array();
        foreach ($arrayorscalar as $key => $value) {
            $newarray[$key] = $this->set_value_in_nested_arrays($value, $newvalue);
        }
        return $newarray;
    }

    /**
     * Pollute the question's input state and PRT result caches so that each
     * input appears to contain the name of the input, and each PRT feedback
     * area displays "Feedback from PRT {name}". Naturally, this method should
     * only be used for special purposes, namely the tidyquestion.php script.
     */
    public function setup_fake_feedback_and_input_validation() {
        // Set the cached input stats as if the user types the input name into each box.
        foreach ($this->inputstates as $name => $inputstate) {
            $this->inputstates[$name] = new stack_input_state(
                    $inputstate->status, $this->set_value_in_nested_arrays($inputstate->contents, $name),
                    $inputstate->contentsmodified, $inputstate->contentsdisplayed, $inputstate->errors, $inputstate->note, '');
        }

        // Set the cached prt results as if the feedback for each PRT was
        // "Feedback from PRT {name}".
        foreach ($this->prtresults as $name => $prtresult) {
            $prtresult->_feedback = array();
            $prtresult->add_feedback(stack_string('feedbackfromprtx', $name));
        }
    }

    /**
     * @return bool whether this question uses randomisation.
     */
    public function has_random_variants() {
        return preg_match('~\brand~', $this->questionvariables);
    }

    /**
     * @return bool whether this question uses state variables
     */
    public function has_state_variables() {
        if ($this->statevariables == null) {
            $this->statevariables = array();
            $kv = new stack_cas_keyval($this->variabledefinitions, $this->options, $this->seed, 't');
            $this->statevariables = $kv->get_state_references($this->statevariables);
        }
        return count($this->statevariables) > 1; // The one is the meta variable countting write operations.
    }

    public function has_writable_state_variables() {
        if ($this->has_state_variables()) {
            return count($this->statevariables['writes']['instance']) > 0 || count($this->statevariables['writes']['global']) > 0;
        }
        return false;
    }

    public function get_num_variants() {
        if (!$this->has_random_variants()) {
            // This question does not use randomisation. Only declare one variant.
            return 1;
        }

        if (!empty($this->deployedseeds)) {
            // Fixed number of deployed versions, declare that.
            return count($this->deployedseeds);
        }

        // Random question without fixed variants. We will use the seed from Moodle raw.
        return 1000000;
    }

    public function get_variants_selection_seed() {
        if (!empty($this->variantsselectionseed)) {
            return $this->variantsselectionseed;
        } else {
            return parent::get_variants_selection_seed();
        }
    }

    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if ($component == 'qtype_stack' && $filearea == 'specificfeedback') {
            // Specific feedback files only visibile when the feedback is.
            return $options->feedback;

        } else if ($component == 'qtype_stack' && in_array($filearea,
                array('prtcorrect', 'prtpartiallycorrect', 'prtincorrect'))) {
            // This is a bit lax, but anything else is computationally very expensive.
            return $options->feedback;

        } else if ($component == 'qtype_stack' && in_array($filearea,
                array('prtnodefalsefeedback', 'prtnodetruefeedback'))) {
            // This is a bit lax, but anything else is computationally very expensive.
            return $options->feedback;

        } else if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);

        } else {
            return parent::check_file_access($qa, $options, $component, $filearea, $args, $forcedownload);
        }
    }

    public function get_context() {
        return context::instance_by_id($this->contextid);
    }

    protected function has_question_capability($type) {
        global $USER;
        $context = $this->get_context();
        return has_capability("moodle/question:{$type}all", $context) ||
                ($USER->id == $this->createdby && has_capability("moodle/question:{$type}mine", $context));
    }

    public function user_can_view() {
        return $this->has_question_capability('view');
    }

    public function user_can_edit() {
        return $this->has_question_capability('edit');
    }

    /* Get the values of all variables which have a key.  So, function definitions
     * and assignments are ignored by this method.  Used to display the values of
     * variables used in a question version.  Beware that some functions have side
     * effects in Maxima, e.g. orderless.  If you use these values you may not get
     * the same results as if you recreate the whole session from $this->questionvariables.
     */
    public function get_question_var_values() {
        $vars = array();
        foreach ($this->session->get_all_keys() as $key) {
            $vars[$key] = $this->session->get_value_key($key);
        }
        return $vars;
    }

    /**
     * Add all the question variables to a give CAS session. This can be used to
     * initialise that session, so expressions can be evaluated in the context of
     * the question variables.
     * @param stack_cas_session $session the CAS session to add the question variables to.
     */
    public function add_question_vars_to_session(stack_cas_session $session) {
        $session->merge_session($this->session);
    }

    /**
     * Enable the renderer to access the teacher's answer in the session.
     * @param vname varaiable name.
     */
    public function get_session_variable($vname) {
        return $this->session->get_value_key($vname);
    }

    public function classify_response(array $response) {
        $this->identify_sequence_number($response);
        $classification = array();

        foreach ($this->prts as $index => $prt) {
            if (!$this->can_execute_prt($prt, $response, true)) {
                foreach ($prt->get_nodes_summary() as $nodeid => $choices) {
                    $classification[$index . '-' . $nodeid] = question_classified_response::no_response();
                }
                continue;
            }

            $prtinput = $this->get_prt_input($index, $response, true);

            $results = $this->prts[$index]->evaluate_response($this->session,
                    $this->options, $prtinput, $this->seed);

            $answernotes = implode(' | ', $results->answernotes);

            foreach ($prt->get_nodes_summary() as $nodeid => $choices) {
                if (in_array($choices->truenote, $results->answernotes)) {
                    $classification[$index . '-' . $nodeid] = new question_classified_response(
                            $choices->truenote, $answernotes, $results->fraction);

                } else if (in_array($choices->falsenote, $results->answernotes)) {
                    $classification[$index . '-' . $nodeid] = new question_classified_response(
                            $choices->falsenote, $answernotes, $results->fraction);

                } else {
                    $classification[$index . '-' . $nodeid] = question_classified_response::no_response();
                }
            }

        }

        return $classification;
    }

    /**
     * Deploy a variant of this question.
     * @param int $seed the seed to deploy.
     */
    public function deploy_variant($seed) {
        $this->qtype->deploy_variant($this->id, $seed);
    }

    /**
     * Un-deploy a variant of this question.
     * @param int $seed the seed to un-deploy.
     */
    public function undeploy_variant($seed) {
        $this->qtype->undeploy_variant($this->id, $seed);
    }
}
