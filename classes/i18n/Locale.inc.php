<?php

/**
 * @file classes/i18n/Locale.inc.php
 *
 * Copyright (c) 2005-2011 Alec Smecher and John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Locale
 * @ingroup i18n
 *
 * @brief Provides methods for loading locale data and translating strings identified by unique keys
 *
 */

// $Id$


import('lib.pkp.classes.i18n.PKPLocale');

define('LOCALE_COMPONENT_APPLICATION_COMMON',	0x00000101);

class Locale extends PKPLocale {
	/**
	 * Get all supported locales for the current context.
	 * @return array
	 */
	function getSupportedLocales() {
		static $supportedLocales;
		if (!isset($supportedLocales)) {
			if (defined('SESSION_DISABLE_INIT') || !Config::getVar('general', 'installed')) {
				$supportedLocales = Locale::getAllLocales();
			} else {
				$site =& Request::getSite();
				$supportedLocales = $site->getSupportedLocaleNames();
			}
		}
		return $supportedLocales;
	}

	/**
	 * Get the supported form locales
	 * @return array
	 */
	function getSupportedFormLocales() {
		return Locale::getSupportedLocales();
	}

	/**
	 * Return the key name of the user's currently selected locale (default
	 * is "en_US" for U.S. English).
	 * @return string 
	 */
	function getLocale() {
		static $currentLocale;
		if (!isset($currentLocale)) {
			if (defined('SESSION_DISABLE_INIT') || !Config::getVar('general', 'installed')) {
				// If the locale is specified in the URL, allow
				// it to override. (Necessary when locale is
				// being set, as cookie will not yet be re-set)
				$locale = Request::getUserVar('setLocale');
				if (empty($locale) || !in_array($locale, array_keys(Locale::getSupportedLocales()))) $locale = Request::getCookieVar('currentLocale');
			} else {
				$sessionManager =& SessionManager::getManager();
				$session =& $sessionManager->getUserSession();
				$locale = $session->getSessionVar('currentLocale');

				$site =& Request::getSite();

				if (!isset($locale)) {
					$locale = Request::getCookieVar('currentLocale');
				}

				if (isset($locale)) {
					// Check if user-specified locale is supported
					$locales =& $site->getSupportedLocaleNames();

					if (!in_array($locale, array_keys($locales))) {
						unset($locale);
					}
				}

				if (!isset($locale)) {
					$locale = $site->getPrimaryLocale();
				}
			}

			if (!Locale::isLocaleValid($locale)) {
				$locale = LOCALE_DEFAULT;
			}

			$currentLocale = $locale;
		}
		return $currentLocale;
	}

	/**
	 * Get the stack of "important" locales, most important first.
	 * @return array
	 */
	function getLocalePrecedence() {
		static $localePrecedence;
		if (!isset($localePrecedence)) {
			$localePrecedence = array(Locale::getLocale());

			$site =& Request::getSite();
			if ($site && !in_array($site->getPrimaryLocale(), $localePrecedence)) $localePrecedence[] = $site->getPrimaryLocale();
		}
		return $localePrecedence;
	}

	/**
	 * Retrieve the primary locale of the current context.
	 * @return string
	 */
	function getPrimaryLocale() {
		static $locale;
		if ($locale) return $locale;

		if (defined('SESSION_DISABLE_INIT') || !Config::getVar('general', 'installed')) return $locale = LOCALE_DEFAULT;

		$site =& Request::getSite();
		$locale = $site->getPrimaryLocale();

		if (!isset($locale) || !Locale::isLocaleValid($locale)) {
			$locale = LOCALE_DEFAULT;
		}

		return $locale;
	}

	/**
	 * Make a map of components to their respective files.
	 * @param $locale string
	 * @return array
	 */
	function makeComponentMap($locale) {
		$componentMap = parent::makeComponentMap($locale);
		$baseDir = "locale/$locale/";
		$componentMap[LOCALE_COMPONENT_APPLICATION_COMMON] = $baseDir . 'locale.xml';
		return $componentMap;
	}
}

?>
