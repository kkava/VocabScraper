<?php 
/* 
Dictionary class for managing dictionary chunks
	-Word lookup
	-N-gram improvements
	-Memory managment and cacheing
  
*/

class Dictionary {
	private $dictPath = '.';
	private $memLimit = 90000000;
	private $splitLen = 5;
	private $useNgram = TRUE;
	private $improveD = TRUE;
	private $verboseMode = FALSE;
	private $dictChunks = array();
	
	// Returns TRUE if the path is valid, else FALSE 
	function __construct($path = '.', $split = 5, $mem = 90000000, $ngram = TRUE, $imp = TRUE, $verb = FALSE) {
		$this->dictPath = $path;
		$this->memLimit = $mem;
		$this->splitLen = $split;
		$this->useNgram = $ngram;
		$this->improveD = $imp;
		$this->verboseMode = $verb;
		$this->dictChunks = array();
		// Ensure path is valid
		if (!file_exists($path))
			throw new Exception("Dictionary:: path not found: $path");
	}
	
	// Looks up a word, caches and improves dict chunk
	// Returns entire dictionary line (including N-gram on the end)
	//	1. Check if chunk is cached
	//		1.1 Load and cache it if not
	//		1.2 Perform memory managment
	//	2. Lookup word
	//	3. If N-gram data missing, 
	//		3.1 Fetch it and
	//		3.2 Mark that the file needs to be saved (or perfom a microsave)
	//	4. Return NULL if dict chunk DNE, FALSE if word wasn't found in chunk or the best dict entry if found.
	public function lookup($DE) {
	
		// Memory managment
		if (memory_get_usage(TRUE) > $this->memLimit) {
			if ($this->verboseMode) echo "\n<p>Memory limit of $this->memLimit B exceeded. Flushing dictionary chunks.<br>\n";
			$this->freeMem();
			$curUsage = memory_get_usage(TRUE);
			$curUsagePercent = number_format(100*$curUsage/$this->memLimit, 2);
			if ($this->verboseMode) echo "Memory usage is now $curUsage B ($curUsagePercent%).<p>\n";
		}
		
		$firstNChars = mb_strtolower($this->transLit(getFirstN($DE, $this->splitLen)), 'UTF-8');	// May be more than N chars due to transliteration of German chars (e.g. ö -> oe)
		$first = getFirstN($DE, $this->splitLen);
		$trans = $this->transLit($DE);
		$lower = mb_strtolower($DE, 'UTF-8');
		if ($this->verboseMode) echo "<br>[$first, $trans, $lower]: $DE -> $firstNChars<br>\n";
		
		if (!isset($this->dictChunks[$firstNChars])) {				// Only load the dictionary chunk once
			$this->initDictChunk($firstNChars);
			//$this->printChunkSummary();
		}

		if (!isset($this->dictChunks[$firstNChars])) {			// Dictionary chunk DNE
			return NULL;										// Null means dict chunk not found
		} else {												// Dictionary chunk was found
			if (isset($this->dictChunks[$firstNChars][$DE])) {	// Word found
				return $this->getBestEntry($this->dictChunks[$firstNChars][$DE]);
			} else { 
				return FALSE;									// FALSE means word wasn't found
			}
		}
	}
	
	// Uses reverse lookup within a compound word to get fragments
	//	1. Get dict chunk for leading fragment
	//	2. For each entry in the chunk, check if it fits at the start of the compound word (i.e. reverse lookup)
	//		+ Might be faster to just check the word by removing one char at a time using fwd lookup
	//	3. If one found, save fragment translation, remove the found fragment and repeat
	//	-Case insensitive
	//	-Use only cached N-gram data
	// 	-Returns null if first fragment not found
	//	-Limited to finding fragments >= $splitLen in length
	// !! Completely untested code which probably won't work very well
	public function revLookupCompoundWord($DE) {
		$someWordsFound = FALSE;
		$compoundTranslation = "";
		$DE = mb_strtolower($DE, 'UTF-8');
		
		while ($DE != "") {
			// Memory managment
			if (memory_get_usage(TRUE) > $this->memLimit) {
				if ($this->verboseMode) echo "\n<p>Memory limit of $this->memLimit B exceeded. Flushing dictionary chunks.<br>\n";
				$this->freeMem();
				$curUsage = memory_get_usage(TRUE);
				$curUsagePercent = number_format(100*$curUsage/$this->memLimit, 2);
				if ($this->verboseMode) echo "Memory usage is now $curUsage B ($curUsagePercent%).<p>\n";
			}
			
			// !! This prevents finding fragments which are shorter than $splitLen
			$firstNChars = mb_strtolower($this->transLit(getFirstN($DE, $this->splitLen)), 'UTF-8');	// May be less, or more than N chars due to transliteration of German chars (e.g. ö -> oe)
						
			if (!isset($this->dictChunks[$firstNChars])) {			// Only load the dictionary chunk once
				$this->initDictChunk($firstNChars);
			}
	
			if (!isset($this->dictChunks[$firstNChars])) {			// Dictionary chunk DNE
				if ($someWordsFound) {
					$compoundTranslation .= "-'$DE'";
					break;											// Break to return current incomplete return string
				} else {
					return NULL;									// Return NULL to indicate that no word fragments were found
				}
			} else {												// Dictionary chunk was found
				// Loop through dict contents, checking each word against the start of the current $DE string
				foreach ($this->dictChunks[$firstNChars] as $DEkey => $entry) {
					$DEcleanLower = mb_strtolower($entry['DEclean'], $encoding = 'UTF-8');
					
					if (preg_match("/^$DEcleanLower.*/u", $DE) === 1) {		// Found by reverse lookup
						$entry = getBestEntry($entry);
						
						if ($someWordsFound) {
							$compoundTranslation .= " " . $entry['ENclean'];
						} else {
							$compoundTranslation .= $entry['ENclean'];
						}
						$someWordsFound = TRUE;
						$DE = mb_substr($DE, strlen($DEclean), NULL, 'UTF-8');	// Trim found fragment from front
						break;																// Stop looking through this chunk
					}
				}
			}
		}
		return $compoundTranslation;	// !! Need to refactor to return entry struct
	}	
	
	// Wrapper for compound word lookup interface.
	//		Returns entry-formatted array, or FALSE
	// Return cases:
	// 1. Chunk found but word lookup failed: Return FALSE
	//		Indicators: only one fragment
	// 2. Only one fragment found within word: Return FALSE
	//		Indicators: two fragments, but last one has ' char in it
	//			-Also check if untranslated fragment is bigger
	// 3. Two or more fragments completely translated: Return imploded string
	//		Indicators: else from above
	public function lookup_compound($DE) {
		$resultArray = $this->fwdLookupCompoundWord(mb_strtolower($DE,'UTF-8'));
		$frags = count($resultArray);
		if ($frags <= 1) {
			return FALSE;
		} elseif ($frags === 2 && strpos($resultArray[1], "'") !== FALSE && (mb_strlen($resultArray[0], 'UTF-8') < mb_strlen($resultArray[1]))) {
			return FALSE;
		} else {
			$EN = implode($resultArray, "-");
			$toLang = array();
			$toLang['POS']	= "compound";
			$toLang['dict'] = "$DE\t$EN\tcompound\t0.0";
			$toLang['DE'] = $DE;
			$toLang['DEclean'] = $DE;
			$toLang['ENclean'] = $EN;
			$toLang['full'] = $EN . " [compound]";
			$toLang['ngram'] = 0.0;
			$toLang['score'] = 0.0;
			return $toLang;
		}
	}
	
	// Recursive lookup of compound words
	//		! Assumes lower case starting condition
	//		! Non-user configurable min fragment length parameter
	//		Returns an array of english word translations (last entry will hold untranslatable extras)
	//	1. Lookup $left
	//	1.1. If not found, trim one char into $right and continue
	//	1.2. If found, accumulate translation and continue recursively using $right

	private function fwdLookupCompoundWord($wholeWord, $splitPos = -1, $minFragLen = 4) {
		$wholeWordLen = mb_strlen($wholeWord, 'UTF-8');
		if ($wholeWordLen === 0)										// TERMINATE: no chars left - fully translated success
			return array();
		if ($splitPos === -1)											// Initializing call, so $splitPos is whole word
			$splitPos = $wholeWordLen;
		
		// Do-While loop efficiently adjusts $splitPos for this recursive iteration
		do {
			if ($this->verboseMode) echo "<br>\nfwdLookup: $wholeWord / $splitPos";			
			if ($splitPos < $minFragLen) {
				$firstChar = mb_substr($wholeWord, 0, 1, 'UTF-8');
				$first2Char = mb_substr($wholeWord, 0, 2, 'UTF-8');
				// Check for binding characters such as 's', 'n', 'en' (plurals) and 't' (wtf?)
				if ($firstChar === 's' || $firstChar === 'n' || $firstChar === 't' || $firstChar === '-') {
					if ($this->verboseMode) echo "<br>\nfwdLookup: trimming residual binding chars.<br>\n";
					return $this->fwdLookupCompoundWord(mb_substr($wholeWord, 1, NULL, 'UTF-8'));
				} elseif ($first2Char === 'en') {
					if ($this->verboseMode) echo "<br>\nfwdLookup: trimming residual binding chars.<br>\n";
					return $this->fwdLookupCompoundWord(mb_substr($wholeWord, 2, NULL, 'UTF-8'));					
				} else {
					if ($this->verboseMode) echo "<br>\nfwdLookup: giving up: $wholeWord / $splitPos<br>\n";
					return array("'$wholeWord'");							// TERMINATE:: some leftover untranslated chars
				}				
			}
			$left = mb_substr($wholeWord, 0, $splitPos, 'UTF-8');
			$fragResult = $this->lookup($left);
			
			if ($fragResult === NULL) {										// Chunk not found, shift letters to the right and retry
				if ($splitPos >= $this->splitLen)
					$splitPos = $this->splitLen - 1;						// OPTIMIZE:: no need to check this same non-existant dict chunk again
				else 
					$splitPos--;
				//return $this->fwdLookupCompoundWord($wholeWord, $splitPos);	// RECUR:: without accumulating an array entry
			} elseif ($fragResult === FALSE) {								// Chunk found, word not found in it, check ucase
				$leftUC = mb_ucfirst($left);
				$fragResult = $this->lookup($leftUC);	
				if ($fragResult === NULL || $fragResult === FALSE) {		// Chunk not found, shift letters to the right and retry (note that $fragResult === NULL cannot be true, because chunk keys are case insensitive and we've already checked the lcase version (thus no need to apply similar $splitLen optimization here) 
					$splitPos--;
					//return $this->fwdLookupCompoundWord($wholeWord, $splitPos);	// Recur, but without accumulating an array entry
				}
			} else {														// Word found
				break;
			}
		} while ($fragResult === NULL || $fragResult === FALSE);
		
		// If we made it this far, then the word $left (or $leftUC) was found
		if ($fragResult['POS'] === 'verb') {
			$EN = array($this->gerund($fragResult['ENclean']));	
		} else {
			$EN = array($fragResult['ENclean']);
		}
		if ($this->verboseMode) echo "<br>\nfwdLookup: found fragment: $EN[0]<br>\n";
		$right = mb_substr($wholeWord, $splitPos, NULL, 'UTF-8');
		return array_merge($EN, $this->fwdLookupCompoundWord($right));	// Recur with remaining untranslated string
	}
	
	// Returns the best entry from the dict entry passed as $entry 
	// (which may contain multiple entries)
	private function getBestEntry(&$entry) {		
		
		if ($this->isMultipleEntry($entry)) {	// Duplicate word case
			
			if (!$this->useNgram) {
				return $entry[0];				// Just return first entry if we're not using N-grams
			}
			
			$firstNChars = mb_strtolower($this->transLit(getFirstN($entry[0]['DEclean'], $this->splitLen)), 'UTF-8');	// May be more than N chars due to transliteration of German chars (e.g. ö -> oe)			
			$DE = $entry[0]['DE'];
			
			$bestWord = NULL;
			foreach ($entry as &$word) {		// Iterate over duplicates to find the best one
				$gotNgram = FALSE;
				if ($word['ngram'] === NULL && $this->useNgram) {
					if ($word['ENclean'] !== "") {
						if ($this->verboseMode) { 
							echo "+";
							$ngramTimer = microtime(TRUE);
						}
						$NG = $this->getMSNgram($word['ENclean']);			// This is very slow!
						if ($this->verboseMode) { 
							echo " [N-gram time: " . number_format(microtime(TRUE) - $ngramTimer, 4) . " s]<br>\n";
						}
					} else {
						if ($this->verboseMode) echo "|";
						$NG = 0.0;
					}
					$gotNgram = TRUE;
					$word['ngram'] = $NG;
					
					// Update the dictLine with new $NG
					// Check if the line already has an N-gram on it
					$splitLine = explode("\t", $word['dict']);
					if (count($splitLine) > 3) {		// If so, split it off and replace it
						$splitLine[3] = "$NG";
						$word['dict'] = implode("\t", $splitLine);
					} else {							// If no N-gram, then just append it
						$word['dict'] = $word['dict'] . "\t$NG";							
					}

					// Update the score
					$word['score'] = $this->calcWordScore($word['dict']);
					
					if ($this->verboseMode) echo "\n<br>dictModified [$firstNChars]: " . $word['dict'] . "<br>\n";
					$this->dictChunks[$firstNChars]['__modified'] = TRUE;
				} elseif ($this->useNgram) {
					if ($this->verboseMode) echo "-";
				}
				
				// Update best word
				if ($bestWord === NULL) {
					$bestWord = $word;
				}
				$replacement = $word['full'];
				$highScore = $word['score'];
				$replacee = $bestWord['full'];
				$replaceeScore = $bestWord['score'];
				$dictPathForLink = $this->dictChunks[$firstNChars]['__path'];						
				if ($word['score'] > $bestWord['score']) {
					if ($this->verboseMode) echo "<br>\n<span style='font-size:75%'><a href=$dictPathForLink>$DE</a> :: Replacing <span style=color:red>$replacee [" . number_format($replaceeScore, 3) . "]</span> with <span style=color:green>$replacement [" . number_format($highScore, 3) . "]</span></span> ";
					$bestWord = $word;
				} else {
					if ($this->verboseMode) echo "<br>\n<span style='font-size:75%'><a href=$dictPathForLink>$DE</a> :: Keeping <span style=color:red>$replacee [" . number_format($replaceeScore, 3) . "]</span> instead of <span style=color:green>$replacement [" . number_format($highScore, 3) . "]</span></span> ";
				}
			}
			
			if ($this->improveD) {
				//$this->printDictChunk($firstNChars);
				$this->saveDictChunk($firstNChars);
			}				
			
			if ($bestWord === NULL) {
				// Error, should have picked one of them, just grab the first one	
				echo "<p>Error: Couldn't find best word for multi-entry $DE.<br>\n";
				if (isset($word[0])) {
					return $word[0];
				} else {
					echo "<p>Error: Couldn't find any word for multi-entry $DE.<br>\n";
					return FALSE;						// FALSE means word not found
				}
			} else {
				return $bestWord;
			}
			
		} else {
			return $entry;	// Simplest case, one unique match was found
		}
	}
	
	// Initializes a chunk of dictionary based on the given path.
	// Returns a dictionary with keys that are simple DE words
	//	and values that are structures like 
	/*		$toLang['POS']	= $partOfSpeach;
			$toLang['dict'] = $dictLine;
			$toLang['DE'] = $DE;
			$toLang['DEclean'] = $DEclean;
			$toLang['ENclean'] = $ENclean;
			$toLang['full'] = $EN;
			$toLang['ngram'] = $NG;
			$toLang['score'] = $this->calcWordScore($dictLine); */
	//	If multiple entries exist for a given key, they are stored in an array under that key.
	//	If the N-gram has never been fetched, it will be flagged with NULL.
	// Returns NULL if the chunk DNE.
	private function initDictChunk($dictKey) {
		// Open dict file for reading
		$dictChunkPath = $this->getDictChunkPath($dictKey);
		if (!file_exists($dictChunkPath)) {
			if ($this->verboseMode) echo "<br>\nDict chunk not found [$dictKey]: $dictChunkPath<br>\n";
			return NULL;
		}
		$dictLines = file($dictChunkPath, FILE_IGNORE_NEW_LINES);
		$rawEntries = count($dictLines);
		if ($rawEntries == 0) {
			echo "<p>Error: Dictionary chunk had no entries [$dictKey]: $dictChunkPath<br>\n";
			return NULL;
		}
		//if ($this->improveD && $this->useNgram)
		//	echo "<hr>Improving dictionary <a href=$dictChunkPath target=_blank>$dictChunkPath</a>.";
		
		// Parse key-value pairs from each line (key: from_lang, value: to_lang)
		$dict = array();
		$replaceLines = array();
		$lineNum = 0;				
		$skipped = 0;				// Count of skipped lines (i.e. those with multi-word DE entries)
		$dictModified = FALSE;		// if the dictionary is modified (i.e. added ngram data)
		//echo "<table>\n";
		//echo "<tr><td>German</td><td>English</tr>\n";
		$time = microtime(TRUE);
		foreach ($dictLines as $dictLine) {
			$lineNum++;			

			$toLang = array();
			$matches = array();
			//echo "Line: $dictLine<br>\n";
			$elements = explode("\t",$dictLine);
			$elementCount = count($elements);
			$DE = $elements[0];
			$EN = $elements[1];
			$POS = $elements[2];
			if ($elementCount == 4 && $this->useNgram) {
				$NG = floatval($elements[3]);
			} else {
				$NG = NULL;							// NULL flag means that NG has never been fetched
			}
			$ENclean = $this->removeDecorations($EN);		// Used for n-gram searches
			$DEclean = $this->removeDecorations($DE);		// Used for sensing words vs phrases, and as raw key into dictionary entry
			
			// Build the entry
			$toLang['dict'] = $dictLine;					// !! In future, could just save this only to save memory
			$toLang['DE'] = $DE;
			$toLang['DEclean'] = $DEclean;
			$toLang['ENclean'] = $ENclean;
			$toLang['full'] = $EN;
			$toLang['ngram'] = $NG;
			$toLang['score'] = $this->calcWordScore($dictLine);
			preg_match('/\{(.*?)\}/', $DE, $matches); 		// Get the gender or part of speech between {}
			if (count($matches) < 2) {	// If not a noun...
				$toLang['POS'] = trim($POS);				// verb, adj, adj past-p, etc.
			} else $toLang['POS'] = trim($matches[1]);		// m, n, f, pl			
			
			// Repeat DE word block
			if (isset($dict[$DEclean])) {
				if ($this->isMultipleEntry($dict[$DEclean])) {		// Already a multiple entry
					array_push($dict[$DEclean], $toLang);	// Add to end of pre-existing array of entries
				} else {									// A single entry, about to become a multiple entry
					$multiEntry = array();
					array_push($multiEntry, $dict[$DEclean], $toLang);
					$dict[$DEclean] = $multiEntry;
				}
			} else {
				$dict[$DEclean] = $toLang;	
			}
		}
		
		$time = microtime(TRUE) - $time;
		$dict['__count'] = $lineNum;
		$dict['__modified'] = FALSE;
		$dict['__path'] = $dictChunkPath;
		$perEntryTime = number_format($lineNum / $time,3);
		//echo "Dictionary loaded $entries entries in $time s ($perEntryTime entries/s).<br>\n";
		$loadCompleteness = number_format(100 * $lineNum / $rawEntries, 2);
		//echo "Loaded $loadCompleteness% of entries. (Can be non-100% due to redundant entries or poorly formatted lines.)<br>\n";
		$dictLines = NULL;
		$replaceLines = NULL;
		$this->dictChunks[$dictKey] = $dict;		// Enter the chunk in the array of active chunks
		//$this->printDictChunk($dictKey);
	}
	
	private function printChunkSummary() {
		echo "<table>\n";
		echo "<tr><td>Dictionary key</td><td>No. of entries</td></tr>\n";
		foreach ($this->dictChunks as $key => $value) {
			$count = $value['__count'];
			echo "<tr><td>$key</td><td>$count</td></tr>\n";
		}
		echo "</table>\n";
	}
	
	private function printDictChunk($dictKey) {
		$lineNum = 0;
		echo "<table>\n";
		echo "<tr><td>Line No.</td><td>German word key</td><td>German entry</td><td>English entry</td><td>English N-gram</td></tr>\n";
		foreach ($this->dictChunks[$dictKey] as $DEword => $EN) {	// $EN may be an entry, or a list of entries
			if ($this->isMultipleEntry($EN)) { 
				foreach ($EN as $subIndex => $ENsub) {
					$lineNum++;
					$ENfull = $ENsub['full'];
					$ngram = $ENsub['ngram'];
					$DE = $ENsub['DE'];
					echo "<tr><td>$lineNum.$subIndex</td><td><span style='color:red'>$DEword</style></td><td>$DE</td><td>$ENfull</td><td>$ngram</td></tr>\n";
				}
			} elseif (strpos($DEword, "__") !== FALSE) {
				echo "<tr><td>-</td><td>$DEword</td><td>$EN</td><td></td><td></td></tr>\n";
			} else {
				$lineNum++;
				$ENfull = $EN['full'];
				$ngram = $EN['ngram'];
				$DE = $EN['DE'];
				echo "<tr><td>$lineNum</td><td>$DEword</td><td>$DE</td><td>$ENfull</td><td>$ngram</td></tr>\n";
			}
		}
		echo "</table>\n";
	}
	
	// Saves a dictionary chunk, overwriting the existing chunk
	private function saveDictChunk($dictKey) {
		
		if (!$this->improveD || !isset($this->dictChunks[$dictKey]) || !$this->dictChunks[$dictKey]['__modified'])
			return;
		
		$dictChunkPath = $this->getDictChunkPath($dictKey);			// Overwrite the current dict chunk
		
		// Build replacement lines in memory so that write operation is fast as possible (to avoid threaded write conflicts)
		$replaceLines = array();
		$chunk = $this->dictChunks[$dictKey];
		foreach ($chunk as $key => $entry) {
			if (strpos($key, "__") !== FALSE)						// Skip reserved keys
				continue;
			if ($this->isMultipleEntry($entry)) {					// Multiple entries
				foreach ($entry as $word) {
					$replaceLines[] = $word['dict'];
					if ($word['dict'] == "") {
						echo "Error: Blank dictionary line found in $dictChunkPath<br>\n";
						return FALSE;
					}
				}
			} else {												// Unique entry
				$replaceLines[] = $entry['dict'];
				if ($entry['dict'] == "") {
					echo "Error: Blank dictionary line found in $dictChunkPath<br>\n";
					return FALSE;			
				}
			}
		}
		
		// Sanity check and write out the lines
		$impCount = count($replaceLines);
		$rawCount = $this->dictChunks[$dictKey]['__count'];
		if ($impCount == $rawCount) {								// First check that all lines are accounted for
			if (file_put_contents($dictChunkPath, implode(PHP_EOL, $replaceLines)) !== FALSE) {
				if ($this->verboseMode) echo "<br>\nSaved improved dictionary [$dictKey]: <a href=$dictChunkPath target=_blank>$dictChunkPath</a><br><hr>\n";
				$this->dictChunks[$dictKey]['__modified'] = FALSE;
			} else {
				echo "\n<p>Error: Couldn't save improved dictionary: $dictChunkPath<br><hr>\n";
				$this->dictChunks[$dictKey]['__modified'] = TRUE;
			}
		} else {
			$this->dictChunks[$dictKey]['__modified'] = TRUE;
			echo "\n<p>Error: Improved dictionary was incomplete, not written: <a href=$dictChunkPath>$dictChunkPath</a><br>\n";
			echo "Error: Improved dict had $impCount vs $rawCount entries originally found.<br>\n";
			$dictChunkErrPath = $this->trimlastN($dictChunkPath, 6) . ".err";
			file_put_contents($dictChunkErrPath, implode(PHP_EOL, $replaceLines));
			echo "Error: Wrote erroneously improved dict to <a href=$dictChunkErrPath>$dictChunkErrPath</a><br><hr>\n";
		}
		return !$this->dictChunks[$dictKey]['__modified'];
	}
	
	private function getDictChunkPath($dictKey) {
		return $this->dictPath . $this->getDictSubfolder($dictKey, 2) . "$dictKey.dict";
	}
	
	private function trimLastN($str, $N) {
		return trim(mb_substr($str, 0, -1*$N, 'UTF-8'));
	}
	
	// Returns the gerund of a verb in the form "to divide" -> "dividing"
	// Returns same string if "to " not found.
	private function gerund($str) {
		if (mb_substr($str, 0, 3, 'UTF-8') === 'to ') {
			return $root = mb_substr($str, 3, NULL, 'UTF-8');
			//$lastChar = mb_substr($root, -1, NULL, 'UTF-8');
			//if ($lastChar === 'e' || $lastChar === 'i' || $lastChar === 'o' || $lastChar === 'u' || $lastChar === 'a')
			//	$root = trimLastN($root, 1);
			//return $root . 'ing';
		} else 
			return $str;
	}
	
	// For detecting multiple entries
	private function isMultipleEntry($entry) {
		return ($this->countdim($entry) > 1);
	}
	
	// Calculates a score based on the N-gram log number and the number of modifiers on the English word
	static function calcWordScore($dictLine) {
		$penalties = 1.0;							// If this is < 1.0 then will reduce score
		$lineElements = explode("\t", $dictLine);
		if (count($lineElements) > 3) {
			$NG = exp(floatval($lineElements[3]));
			if ($NG == 1.0)
				return 0.0;
		} else {
			return 0.0;
		}
		if (strpos($dictLine, "[arch") !== FALSE) {
			$penalties *= 0.5;						// half the score if it's archaic
		}
		$modCount = self::modifierCount($dictLine);
		if ($modCount > 1) {
			$penalties /= ($modCount * 2);
		}
		return $penalties * 1000.0 * $NG;
	}
		
	// Cleans off decorations and non-alphanumeric charachters
	static function removeDecorations($str) {
		//dbg profile();
		// Remove all bracket contents
		$str = preg_replace('/\{.*?\}/', '', $str);	// '{removed}'
		$str = preg_replace('/\[.*?\]/', '', $str);	// '[removed]'
		$str = preg_replace('/\<.*?\>/', '', $str);	// '<removed>'
		// Remove 'sth.' and 'etw.'
		$str = preg_replace('/\b[\w{1,3}]\./', '', $str);	// e.g. etw. jdn. etc.
		// Remove all special chars except dash and space (e.g. '...,')
		$str = preg_replace('/[^\p{L}\p{N} -]/u', '', $str);
		// Collapse multiple spaces into single spaces
		$str = preg_replace('/\s+/', ' ', $str);
		$str = trim($str);
		//dbg profile();
		return $str;
	}	
	
	// Transliterates only german-specific ascii and unicode characters for filenames
	static function transLit($word) {

		// UTF-8
 		$word = str_replace(self::decodeUTF8('\u00e4'),'ae',$word);
		$word = str_replace(self::decodeUTF8('\u00f6'),'oe',$word);
		$word = str_replace(self::decodeUTF8('\u00fc'),'ue',$word);
		$word = str_replace(self::decodeUTF8('\u00c4'),'ae',$word);
		$word = str_replace(self::decodeUTF8('\u00d6'),'oe',$word);
		$word = str_replace(self::decodeUTF8('\u00dc'),'ue',$word);
		$word = str_replace(self::decodeUTF8('\u00df'),'ss',$word);

		// Extended ASCII
		$word = str_replace('ä','ae',$word);	
		$word = str_replace('ö','oe',$word);
		$word = str_replace('ü','ue',$word);
		$word = str_replace('Ä','ae',$word);
		$word = str_replace('Ö','oe',$word);
		$word = str_replace('Ü','ue',$word);
		$word = str_replace('ß','ss',$word);		
		
		return $word;
	}
	
	// Untransliterates only german-specific ascii and unicode characters for lookup
	static function unTransLit($word) {
		// Do only the proper UTF-8 conversion
		$word = str_replace('ae',self::decodeUTF8('\u00e4'),$word);
		$word = str_replace('oe',self::decodeUTF8('\u00f6'),$word);
		$word = str_replace('ue',self::decodeUTF8('\u00fc'),$word);
		$word = str_replace('ae',self::decodeUTF8('\u00c4'),$word);
		$word = str_replace('oe',self::decodeUTF8('\u00d6'),$word);
		$word = str_replace('ue',self::decodeUTF8('\u00dc'),$word);
		$word = str_replace('ss',self::decodeUTF8('\u00df'),$word);
		
		return $word;
	}	
	
	// Returns the number of modifiers on a word (things in brackets, or abbreviations) to help with word scoring 
	static function modifierCount($str) {
		$count = 0;
		preg_match_all('/\([^)]*\)|\[[^]]*\]|\{[^}]*\}|\<[^}]*\>|\b[\w]{1,3}\./', $str, $matches);
		// Now parse each match to also count each word within a each match as a count
		$matches = $matches[0];
		foreach ($matches as $match) {
			//echo "COUNT:::: $match<br>\n";
			$count += 1;
			$match = trim($match);
			$count += substr_count($match, ' ');
		}
		//echo "COUNT: $str :: $count<br>\n";
		return $count;
	}	
	
	// Returns log of N-gram value as returned from MS
	private function getMSNgram($str) {
		//dbg profile();
		$str = urlencode(trim($str));
		if ($str !== '') {
			$ngramLog = file_get_contents("http://weblm.research.microsoft.com/weblm/rest.svc/bing-body/apr10/1/jp?u=[PUT_YOUR_MSNGRAM_KEY_HERE]&p=$str");
			if ($ngramLog === FALSE) {
				//dbg profile();	
				return 0.0;
			} else {
				//dbg profile();
				return floatval($ngramLog);
			}
		}
		//dbg profile();
		return 0.0;
	}	
	
	private function getDictSubfolder($str, $N = 2) {
		$path = '';
		for ($i = 1; $i <= $N; $i++) {
			if($i > mb_strlen($str, 'UTF-8'))
				break;
			$path = $path . mb_substr($str, 0, $i, 'UTF-8') . '/';
		}
		return $path;
	}
	
	static function decodeUTF8($str) {
		$str = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
				return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
		}, $str);
		return $str;
	}	
	
	public function freeMem() {
		$this->dictChunks = array();	
	}
	
	private function countdim($array) {
		if (is_array($array)) {
			if (is_array(reset($array))) {
				$return = $this->countdim(reset($array)) + 1;
			} else {
				$return = 1;
			}
			return $return;
		} else 
			return 0;
    }
}


?>
