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
use SilverStripe\ORM\FieldType\DBField;

/**
 * Adds custom fields to include business communication,
 * address and other related fields
 */
class SiteConfigExtension extends \SilverStripe\Core\Extension
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
		$allCountries 	= \SilverStripe\Model\List\ArrayList::create((new ISO3166())->all());

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
			$fieldSocialNetworks 	= GridField::create('SocialNetworks', 'Social Networks', $this->getOwner()->SocialNetworks())
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
		if (($this->getOwner()->isChanged('BusinessDataPostalCode')) && (!$this->getOwner()->isChanged('BusinessDataGeoLatitude')) &&
			(!$this->getOwner()->isChanged('BusinessDataGeoLongitude')) && (!empty($this->getOwner()->BusinessDataPostalCode))
		)
		{
			/** Update the lat/long from an API, unless disabled */
			if (self::config()->update_geodata_by_api)
			{
				/** Call data from API */
				$postcodeIo 	= new PostcodesIo();

				if ($postCodeData 	= $postcodeIo->postcodeLookup($this->getOwner()->BusinessDataPostalCode))
				{
					$this->getOwner()->BusinessDataGeoLatitude 	= $postCodeData['latitude'];
					$this->getOwner()->BusinessDataGeoLongitude 	= $postCodeData['longitude'];
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

		if (!empty($this->getOwner()->BusinessDataStreetAddress))
			$address[]	= $this->getOwner()->BusinessDataStreetAddress;

		if (!empty($this->getOwner()->BusinessDataAddressLocality))
			$address[]	= $this->getOwner()->BusinessDataAddressLocality;

		if (!empty($this->getOwner()->BusinessDataAddressRegion))
			$address[]	= $this->getOwner()->BusinessDataAddressRegion;

		if (!empty($this->getOwner()->BusinessDataPostalCode))
			$address[]	= $this->getOwner()->BusinessDataPostalCode;

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
			'@type'             =>  $this->getOwner()->BusinessDataMainSchema,
			'@id'               =>  $this->getOwner()->BusinessDataSubSchema,
			'mainEntityOfPage'  =>  $this->getOwner()->SiteURL,
		];

		/** Add in the basics from the SiteConfig */
		if ($this->getOwner()->BusinessDataSiteName)
		{
			$data['name'] = $this->getOwner()->BusinessDataSiteName;
		}

		if ($this->getOwner()->BusinessDataSiteDescription)
		{
			$data['description'] = $this->getOwner()->BusinessDataSiteDescription;
		}

		/** Add logo */
		$logo 	= $this->getOwner()->LogoImage;

		if (($logo) && ($logo->exists()))
		{
			$data['logo'] = [
				'@type'     =>  'ImageObject',
				'url'       =>  $logo->getAbsoluteURL(),
				'width'     =>  $logo->getWidth() . 'px',
				'height'    =>  $logo->getHeight() . 'px'
			];
		}

		if ($this->getOwner()->SiteURL)
		{
			$data['url'] = $this->getOwner()->SiteURL;
		}

		/** Check that we at least have part of the address */
		if ($this->getOwner()->BusinessDataStreetAddress || $this->getOwner()->BusinessDataAddressLocality || $this->getOwner()->BusinessDataPostalCode)
		{
			$address = [
				'@type'     =>  'PostalAddress'
			];

			if ($this->getOwner()->BusinessDataAddressCountry)
			{
				$address['addressCountry'] = $this->getOwner()->BusinessDataAddressCountry;
			}
			if ($this->getOwner()->BusinessDataAddressLocality)
			{
				$address['addressLocality'] = $this->getOwner()->BusinessDataAddressLocality;
			}
			if ($this->getOwner()->BusinessDataAddressRegion)
			{
				$address['addressRegion'] = $this->getOwner()->BusinessDataAddressRegion;
			}
			if ($this->getOwner()->BusinessDataPostalCode)
			{
				$address['postalCode'] = $this->getOwner()->BusinessDataPostalCode;
			}
			if ($this->getOwner()->BusinessDataStreetAddress)
			{
				$address['streetAddress'] = $this->getOwner()->BusinessDataStreetAddress;
			}

			$data['address'] = $address;
		}

		/** Add lat/long if applicable */
		if ($this->getOwner()->BusinessDataGeoLongitude && $this->getOwner()->BusinessDataGeoLatitude)
		{
			$coordinates = ['@type'     =>  'GeoCoordinates', 'latitude'  =>  $this->getOwner()->BusinessDataGeoLatitude, 'longitude' =>  $this->getOwner()->BusinessDataGeoLongitude];
		}

		/** Include contact details, if they're set */
		if ($this->getOwner()->BusinessDataMainTelephone)
		{
			$data['telephone'] = $this->getOwner()->BusinessDataMainTelephone;
		}
		if ($this->getOwner()->BusinessDataMainEmail)
		{
			$data['email'] = $this->getOwner()->BusinessDataMainEmail;
		}

		/** Include co-ordinates and a map, if applicable */
		if (isset($coordinates))
		{
			$data['geo'] = $coordinates;
		}

		if ($this->getOwner()->getMicroDataSchemaType(true) === 'LocalBusiness')
		{
			if ($this->getOwner()->getSocialMetaMapLink())
			{
				$data['hasMap'] = $this->getOwner()->getSocialMetaMapLink();
			}
		}

		/** Include any social networks */
		$socialNetworks = $this->getOwner()->SocialNetworks();

		if (($socialNetworks) && (count($socialNetworks) > 0))
		{
			$sameAs = [];

			foreach ($socialNetworks as $network)
			{
				$sameAs[] = $network->URL;
			}

			$data['sameAs'] = $sameAs;
		}

		$this->getOwner()->invokeWithExtensions('updateSchemaData', $data);

		return $data;
	}

	/** Returns the schema type */
	public function getMicroDataSchemaType($baseTypeOnly = false): bool
	{
		return ($this->getOwner()->BusinessDataSubSchema && !$baseTypeOnly)
			? $this->getOwner()->BusinessDataSubSchema
			: $this->getOwner()->BusinessDataMainSchema;
	}

	/**
	 * Returns a Google Maps link for the organisation
	 *
	 * @return string
	 */
	public function getSocialMetaMapLink(): string
	{
		$address 	= $this->getOwner()->getAddressArray();

		if (!empty($this->getOwner()->BusinessDataLegalName))
		{
			$address 	= array_merge([$this->getOwner()->BusinessDataLegalName], $address);
		}

		if (!empty($address))
		{
			return 'https://www.google.co.uk/maps/place/' . urlencode(implode(", ", $address));
		}

		return '';
	}
}
