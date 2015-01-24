Apollo
======

Apollo is a collection of PHP scripts written to provide a web-based
interface for students to upload coursework. It is intended to be used
alongside a [`socrates`][soc] installation.

Apollo reads parses and uses `socrates` criteria files to determine the
expected files for a given assignment, as well as the due date of each
file, and automatically opens & closes submissions for the files. Apollo
will ensure that students are uploading the correct files by checking
their file names and file sizes.

Apollo also supports reading `socrates` grade files, when graders have
finished using `socrates` for grading. For each `socrates` grading group,
Apollo will display the grade and any other details.

Lastly, Apollo allows students to submit grading concerns about a given
grade. Apollo saves a YAML file to the file system when a student
submits a concern, which can be edited to contain the feedback of the
grading coordinator.

Apollo makes use of the [Spyc][spyc] YAML library for PHP. Many thanks
to Vlad Andersen for his work on this library, which makes `socrates`
integration possible!

[soc]: https://github.com/abreen/socrates
[spyc]: https://github.com/mustangostang/spyc
