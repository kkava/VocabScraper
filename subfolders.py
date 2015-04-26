# e.g. "aptitude", 3 -> "a\ap\apt\"
def getSubFolders(word, leadChars):
    path = ''
    for i in range(1, leadChars+1):
        if(i > len(word)):
            break
        path = path + word[:i] + "\\"
    return path
