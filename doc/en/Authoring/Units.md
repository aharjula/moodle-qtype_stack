# Units

It is quite common in science subjects to need to accept an answer which has _units_,
for example using \(m/s\).  Fortunately, Maxima already has a units package.

## The differences between `unit` and `units` packages  ##

Note that in Maxima there are two packages which enable a user to manipulate physical units.

1. `load(unit);` the code is in `Maxima-5.21.1\share\maxima\5.21.1\share\contrib\unit\unit.mac`
2. `load(units);` the code is in `Maxima-5.21.1\share\maxima\5.21.1\share\physics\units.mac`  See also [Maxima documentation](http://maxima.sourceforge.net/docs/manual/en/maxima_76.html#SEC319)

The differences between these are discussed in Maxima's
[online manual](http://maxima.sourceforge.net/docs/manual/en/maxima_76.html#SEC321).

We don't use these packages.

### Unit package ###

There is a lighter weight units package in the file stack/maxima/stackunit.mac, that
provides a way for defining the presentation of most SI prefix and unit combinations
as well a way for returning any expression using those units to the base SI units.
Comparing two expressions that have been returned to the same base units is obviously simpler.

Support includes liters and SI [units](https://en.wikipedia.org/wiki/International_System_of_Units#Base_units) and [derived units](https://en.wikipedia.org/wiki/International_System_of_Units#Derived_units) in this list [m,g,s,A,ohm,K,mol,cd,Hz,N,Pa,J,W,C,V,F,S,Wb,T,H,Bq,Gy,Sv,lm,lx]


We do not note radians, steradians, or degrees of Celcius. This is due to practical reasons.

## Examples  ##

### Example 1  ###

Lets assume that we are asking for a length of something large and the student could answer
with anything from giga-meters to femto-meters. Obviously we do not want to test for all of
those cases with all of those possible units.

 1. Declare that you are using units in this question by putting "stack_unit_si_declare(length);"
    into the variable-definition field. This is not entirelly necessary, but it ensures that all
    the units are presented with a non italic font and that they are considered as constants in
    the validation code.
 2. Calculate your model answer using any (SI) units you wish just make sure that the unit is
    present in that answer e.g. "ta:rand(2345432543)*km"
 3. Return that answer to the base units with the command "ta:stack_unit_si_to_si_base(ta);"
 4. Do the same to the students answer "sans:stack_unit_si_to_si_base(ans1);"
 5. You can then just compare them. Or if you happen to allow floats or for some other reason
    want to get a raw number to work with extract the "multiplier of the unit" from the answers
    with "rawnumber:coeff(sans,m);". With raw numbers it is then possible to test all sorts of
    accuracy and presentation issues. TODO: SigFigs-testing...
