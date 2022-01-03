<?php

# Alert the user that this is not a valid entry point to MediaWiki if they try to access the file directly.
if ( !defined( 'MEDIAWIKI' ) ) {
	echo <<<EOT
To install SphinxSearch extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/SphinxSearch/SphinxSearch.php" );
EOT;
	exit( 1 );
}

$wgExtensionCredits['other'][] = array(
	'path'           => __FILE__,
	'version'        => '0.1-beta',
	'name'           => 'SphinxSearch',
	'author'         => array( 'José Bernal' ),
	'url'            => '', //'https://www.mediawiki.org/wiki/Extension:SphinxSearch',
	'descriptionmsg' => 'sphinxsearch-desc'
);

$dir = dirname( __FILE__ ) . '/';



$wgAutoloadClasses[ 'SphinxSearchEngine' ] = $dir . 'classes/SphinxSearchEngine.php';
$wgAutoloadClasses[ 'SphinxAdvancedSearchEngine' ] = $dir . 'classes/SphinxAdvancedSearchEngine.php';
$wgAutoloadClasses[ 'SphinxSearchResultSet' ] = $dir . 'classes/SphinxSearchResultSet.php';
$wgAutoloadClasses[ 'SphinxSearchResult' ] = $dir . 'classes/SphinxSearchResult.php';
$wgAutoloadClasses[ 'SphinxSearchSampleResponse' ] = $dir . 'classes/SphinxSearchSampleResponse.php';
$wgExtensionMessagesFiles['SphinxSearch'] = $dir . 'SphinxSearch.i18n.php';
// $wgExtensionFunctions[ ] = 'efSphinxSearchPrefixSetup';





# To completely disable the default search and replace it with SphinxSearch,
# set this BEFORE including SphinxSearch.php in LocalSettings.php
# $wgSearchType = 'SphinxSearchEngine';
# All other variables should be set AFTER you include this file in LocalSettings


# https://www.mediawiki.org/wiki/Manual:$wgSearchType
if ( $wgSearchType == 'SphinxSearchEngine' ) {
	$req = new \WebRequest();
	$params = $req->getValues();
	$profile = $params["profile"];
	$wgSearchType = $profile == "advanced" ? "SphinxAdvancedSearchEngine" : "SphinxSearchEngine";
	// var_dump($params);exit;

	$wgDisableSearchUpdate = true;
}


# This assumes you have copied sphinxapi.php from your Sphinx
# installation folder to your SphinxSearch extension folder
# not needed if you install http://pecl.php.net/package/sphinx
if ( !class_exists( 'SphinxClient' ) ) {
	require_once ( $dir . "lib/sphinxapi.php" );
}


// Make sure the global settings have been set.
validateSphinxGlobalSettings();



# If you have multiple index files, you can specify their weights like this
# See http://www.sphinxsearch.com/docs/current.html#api-func-setindexweights
# $wgSphinxSearch_index_weights = array(
#	"wiki_main" => 100,
#	"wiki_incremental" => 10
# );
$wgSphinxSearch_index_weights = null;

# Default Sphinx search mode
# $wgSphinxSearch_mode = SPH_MATCH_EXTENDED2;
$wgSphinxSearch_mode = SPH_MATCH_ANY;

# Default sort mode
$wgSphinxSearch_sortmode = SPH_SORT_RELEVANCE;
$wgSphinxSearch_sortby = '';

# How many matches searchd will keep in RAM while searching
$wgSphinxSearch_maxmatches = 1000;

# When to stop searching all together (if not zero)
$wgSphinxSearch_cutoff = 0;

# Weights of individual indexed columns. This gives page titles extra weight
$wgSphinxSearch_weights = array(
	'old_text' 		=> 1,
	'page_title' 	=> 500
);

# Set to true to use MW's default search snippets and highlighting
$wgSphinxSearchMWHighlighter = false;

# Should the suggestion (Did you mean?) mode be enabled? Possible values:
# enchant - see http://www.mediawiki.org/wiki/Extension:SphinxSearch/Search_suggestions
# soundex - uses MySQL soundex() function to recommend existing titles
# aspell - uses aspell command-line utility to look for similar spellings
$wgSphinxSuggestMode = '';

# Path to aspell, adjust value if not in the system path
$wgSphinxSearchAspellPath = 'aspell';

# Path to (optional) personal aspell dictionary
$wgSphinxSearchPersonalDictionary = '';

# If true, use SphinxMWSearch for prefix search instead of the core default.
# This influences results from ApiOpenSearch.
$wgEnableSphinxPrefixSearch = false;

function efSphinxSearchPrefixSetup() {
	global $wgHooks, $wgEnableSphinxPrefixSearch;

	if ( $wgEnableSphinxPrefixSearch ) {
		$wgHooks[ 'PrefixSearchBackend' ][ ] = 'SphinxSearchEngine::prefixSearch';
	}
}


// Make sure the global settings have been set.
function validateSphinxGlobalSettings(){

	global $wgSphinxSearch_host, $wgSphinxSearch_port, $wgSphinxSearch_index, $wgSphinxSearch_index_list;


	$globalSettings = array(
		"wgSphinxSearch_host" 		=> $wgSphinxSearch_host,
		"wgSphinxSearch_port" 		=> $wgSphinxSearch_port,
		"wgSphinxSearch_index" 		=> $wgSphinxSearch_index,
		"wgSphinxSearch_index_list" => $wgSphinxSearch_index_list
	);

	foreach($globalSettings as $label => $setting) {

		if(empty($setting)) {

			die("SPHINX_EXTENSION_SETTINGS_ERROR: The '$$label' global setting has not not been set. Check your 'LocalSettings.php' or 'SiteSpecificSettings.php'.");

		}
	}
}