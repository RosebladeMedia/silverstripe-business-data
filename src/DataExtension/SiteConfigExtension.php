<?php

namespace Roseblade\BusinessData\DataExtension;

use BeastBytes\PostcodesIo\PostcodesIo;
use Innoweb\InternationalPhoneNumberField\Forms\InternationalPhoneNumberField;
use League\ISO3166\ISO3166;
use Roseblade\BusinessData\DataObject\SocialNetwork;
use Sheadawson\DependentDropdown\Forms\DependentDropdownField;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBField;

/**
 * Adds custom fields to include business communication,
 * address and other related fields
 */
class SiteConfigExtension extends DataExtension
{
	use Configurable;

	/**
	 * @var bool
	 */
	private static $update_geodata_by_api	= true;

	/**
	 * @var array
	 */
	private static $db 	= [

		/** Standard fields */

		'BusinessDataSiteName'				=> 'Varchar(255)',
		'BusinessDataSiteDescription'		=> 'Text',
		'BusinessDataLegalName'				=> 'Text',

		/** Contact details */

		'BusinessDataMainTelephone'			=> 'Phone',
		'BusinessDataMainEmail'				=> 'Varchar(255)',

		'BusinessDataStreetAddress'			=> 'Varchar(255)',
		'BusinessDataAddressLocality'		=> 'Varchar(255)',
		'BusinessDataAddressRegion'			=> 'Varchar(255)',
		'BusinessDataPostalCode'			=> 'Varchar(255)',
		'BusinessDataAddressCountry'		=> 'Varchar(2)',

		/** Geo */

		'BusinessDataGeoLatitude'			=> 'Varchar(32)',
		'BusinessDataGeoLongitude'			=> 'Varchar(32)',

		/** Meta Data */

		'BusinessDataMainSchema'			=> 'Varchar(255)',
		'BusinessDataSubSchema'				=> 'Varchar(255)'
	];

	/**
	 * @var array
	 */
	private static $has_one 	= [
		'LogoImage'			=> Image::class,
		'DefaultImage'		=> Image::class,
		'FavIcon'			=> Image::class
	];

	/** 
	 * @var array
	 */
	private static $has_many 	= [
		'SocialNetworks'	=> SocialNetwork::class
	];

	/**
	 * @var array
	 */
	private static $owns 		= [
		'LogoImage',
		'DefaultImage',
		'FavIcon'
	];

	//--------------------------------------------------------------------------

	/**
	 * Update CMS fields to include new fields
	 *
	 * @param FieldList $fields
	 * 
	 * @return void
	 */
	public function updateCMSFields(FieldList $fields): void
	{
		/** Site Details */
		$fields->addFieldsToTab(
			'Root.Business.Site',
			[
				TextField::create('BusinessDataSiteName', 'Site Name')
					->setDescription('Used for meta data for social media'),
				TextareaField::create('BusinessDataSiteDescription', 'Site Description')
					->setDescription('Short description used for default meta description')
			]
		);

		/** Main contact details */
		$allCountries 	= ArrayList::create((new ISO3166())->all());

		$fields->addFieldsToTab(
			'Root.Business.Contact.Address',
			[
				HeaderField::create('HeaderBusinessContactAddress', 'Business Address'),
				TextField::create('BusinessDataStreetAddress', 'Street Address'),
				TextField::create('BusinessDataAddressLocality', 'City / Town'),
				TextField::create('BusinessDataAddressRegion', 'Region'),
				TextField::create('BusinessDataPostalCode', 'Post Code'),
				DropdownField::create('BusinessDataAddressCountry', 'Country', $allCountries->map('alpha2', 'name'))
			]
		);

		/** Contact details */
		$fields->addFieldsToTab(
			'Root.Business.Contact.Communication',
			[
				HeaderField::create('HeaderBusinessContactCommunication', 'Communication'),
				InternationalPhoneNumberField::create('BusinessDataMainTelephone', 'Primary Telephone Number')
					->setDescription('If you have more than one number, this would be the main one you give customers'),
				EmailField::create('BusinessDataMainEmail', 'Primary Email Address')
					->setDescription('If you have more than one address, this would be the main one you give your customers'),

			]
		);

		/** Social media networks */
		$fields->addFieldToTab(
			'Root.Business.Contact.Social',
			$fieldSocialNetworks 	= GridField::create('SocialNetworks', 'Social Networks', $this->owner->SocialNetworks())
		);

		$config = GridFieldConfig_RecordEditor::create();

		$fieldSocialNetworks->setConfig($config);

		/** Branding etc */
		$fields->addFieldsToTab(
			'Root.Business.Branding',
			[
				TextField::create('BusinessDataLegalName', 'Business Legal Name'),
				UploadField::create('LogoImage', 'Logo')
					->setAllowedMaxFileNumber(1)
					->setAllowedFileCategories('image')
					->setFolderName('branding'),
				UploadField::create('DefaultImage', 'Default Image')
					->setAllowedMaxFileNumber(1)
					->setAllowedFileCategories('image')
					->setFolderName('branding'),
				UploadField::create('FavIcon', 'Fav Icon')
					->setAllowedMaxFileNumber(1)
					->setAllowedFileCategories('image')
					->setFolderName('branding')
			]
		);

		$typeSpecificSource = function ($type)
		{
			if ($type === 'Organization')
			{
				$key = 'organization_types';
			}
			else if ($type === 'LocalBusiness')
			{
				$key = 'localbusiness_types';
			}
			else if ($type === 'Event')
			{
				$key = 'event_types';
			}
			else
			{
				return [];
			}
			return self::config()->{$key};
		};

		$fields->addFieldsToTab('Root.Business.MetaData', [
			$typeField = DropdownField::create(
				'BusinessDataMainSchema',
				'Type',
				[
					'Organization'  => 'Organisation',
					'LocalBusiness' => 'Local Business',
					'Event'         => 'Event'
				]
			),
			DependentDropdownField::create(
				'BusinessDataSubSchema',
				'More specific type',
				$typeSpecificSource
			)
				->setDepends($typeField)
				->setEmptyString('- select -'),
		]);
	}

	//--------------------------------------------------------------------------

	/**
	 * Custom on before write to update the lat/long
	 *
	 * @return void
	 */
	public function onBeforeWrite(): void
	{
		/** Has post code changed, and lat/long not? */
		if (($this->owner->isChanged('BusinessDataPostalCode')) && (!$this->owner->isChanged('BusinessDataGeoLatitude')) &&
			(!$this->owner->isChanged('BusinessDataGeoLongitude')) && (!empty($this->owner->BusinessDataPostalCode))
		)
		{
			/** Update the lat/long from an API, unless disabled */
			if (self::config()->update_geodata_by_api)
			{
				/** Call data from API */
				$postcodeIo 	= new PostcodesIo();

				if ($postCodeData 	= $postcodeIo->postcodeLookup($this->owner->BusinessDataPostalCode))
				{
					$this->owner->BusinessDataGeoLatitude 	= $postCodeData['latitude'];
					$this->owner->BusinessDataGeoLongitude 	= $postCodeData['longitude'];
				}
			}
		}
	}

	//--------------------------------------------------------------------------

	/**
	 * Returns a HTML field with the saved address
	 *
	 * @return DBField
	 */
	public function getAddressBlock(): DBField
	{
		return DBField::create_field('HTMLText', implode("<br>", $this->getAddressArray()));
	}

	/**
	 * Returns an array with the saved address
	 *
	 * @return array
	 */
	public function getAddressArray(): array
	{
		$address	= [];

		if (!empty($this->owner->BusinessDataStreetAddress))
			$address[]	= $this->owner->BusinessDataStreetAddress;

		if (!empty($this->owner->BusinessDataAddressLocality))
			$address[]	= $this->owner->BusinessDataAddressLocality;

		if (!empty($this->owner->BusinessDataAddressRegion))
			$address[]	= $this->owner->BusinessDataAddressRegion;

		if (!empty($this->owner->BusinessDataPostalCode))
			$address[]	= $this->owner->BusinessDataPostalCode;

		return $address;
	}

	//--------------------------------------------------------------------------

	/**
	 * Returns an array of data suitable for microdata markup
	 *
	 * @return array
	 */
	public function getMicroDataSchemaData(): array
	{
		$data = [
			'@context'          =>  'http://schema.org',
			'@type'             =>  $this->owner->BusinessDataMainSchema,
			'@id'               =>  $this->owner->BusinessDataSubSchema,
			'mainEntityOfPage'  =>  $this->owner->SiteURL,
		];

		/** Add in the basics from the SiteConfig */
		if ($this->owner->BusinessDataSiteName)
		{
			$data['name'] = $this->owner->BusinessDataSiteName;
		}

		if ($this->owner->BusinessDataSiteDescription)
		{
			$data['description'] = $this->owner->BusinessDataSiteDescription;
		}

		/** Add logo */
		$logo 	= $this->owner->LogoImage;

		if (($logo) && ($logo->exists()))
		{
			$data['logo'] = [
				'@type'     =>  'ImageObject',
				'url'       =>  $logo->getAbsoluteURL(),
				'width'     =>  $logo->getWidth() . 'px',
				'height'    =>  $logo->getHeight() . 'px'
			];
		}

		if ($this->owner->SiteURL)
		{
			$data['url'] = $this->owner->SiteURL;
		}

		/** Check that we at least have part of the address */
		if ($this->owner->BusinessDataStreetAddress || $this->owner->BusinessDataAddressLocality || $this->owner->BusinessDataPostalCode)
		{
			$address = [
				'@type'     =>  'PostalAddress'
			];

			if ($this->owner->BusinessDataAddressCountry)
			{
				$address['addressCountry'] = $this->owner->BusinessDataAddressCountry;
			}
			if ($this->owner->BusinessDataAddressLocality)
			{
				$address['addressLocality'] = $this->owner->BusinessDataAddressLocality;
			}
			if ($this->owner->BusinessDataAddressRegion)
			{
				$address['addressRegion'] = $this->owner->BusinessDataAddressRegion;
			}
			if ($this->owner->BusinessDataPostalCode)
			{
				$address['postalCode'] = $this->owner->BusinessDataPostalCode;
			}
			if ($this->owner->BusinessDataStreetAddress)
			{
				$address['streetAddress'] = $this->owner->BusinessDataStreetAddress;
			}

			$data['address'] = $address;
		}

		/** Add lat/long if applicable */
		if ($this->owner->BusinessDataGeoLongitude && $this->owner->BusinessDataGeoLatitude)
		{
			$coordinates = array(
				'@type'     =>  'GeoCoordinates',
				'latitude'  =>  $this->owner->BusinessDataGeoLatitude,
				'longitude' =>  $this->owner->BusinessDataGeoLongitude,
			);
		}

		/** Include contact details, if they're set */
		if ($this->owner->BusinessDataMainTelephone)
		{
			$data['telephone'] = $this->owner->BusinessDataMainTelephone;
		}
		if ($this->owner->BusinessDataMainEmail)
		{
			$data['email'] = $this->owner->BusinessDataMainEmail;
		}

		/** Include co-ordinates and a map, if applicable */
		if (isset($coordinates))
		{
			$data['geo'] = $coordinates;
		}

		if ($this->owner->getMicroDataSchemaType(true) === 'LocalBusiness')
		{
			if ($this->owner->getSocialMetaMapLink())
			{
				$data['hasMap'] = $this->owner->getSocialMetaMapLink();
			}
		}

		/** Include any social networks */
		$socialNetworks = $this->owner->SocialNetworks();

		if (($socialNetworks) && (count($socialNetworks) > 0))
		{
			$sameAs = [];

			foreach ($socialNetworks as $network)
			{
				$sameAs[] = $network->URL;
			}

			$data['sameAs'] = $sameAs;
		}

		$this->owner->invokeWithExtensions('updateSchemaData', $data);

		return $data;
	}

	/** Returns the schema type */
	public function getMicroDataSchemaType($baseTypeOnly = false): bool
	{
		return ($this->owner->BusinessDataSubSchema && !$baseTypeOnly)
			? $this->owner->BusinessDataSubSchema
			: $this->owner->BusinessDataMainSchema;
	}

	/**
	 * Returns a Google Maps link for the organisation
	 *
	 * @return string
	 */
	public function getSocialMetaMapLink(): string
	{
		$address 	= $this->owner->getAddressArray();

		if (!empty($this->owner->BusinessDataLegalName))
		{
			$address 	= array_merge([$this->owner->BusinessDataLegalName], $address);
		}

		if (!empty($address))
		{
			return 'https://www.google.co.uk/maps/place/' . urlencode(implode(", ", $address));
		}

		return null;
	}
}
