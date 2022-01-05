<?php


class SphinxSearchResultSet extends SearchResultSet {

	var $mNdx = 0;

	var $mSuggestion = '';

	var $total_hits = 0;

	var $mResultSet = array();




	/**
	 * Constructor
	 * 
	 * Creates an array of MediaWiki rows using the doc ids from
	 *  the SphinxSearch query.
	 */
	function __construct( $resultSet, $total_hits, $terms, $dbr ) {
		// ar_dump($resultSet); exit;

		global $wgSearchHighlightBoundaries, $wgSphinxSearch_index;



		$this->mResultSet = $resultSet; //@jbernal

		$this->total_hits = $total_hits;
		$this->mNdx = 0;
		$this->mTerms = preg_split( "/$wgSearchHighlightBoundaries+/ui", $terms );
	}




	/**
	 * @return SphinxSearchResult: next result, false if none
	 */
	function next() {
		global $wgSphinxSearchMWHighlighter;

		if ( isset( $this->mResultSet[$this->mNdx] ) ) {
			$row = $this->mResultSet[$this->mNdx];
			++$this->mNdx;

			if($wgSphinxSearchMWHighlighter) {
				$result = new SearchResultDefaultHighlighter($row);
			} else {
				$result = new SearchResultSphinxHighlighter($row);
			}
			
			return $result;
		} else {

			return false;
		}
	}

	function free() {
		unset( $this->mResultSet );
	}

	/**
	 * Some search modes return a suggested alternate term if there are
	 * no exact hits. Returns true if there is one on this set.
	 *
	 * @return Boolean
	 */
	function hasSuggestion() {
		global $wgSphinxSuggestMode;

		if ( $wgSphinxSuggestMode ) {
			$this->mSuggestion = '';
			if ( $wgSphinxSuggestMode === 'enchant' ) {
				$this->suggestWithEnchant();
			} elseif ( $wgSphinxSuggestMode === 'soundex' ) {
				$this->suggestWithSoundex();
			} elseif ( $wgSphinxSuggestMode === 'aspell' ) {
				$this->suggestWithAspell();
			}
			if ($this->mSuggestion) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Wiki-specific search suggestions using enchant library.
	 * Use SphinxSearch_setup.php to create the dictionary
	 */
	function suggestWithEnchant() {
		if (!function_exists('enchant_broker_init')) {
			return;
		}
		$broker = enchant_broker_init();
		enchant_broker_set_dict_path($broker, ENCHANT_MYSPELL, dirname( __FILE__ ));
		if ( enchant_broker_dict_exists( $broker, 'sphinx' ) ) {
			$dict = enchant_broker_request_dict( $broker, 'sphinx' );
			$suggestion_found = false;
			$full_suggestion = '';
			foreach ( $this->mTerms as $word ) {
				if ( !enchant_dict_check($dict, $word) ) {
					$suggestions = enchant_dict_suggest($dict, $word);
					while ( count( $suggestions ) ) {
						$candidate = array_shift( $suggestions );
						if ( strtolower($candidate) != strtolower($word) ) {
							$word = $candidate;
							$suggestion_found = true;
							break;
						}
					}
				}
				$full_suggestion .= $word . ' ';
			}
			enchant_broker_free_dict( $dict );
			if ($suggestion_found) {
				$this->mSuggestion = trim( $full_suggestion );
			}
		}
		enchant_broker_free( $broker );
	}

	/**
	 * Default (weak) suggestions implementation relies on MySQL soundex
	 */
	function suggestWithSoundex() {
		$joined_terms = $this->db->addQuotes( join( ' ', $this->mTerms ) );
		$res = $this->db->select(
			array( 'page' ),
			array( 'page_title' ),
			array(
				"page_title SOUNDS LIKE " . $joined_terms,
				// avoid (re)recommending the search string
				"page_title NOT LIKE " . $joined_terms
			),
			__METHOD__,
			array(
				'ORDER BY' => 'page_counter desc',
				'LIMIT' => 1
			)
		);
		$suggestion = $this->db->fetchObject( $res );
		if ( is_object( $suggestion ) ) {
			$this->mSuggestion = trim( $suggestion->page_title );
		}
	}

	function suggestWithAspell() {
		global $wgLanguageCode, $wgSphinxSearchPersonalDictionary, $wgSphinxSearchAspellPath;

		// aspell will only return mis-spelled words, so remember all here
		$words = $this->mTerms;
		$word_suggestions = array();
		foreach ( $words as $word ) {
			$word_suggestions[ $word ] = $word;
		}

		// prepare the system call with optional dictionary
		$aspellcommand = 'echo ' . escapeshellarg( join( ' ', $words ) ) .
			' | ' . escapeshellarg( $wgSphinxSearchAspellPath ) .
			' -a --ignore-accents --ignore-case --lang=' . $wgLanguageCode;
		if ( $wgSphinxSearchPersonalDictionary ) {
			$aspellcommand .= ' --home-dir=' . dirname( $wgSphinxSearchPersonalDictionary );
			$aspellcommand .= ' -p ' . basename( $wgSphinxSearchPersonalDictionary );
		}

		// run aspell
		$shell_return = shell_exec( $aspellcommand );

		// parse return line by line
		$returnarray = explode( "\n", $shell_return );
		$suggestion_needed = false;
		foreach ( $returnarray as $key => $value ) {
			// lines with suggestions start with &
			if ( $value[0] === '&' ) {
				$correction = explode( ' ', $value );
				$word = $correction[ 1 ];
				$suggestions = substr( $value, strpos( $value, ':' ) + 2 );
				$suggestions = explode( ', ', $suggestions );
				if ( count( $suggestions ) ) {
					$guess = array_shift( $suggestions );
					if ( strtolower( $word ) != strtolower( $guess ) ) {
						$word_suggestions[ $word ] = $guess;
						$suggestion_needed = true;
					}
				}
			}
		}

		if ( $suggestion_needed ) {
			$this->mSuggestion = join( ' ', $word_suggestions );
		}
	}

	/**
	 * @return String: suggested query, null if none
	 */
	function getSuggestionQuery(){
		return $this->mSuggestion;
	}

	/**
	 * @return String: HTML highlighted suggested query, '' if none
	 */
	function getSuggestionSnippet(){
		return $this->mSuggestion;
	}

	/**
	 * @return Array: search terms
	 */
	function termMatches() {
		return $this->mTerms;
	}

	/**
	 * @return Integer: number of results
	 */
	function numRows() {
		return count( $this->mResultSet );
	}

	/**
	 * Some search modes return a total hit count for the query
	 * in the entire article database. This may include pages
	 * in namespaces that would not be matched on the given
	 * settings.
	 *
	 * Return null if no total hits number is supported.
	 *
	 * @return Integer
	 */
	function getTotalHits() {
		return $this->total_hits;
	}

	/**
	 * Return information about how and from where the results were fetched.
	 *
	 * @return string
	 */
	function getInfo() {
		return wfMsg( 'sphinxPowered', "http://www.sphinxsearch.com" );
	}



}