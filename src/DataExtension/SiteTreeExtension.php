<?php

namespace Roseblade\BusinessData\DataExtension;

use SilverStripe\CMS\Controllers\RootURLController;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataExtension;
use SilverStripe\SiteConfig\SiteConfig;

class SiteTreeExtension extends DataExtension
{
	public const INCLUDE_SITE_JSONLD_HOME = 'home';
	public const INCLUDE_SITE_JSONLD_ALL = 'all';

	private static $minify_jsonld = true;

	/**
	 * Adjusts the meta tags for the page to include our custom ones
	 * 
	 * @param array $tags
	 */
	public function MetaComponents(array &$tags)
	{
		$schemaData 			= null;
		$includeSiteSchemaData	= $this->owner->getIncludeSiteSchemaData();

		/** Are we including it? */
		if ($includeSiteSchemaData)
		{
			$siteConfig = SiteConfig::current_site_config();
			$schemaData = $siteConfig->getMicroDataSchemaData();
		}

		if ($schemaData)
		{
			$options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

			if (Config::inst()->get(self::class, 'minify_jsonld') === false)
			{
				$options = $options | JSON_PRETTY_PRINT;
			}

			$tags['ld+json'] = [
				'tag' => 'script',
				'attributes' => [
					'type' => 'application/ld+json',
				],
				'content' => json_encode($schemaData, $options)
			];
		}
	}

	/**
	 * Mark the page to include site jsonld data on this page
	 *
	 * @param bool $include
	 */
	public function setIncludeSiteSchemaData(bool $include)
	{
		$this->owner->include_site_jsonld_override 	= $include;
	}

	/**
	 * Returns true/false on whether or not to include the site schema data for this page
	 *
	 * @return bool
	 */
	public function getIncludeSiteSchemaData(): bool
	{
		$currentLink = trim($this->owner->Link(), '/');

		/** If it's specifically set to do either/or, we'll abide by that */
		if (isset($this->owner->include_site_jsonld_override))
		{
			return $this->owner->include_site_jsonld_override;
		}
		/** Else if it's set to "home", and the current link is "home", return true */
		elseif (($this->owner->config()->include_site_jsonld == self::INCLUDE_SITE_JSONLD_HOME) && ($currentLink == '' || $currentLink === RootURLController::get_homepage_link()))
		{
			return true;
		}
		/** Else if it's set to include it on all pages, return true */
		elseif ($this->owner->config()->include_site_jsonld == self::INCLUDE_SITE_JSONLD_ALL)
		{
			return true;
		}

		/** If it's reached this point, we don't need it. Return false */
		return false;
	}
}
