# msys-os

This is my very light weight configuration management system.

It simply exploits PHP as a macro language to generate shell scripts
that will then be executed by the systems that we want to configure.

The idea is that it replaces the operator entering commands directly
by a script that is executed whenever a change needs to happen.  These
scripts are generated by PHP including a library of canned routines.

To make configuration repeatable/manageable all scripts can be stored
in `git`.

