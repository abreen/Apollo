Apollo
======

Apollo is a collection of PHP scripts written to provide a web-based
interface for students to upload coursework. It was originally written
to be used with a single [`socrates`][soc] installation on a multi-user
system, but has been since decoupled from `socrates`.

Apollo reads, parses and uses *metafiles* to determine the
expected files for a given assignment, as well as the due date of each
file. (In this context, a metafile is a file containing data about other
files. Here, the data are the due date and late multipliers.) Apollo will
automatically start & stop allowing submissions for the files, based on
the specified due dates. Apollo will also ensure that students are
uploading the correct files by checking their file names and file sizes.

Apollo also supports reading `socrates` **plain text** grade files, when
graders have finished using `socrates` for grading. However, you are not
required to use `socrates` to produce grade files: the only thing Apollo
requires is the existence of a single line (usually at the end of the file)
containing

    total: A/B

where both A and B are integers or floating-point numbers, A is the
number of points earned by the student, and B is the total number of
possible points.

Note as well that Apollo supports multiple grade files for a given
assignment. Suppose the assignment is `ps7`. Apollo will look for a grade
file named `ps7-grade.txt` in `DROPBOX_DIR`. If this is present, the
contents of that plain text file are shown to the student. However, if
any other grade files with a *grading group* portion (any alphabetic string
between the `ps7` part and the `-grade.txt` part), e.g. `ps7a-grade.txt`
and `ps7b-grade.txt` are present, Apollo will show them as well, labeling
the grades as being for "Group A" and "Group B", respectively.

<!--
Lastly, Apollo allows students to submit grading concerns about a given
grade. Apollo saves a YAML file to the file system when a student
submits a concern, which can be edited to contain the feedback of the
grading coordinator.
-->

Apollo makes use of the [Spyc][spyc] YAML library for PHP. Many thanks
to Vlad Andersen for his work on this library, which makes `socrates`
integration possible!

[soc]: https://github.com/abreen/socrates
[spyc]: https://github.com/mustangostang/spyc
