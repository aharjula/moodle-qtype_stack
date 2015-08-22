# Scientific Units

It is quite common in science subjects to need to accept an answer which has _units_,
for example using \(m/s\). STACK now supports evaluation of numerical answers which also contain scientific units.

## Existing Maxima packages for units  ##

Note that in Maxima there are a number of packages which enable a user to manipulate physical units.

1. `load(unit);` the code is in, e.g. `Maxima-5.30.0\share\maxima\5.30.0\share\contrib\unit\unit.mac`
2. `load(units);` the code is in, e.g. `Maxima-5.30.0\share\maxima\5.30.0\share\physics\units.mac`  
3. `load(ezunits);` the code is in, e.g. `Maxima-5.30.0\share\maxima\5.30.0\share\ezunits\ezunits.mac`

The differences between these are discussed in Maxima's [online manual](http://maxima.sourceforge.net/docs/manual/maxima_81.html#SEC381).

STACK does not use these packages.

## STACK's lightweight support for units ##

There is a lighter weight units package in the file `stack/maxima/stackunit.mac`.

We have provided yet another package for the following reasons:

1. The other packages are very slow to load.
2. We wanted finer control over how units are dislayed, e.g. italic or Roman script.
3. We have defined units as Maxima constants.  This separates out "constants" from "variables".  See the documentation on `listconstvars`

This package provides a way for defining the presentation of most SI prefix and unit combinations
as well a way for returning any expression using those units to the base SI units.
Comparing two expressions that have been returned to the same base units is obviously simpler.

Support includes liters and SI [units](https://en.wikipedia.org/wiki/International_System_of_Units#Base_units) and [derived units](https://en.wikipedia.org/wiki/International_System_of_Units#Derived_units) in this list `[m,g,s,A,ohm,K,mol,cd,Hz,N,Pa,J,W,C,V,F,S,Wb,T,H,Bq,Gy,Sv,lm,lx]`

We do not include radians, steradians, or degrees of Celcius. This is due to practical reasons.

## Examples  ##

### Example 1  ###

Let's assume that we are asking for a length of something large and the student could answer
with anything from giga-meters to femto-meters. Obviously we do not want to test for all of
those cases with all of those possible units.

 1. Declare that you are using units in this question by putting `stack_unit_si_declare(length);`
    into the variable-definition field. This is not entirelly necessary, but it ensures that all
    the units are presented with a non italic font and that they are considered as constants in
    the validation code.
 2. Calculate your model answer using any (SI) units you wish just make sure that the unit is
    present in that answer e.g. `ta:rand(2345432543)*km;`
 3. Return that answer to the base units with the command `ta:stack_unit_si_to_si_base(ta);`
 4. Do the same to the students answer `sans:stack_unit_si_to_si_base(ans1);` this happens
    in the feedback variables or the argument to the answer test.
 5. You can then just compare them. Or if you happen to allow floats or for some other reason
    want to get a raw number to work with extract the "multiplier of the unit" from the answers
    with `rawnumber:coeff(sans,m);`. With raw numbers it is then possible to test all sorts of
    accuracy and presentation issues. 
    
TODO: we need to improve significant figures testing.  This is a known feature request.


### Example 2 ###

For example we might ask the student to evaluate multiple formulas returning energy, i.e. Joules,
and we want to find out if the student has actually done all the unit conversions, i.e. converted
all the Watts and Amperes with seconds and so back to Joules.

 1. With normal expressions we would just use the `listofvars()` function to get a list of variables
    present in the expression, but units are constants. So you need to set the option
    `listconstvars:true;` to also get them in the list.
 2. Simply checking the length of that list tells you plenty about the student's calculations.
 3. Just do not return that answer to base units before checking that.
