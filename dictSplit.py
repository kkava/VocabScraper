# Opens a dictionary and splits it into multiple files
# based on the first few characters of each entry.
#   -Assumes dictionary is already sorted ascending A-Z.
#       ! Note that dict.cc is sorted on the whole line entry, not just the German entry on it's own!
#   -Case insensitive.
#   -Split file names from entries with fewer characters than specified are simply truncated
#   -Following dict.cc format, all spaces in each entry are removed before processing into chunks
#   -Folders are created in the a/ab/abcd.dict format (where N=2, M=4 here) 

import os, sys, re, shutil
from translit import transLit
from subfolders import getSubFolders
from alphadash import isAlphaDash
## import msngramscraper

leadChars = 5       # e.g. 2 == 'ab' sorting (~27^2 files), 3 == 'abc' sorting (~27^3 files), 4 == 'abcd' ~ 26k files, 5 ~ 69k files, 6 ~ 136k files.

# Open and read the dict.cc DE-EN dictionary(from 2015)
dictFileName = 'de-en_dict.cc'
dictPath = os.getcwd() + '\\' + dictFileName
dictFile = open(dictPath, 'r', encoding='UTF-8')
dictLines = dictFile.readlines()

curLeadString = ''  # Holds current set of n leading characters

dictPathSplit = dictPath + '_split_' + str(leadChars) + '\\'
shutil.rmtree(dictPathSplit, ignore_errors=True)
os.makedirs(dictPathSplit, exist_ok=True)

cleanWord = re.compile(r'[^\w -]', re.UNICODE)   # For cleaning American words and phrases in prep for n-gram lookup (possibly not German safe!)
removeDecoration = re.compile(r'(\([^)]*\))|(\[[^]]*\])|(\{[^}]*\})|(\<[^}]*\>)|(\b[\w]*\.)', re.UNICODE)

# Rejected lines are output for debugging purposes
rejectFile = open(dictPathSplit + 'rejected.dict', 'w', encoding='UTF-8')

# Iterate over lines, breaking when changes in lead string occur
numFiles = 0
curLineNum = 0
linesWritten = 0
distribution = dict()
histogram = dict()
totalLines = len(dictLines)
for line in dictLines:
    curLineNum += 1

    # skip comment lines starting with '#'
    if line[0] == '#' or line[0] == '\n':
        print ("Skipped blank or comment line " + str(curLineNum))
        rejectFile.write(line)
        continue

    # Check if new file required
    #   Split on \t
    curLineEntries = line.split('\t')

    # Grab German word
    curLineLead = curLineEntries[0]
    # Remove tags and decorations such as etw. jdn. [heraldry] [archaic] {m} etc.
    curLineLead = removeDecoration.sub('', curLineLead)
    # Remove any remaining special characters except dash (e.g. / ... ,)
    curLineLead = cleanWord.sub('', curLineLead)
    #   Remove all spaces (i.e. collapse the entry
    curLineLead = curLineLead.replace(' ', '')
    #   Lowercase and grab only lead chars
    curLineLead = (curLineLead[0:leadChars]).strip().lower()
    #   Transliterate (german-specific chars only)
    curLineLead = transLit(curLineLead)

##    # Grab english word, neglecting (stripping) decorations
##    curLineEnglish = curLineEntries[1]
##    trimPos1 = curLineEnglish.find('[')
##    trimPos2 = curLineEnglish.find('{')
##    trimPos = max([trimPos1, trimPos2])
##    if (trimPos > 0):
##        curLineEnglish = curLineEnglish[:trimPos].strip()
##    curLineEnglish = cleanWord.sub('', curLineEnglish).strip()

##    # Get N-Gram of the word
##    # First check that N isn't too big
##    N = len(curLineEnglish.split(' '))
##    if (N < 4) :
##        curPopularity = msngramscraper.scraper(curLineEnglish)
##        if (curPopularity == None):
##            curPopularity = 0
##    else :
##        curPopularity = 0
##    print (str(curLineGerman)+" :: "+str(curLineEnglish)+" ("+str(curPopularity)+")")

    if curLineLead == '' or not isAlphaDash(curLineLead):
        #print ("Skipped blank or non-alpha line " + str(curLineNum))
        rejectFile.write(line)
        continue

    # Need to record histogram data and write it out at the end
    # [keyString]   [numEntries]

    # Dictionary chunk changeover point detection
    if curLeadString != curLineLead:
        curLeadString = curLineLead
        subFolders = getSubFolders(curLeadString, 2)
        os.makedirs(dictPathSplit + subFolders, exist_ok=True)
        curOutFileName = dictPathSplit + subFolders + curLeadString + '.dict'
        if 'curOutFile' in locals():
            curOutFile.close()
            del curOutFile
        if os.path.isfile(curOutFileName):
            curOutFile = open(curOutFileName, 'a', encoding='UTF-8')    # Catches cases where sorting has been fucked up, but requires that the dict root folder must be initially empty
            #print ("Appending to " + curOutFileName)
        else:
            curOutFile = open(curOutFileName, 'w', encoding='UTF-8')
            #print ("Writing to " + curOutFileName)
            numFiles += 1

    # Write out the line and record it for the histogram
    if 'curOutFile' in locals():
        curOutFile.write(line)
        linesWritten += 1
        if curLeadString in distribution:
            distribution[curLeadString] += 1
        else:
            distribution[curLeadString] = 1

if 'curOutFile' in locals():
    curOutFile.close()

# Histogram of word-file distribution (i.e. how many words per file)
distFile = open(dictPathSplit + 'distribution.dict', 'w', encoding='UTF-8')
histFile = open(dictPathSplit + 'histogram.dict', 'w', encoding='UTF-8')

# Write out the distribution [leadStr]  [numEntries]
# This is like the raw data used to make a histogram of dict chunk size
distFile.write("Sorting key\tNumber of Entries\n")
for key, value in distribution.items():
    distFile.write(key + "\t" + str(value) + "\n")

# Sort the histogram to produce [fileLength]   [numFilesOfThatLength]
for leadStr, numEntries in distribution.items():
    numEntriesStr = str(numEntries)
    if numEntriesStr in histogram:
        histogram[numEntriesStr] += 1
    else:
        histogram[numEntriesStr] = 1

# Write out the histogram [fileLength]   [numFilesOfThatLength]
histFile.write("Number of entries\tNumber of Dict Chunks\n")
for key, value in histogram.items():
    histFile.write(str(key) + "\t" + str(value) + "\n")

rejectFile.close()
distFile.close()
histFile.close()

print ("\nFiles written: " + str(numFiles) + "\n")
print ("\nLines written: " + str(linesWritten) + " / " + str(totalLines) + "\n")













