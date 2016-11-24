# STACK - the stateful variant

This branch of the code has been frozen and is being provided as reference for a publication in [MSOR Connections](https://journals.gre.ac.uk/index.php/msor/index) as a part of the proceedings of [EAMS 2016](http://eams.ncl.ac.uk/) at the time of the freeze Abacus branch was still separate from master branch and this code was still being used to test some ideas. However at the time of freeze it was already quite obvious that the features presented by this proof of concept code were useful but would be significantly simpler to use if major changes to the question model were to be done, thus this proof of concept branch was not expected to develop further.

This branch contains the experimental question model expansion that includes state-variables. Check the
[doc/en/Authoring/State.md](doc/en/Authoring/State.md) to start working with it.

The version of STACK this is based is the so called [Abacus branch](https://github.com/maths/moodle-qtype_stack/tree/abacus) which extends the [master branch](https://github.com/maths/moodle-qtype_stack/tree/master) of STACK with conditional rendering and other additions to the output layer. The conditional rendering with changing state make this stateful extension able to change the part of the question presented to the user.

[STACK](doc/en/About/index.md)
is an open-source system for computer-aided assessment in Mathematics and related
disciplines, with emphasis on formative assessment.

STACK was created by Chris Sangwin of Loughborough University, and includes the work of
[other contributors](doc/en/About/Credits.md). State variables, blocks and scientific units were added by Matti Harjula

## Current state of development

This version contains some major changes from the previous versions, notably the question blocks from Aalto Finland.
STACK continues to be used at Loughborough University, the Open University and the University of Birmingham.

Please continue to report any bugs you find at https://github.com/maths/moodle-qtype_stack/issues.

The [current state of development](https://github.com/maths/moodle-qtype_stack/blob/master/doc/en/Developer/Development_track.md) is explain more fully in the [developer documentation](https://github.com/maths/moodle-qtype_stack/blob/master/doc/en/Developer/index.md).


## Documentation

The [documentation is here](https://github.com/maths/moodle-qtype_stack/blob/master/doc/en/index.md), including the [installation instructions](https://github.com/maths/moodle-qtype_stack/blob/master/doc/en/Installation/index.md).


## License

Stack is Licensed under the [GNU General Public, License Version 3](https://github.com/maths/moodle-qtype_stack/blob/master/COPYING.txt).
