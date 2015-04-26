# Like isalpha but allows numbers and dashes
# Returns boolean TRUE if the string is clean
import re
def isAlphaDash(test):
    s = re.compile(r'[^\w -]', re.UNICODE)
    return (s.search(test) == None)
