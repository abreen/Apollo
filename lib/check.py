"""
Python 2 command line utility for checking Python code for indentation & syntax
issues. This utility should validate Python 2 properly, and most Python 3 code
properly. See the source code for examples of valid Python 3 code that will
fail to validate.
"""

# Python 3 syntax this utility will erroneously claim is invalid (not a
# complete list):

# 1. the nonlocal statement
# 2. extended iterable unpacking
# 3. weird things with keyword arguments

# Note that if this script is run with Python 2.5 or earlier, additional
# problems may arise with Python 3 code that contains dictionary
# comprehensions, set literals, octal & binary & bytes literals, and probably
# more.


import sys
import ast

filename = sys.argv[1]

with open(filename, 'r') as f:
    source = f.read()

try:
    ast.parse(source, filename)
except (IndentationError, SyntaxError) as e:
    if type(e) is IndentationError:
        print 'indentation error'
    elif type(e) is SyntaxError:
        print 'syntax error'

    print e.lineno
    print e.msg
    print e.text.strip()

    sys.exit(1)
