<?php

/**
 * @file SehlPlugin.inc.php
 *
 * Copyright (c) 2005-2011 Alec Smecher and John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SehlPlugin
 * @ingroup plugins_generic_sehl
 *
 * @brief Search Engine HighLighting plugin
 */

// $Id$


import('lib.pkp.classes.plugins.GenericPlugin');

class SehlPlugin extends GenericPlugin {
	/** @var $queryTerms string */
	var $queryTerms;

	function register($category, $path) {
		if (parent::register($category, $path)) {
			if (!Config::getVar('general', 'installed')) return false;

			HookRegistry::register('SchemaPlugin::displayRecordSummary',array(&$this, 'displayTemplateCallback'));
			HookRegistry::register('SchemaPlugin::displayRecord',array(&$this, 'displayTemplateCallback'));

			$templateMgr =& TemplateManager::getManager();
			$templateMgr->addStylesheet(Request::getBaseUrl() . '/' . $this->getPluginPath() . '/sehl.css');

			return true;
		}
		return false;
	}

	/**
	 * Get the name of the settings file to be installed site-wide when
	 * the harvester is installed.
	 * @return string
	 */
	function getInstallSitePluginSettingsFile() {
		return $this->getPluginPath() . '/settings.xml';
	}

	/**
	 * Given a $page and $op, return a list of field names for which
	 * the plugin should be used.
	 * @param $templateMgr object
	 * @param $page string The requested page
	 */
	function parse_quote_string($query_string) {
		/* urldecode the string and setup variables */
		$query_string = urldecode($query_string);
		$quote_flag = false;
		$word = '';
		$terms = array();

		/* loop through character by character and move terms to an array */
		for ($i=0; $i<strlen($query_string); $i++) {
			$char = substr($query_string, $i, 1);
			if ($char == '"') {
				if ($quote_flag) $quote_flag = false;
				else $quote_flag = true;
			}
			if (($char == ' ') && (!($quote_flag))) {
				$terms[] = $word;
				$word = '';
			} else {
				if (!($char == '"')) $word .= $char;
			}
		}
		$terms[] = $word;
		/* return the fully parsed array */
		return $terms;
	}

	function displayTemplateCallback($hookName, $args) {
		$templateMgr =& $args[0];
		$template =& $args[1];

		// Determine the query terms to use.
		$queryVariableNames = array(
			'q', 'p', 'ask', 'searchfor', 'key', 'query', 'search',
			'keyword', 'keywords', 'qry', 'searchitem', 'kwd',
			'recherche', 'search_text', 'search_term', 'term',
			'terms', 'qq', 'qry_str', 'qu', 's', 'k', 't', 'va'
		);
		$this->queryTerms = array();
		if (($referer = getenv('HTTP_REFERER')) != '') {
			$urlParts = parse_url($referer);
			if (isset($urlParts['query'])) {

				$queryArray = explode('&', $urlParts['query']);
				foreach ($queryArray as $var) {
					$varArray = explode('=', $var);
					if (in_array($varArray[0], $queryVariableNames)) {
						$this->queryTerms += $this->parse_quote_string($varArray[1]);
					}
				}
			}
		}

		// Catch a couple of common POST for request variables too
		if (($q = Request::getUserVar('q')) != '') $this->queryTerms[] = $q;
		if (($q = Request::getUserVar('query')) != '') $this->queryTerms[] = $q;

		if (empty($this->queryTerms)) return false;

		$templateMgr->register_outputfilter(array(&$this, 'outputFilter'));


		return false;
	}

	function outputFilter($output, &$smarty) {
		foreach ($this->queryTerms as $q) {
			// Thanks to Brian Suda http://suda.co.uk/projects/SEHL/
			$newOutput = '';
			$pat = '/((<[^!][\/]*?[^<>]*?>)([^<]*))|<!---->|<!--(.*?)-->|((<!--[ \r\n\t]*?)(.*?)[ \r\n\t]*?-->([^<]*))/si';
			preg_match_all($pat, $output, $tag_matches);

			for ($i=0; $i< count($tag_matches[0]); $i++) {
				if (
					(preg_match('/<!/i', $tag_matches[0][$i])) ||
					(preg_match('/<textarea/i', $tag_matches[2][$i])) ||
					(preg_match('/<script/i', $tag_matches[2][$i]))
				) {
					$newOutput .= $tag_matches[0][$i];
				} else {
					$newOutput .= $tag_matches[2][$i];
					$holder = preg_replace('/(.*?)(\W)('.preg_quote($q,'/').')(\W)(.*?)/iu',"\$1\$2<span class=\"sehl\">\$3</span>\$4\$5",' '.$tag_matches[3][$i].' ');
					$newOutput .= substr($holder,1,(strlen($holder)-2));
				}
			}
			$output = $newOutput;
		}
		return ($output);
	}

	function getDisplayName() {
		return Locale::translate('plugins.generic.sehl.name');
	}

	function getDescription() {
		return Locale::translate('plugins.generic.sehl.description');
	}
}

?>
