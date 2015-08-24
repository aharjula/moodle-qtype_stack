# State-variables

This is a new feature added from STACK 4.  Primarily, it adds the state-variables and the interface for bringing data into questions
as well as exporting it from them. In addition to that it expands the question model with one extra field meant for code shared
between the question-variables, feedback-evaluation as well as the validation logic.

## Variable-definitions

Questions have a `state-variables` field.   This is primarily used for declaring the state-variables used in the question. Note, you
can define and use variables anywhere although you may only use variables that have been mentioned outside CAStext inside CAStext,
due to a major performance hit.

The `state-variables`  field has another important function. It can be used to define texput-rules that apply in the
(AJAX-)validation code and thus ensure consistent layout. In addition to that you may also use the field to declare various things
that need to apply in the whole question.  Declaring something constant will also apply in the validation.

For example, a teacher will not want values of question variables to be available during validation.  The question variables
defininitions include details of random generation needed for the question.

## The loading process of state-variables

When a question is loaded for use all of its key-val fields will be searched for references to state variables i.e. for
`stack_state_*-functions`, the variables identified by this process are then injected to the CAS-session so that those functions
will return the values of those variables inside the CAS. The loading process depends on the types of state-variables referenced:

1. `'user'`-variables are read-only details about the user like student-number or first-name and will only get injected to the
   CAS-session if requested.  
2. `'global'`-variables are variables that are tied to the current user but are shared between questions, these are ideal for
   progress tracking. Only those 'global' variables that are specially mentioned in the question get loaded and the value of them
   gets frozen for that instance of the question at the time of the questions instantiation, thus minimising the chaos caused by
   changes to those variables elsewhere during the time this instance is alive. Nevertheless you can always get the live value and
   change that if need be.
3. `'instance'`-variables are the main state-variables their scope is limited to this instance of the question for this user in this
   quiz (or other context). If the question uses even one of these all of these will be loaded (i.e. you can name these in a dynamic
   way and there is no need for static references).
4. Other scopes do exist but they are less obvious and we have not yet implemented any. Expect the `'system'` scope to tell you the
   time, the `'input'` scope to tell you the previous values of specific inputs, and the `'prt'` scope to tell you about attempts to
   specific PRTs.


## Use and modification of state-variables

When you want to access a state variable you will simply request for its value:

     value:stack_state_get("instance","example-var","value if undefined");

You may do that request anywhere with two exceptions:
 1. in CAStext you may only access variable that have been accessed in key-val field elsewhere. (performance issues, block our
    ability to identify references soley from CASText)
 2. it is unwise to act on state-variables in the question-variables -field, unless you really understand the processing of that
    field.

In addition to those exceptions there are a few recommendations:
 1. If you store the value of a state-variable into a local-variable do not expect that value to be correct if you reference the
    local-variable in another key-val field. It is simpler to just always use the full `stack_state_get()`-function call to
    reference state-variables. The local-variable is naturally handy when you need to keep the code short and readable.
 2. The default-value is a mandatory parameter for the get function and you should make sure that it is the same value in every call
    for that same variable.

The values of the variables can be anything that the CAS can push through `grind()` and then regenerate. Typically, you'll use
strings, booleans, lists, sets, integers and matrices.

Setting state-variable values can be done with one of these:

   dumvarA:stack_state_set("instance","example-var",7+x);
   dumvarB:stack_state_increment_once("progress-counter");
   dumvarC:stack_state_decrement_once("progress-counter");

The `stack_state_set()` - function works for both 'instance' and 'global'-variables. The increment/decrement functions work with
'global'-variables and create a lock-variable in the 'instance'-scope making sure that the value only gets changed once in this
question-instance.


## Basic state-machine building

In STACK-questions the potential response trees are the primary ways of generating feedback. They are also the primary way of tying
actions to the input fields. Basically, a PRT will only activate if all the input-fields it references have been filled and once it
does one can execute arbitrary code in feedback-variables -field. That field is the place where most of the logic of the
state-machine will live i.e. it is the place where the state-transitions happen.

The visible part of the state-machine is the display of the states and it happens in various CAStext-fields using
[blocks](Question_blocks.md). Typically you have some state-variable defining the 'scene' of the story of the question and in the
CAStext you conditionally only show the content related to that 'scene'.

A few recommendations:
 1. Only write state-variables during 'scene'-transitions.
 2. Avoid using the same PRT in multiple 'scenes'. Same applies to the input-fields.
 3. Loops in the story-board, i.e. returning to the same 'scene' is a bad idea.
 4. Note the grading issues of non-visited 'scenes', a way to handle this is to have an input-field in the first 'scene' and a PRT
    that handles grading that will only activate in exit-scenes. Other PRTs would not give grades in this system.


## Limitations on variable names

Due to the way the variables are stored the length of the variable name is limited in the following ways:

 1. A global-scope variables name should not be over 21-chars, (64 in special cases)
 2. An instance-scope variables name should not be over 21-chars
 3. If you intend to use the increment/decrement functions with global or instance scope variables they cannot start with the
    strings "[il]:" or "[dl]:". If you do not use those functions you have five extra chars to use for the var-name
 4. Otherwise you are free to use everything in ASCII-space that you can push to a Maxima string.

To keep your life simple just limit the variable names to 18-chars as that limit applies to various other STACK-fields.


# List of named system-information

Through the `stack_state_get()`-function one can also read (never write) things from the following scopes:

* `'user'`-scope
 * `'id'` = the database id-number of the user as an integer, could be useful as a seed
 * `'firstanme'`, `'lastname'` = strings
 * `'idnumber'` = a string with the student-id fields value
 * `'username'` = a string that could be used as a seed or passed to external tools
