Warning this text uses the word "state" for multiple things:
 1. as an operating mode
 2. as an value of an variable at some point
 3. and as the results of the combination of the previous two

# State variables and their storage.

The state system stores the state of the question from handling one steps inputs to the handling of the next steps inputs. Thus
allowing one to make decisions about those inputs based on previous inputs. This makes it possible to dynamically modify
the question as the student gives inputs e.g. by asking more detailed questions after a slightly wrong answer has been received or
by giving hints. Basically, you can build games with storylines.

Problem is that the Moodle question model does not support this. In that model the question exists in exactly two states:

 1. The initialization state where there are no inputs and the question can do basic randomization and store its results to
	the database so that the same randomizations can be used in future steps.
 2. The attemp state is the second of the possible states, in it the question is first given the values it saved in
	the initialization phase and then given an input to mark. In this state the question has no write access to the database
	that will store that input and the marks the question gives. Actually, the question does not even know if it has been attempted
	before as it has no access to the previous attempts. Even the sequence number of this attempt step is being hidden from
	the question at this phase as are most of the database identifiers that would help gaining access to the actual data and
	history.

And we want the question to have infinite number of states instead of just those two...

So to make things work we need to do the following:

 1. Create a way for the question to store the state it generates so that it can work with it. So we will create some
	database tables:
		1. ```qtype_stack_shared_state``` will be the one that stores state that spans questions, i.e. the state that profiles
			the student. Its primary identifiers will be the database-id of the student/user and the name of the state variable.
            In the case of the shared global state the value of the state is stored into the instance state of the question during
            initialization and when the question reads it it reads it from there so that returning to the question even after
            the state has changed elsewhere can be handled in a sensible way. Due to this logic writing to the global state is
            generally handled with functions that increment of decrement that state and provide their own ways of limiting calls.
		2. ```qtype_stack_instance_state``` will be the one that transfers the state of the question between steps. In this case
			we need more identifiers:
				1. The question needs to be identified so we use the container that we are given in the initialization phase to
				 	store stuff as the identifier. Luckily, it has an database-id ```attemptstepid```, and we can even get it from
					it by asking for that id. But as that id is unknown during the initialization step we need to do something
					special then, well we will store that steps values to the container given.
				2. We need to identify the step we are working with so that we can select the correct state of the question to work
					with i.e. not the state of the question during the step before last nor the state during the next step so we
					will have an ```sequencenumber``` to tell us which state this is. Basically, only be need when the teacher
					wants to see what happened during previous steps or when you want to debug your game.
				3. Name is obviously again needed as the state is a key-value store.
 2. Make the question aware about the place it is in the sequence of attempt steps the student is giving. Handling this would
	either require reading the database directly and assuming certain things about the flow of time (also we have causality issues
	as the things we would want to read might not have been written yet.) or we need to add some sort of an counter somewhere. We
	will do this with a counter that will be included as an hidden input in the question text. Unfortunately, to read that counter
	in the correct place we will need to rearrange some evaluation or re-evaluate things when the value becomes available.
 3. Identify whether the question uses state variables and if so if it writes to them. This affects the types of question behaviours
    that can be allowed. It also affects the places where the state-storage needs to be considered and which parts can be evaluated
    in the traditional order and which ones need to be held back.
 4. If state variables are used, read and construct the state variables the question needs from the stores and inject them to all of
    the cassessions used in the question.
 5. If writable variables are used we will also need to extract them from the evaluated cassessions and store them into those
    stores.

