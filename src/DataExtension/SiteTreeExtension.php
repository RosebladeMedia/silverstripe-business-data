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

	/**
	 * Whether or not to minify JSON
	 * 
	 * @var bool
	 */
	private static $minify_jsonld 	= true;

	/**
	 * Whether or not to include icon files
	 * 
	 * @var bool
	 */
	private static $include_icons 	= true;

	private static $icon_size		= 'Pad';
	private static $icon_fill 		= "#ffffff";

	//--------------------------------------------------------------------------

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

		$icons 	= $this->owner->getIconCode();

		if (!empty($icons))
		{
			$tags 	= array_merge($tags, $icons);
		}
	}

	//--------------------------------------------------------------------------

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

	//--------------------------------------------------------------------------

	/**
	 * Generates an array of icons to be used in the meta tag area
	 *
	 * @return array
	 */
	public function getIconCode(): array
	{
		$siteConfig = SiteConfig::current_site_config();

		$icons 		= [];

		if (isset($siteConfig->FavIcon))
		{
			$count 		= 0;
			$newSizeH	= 256;
			$newSizeW	= 256;

			foreach ($this->owner->config()->icons as $icon)
			{
				if (isset($icon['sizes']))
				{
					list($newSizeH, $newSizeW) 	= explode("x", $icon['sizes']);
				}

				$newIcon 		= $this->getIconFile($newSizeH, $newSizeW);

				if (!empty($newIcon))
				{
					$icon['href']	= $newIcon->AbsoluteLink();

					foreach ($icon as $attr => $val)
					{
						if ((strpos($val, "{") !== false) && (strpos($val, "}")) !== false)
						{
							$func 			= str_replace(['{', '}'], '', $val);
							$val 			= str_replace("{" . $func . "}", $newIcon->{$func}(), $val);
							$icon[$attr]	= $val;
						}
					}

					$icons[$count]	= [
						'tag'			=> 'link',
						'attributes'	=> $icon
					];

					$count++;
				}
			}
		}

		return $icons;
	}

	/**
	 * Generates a new icon in the given size
	 *
	 * @param mixed $sizeH
	 * @param mixed $sizeW
	 * 
	 * @return [type]
	 */
	public function getIconFile($sizeH, $sizeW)
	{
		$siteConfig = SiteConfig::current_site_config();
		$icon 		= $siteConfig->FavIcon;
		$function 	= $this->owner->config()->icon_size_function;

		/** Padding is the only one with 3 parameters (for the fill) */
		if (strtolower($function) == "pad")
		{
			$newIcon 	= $icon->Pad($sizeH, $sizeW, $this->owner->config()->icon_fill);
			return $newIcon;
		}
		else
		{
			$newIcon 	= $icon->{$function}($sizeH, $sizeW);
			return $newIcon;
		}
	}
}
