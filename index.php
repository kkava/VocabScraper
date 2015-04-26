<!--
 2015-02-25:	Pickup: process dictionaries into 'ab' chunks on local machine then upload
	Will also need to write code to eat the chunks as needed
	Take care with special characters and shorter than 'ab' entries
 2015-02-26:	Partitioned dictionary into 'abc' chunks, about 5000 files
	Still need to write chunk manager / lookup
	Considering writing a class, instance of which does this
 2015-03-06:	
	New transliteration engine needs checking.
	See for example "�ben" or "�ber" to make sure they are coming through.
 2015-03-07:
	Fixed html stripper to place spaces.
	Fixed verb deconjugator (string concat mistake).
	Future: rank all english words in dict.cc by frequency (add a column)
		See: https:github.com/jbowens/google-ngrams-scraper/blob/master/NgramScraper.py
			-Python module dependencies are proving difficult to install/access
 2015-03-09:
	Ngram data is very slow to access. Tried google, now using MS url request.
	Currently the code only checks ngrams when a duplicate entry is found on the DE side.
	However, we could modify the dictionary to load multiple entries, then only search
		for an ngram when the word is actually looked up (and redundant entries exist).
		That way the dictionary entries which are loaded (but not used in a lookup) do not 
		result in ngram calls.
	Consider saving non-redundant dictionary lines (i.e. best line found) to overwrite 
		existing dictionary set, or saving the ngram ratings to the dictionary chunks 
		as they are found.
	Perhaps track line numbers and replace a set of redundant lines at the end of the sweep through the chunk.
 2015-03-10:
	dict.cc is sorted on whole-line basis, so some entries are not correctly sorted. See gmail to dict.cc.
	This makes repeat blocks discontinuous. Need to rewrite the n-gram tagging code to account for this.
	Consider just adding n-gram data to all lines.
		Done. But only to lines we use (single-word entries).
	Consider breaking up dictionaries into folders such as ab/abre.dct. Currently hitting file number limits.
 2015-03-15:
	Need to break into 5-letter chunks.
	When &ngram but not &imp, it shouldn't query non-cached ngrams.
	Switch to 5-letter split (~59k files).
	Get n-grams for all entries? (i.e. not just those with a single-word on the DE side)
	Save n-gram numbers as log instead of decimal to save space.
	4-letter words seem to be missing now, e.g. "Type"
		Fixed in splitter.
	Update trim decorations code in here to be as good as that in the splitter.py
	Consider refactoring to not improve dict chunks in which the word isn't even eventually found (i.e. check the dict chunk first, then improve it)
	Also perhaps no need to improve chunks when the word being queried is non-duplicate
		Done.
		Undone after factorization - we now only save N-grams for found words which are duplicates. Possible due to improved sorting within our split dictionary.
 2015-03-17:
	adding <> removal to dictionary lines makes this 100x slower for some reason. This isn't the case if &ngram isn't there.
		This was a red herring. Fixed per comment below.
	Implementing a simple profiler that is just a global array timer['myFunc']=time_spent_there then print it out as needed.
 2015-03-24:
 	Still need to work on figuring out code slowdown when &ngram= is activated.
 		Fixed - logic was wrong so calls to MS N-gram service were happening which did not need to happen
 	Adding simple form and header at top to start a new scrape.
 	Consider adding a timelimit parameter on the n-gram fetching.
 		Addded &time=XXX parameter. If zero or not set, no limit.
 	Modify n-gram score with the number of modifiers attached to the corresponding English entry
 		e.g. "Oz [Aus.] [Br.] [coll.] [Australia]" would have a reduced score due to having 4 modifying tags.
 		This is now done. It mostly improves the results.
 	+ Consider combining multiple translations if their scores are very close (e.g. within 1 OM)
 	Fix form checkboxes to actually work (right now they are always on because the current code only checks for the presence of the parms, not the values)
 		Done.
 2015-03-25:
 	When ngram is on but imp is not, ngram calls are still being made. They should not be.
 		fixed.
 	Improved / refactored the code that tried alternates, which lead to a 15% increase in the number of found words. 
 	Add minimum word length parameter.
 	Added html_entity_decode to grab encoded special chars
 	Consider removing "etw." from German side for lookup purposes
 		Done.
 	+ Consider adding a "Crawl" mode where it trains the dictionary by crawling German links
 		-Be careful about termination conditions!
 	+ Consider changing the keys of $vocabList to be the german word (currently $EN) because only they are guaranteed to be unique
 	Added tranliteration and untransliteration checks at the end of the alternate word forms
 	Consider changing &time= parameter to only apply to the time spent on new n-gram improvements.
 		-No, now that the server has recognized me as a problem the 90s global timelimit is enforced.
 	Even if .ngram file already exists, improve it if there is no ngram entry on a given line
 		-This is needed because our dictionary parsing has improved, e.g. not considering etw. on the DE side
 		-Also because we now strip <> et.al. whereas previosuly they counted as multiple words and led to dismissal
 		-Should allow n-grams for multi-word entries
 	+ Do something about the "das Gold" -> "or" problem. Perhaps a limit or downgrade of score when n-gram frequency is too high! 
 		-Could regulate this with DE word length...
 		-That is, if the german word is long, and the english word has high n-gram freq., degrade the score.
 		-http://www.reddit.com/r/de/comments/26mblv/hallo_ich_w%C3%BCrde_gern_mit_der_deutschsprachigen/
 2015-03-27
 
 	Removing etw. jdn. etc. from German word along with decorations. Inspired by many lines like "etw. ausmachen jdn.".
 		-Changed both dictionary splitter and this code.
 		However, now the last word of phrases are being removed, e.g. "Keine Ahnung." -> "Keine", and "Dunno." -> ""
 			-This is very bad because it mixes up multiple entries, e.g. Keine Ahnung and Keine are now incorrectly redundant.
 			Fixed by only removing 1-3 chars before a period. Not perfect but has improved the results.
 			
 	Added numbers and dashes to list of allowable characters. 
 		-This mirrors changes to the dict splitter. We now have much more dictionary coverage (99.75%).
 	Consider restructuring such that if any new n-gram data is obtained, the original dictionary files are modified
 		-Instead of saving separate (but mostly redundant) .ngram files
 		Done.
 	+ Consider adding german word n-grams to LHS of each row. This could aid scoring of rows relative to one another.
 	Fix n-gram (or perhaps scoring) error for special cases like this:
 		Amerikaner {pl} :: Replacing American [0.0151364856632] with [cookies with chocolate frosting or powdered sugar frosting] [0.1] .......
 			-Do this before any further training.
 			-Idea: in such cases, either:
 				-remove enclosing brackets?
 				-just give zero score if cleaned entry is blank
 					Done. Wiping dictionary knowledge.
 					Note that exp(0) is 1.0.
 	We have to only populate n-gram of the word we are actually looking up. This shit is way too slow.
 		-Requires some major refactoring but is necessary.
 		-Pass word to dictionary so it knows what to target for n-gram cacheing (not such a big deal!)
 			-But then subsequent lookups would have to also have update logic in them...
 			-Probably need a dictionary class with chunks and internal memory management
 		Done.
 2015-03-29
 	+ Consider adding parameter to train the entire dict chunk instead of just the found word
 	Investigate repeatedly loaded dictionaries (e.g. Auslands�sterreicher)
 		-Looks like this happens when one word is capitalized and another is lowercase
 		-Had to do with when ngram = 0, because 0 == NULL, fixed by using === instead of ==
 	Running over memory - refactor dict code to only save raw line, then write helper functions to split it up
 		-Or possibly save the split up line only
 		Actually I just increased the memory safety factor. But we should probably give the dict structure a diet anyways.
 2015-03-31
 	Add a verbose flag to show all substitutions as they occur.
 		-Also don't show any if flag isn't set
 	Remove imp flag, re-brand ngram flag to single "Intelligent word choice (slower)" option
 		-Should be on by default, even if &ngram= isn't present
 	Why is words.txt not loading when it is more than a few words?
 		-Probably was due to trim(,'UTF-8') mistake.
 	Adding auto-scroller js
 2015-04-01
 	Add a log file with user info, url and statistics (% new words, time/word etc.)
 	Added alternate forms for -n verbs (in addition to our -en verb alts) (e.g. sammeln)
 2015-04-03
 	Adding anki formatted output
 		-Zip	PHP:: new ZipArchive()
 			-SQLite3	(collection.anki2)	PHP:: new SQLite3().open("template.anki2")
 			-TXT		(media - file containing only "{}")
 2015-04-06
 	Wrote apkg generator code.
 		-Need to add ./out folder periodic cleanup code
 			Done.
 		-Also need slick redirect for file download (Done)
 	Consider bailing on a word if dict chunk not found on original version
 		-Could miss a few special cases but overall would speed things greatly.
 	Flugzeug dict chunk not being found although it is there.
 		http://kkava.com/vocab/?src=http%3A%2F%2Fscienceblogs.de%2Fhier-wohnen-drachen%2F2015%2F03%2F26%2Fdas-generische-femininum%2F&ngram=on&imp=on
 		http://kkava.com/vocab/de-en_dict.cc_split_5/f/fl/flugz.dict
 		-Was incorrectly specifying 'UTF-8' in trim() - this was filtering all U, T and F words (char at end or beggining).	
 2015-04-09 
 	Changed code and interface to only show ngram option, and always improve the dictionary
 2015-04-10
 	Slight UI changes.
 	Added a donate button.
 	+ Consider using full German entry on LHS instead of undecorated one
 	+ Consider adding more flashcard deck format options
 		Added an inactive drop-down menu for now.
 		
 		https://www.google.com/trends/explore#q=Flashcard%2C%20%2Fm%2F043s6yg%2C%20Memorang%2C%20%2Fm%2F03qgpvl%2C%20Vendant&cmpt=q&tz=
 		http://en.wikipedia.org/wiki/List_of_flashcard_software
 	
 		Top coverage of platforms:
 		http://mnemosyne-proj.org/help/importing.php
 		https://www.memorangapp.com/
 		https://www.repetitionsapp.com/
 		
 		Top android apps:
 		Quizlet
 		Anki
 		Flashcards Deluxe
 		Cram.com Flashcards
 		FREE Flashcards Helper
 		Flashcards Maker
 		
 		Others:
 		http://en.wikipedia.org/wiki/OpenCards
 		https://pleco.com/ipmanual/flash.html
 		http://flashcardmaster.sourceforge.net/
 		http://www.vendant.com/fcm2-help.htm
 		http://flashcardsdeluxe.com/Flashcards/
 		http://flashcardhero.com/user-guide/e02/
 		
 	+ Need to reduce memory footprint of dict chunks so more can persist during lengthy translations
 	Consider adding deck collection label changing (e.g. to src website URL)
 		-in 'col'.'decks' - search for '{"desc": "some description",' and replace it.
 		-not sure what happens to desc field when decks are merged in the app
 			-maybe make titles unique too, like with source domain name
 			-it overwrites the deck description, but because of this 111  difference, redundant cards are kept
 	Modify deck template to increase daily limit by default (to maybe 500, or maybe entire deck)
 		-Made it 400.
 2015-04-12
 	Refactored cleanOut() to accept multiple masks with preg_match()
 	Added TSV output and refactored as necessary
 		-Need some testing, done. TSV is much smaller than .apkg.
 	v2.6
 2015-04-14
 	+ Reverse cards on/off
 	Missed entries in http://kkava.com/vocab/?src=http%3A%2F%2Fm.welt.de%2Fregionales%2Fnrw%2Farticle139389505%2FAls-deutschen-Juden-ihre-Heimat-genommen-wurde.html
 		-Jahren - not in dict.cc
 		-beließen - perhaps misspelling or conjugation of belassen - not in dict.cc
		-Kindern - not in dict.cc
	+ Expose min word length param, increase default to 4
		-Default increased v2.8
	+ en-de funcionality
		-Any dictionary functionality 
	Improve html tag removal re: http://www.heftig.co/haus-ontario-canada/
		-Now removing <style> tag contents.
	Decapitalize first letters of sentences.
	Deck description and name are now unique to each download URL and scrape date.
 2015-04-18
 	v2.8
 	Add "show surrounding words" feature
 		-Requires not removing duplicates from word stream
 			-Dups now checked at lookup time
 	Simplify default deck title to just the URL (or some shortening thereof)
 	If original word is shorter than split length, don't bail on dict chunk not found
 		e.g. fiese (nasty) not found in:
 		http://kkava.com/vocab/?src=http%3A%2F%2Fwww.ingenieur.de%2FArbeit-Beruf%2FArbeitsmarkt%2FDie-sechs-fiesesten-Fragen-im-Vorstellungsgespraech&out=anki&ngram=on#ListTop
 	+Put parameter list with descriptions in single PHP block comment
 	+Refactor parameter code to be in one place only
 	Include all settings in deck description url
 	Fix poor translation of 'lahm' : currently just picking first one.
 		http://kkava.com/vocab/de-en_dict.cc_split_5/l/la/lahm.dict
 		-Fixed with tweak to scoring code in dict.php
 2015-04-22
 	v3.0
 	Add compound words lookup
 	  Ended up writing two functions, but only using one (fwd lookup).
 	  Reverse lookup function is completely untested.
		-add case insensitive quick search option to dict.php that returns a boolean result
		-split within the middle of the word, starting from the center and working your way outwards, or perhaps start from 3/4 position (or even the end), then find first fragment, then repeat with remaining portion.
		  can skip first 3 chars trim, as that has been done in the adjective deinflection code (although perhaps not in a cases insensitive way).
		  or can do progressive reverse lookup from within word to dict chunk
		-longest match fragment will give best translation as opposed to shortest match.
		-some mechanism for saving incomplete fragment translations, bogus e.g. through-drive-"fachstelten".
		-should the splitter be built into the front end i.e. part of the alternate forms list, or part of the dictionary? 
		  +Probably have a secondary dict lookup function that handles compound words with a quick and dirty case insensitive lookup would be cleanest.
		-consider changing the translator to load the whole dict into a string and use regex lookup via preg_match_all? 	
	Could make compound interpreter better by interpreting conjoining 's' chars on failure of remaining $right string 
		-try to rtrim 's' from $right on recursion 
		-e.g. Krankenkassenkostendämpfungsgesetzbeschlussvorlagenberatungsprotokollüberprüfungsausschussvorsitzende
		-How to detect this condition? Indeed, how to detect the termination condition of the recursion? Obvious - the null return array.
			-no that isn't right, only occurs when string has been completely consumed.
			-Actually, could be combo of:
				1. dict chunk not found
				2. string starts with s
	Optimize compound lookup with pasing $wholeWord and $leftIdx instead of $left and $right
		-Aditionally, if dict chunk not found, skip $leftIdx down below $splitLen
	If compounded fragment is a verb, convert to gerund first, e.g. "to caulk" -> "caulking"
	Fix UTF-8 bug with "Bügelbretter" -> "B?gelbretter" in words.txt
		-This still persists... the dictKey is not being properly parsed from the word.
		-Current dictKey is calculated as "beuge" when it should be "beugel"
		-Actually transliterated altword was causing bail due to dict chunk not found
			-Added a check for that altword $form === 'trlit'
	Dict chunk being saved and re-saved for same word - see words.txt with verbose mode on.
		-Chunk is not being saved.
		-Pass by reference mistake
	+Experiment with improving performance on words.txt when $minFragLen = 3 vs 4 (both have a different set of misses and hits).
		-The problem is with the interaction between binding char stripping and min frag length termination (i.e. binder stripping is triggered by frag length termination)
	+Add compound word type guessing (e.g. if most fragments are nouns, it's a noun with the gender of the last fragment)
	+Expose advanced options
-->

<?php
	error_reporting(E_ALL);
	set_error_handler("err");

	// URL PARAMETERS
	if (isset($_GET['v']) && $_GET['v'] !== 'off') {
		$verboseMode = TRUE;
	} else {
		$verboseMode = FALSE;
	}	
	
	if (isset($_GET['src'])) {
		$focusURL = $_GET['src'];
		if ($focusURL == "") {		// If src= is set in url, but no page specified, process default page
			$focusURL = "http://www.reddit.com/r/de";
		}
		if (strtolower(getFirstN($focusURL, 4)) !== "http") 
			$focusURL = "http://$focusURL";
	} else {
	    $focusURL = NULL;
	}
	
	$improveDict = TRUE;			// Changed this to always be true instead of a parameter
	if (isset($_GET['ngram'])) {
		$useNgrams = ($_GET['ngram'] == "on");
	} else {
		$useNgrams = TRUE;
	}
		
	if (isset($_GET['time'])) {
		$timeLimit = floatval($_GET['time']);
	} else {
		$timeLimit = 88;		// s
	}
	
	if (isset($_GET['min'])) {
		$minWordLen = floatval($_GET['min']);
	} else {
		$minWordLen = 4;		// 0 == no limit, 2 means at least 2 UTF-8 chars
	}

	if (isset($_GET['surr'])) {
		$surroundingWords = intval($_GET['surr']);
	} else {
		$surroundingWords = 0;		// 0 == no surrounding words shown, 4 means the two words on either side, etc. 
	}		
	
	if (isset($_GET['out']))
		$outFormat=$_GET['out'];
	else
		$outFormat = "anki";	
?>
<html>
<head>
<meta charset="UTF-8">
<LINK REL="stylesheet" HREF="/main.css" TYPE="text/css">
<link rel="image_src" href="http://www.kkava.com/img/vocab.png" />
<script>
	function sd() { window.scrollTo(0,document.body.scrollHeight); }
	function showAdvancedOptions(showOpts) {
		if (showOpts) {
			hide('advOptLink');
			show('basicOptLink');
			trshow('advOpts');
		} else {
			show('advOptLink');
			hide('basicOptLink');
			hide('advOpts');
		}
	}
	function hide(idToHide) {
		var result_style = document.getElementById(idToHide).style;
		result_style.display = 'none';
	}
	function show(idToHide) {
		var result_style = document.getElementById(idToHide).style;
		result_style.display = 'inline';
	}
	function trshow(idToHide) {
		var result_style = document.getElementById(idToHide).style;
		result_style.display = 'table-row';
	}
</script>
</head>
<body>
<h1>German Vocabulary Scraper</h1>
<table width=100%>
<tr>
<td width=35%>
<center>
<h3>
<form action="." method="GET">
<table border=0>
<tr>
<td>
	URL&nbsp;&nbsp;
</td>
<td>
	<input type="text" name="src" placeholder="http://www.reddit.com/r/de" value="<?php echo $focusURL; ?>" size="45" style="background-color:lightblue;border:1px dotted black;height:20px;"> <input type="submit" value="Go">
</td>
</tr>
<tr>
	<td>Output&nbsp;&nbsp;</td>
	<td>
		<select name="out" style="background-color:lightblue;border:1px dotted black;height:20px;">
			<option value="anki" <?php if ($outFormat == "anki") echo "selected"; ?>>Anki Flashcards</option>
			<option value="tsv" <?php if ($outFormat == "tsv") echo "selected"; ?>>Tab Separated Values (TSV) - Quizlet</option>
			<option value="none" <?php if ($outFormat == "none") echo "selected"; ?>>None</option>
		</select>
	</td>
</tr>
<tr id='advOpts' style="display: none;">
	<td></td>
	<td style='background-color:rgba(255,255,255,0.2);'>
		<input type="checkbox" name="ngram" <?php if ($useNgrams) echo "checked"; ?>>Intelligent word choice<br>
		<input type="checkbox" name="v" <?php if ($verboseMode) echo "checked"; ?>>Verbose mode<br>		
		Show surrounding words:&nbsp;&nbsp;<input type="text" name="surr" value="<?php echo $surroundingWords; ?>" size="4"><br>
	</td>
</tr>
</table>
<div id='advOptLink'>
	<a href="javascript:showAdvancedOptions(true);">Show options...</a>
</div>
<div id='basicOptLink' style='display: none;'>
	<a href="javascript:showAdvancedOptions(false);">Hide options</a>
</div>
</form>
</h3>
</center>
</td>
<td>
Study a flashcard deck of context-specific German words generated by the Vocabulary Scraper. You can then practice your reading comprehension by attempting to read the source website on your own. 
By focusing on current web content, you can learn words which are being spoken in German right now, by native German speakers.
<p>
The Vocabulary Scraper grabs words from the specified URL and translates them using a combination of <a href="http://dict.cc">dict.cc</a> and <a href="http://research.microsoft.com/en-us/collaboration/focus/cs/web-ngram.aspx">MS Web N-gram service</a>.
The N-grams serve to select the best English translation when there are multiple choices in dict.cc. Any such improvements are then saved to the dictionary for later use. 
The default output format is <a href="http://ankisrs.net/">Anki</a>, which is an inteligent free flashcard program available for <a href="https://play.google.com/store/apps/details?id=com.ichi2.anki&hl=en">Android</a>, <a href="https://itunes.apple.com/en/app/ankimobile-flashcards/id373493387?mt=8">iOS</a> and <a href="http://ankisrs.net/">desktop computers</a>.
Tab separated value output is also available for import into other flashcard programs such as <a href="https://quizlet.com/">Quizlet</a>.
<p>
Click the "Go" button at left to see a demo, or enter a URL of your own (it's free).
<br>
<center>
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top" style="margin-bottom:0px">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="DYDLQQH4DB8AG">
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>
</center>
</td>
</tr>
</table>
<hr>
<?php
	if ($focusURL === NULL) die();		// Only display the above HTML if src= not defined in URL parameter set
	
	$ver = 3.0;
	$numCharsSort = 5;			// Use first N common chars to form dict chunks
	$memLimit = 96 * 1024 * 1024 * 0.90;	// memory limit of 96 MB, in B, with safety factor
	$totalTime = microtime(TRUE);
	
	include "./dict.php";	
	
	//$cwd = getcwd();
	//echo "Current working dir is: $cwd<br>\n";
	//phpinfo(INFO_ALL);
	if ($verboseMode) {
		echo "Verbose output enabled.<br>\n";	
		echo "Scraping content from <a href=$focusURL target=_blank>$focusURL</a>:<br>\n";		
		if ($useNgrams) echo "Using n-gram data to choose best words.<br>\n";		
		echo "Time limit set at $timeLimit s.<br>\n";
		echo "Minimum word length set at $minWordLen characters.<br>\n";		
		echo "Surrounding words display set at $surroundingWords words.<br>\n";		
	}

	$time = microtime(TRUE);
	$rawHTML = file_get_contents("$focusURL");	// Download the site's HTML
	if ($rawHTML === FALSE) {
		echo "Couldn't find source $focusURL.<br>\n";
		die();
	} else {
		// Get page title
		$pageTitle = getTitle($rawHTML);
		// Decode html entities such as &uuml; -> �
		$cleanedContent = html_entity_decode($rawHTML, ENT_HTML401, "UTF-8");
		// Save some memory
		$rawHTML = null;
		// Remove scripts and stylesheets
		$cleanedContent = preg_replace('#<script(.*?)>(.*?)</script>#is', ' ', $cleanedContent);
		$cleanedContent = preg_replace('#<style(.*?)>(.*?)</style>#is', ' ', $cleanedContent);
		// Strip all HTML tags, replacing with spaces
		$cleanedContent = preg_replace('/<[^>]*>/', ' ', $cleanedContent);
		// Lowercase all sentence begginings to avoid false-noun hits in german dictionary
		$cleanedContent = preg_replace_callback(
			'/[.!?]\s*(\p{L})/u',
			function ($matches) { return mb_strtolower($matches[0], 'UTF-8'); }, 
    		$cleanedContent);
		// Only keep word characters (letters and numbers including unicode, spaces and dashes remain)
		$cleanedContent = preg_replace('/[^\p{L}\p{N} -]/u', ' ', $cleanedContent);
	}
	
	if ($verboseMode) echo "<hr>$cleanedContent";
	
	$words = preg_split('/\s+/', $cleanedContent);
	// $words = array_unique($words);							// Efficiency later on (removed for surrounding words feature)
	$cleanedContent = null;									// Save some memory
	$numWords = count($words);
	$time = microtime(TRUE) - $time;
	$wordsPerSec = number_format($numWords / $time, 3);
	if ($verboseMode) echo "$numWords words scraped in " . number_format($time, 3) . " s ($wordsPerSec words/s).<br>\n<hr>\n";
	
	// Look up each word in the dict and save ones which are found
	$time = microtime(TRUE);
	$vocabList = array();
	$dict = new Dictionary("./de-en_dict.cc_split_$numCharsSort/", $numCharsSort, $memLimit, $useNgrams, $improveDict, $verboseMode);
	
	// Used to compile statistics and color output. array(curWord, staticCount (for all words), color, description)
	// Note that array order is important. For example, the adjective trims are pretty brutal and likely to get false hits, so they go last. 
	$altWords['orig']	= array("", 0, "black",		"Word found");
	$altWords['lcase']	= array("", 0, "#ff9933",	"Lowercase alternate found");	
	$altWords['ucase']	= array("", 0, "#feae2d",	"Uppercase alternate (noun) found");
	$altWords['verb1']	= array("", 0, "yellow",	"Verb stem found (1 char + en)");
	$altWords['verb2']	= array("", 0, "lightgreen","Verb stem found (2 char + en)");
	$altWords['verb3']	= array("", 0, "#69d025",	"Verb stem found (3 char + en)");
	$altWords['verb4']	= array("", 0, "green",		"Verb stem found (1 char + n)");
	$altWords['verb5']	= array("", 0, "lightblue",	"Verb stem found (2 char + n)");
	$altWords['adj1']	= array("", 0, "#22ccaa",	"Adjective stem found (1 char)");
	$altWords['adj2']	= array("", 0, "#4444dd",	"Adjective stem found (2 char)");
	$altWords['trlit'] 	= array("", 0, "#3b0cbd",	"Transliterated word found");			// Check if the word entry in dict.cc is in transliterated form (i.e. probably a mistake)
	$altWords['utrlit']	= array("", 0, "#9933FF",	"Untransliterated word found");
	$altWords['comp'] 	= array("", 0, "pink",		"Compound word found");
	$altWords['noword']	= array("", 0, "#808080",	"Word not found");						// array[0] not used here
	$altWords['nochunk']= array("", 0, "#B2B2B2",	"Dictionary chunk not found");			// array[0] not used here
	
	$alreadyFound = array();
	foreach ($words as $idx => $DEword) {
		if ($DEword == '') continue;
		if (mb_strlen($DEword, 'UTF-8') < $minWordLen) {
			if ($verboseMode) echo "<br>\nShort word skipped: $DEword. "; 
			continue;
		}
		if (($timeLimit != 0.0) && (microtime(TRUE) - $totalTime > $timeLimit)) {
			echo "<p>Time limit of $timeLimit s exceeded. Truncating translation. Try reloading the page to get further through the translation.<br>\n";
			break;
		}
		if (in_array($DEword, $alreadyFound)) {
			if ($verboseMode) echo "<br>\nDuplicate word skipped: $DEword. ";
			continue;
		} else {
			$alreadyFound[] = $DEword;
		}		
		
		// Compile a list of possible alternate word forms, and their colors for display purposes
		$root1 = mb_strtolower(trimLastN($DEword, 1), 'UTF-8');
		$root2 = mb_strtolower(trimLastN($DEword, 2), 'UTF-8');
		$root3 = mb_strtolower(trimLastN($DEword, 3), 'UTF-8');
		$altWords['orig'][0] 	= $DEword;
		$altWords['lcase'][0]	= mb_strtolower($DEword, 'UTF-8');
		$altWords['ucase'][0]	= mb_ucfirst($DEword, 'UTF-8');	
		$altWords['verb1'][0]	= $root1 . 'en';
		$altWords['verb2'][0]	= $root2 . 'en';
		$altWords['verb3'][0]	= $root3 . 'en';
		$altWords['verb4'][0]	= $root1 . 'n';
		$altWords['verb5'][0]	= $root2 . 'n';
		$altWords['adj1'][0]	= $root1;
		$altWords['adj2'][0]	= $root2;
		$altWords['trlit'][0]	= Dictionary::transLit($DEword);	// Special check taken below to avoid early termination with words with umlauts in firstNChars	
		$altWords['utrlit'][0]	= Dictionary::unTransLit($DEword);		
		$altWords['comp'][0] 	= $DEword;
		
		
		// Perfom lookup
		$wordFound = FALSE;
		$dictChunkFound = FALSE;
		$shortStem = (strlen($altWords['orig'][0]) <= $numCharsSort);
		foreach ($altWords as $form => $altWord) {
			if ($verboseMode) echo "altWords: $form: $altWord[0]<br>\n";
			$DEword = $altWord[0];
			if ($DEword == "") continue;
			if (mb_strlen($DEword, 'UTF-8') < $minWordLen) {
				if ($verboseMode) echo "<br>\nShort alternate word skipped: $DEword. "; 
				continue;
			}			
			
			$color = $altWord[2];
			if ($form === 'comp')
				$result = $dict->lookup_compound($DEword);
			else
				$result = $dict->lookup($DEword);
			
			if ($result === NULL) {		// Dict chunk not found	
				if ($shortStem || $form === 'trlit')
					continue;			// In this case we want to seek other stem variations which will be in different chunks
				else
					break;				// If the word is long, then changing the inflection/conjugation won't change the fact that the chunk wasn't found, so quit
			} else {
				$dictChunkFound = TRUE;	// Dictionary chunk exists
			}
			
			if ($result !== FALSE) {	// Dictionary chunk exists AND word (or alternate) exists in it
				$wordFound = TRUE;
				$EN = $result;
				echo "<a style='color:$color;text-decoration:none;font-size:85%' href='https://translate.google.com/#de/en/$DEword' target='_blank'>$DEword</a> ";
				echo "<script>sd();</script>";
				$altWords[$form][1] ++;
				break;
			}
		}
		
		if (!$dictChunkFound) {				// Dictionary chunk DNE
			$altWords['nochunk'][1] ++;
			$DEword = $altWords['orig'][0];
			$color = $altWords['nochunk'][2];
			echo "<a style='color:$color;text-decoration:none;font-size:60%' href='https://translate.google.com/#de/en/$DEword' target='_blank'>$DEword</a> ";
			continue;
		} elseif (!$wordFound) {		// Word not found in chunk
			$altWords['noword'][1] ++;
			$DEword = $altWords['orig'][0];
			$color = $altWords['noword'][2];
			echo "<a style='color:$color;text-decoration:none;font-size:60%' href='https://translate.google.com/#de/en/$DEword' target='_blank'>$DEword</a> ";
			continue;
		}
		
		switch ($EN['POS']) {
		case 'n':
			$DEarticle = 'das ';
			$ENarticle = '';		// Note that this field is no longer used, as dict.cc has "to " in front of all english verbs
			$DEdecoration = '';
			break;
		case 'f':			// feminine
			$DEarticle = 'die ';
			$ENarticle = '';
			$DEdecoration = '';
			break;
		case 'pl':			// plural (gets decoration to distinguish from feminine 'die'
			$DEarticle = 'die ';
			$ENarticle = '';
			$DEdecoration = ' {pl}';
			break;
		case 'm':			// masculine
			$DEarticle = 'der ';
			$ENarticle = '';
			$DEdecoration = '';
			break;
		case 'verb':		// verbs	
			$DEarticle = '';
			$ENarticle = '';
			$DEdecoration = '';
			break;
		default:			// adjectives and unrecognized parts of speach
			$DEarticle = ''; 
			$ENarticle = '';
			$DEdecoration = '';
			break;
		}
		// Add on surrounding words decoration
		if ($surroundingWords > 0) {
			$context = getSurroundingWords($words, $idx, $surroundingWords);
			$DEdecoration .= "<hr align='center' style='border:none;border-top:1px dotted red;height:0px;line-height:0;'/><span style='color:red;font-size:75%;'>... $context ...</span>";
		}
		$vocabList[$ENarticle . $EN['full']] = $DEarticle . $DEword . $DEdecoration;	// 'Car' = 'das Auto {n}'
	}
	$alreadyFound = NULL;	// Clear some memory
	echo "<hr>\n";
	echo "<table>\n";
	echo "<tr><th colspan=2 style='text-align:center;'>Statistics</th></tr>\n";
	echo "<tr><th>Category</th><th>&nbsp;Count</th></tr>\n";
	$totWords = 0;
	foreach ($altWords as $type) {
		$count = $type[1];
		$color = $type[2];
		$descr = $type[3];
		echo "<tr><td><span style='color:$color'>$descr</span></td><td>&nbsp;$count</td></tr>\n";
		$totWords += $count;
	}
	echo "<tr><td>Total</td><td>&nbsp;$totWords</td></tr>\n";
	echo "</table>";
	
	$dict->freeMem();
	$time = microtime(TRUE)-$time;
	$vocabCount = count($vocabList);
	$wordsPerSec = number_format($totWords / $time, 3);
	$foundWordsPerSec = number_format($vocabCount / $time, 3);
	$time = number_format($time, 3);
	echo "<p>Processed $totWords words in $time s ($wordsPerSec words/s).<br>\n";
	echo "Found $vocabCount words in $time s ($foundWordsPerSec words/s).<br>\n";

	if ($vocabCount > 0) {

		// ANKI OUTPUT
		if ($outFormat == "anki") {
			$tempID = randStr(10);
			$tempPath = getOutPath($tempID, ".anki2");
			$outPath = getOutPath($tempID, ".apkg");
			$outURL = getOutURL($tempID);
			$SQLtemplate = "./out/_collection.anki2";
			$mediaTemplate = "./out/_media";
			
			$space = cleanOut(getOutPath());			// Enforce an out folder quota of 10 MB (... for each file type)
			if ($verboseMode) echo "Output space contained " . $space[0] . " B and freed " . $space[1] . " B.<br>\n";
			
			copy($SQLtemplate, $tempPath);				// Copy template to temp file (which will be deleted on completion)
			
			try {
				$ankiSQL = new SQLite3($tempPath);

				// Update the deck description
				$date = date("Y-m-d", time());
				$deckDetails = $ankiSQL->querySingle('SELECT decks FROM col;', TRUE)['decks'];
				//var_dump($deckDetails);
				$parsedURL = parse_url($focusURL);
				$deckName = "$date: $parsedURL[host] - $pageTitle";
				$curURL = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
				$deckDescription = "Vocabulary scraped from <a href=$focusURL>$focusURL</a> by <a href='$curURL'>kkava.com/vocab</a> on $date.<hr>Powered by <a href='http://dict.cc'>dict.cc</a>.";
				$deckDetails = preg_replace('/"desc": "([^"]*)"/', '"desc": "' . $deckDescription . '"', $deckDetails);
				$deckDetails = preg_replace('/"name": "([^"]*)"/', '"name": "' . $deckName . '"', $deckDetails);
				$deckDetails = SQLite3::escapeString($deckDetails);
				//echo "<hr>$deckDetails<hr>";
				$sqlCom = "UPDATE col SET decks='$deckDetails';";
				//echo "<hr>$sqlCom<hr>";
				$ankiSQL->exec($sqlCom);
				//$deckDetails = $ankiSQL->querySingle('SELECT decks FROM col;', true)['decks'];
				//var_dump($deckDetails);
				
				echo "<a id=ListTop></a>\n";
				echo "\n<h1>Scraped vocabulary list ($vocabCount)</h1>\n";
				echo "<p><a href=$outPath>Download Anki flashcard deck here</a>.<br>\n";
				echo "<table>\n";
				echo "<tr><th>German</th><th>English</th></tr>\n";
				$lastNID = 0;
				$lastCID = 0;
				$lastCID2 = 0;
				$cardCount = 0;	// Fwd and Rev cards count as one.
				foreach ($vocabList as $EN => $DE) {
					echo "<tr><td>$DE</td><td>$EN</td></tr>\n";
					// Create a new NOTE
					//	id: 	timestampID(col.db, "notes")
					//	guid:	Random string of 10 chars (64 bits) e.g. L:3VH<Xty7, with special chars: !#$%&()*+,-./:;<=>?@[]^_`{|}~
					//	mid:	Model ID. Fixed i.e. 1428070445250
					//	mod:	Time in integer seconds (effectively like an ordered random set of ints). int(time.time())
					//	usn:	always -1
					//	tags:	user defined string, e.g. can be part of speech
					//	flds:	concatenated string of both card sides, separated by \x1f e.g. English Entry\x1fGerman Entry
					//	sfld:	LHS entry, e.g. "English Entry"
					//	csum:	fieldChecksum(val): int(checksum(stripHTMLMedia(data).encode("utf-8"))[:8], 16)
					//				-checksum(data): sha1(data).hexdigest()
					//	flags:	0
					//	data:	null, e.g. ""
					$nid = (int)(microtime(TRUE)*1000);	// In anki, the uniqueness is ensured by querying the database.
					if ($nid <= $lastNID)				// ... we do it more cheaply.
						$nid = $lastNID + 1;
					$lastNID = $nid;
					$guid = randStrExt(10);				// In anki, this is incremented (... in "Base 92") from note to note. However a random string should work.
					$mid = 1428070445250;
					$mod = (int)(microtime(TRUE));
					$tags = "";
					$flds = sqlEsc($DE . "\x1f" . $EN);
					$sfld = sqlEsc($DE);
					$csum = (int)(hexdec(getFirstN(sha1($flds), 8)));
					$sql =<<<EOF
					  INSERT INTO notes (id,guid,mid,mod,usn,tags,flds,sfld,csum,flags,data)
					  VALUES ($nid, '$guid', $mid, $mod, -1, '$tags', '$flds', '$sfld', $csum, 0, '' );
EOF;
					$ankiSQL->exec($sql);	// !! probably want to save string and multi_exec at end of loop, also error checking!
					// Create two new cards (DE->EN and reverse) referencing the new note ID (nid)
					// Get the last entry id in notes to reference in cards
					// See: https://github.com/dae/anki/blob/master/anki/cards.py for refence code
					//	id:		timestampID(col.db, "notes")
					//	nid:	note id from note insertion
					//	did:	1
					//	ord:	order, 0: fwd, 1: rev
					//	mod:	Time in integer seconds (effectively like an ordered random set of ints). int(time.time())
					//	usn:	-1
					//	type:	0
					//	queue:	0
					//	due:	integer starting from 1 on first fwd-rev card set (probably controls card order)
					//	ivl:	0
					//	factor:	0
					//	reps:	0
					//	lapses:	0
					//	left:	0
					//	odue:	0
					//	odid:	0
					//	flags:	0
					//	data:	""
					$cid = (int)(microtime(TRUE)*1000);	// In the original anki source code, the uniqueness is ensured by querying the database.
					if ($cid <= $lastCID)			// ... we do it more cheaply.
						$cid = $lastCID + 1;
					$lastCID = $cid;		
					$cardCount++;
					// Fwd card
					$sql =<<<EOF
					  INSERT INTO cards (id,nid,did,ord,mod,usn,type,queue,due,ivl,factor,reps,lapses,left,odue,odid,flags,data)
					  VALUES ($cid, $nid, 1, 0, $mod, -1, 0, 0, $cardCount, 0, 0, 0, 0, 0, 0, 0, 0, '' );
EOF;
					$ankiSQL->exec($sql);	// !! probably want to save string and multi_exec at end of loop, also error checking!	
					// Reverse card
					$cid2 = $cid+1;
					if ($cid2 <= $lastCID2)
						$cid2 = $lastCID2 + 1;
					$lastCID2 = $cid2;	
					$sql =<<<EOF
					  INSERT INTO cards (id,nid,did,ord,mod,usn,type,queue,due,ivl,factor,reps,lapses,left,odue,odid,flags,data)
					  VALUES ($cid2, $nid, 1, 1, $mod, -1, 0, 0, $cardCount, 0, 0, 0, 0, 0, 0, 0, 0, '' );
EOF;
					$ankiSQL->exec($sql);	// !! probably want to save string and multi_exec at end of loop, also error checking!		
				}
				echo "</table>\n";
				// Save anki database
				$ankiSQL->close();
				
				// Zip it along with _media
				//	Filenames in zip must be
				//		collection.anki2
				//		media
				$apkgFile = new ZipArchive();
				$apkgFile->open($outPath, ZipArchive::CREATE);
				$apkgFile->addFile($tempPath, "collection.anki2");
				$apkgFile->addFile($mediaTemplate, "media");
				$apkgFile->close();
				// Erase temp file
				unlink($tempPath);				
				
			} catch (Exception $e) {
				echo "Error: couldn't open SQlite database: $tempPath<br>\n";
			}

		// TSV OUTPUT
		} elseif ($outFormat == "tsv") {
			$tempID = randStr(10);
			$outPath = getOutPath($tempID, ".tsv");
			$outURL = getOutURL($tempID, "tsv");
				
			$space = cleanOut(getOutPath());	// Enforce an out folder quota of 10 MB (... for each file type)
			if ($verboseMode) echo "Output space contained " . $space[0] . " B and freed " . $space[1] . " B.<br>\n";
			
			echo "<a id=ListTop></a>\n";
			echo "\n<h1>Scraped vocabulary list ($vocabCount)</h1>\n";
			echo "<p><a href=$outPath>Download TSV flashcard deck here</a>.<br>\n";
			echo "<table>\n";
			echo "<tr><th>German</th><th>English</th></tr>\n";
			$cardCount = 0;	// Fwd and Rev cards count as one.
			
			$TSVoutFile = fopen($outPath, "w");
			if ($TSVoutFile === FALSE) {
				echo "Error: couldn't open TSV file: $outPath<br>\n";
			} else {
				foreach ($vocabList as $EN => $DE) {
					echo "<tr><td>$DE</td><td>$EN</td></tr>\n";
					fwrite($TSVoutFile, "$DE\t$EN\n");
				}
				fclose($TSVoutFile);
				echo "</table>\n";
			}
			
		// HTML TABLE OUTPUT ONLY
		} else {
			echo "<a id=ListTop></a>\n";
			echo "\n<h1>Scraped vocabulary list ($vocabCount)</h1>\n";
			echo "<table>\n";
			echo "<tr><th>German</th><th>English</th></tr>\n";			
			foreach ($vocabList as $EN => $DE) {
				echo "<tr><td>$DE</td><td>$EN</td></tr>\n";
			}
			echo "</table>\n";
		}
	}
	
	// CLOSEOUT
	//dbg print profile();
	$totalTime = number_format(microtime(TRUE) - $totalTime, 3);
	if ($verboseMode) echo "<p>Total time: $totalTime s.<br>\n";	
	
	// Write out log file
	$userIP = $_SERVER['REMOTE_ADDR'];
	date_default_timezone_set("America/New_York");
	$time = date("Y-m-d h:i:sa");
	$logFile = fopen("./usage.log", 'a');
	if ($logFile !== NULL) {
		fwrite($logFile, "$time\t$ver\t$userIP\t$focusURL\t$numWords\t$vocabCount\t$wordsPerSec\n");
		fclose($logFile);
	}
	
	// Provide link and redirect (w/ javascript)	
	echo "<script>\nwindow.location.hash = '#ListTop';\n";		// Scroll to top of vocab list
	if (isset($outURL)) echo "window.location='$outURL';\n";		// Redirect to file download
	echo "</script>\n";
	
	die();
	
/* ---------------------------------------------------------------------------------------------------------*/
	
	function getTitle($content) {
		if (preg_match("/\<title\>(.*)\<\/title\>/i",$content,$title) === 0)
			return "Untitled page";
		else
			return $title[1];
	}

	// Returns the surrounding words at a given index in an array of words
	function getSurroundingWords($words, $pos, $num) {
		$num++;
		$firstIndex = $pos - (int)($num / 2);
		if ($firstIndex < 0)
			$firstIndex = 0;
		$lastIndex = $pos + (int)($num / 2);
		if ($lastIndex > count($words))
			$lastIndex = count($words);
		return implode(' ', array_slice($words, $firstIndex, $lastIndex-$firstIndex));
	}

	// Returns randomized filename for unique semi-persistent output
	function getOutPath($tempID = "", $ext = "") {
		return "./out/$tempID$ext";
	}
	
	function getOutURL($key, $type="anki") {
		return "http://www.kkava.com/vocab/out/?key=$key&type=$type";
	}
	
	// Random string from MD5 (filename safe)
	function randStr($len = 10) {
		return substr(str_shuffle(MD5(microtime())), 0, $len);
	}
	
	// Random string with some special characters in it (not filename safe)
	function randStrExt($len = 10) {
		$basis = implode('', array_merge(range('A', 'Z'), range('a', 'z'), range('0', '9'))) . "!#$%&()*+,-./:;<=>?@[]^_`{|}~";
		return getFirstN(str_shuffle($basis), $len);
	}

	// Returns first N chars of a string (unicode safe)
	function getFirstN($str, $N) {
		return trim(mb_substr($str, 0, $N, 'UTF-8'));
	}
	
	function getLastN($str, $N) {
		return trim(mb_substr($str, -1*$N, NULL, 'UTF-8'));
	}
	
	function trimLastN($str, $N) {
		return trim(mb_substr($str, 0, -1*$N, 'UTF-8'));
	}
	
	function mb_ucfirst($string, $e ='UTF-8') {
        if (function_exists('mb_strtoupper') && function_exists('mb_substr') && !empty($string)) { 
            $string = mb_strtolower($string, $e); 
            $upper = mb_strtoupper($string, $e); 
            preg_match('#(.)#us', $upper, $matches); 
            $string = $matches[1] . mb_substr($string, 1, mb_strlen($string, $e), $e); 
        } else { 
            $string = ucfirst($string); 
        } 
        return $string; 
    }
    
    // Escapes apostrophe char for SQL queries
    function sqlEsc($str) {
    	return str_replace("'","''",$str);	
    }
	
	// Saves the time taken by the calling function in $GLOBALS['profile']
	function profile() {
		global $profile;
		if ($profile === null) $profile = array();
		list(, $caller) = debug_backtrace(false);
		$caller = $caller['function'];
		// Init the timer entry for this function
		if (!array_key_exists("$caller", $profile)) {
				$profile["$caller"]['Function'] = $caller;
				$profile["$caller"]['Calls'] = 1;
				$profile["$caller"]['Total Time'] = 0.0;
				$profile["$caller"]['Start Time'] = 0.0;
				$profile["$caller"]['End Time'] = 0.0;
				$profile["$caller"]['Average Time'] = 0.0;
		}
		if ($profile["$caller"]['Start Time'] === 0.0) {		// Timer is starting
			$profile["$caller"]['Calls'] += 1;
			$profile["$caller"]['Start Time'] = microtime(TRUE);
		} else {								// Timer is now turning off and recording time delta
			$profile["$caller"]['End Time'] = microtime(TRUE);
			$thisTime = $profile["$caller"]['End Time'] - $profile["$caller"]['Start Time'];
			$profile["$caller"]['Total Time'] += $thisTime;
			$profile["$caller"]['Average Time'] = $profile["$caller"]['Total Time'] / $profile["$caller"]['Calls'];
			$profile["$caller"]['Start Time'] = 0.0;			// 0.0 means timer is not set/active
		}
	}
	
	// Prints out profiler results
	function print_profile() {
		global $profile;
		if ($profile === null)
			return;
		echo "Profile:<br>\n";
		foreach ($profile as $callerName => $callerData) {
			echo "<hr>\n";
			foreach ($callerData as $stat => $value) {
				echo "    $stat:\t$value<br>\n";
			}
		}
		echo "<hr>\n";
	}
	
	function err($lev, $msg, $errfile, $errline, $errcontext) {
		echo "<p>Error [$lev]: $msg [$errfile:$errline]<br>\n";
	}
	
	// Cleans out a folder based on a size limit and mask
	//	The mask indicates deletable files
	//	Returns an array with the original size of the folder and the amount of space freed
	function cleanOut($path, $byteLimit = 10000000, $mask = "/.tsv$|.apkg$/") {
		$total_size = 0;
		$deleted_size = 0;
		$cleanPath = rtrim($path, '/'). '/';
		$files = scan_dir($cleanPath);
		foreach($files as $t) {
			if ($t=="." || $t=="..") {
				continue;
			} else {
				$currentFile = $cleanPath . $t;
				if (is_file($currentFile)) {
					$size = filesize($currentFile);
					$total_size += $size;
					// Delete subsequent files over limit
					if ($total_size >= $byteLimit && preg_match($mask, $t) === 1) {
						$deleted_size += $size;
						//$date = date("y-m-d h:m:s", filemtime($currentFile));
						//echo "Deleting file $currentFile of size $size B and date $date.<br>\n";
						unlink($currentFile);
					}
				}
			}
		}
		return array($total_size, $deleted_size);
	}
	
	// Like scandir, but returns ordered array with newest files first
	function scan_dir($dir) {
		$ignored = array('.', '..', '.svn', '.htaccess');
	
		$files = array();    
		foreach (scandir($dir) as $file) {
			if (in_array($file, $ignored)) continue;
			$files[$file] = filemtime($dir . '/' . $file);
		}
	
		arsort($files);
		$files = array_keys($files);
	
		return ($files) ? $files : false;
	}	
?>
	
</body>
</html>
