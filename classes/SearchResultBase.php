<?php




class SearchResultBase extends SearchResult {

	static $snippet = null;

	static $wgUser;
	
	static $wgAdvancedSearchHighlighting;
	
	static $wgSphinxSearchMWHighlighter;




	public function __construct( $row ) {
		global $wgUser, $wgAdvancedSearchHighlighting, $wgSphinxSearchMWHighlighter;

		parent::__construct( $row );
		if(null == $wgUser) {

			self::$wgUser = $wgUser;
			self::$wgAdvancedSearchHighlighting = $wgAdvancedSearchHighlighting;
			self::$wgSphinxSearchMWHighlighter = $wgSphinxSearchMWHighlighter;
		}
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
		

		// Reference to parent function getTextSnippet.
		if ( self::$wgSphinxSearchMWHighlighter ) {
			return parent::getTextSnippet($terms);
		} else {
			$this->initText();
			return self::$snippet->getExcerpts($this->mText, $terms);
		}
	}

}