<?php
/**
 * @class SphinxAdvancedSearchEngine
 * 
 * Extend MediaWiki's default SearchEngine class to support full-text searches using the SphinxSearch API.
 *  This class is specified in $wgSearchType. This class implmeents filtering functions when performing an advanced search.
 * 
 * NOTE: Sample data can be found in SphinxSearchSampleResponse.php.
 */


class SphinxAdvancedSearchEngine extends SphinxSearchEngine {

	var $client = null;

	var $categories = array();

	var $exc_categories = array();

	var $prefix_handlers = array(
		'intitle' 		=> 'filterByTitle',
		'incategory' 	=> 'filterByCategory',
		'prefix' 		=> 'filterByPrefix',
	);

	/**
	 * @override replacePrefixes.
	 * Prepare query for sphinx search daemon
	 *
	 * @param string $query
	 * @return string rewritten query
	 */
	function replacePrefixes( $query ) {

		// @jbernal
		return $query;

		if ( trim( $query ) === '' ) {
			return $query;
		}

		// ~ prefix is used to avoid near-term search, remove it now
		if ( $query[ 0 ] === '~' ) {
			$query = substr( $query, 1 );
		}

		$parts = preg_split( '/(")/', $query, -1, PREG_SPLIT_DELIM_CAPTURE );
		$inquotes = false;
		$rewritten = '';
		foreach ( $parts as $key => $part ) {
			if ( $part == '"' ) { // stuff in quotes doesn't get rewritten
				$rewritten .= $part;
				$inquotes = !$inquotes;
			} elseif ( $inquotes ) {
				$rewritten .= $part;
			} else {
				if ( strpos( $query, ':' ) !== false ) {
					$regexp = $this->preparePrefixRegexp();
					$part = preg_replace_callback(
						'/(^|[| :]|-)(' . $regexp . '):([^ ]+)/i',
						array( $this, 'replaceQueryPrefix' ),
						$part
					);
				}
				$rewritten .= str_replace(
					array( ' OR ', ' AND ' ),
					array( ' | ', ' & ' ),
					$part
				);
			}
		}
		return $rewritten;
	}

	/**
	 * @return string Regexp to match namespaces and other prefixes
	 */
	function preparePrefixRegexp() {
		global $wgContLang, $wgCanonicalNamespaceNames, $wgNamespaceAliases;

		// "search everything" keyword
		$allkeyword = wfMsgForContent( 'searchall' );
		$this->prefix_handlers[ $allkeyword ] = 'searchAllNamespaces';

		$all_prefixes = array_merge(
			$wgContLang->getNamespaces(),
			$wgCanonicalNamespaceNames,
			array_keys( array_merge( $wgNamespaceAliases, $wgContLang->getNamespaceAliases() ) ),
			array_keys( $this->prefix_handlers )
		);

		$regexp_prefixes = array();
		foreach ( $all_prefixes as $prefix ) {
			if ( $prefix != '' ) {
				$regexp_prefixes[] = preg_quote( str_replace( ' ', '_', $prefix ), '/' );
			}
		}

		return implode( '|', array_unique( $regexp_prefixes ) );
	}

	/**
	 * preg callback to process foo: prefixes in the query
	 * 
	 * @param array $matches
	 * @return string
	 */
	function replaceQueryPrefix( $matches ) {
		if ( isset( $this->prefix_handlers[ $matches[ 2 ] ] ) ) {
			$callback = $this->prefix_handlers[ $matches[ 2 ] ];
			return $this->$callback( $matches );
		} else {
			return $this->filterByNamespace( $matches );
		}
	}

	function filterByNamespace( $matches ) {
		global $wgContLang;
		$inx = $wgContLang->getNsIndex( str_replace( ' ', '_', $matches[ 2 ] ) );
		if ( $inx === false ) {
			return $matches[ 0 ];
		} else {
			$this->namespaces[] = $inx;
			return $matches[ 3 ];
		}
	}

	function searchAllNamespaces( $matches ) {
		$this->namespaces = null;
		return $matches[ 3 ];
	}

	function filterByTitle( $matches ) {
		return '@page_title ' . $matches[ 3 ];
	}

	function filterByPrefix( $matches ) {
		$prefix = $matches[ 3 ];
		if ( strpos( $matches[ 3 ], ':' ) !== false ) {
			global $wgContLang;
			list( $ns, $prefix ) = explode( ':', $matches[ 3 ] );
			$inx = $wgContLang->getNsIndex( str_replace( ' ', '_', $ns ) );
			if ( $inx !== false ) {
				$this->namespaces = array( $inx );
			}
		}
		return '@page_title ^' . $prefix . '*';
	}

	function filterByCategory( $matches ) {
		$page_id = $this->db->selectField( 'page', 'page_id',
			array(
				'page_title' => $matches[ 3 ],
				'page_namespace' => NS_CATEGORY
			),
			__METHOD__
		);
		$category = intval( $page_id );
		if ( $matches[ 1 ] === '-' ) {
			$this->exc_categories[ ] = $category;
		} else {
			$this->categories[ ] = $category;
		}
		return '';
	}

}




