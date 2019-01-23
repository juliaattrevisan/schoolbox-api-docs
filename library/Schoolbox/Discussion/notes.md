This is a generic structure for discussion threads.

Whilst it's initially intended for use as news discussions, eventually I
believe it will become the backend for the following already-existing
discussion thread contexts:
* blogs (if not deprecated by news)
* forums
* social stream
* grades

and could also become the backend for threads in new contexts:
* assessments (due work)


Migration notes
* Blogs don't require an extension of the proposed structure
* Forums require multiple threads per item
* Socialstream requires at least two levels of post nesting, maybe also changes to how posts are formatted
* Grades requires extension of permissions on threads (who can post? Parents of student? Teachers? The student themselves?)
* Assessments require the same extension of permissions as grades do?


We may need to make different features optional for different contexts:
* Cut-down editors for some contexts?
  * e.g. news doesn't get nested threads, but socialstream does?

----

# Seeding

How'm I going to put test data into the DB?

* start from initial dataset?
* clear out old comms articles
* create demo data for them
* create x comms articles, 0-n comments for each of them

I'm going to be limited with the usefulness of the fake data I can create, as
permissions can be based on non-fake data (e.g. Synergetic).

Consider:
* some fake data with non-fake parts (e.g. a class with a known class code)
* using Schoolbox data, and faking ALL data
  * likely best idea in long run
