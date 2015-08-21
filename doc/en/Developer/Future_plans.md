# Future plans

The following features are in approximate priority order.  How to report bugs and make suggestions is described on the [community](../About/Community.md) page.

## Features to add ##

### Inputs ###

* Add new input types
 1. Dropdown/Multiple choice input type.
 2. Dragmath (actually, probably use javascript from NUMBAS instead here).
 3. Sliders.
 4. Geogebra input.
* Reasoning by equivalence input type.
* Inputs which enable student to input steps in the working. In particular, variable numbers of input boxes.
* Add a "scratch working" area in which students can record their thinking etc. alongside the final answer.
* Add support for coordinates, so students can type in (x,y).  This should be converted internally to a list.
* It is very useful to be able to embed input elements in equations, and this was working in STACK 2.0. However is it possible with MathJax or other Moodle maths filters?
* Modify the text area input so that each line is validated separately.

### Improve the editing form ###

* A button to remove a given PRT or input, without having to guess that the way to do it is to delete the placeholders from the question text.
* A button to add a new PRT or input, without having to guess that the way to do it is to add the placeholders to the question text.
* A button to save the current definition and continue editing. This would be a Moodle core change. See https://tracker.moodle.org/browse/MDL-33653.
* Improve the way questions are deployed.
* You cannot use one PRT node to guard the evaluation of another, for example Node 1 check x = 0, and only if that is false, Node 2 do 1 / x. We need to change how PRTs do CAS evaluation.
* When validating the editing form, also evaluate the Maxima code in the PRTs, using the teacher's model answers.
 1. Auto deploy.  E.g. if the first variable in the question variables is a single a:rand(n), then loop a=0..(n-1).
 2. Remove many versions at once.

### Other ideas ###

* Multi-lingual support for questions.  See [languages](Languages.md).  
* Implement "CommaError" checking for CAS strings.  Make comma an option for the decimal separator.
* Decimal separator, both input and output.
* Implement "BracketError" option for inputs.  This allows the student's answer to have only those types of parentheses which occur in the teacher's answer.  Types are `(`,`[` and `{`.  So, if a teacher's answer doesn't have any `{` then a student's answer with any `{` or `}` will be invalid.
* Enable individual questions to load Maxima libraries.
* It would be very useful to have finer control over the validation feedback. For example, if we have a polynomial with answer boxes for the coefficients, then we should be able to echo back "Your last answer was..." with the whole polynomial, not just the numbers.
* Better options for automatically generated plots.  (Aalto use of tikzpicture?)  (Draw package?)
* Make the mark and penalty fields accept arbitrary maxima statements.
* Check CAS/maxima literature on -inf=minf.
* Facility to import test-cases in-bulk as CSV (or something). Likewise export.
* Refactor answer tests.
 1. They should be like inputs. We should return an answer test object, not a controller object.
 2. at->get_at_mark() really ought to be at->matches(), since that is how it is used.

## Features that might be attempted in the future - possible self contained projects ##

* Investigate how a whole PRT might make only one CAS call.
* Provide an alternative way to edit PRTs in a form of computer code, rather than lots of form fields. For example using http://zaach.github.com/jison/ or https://github.com/hafriedlander/php-peg. 
* Read other file formats into STACK.  In particular
  * AIM
  * WebWork, including the Open Problem Library:  http://webwork.maa.org/wiki/Open_Problem_Library
  * MapleTA
* Possible Maxima packages:
 * Better support for rational expressions, in particular really firm up the PartFrac and SingleFrac functions with better support.
 * Support for inequalities.  This includes real intervals and sets of real numbers.
 * Support for the "draw" package.
* Add support for qtype_stack in Moodle's lesson module.

