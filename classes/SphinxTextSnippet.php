<?php


class SphinxTextSnippet {

	private $index;

	private $options;

	private $contextLines = 2;

	private $contextChars = 75;

	private $beforeMatch = "(searchmatch)";

	private $afterMatch = "(/searchmatch)";

	private $chunkSeparator = "...";




	public function __construct($index, $options = array()) {

		$this->index = $index;

		$this->options = count($options) === 0 ? array(
			"before_match" => $this->beforeMatch,
			"after_match" => $this->afterMatch,
			"chunk_separator" => $this->chunkSeparator,
			"limit" => ($this->contextLines * $this->contextChars),
			"around" => $this->contextchars,
		) : $options;

		// list( $contextlines, $contextchars ) = SphinxSearchEngine::userHighlightPrefs( self::$wgUser );
	}




	public function getExcerpts($text, $terms, $index = null) {

		$client = SphinxSearchEngine::getClient();

		$excerpts = $client->BuildExcerpts(
			array( $text ),
			$this->index,
			join( ' ', $terms ),
			$this->options
		);

		if ( is_array( $excerpts ) ) {
			$ret = '';
			foreach ( $excerpts as $entry ) {
				// remove some wiki markup
				$entry = preg_replace(
					'/([\[\]\{\}\*\#\|\!]+|==+|<br ?\/?>)/',
					' ',
					$entry
				);
				$entry = str_replace(
					array("<", ">"),
					array("&lt;", "&gt;"),
					$entry
				);
				$entry = str_replace(
					array( "(searchmatch)", "(/searchmatch)" ),
					array( "<span class='searchmatch'>", "</span>" ),
					$entry
				);
				$ret .= "<div style='margin: 0.2em 1em 0.2em 1em;'>$entry</div>\n";
			}
		} else {
			$ret = wfMsg( 'internalerror_info', $client->GetLastError() );
		}
		
		return $ret;
	}



}