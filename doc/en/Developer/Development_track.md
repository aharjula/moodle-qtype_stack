# Development track for STACK 4.0 (alpha)

This page describes the major tasks we still need to complete in order to be able to release the next version: STACK 4.0. Plans looking further into the future are described  on [Future plans](Future_plans.md).
The past development history is documented on [Development history](Development_history.md).

How to report bugs and make suggestions is described on the [community](../About/Community.md) page.

## Version 4.0

This version of STACK contains a number of major new features.
1. Scientific [units](../Authoring/Units.md).
2. [Question blocks](../Authoring/Question_blocks.md).
3. [State variables](../Authoring/State.md).

## STACK State

* Introduce a variable so the maxima code "knows the attempt number". This should be through the state variables.  How to count "attempts" of different prts and inputs needs to be decided.  [Note to self: check how this changes reporting.]
* Unit tests of questions which make use of state.
* State storage does it need a new behaviour, now we write to read only things...
* What do we want to expose through the system-state? Number of attempts, previous inputs?

## Scientific units

* *done* Introduce a scientific units package.
* Unite tests of a question which makes use of scientific units.
* Provide more robust support for decimal places (see below)

## Ephemeral forms for numbers.

Currently there are problems with the NumSigfigs tests and the other numerical tests.  

This is due to the fact that the NumSigFigs answer test code uses maxima's `floor()` function, which gives `floor(0.1667*10^4)` as `1666` not `1667` as expected.

To avoid this problem we need an "ephemeral form" for representing numbers at
a syntactic level.   This test probably needs to operate at the PHP level on
strings, rather then through Maxima.  

## Other features ##

 * Button as an input type, activates a PRT and forgets itself after that. i.e. that input value is not stored or remembered
 * Hidden input-field, something for use when integrating applets and stuff. Basically HTML-hidden-field that has an id or unique class that can be given through some means through the castext to scripts to use. Probably {#stack_state_get("input","fieldX__identifier","Null")#}
 * *done* Support (documentation only) for [JSXGraph](../Installation/JSXGraph.md) which replaces support for GeoGebra.
 * Maxima to other languages converter with JSXGraph/JessieCode as the prototype, Matlab and Mathematica as the next step. stack_jessie(%e^(-5*x)*%pi) => "EULER^(-5*x)*PI"
 
## STACK custom reports

Basic reports now work.

* *done* Add titles and explanations to the page, and document with examples.
* Really ensure "attempts" list those with meaningful histories.  I.e. if possible filter out navigation to and from the page etc.
* Add better maxima support functions for off-line analysis.

## Parallel development

As of Aug 2015, there are prallel developments to implement the following.

* Reasoning by equivalence input type.  
* Inputs which enable student to input steps in the working. In particular, variable numbers of input boxes.
* Add a "scratch working" area in which students can record their thinking etc. alongside the final answer.
* Modify the text area input so that each line is validated separately.

We anticipate these changes will merge into STACK 4.0 at some near future point.
