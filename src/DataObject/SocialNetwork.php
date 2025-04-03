<?php

namespace Roseblade\BusinessData\DataObject;

use BurnBright\ExternalURLField\ExternalURLField;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Forms\DropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;

class SocialNetwork extends DataObject
{
	use Configurable;

	private static $table_name = 'Roseblade_SocialNetworks';

	/**
	 * List of social networks in a identifier => human title array
	 * Configurable via yaml as 'network_list'
	 * 
	 * @var array
	 */
	private static $network_list = [];

	/**
	 * Database fields
	 * 
	 * @var array
	 */
	private static $db = [
		'NetworkName'	=> 'Varchar(255)',
		'Label'			=> 'Varchar(255)',
		'URL'			=> 'ExternalURL',
		'Sort'			=> 'Int'
	];

	private static $has_one = [
		'SiteConfig'		=> SiteConfig::class
	];

	private static $summary_fields = [
		'Network',
		'Label'
	];

	public function getCMSFields()
	{
		$fields 	= parent::getCMSFields();

		$fields->addFieldsToTab(
			'Root.Main',
			[
				DropdownField::create('NetworkName', 'Social Network', $this->getNetworkList())
			]
		);

		$fields->replaceField('URL', ExternalURLField::create('URL', 'URL'));

		$fields->removeByName('SiteConfigID');
		$fields->removeByName('Sort');

		return $fields;
	}

	public function getNetworkList()
	{
		$networks = self::config()->network_list;

		asort($networks);

		return $networks;
	}

	public function getNetwork()
	{
		$networkName 	= $this->getNetworkList();

		if (isset($networkName[$this->NetworkName]))
		{
			return $networkName[$this->NetworkName];
		}

		return $this->NetworkName;
	}

	public function getTitle()
	{
		return $this->Label;
	}
}
