<?php




class SearchResultSphinxHighlighter extends SearchResult {

	static $snippet = null;




	public function __construct( $row ) {

		parent::__construct( $row );
	}

	public function setSnippetUtility($util) {
		if(self::$snippet == null) {
			self::$snippet = $util;
		}
	}

	/**
	 * Emulates SearchResult getTextSnippet so that we can use our own userHighlightPrefs
	 * (only needed until userHighlightPrefs in SearchEngine is fixed)
	 *
	 * @param $terms array of terms to highlight
	 * @return string highlighted text snippet
	 */
	function getTextSnippet( $terms ) {
	
		// list( $contextlines, $contextchars ) = SphinxSearchEngine::userHighlightPrefs( $wgUser );
		$this->initText();
		return self::$snippet->getExcerpts($this->mText, $terms);
	}

}