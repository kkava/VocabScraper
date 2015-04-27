# VocabScraper
A vocabulary scraper that can be used to build vocabulary lists from German websites. Compound word translation, N-gram assisted word selection.

Installation / use:

1. Download this repository

2. Get dict.cc DE-EN dictionary - you must request it from http://www1.dict.cc/translation_file_request.php?l=e

2. Replace the file de-en_dict.cc with the downloaded one (keeping the name "de-en_dict.cc")

3. Run dictSplit.py
    -This produces a subdirectory dictionary structure which dict.php then uses to look up words
    -The original dictionary file is not used by dict.php

4. Aquire an MS N-gram service key from 
    http://weblm.research.microsoft.com/info/index.html

5. Replace the string "[PUT_YOUR_MSNGRAM_KEY_HERE]" in dict.php with your key

6. Place the modified package (including dictionary sub-folders) on a PHP-capable server

7. Access the location with a browser to run the program 
