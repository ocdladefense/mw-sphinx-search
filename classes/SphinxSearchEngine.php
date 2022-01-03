<?php
/**
 * @class SphinxSearchEngine
 * 
 * Extend MediaWiki's default SearchEngine class to support full-text searches using the SphinxSearch API.
 *  This class is specified in $wgSearchType.
 * 
 * NOTE: Sample data can be found in SphinxSearchSampleResponse.php.
 */


class SphinxSearchEngine extends SearchEngine {



	protected $client = null;

	protected $categories = array();

	protected $exc_categories = array();

	protected $prefix_handlers = array(
		'intitle' 		=> 'filterByTitle',
		'incategory' 	=> 'filterByCategory',
		'prefix' 		=> 'filterByPrefix',
	);

	// Whether to use sample results in SphinxSearchSampleResponse.
	// If true, the search query will be constructed but not sent to Sphinx.
	static $USE_TEST_RESULTS = false;



	/**
	 * Perform a full text search query and return a result set. 
	 * For empty queries we bail out.  Otherwise,
	 * We query for relevant docIds from SphinxSearch,
	 * and then load MediaWiki pages from those docIds to construct
	 * the SearchResultSet.
	 *
	 * @param string $term - Raw search term
	 * @return SphinxSearchResultSet
	 * @access public
	 */
	public function searchText( $term ) {

		global $wgSphinxSearch_index_list,
		
		$wgSphinxSuggestMode;

		// Did the user provide search terms?
		$isEmptySearch = trim( $term ) === '';


		// Don't do anything for blank searches.
		if ( $isEmptySearch === true && !$wgSphinxSuggestMode ) {
			return null;
		} else if($isEmptySearch === true){
			return new SphinxSearchResultSet( array(), $term, $this->client, $this->db );
		}

		
		wfRunHooks( 'SphinxSearchBeforeResults', array(
			&$term,
			&$this->offset,
			&$this->namespaces,
			&$this->categories,
			&$this->exc_categories
		) );
		

		$this->client = $this->prepareSphinxClient();
		wfRunHooks( 'SphinxSearchBeforeQuery', array( &$term, &$this->client ) );


		$this->searchTerms = $term;


		$term = self::formatSearchTerms($term);
		wfDebug( "SphinxSearch query: $term\n" );

		// First query SphinxSearch to get our relevant docIds.
		$sphinxResult = self::$USE_TEST_RESULTS ? SphinxSearchSampleResponse::$results : 
			$this->client->Query($term,$wgSphinxSearch_index_list);


		$docIds = array_keys($sphinxResult["matches"]);
		$total_found = $sphinxResult["total_found"];
		// var_dump($docIds);exit;
		// var_dump($sphinxResult);exit;  

		// Then query loads those pages from MediaWiki.
		$mResults = $this->loadMediaWikiPages($docIds);

		// var_dump($mResults);exit;

		return new SphinxSearchResultSet( $mResults, $total_found, $term, $this->client, $this->db );
	}


	/**
	 * @function loadMediaWikiRows
	 * 
	 * SphinxSearch will return a list of docIds corresponding to MediaWiki page_ids.
	 * Use these docIds to load the corresponding MediaWiki pages.
	 */
	protected function loadMediaWikiPages($docIds) {
		
		$mResultSet = array();

		if(!is_array($docIds)) return array();


		// $this->total_hits = $resultSet[ 'total_found' ];

		// foreach ( $resultSet['matches'] as $id => $docinfo ) { // Comment out b/c docInfo isn't being used.
		foreach( $docIds as $page_id ) {
			$res = $this->db->select(
				'page',
				array( 'page_id', 'page_title', 'page_namespace' ),
				array( 'page_id' => $page_id ),
				__METHOD__,
				array()
			);
			if ( $this->db->numRows( $res ) > 0 ) {
				$mResultSet[] = $this->db->fetchObject( $res );
			}
		}

		return $mResultSet;
	}



	/**
	 * Utility method to further escape / format the query for Sphinx.
	 * 
	 * @param $term String The search terms to be formatted.
	 * @return String The resulting search string, formatted to meet Sphinx requirements.
	 */
	protected static function formatSearchTerms($term) {

		$escape = '/';
		$delims = array(
			'(' => ')',
			'[' => ']',
			'"' => '',
		);

		// Temporarily replace already escaped characters.
		$placeholders = array(
			'\\(' => '_PLC_O_PAR_',
			'\\)' => '_PLC_C_PAR_',
			'\\[' => '_PLC_O_BRA_',
			'\\]' => '_PLC_C_BRA_',
			'\\"' => '_PLC_QUOTE_',
		);
		$term = str_replace(array_keys($placeholders), $placeholders, $term);
		foreach ($delims as $open => $close) {
			$open_cnt = substr_count( $term, $open );
			if ($close) {
				// If counts do not match, escape them all.
				$close_cnt = substr_count( $term, $close );
				if ($open_cnt != $close_cnt) {
					$escape .= $open . $close;
				}
			} elseif ($open_cnt % 2 == 1) {
				// If there is no closing symbol, count should be even.
				$escape .= $open;
			}
		}
		$term = str_replace($placeholders, array_keys($placeholders), $term);
		$term = addcslashes( $term, $escape );

		return $term;
	}


	/**
	 * @override getNearMatch
	 * 
	 * Do not go to a near match if query prefixed with ~
	 *
	 * @param $searchterm String
	 * @return Title
	 */
	public static function getNearMatch( $searchterm ) {
			// return null;
		if ( $searchterm[ 0 ] === '~' ) {
			return null;
		} else {
			return parent::getNearMatch( $searchterm );
		}
	}



	/**
	 *  PrefixSearchBackend override for OpenSearch results
	 */
	public static function prefixSearch( $namespaces, $term, $limit, &$results ) {
		$search_engine = new SphinxSearchEngine( wfGetDB( DB_SLAVE ) );
		$search_engine->namespaces = $namespaces;
		$search_engine->setLimitOffset( $limit, 0 );
		$result_set = $search_engine->searchText( '@page_title: ^' . $term . '*' );
		$results = array();
		if ( $result_set ) {
			while ( $res = $result_set->next() ) {
				$results[ ] = $res->getTitle()->getPrefixedText();
			}
		}
		return false;
	}

	
	/**
	 * @override replacePrefixes.
	 * Prepare query for sphinx search daemon
	 *
	 * @param string $query
	 * @return string rewritten query
	 */
	public function replacePrefixes($query) {
		return $query;
	}


	/**
	 * @return SphinxClient: ready to run or false if term is empty
	 */
	public function prepareSphinxClient() {
		global $wgSphinxSearch_sortmode, $wgSphinxSearch_sortby, $wgSphinxSearch_host,
			$wgSphinxSearch_port, $wgSphinxSearch_index_weights,
			$wgSphinxSearch_mode, $wgSphinxSearch_maxmatches,
			$wgSphinxSearch_cutoff, $wgSphinxSearch_weights;

		$cl = new SphinxClient();

		$cl->SetServer( $wgSphinxSearch_host, $wgSphinxSearch_port );
		if ( $wgSphinxSearch_weights && count( $wgSphinxSearch_weights ) ) {
			$cl->SetFieldWeights( $wgSphinxSearch_weights );
		}
		if ( is_array( $wgSphinxSearch_index_weights ) ) {
			$cl->SetIndexWeights( $wgSphinxSearch_index_weights );
		}
		if ( $wgSphinxSearch_mode ) {
			$cl->SetMatchMode( $wgSphinxSearch_mode );
		}
		if ( $this->namespaces && count( $this->namespaces ) ) {
			$cl->SetFilter( 'page_namespace', $this->namespaces );
		}
		if( !$this->showRedirects ) {
			$cl->SetFilter( 'page_is_redirect', array( 0 ) );
		}
		if ( $this->categories && count( $this->categories ) ) {
			$cl->SetFilter( 'category', $this->categories );
			wfDebug( "SphinxSearch included categories: " . join( ', ', $this->categories ) . "\n" );
		}
		if ( $this->exc_categories && count( $this->exc_categories ) ) {
			$cl->SetFilter( 'category', $this->exc_categories, true );
			wfDebug( "SphinxSearch excluded categories: " . join( ', ', $this->exc_categories ) . "\n" );
		}
		$cl->SetSortMode( $wgSphinxSearch_sortmode, $wgSphinxSearch_sortby );
		$cl->SetLimits(
			$this->offset,
			$this->limit,
			$wgSphinxSearch_maxmatches,
			$wgSphinxSearch_cutoff
		);


		return $cl;
	}

	/**
	 * @override userHighlightPrefes()
	 * 
	 * Find snippet highlight settings for all users
	 *
	 * @return Array contextlines, contextchars
	 */
	public static function userHighlightPrefs() {
		return parent::userHighlightPrefs();
	}


}




