<?php




class SearchResultDefaultHighlighter extends SearchResult {




	public function __construct( $row ) {

		parent::__construct( $row );
	}


	/**
	 * Emulates SearchResult getTextSnippet so that we can use our own userHighlightPrefs
	 * (only needed until userHighlightPrefs in SearchEngine is fixed)
	 *
	 * @param $terms array of terms to highlight
	 * @return string highlighted text snippet
	 */
	function getTextSnippet( $terms ) {
		

		// Reference to parent function getTextSnippet.
		return parent::getTextSnippet($terms);
	}

}