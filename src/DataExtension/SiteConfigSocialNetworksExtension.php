<?php

/**
 * Adds sameAs structured data from SiteConfig's attached social networks.
 *
 * @package roseblade/businessdata
 * @author  Roseblade Media
 */

declare(strict_types=1);

namespace Roseblade\BusinessData\DataExtension;

use SilverStripe\SiteConfig\SiteConfig;

/**
 * Adds a `sameAs` entry to SiteConfig's JSON-LD structured data from
 * whatever social network profile links are attached to it, if
 * roseblade/silverstripe-social-networks is installed.
 *
 * This extension is only ever applied by
 * `_config/social-networks.yml`, which is itself gated on that
 * module's `WithSocialNetworks` extension actually existing (see that
 * file's `Only: classexists` guard); on a site without the module
 * installed, this class is never applied and never runs.
 *
 * Deliberately a separate extension from {@see SiteConfigExtension},
 * rather than logic added directly to
 * {@see SiteConfigExtension::getMicroDataSchemaData()}: that method
 * has no reference to `Roseblade\SocialNetworks\...` anywhere, and
 * reaches this extension purely through the `updateSchemaData`
 * extension hook it already calls. This is what lets
 * roseblade/silverstripe-social-networks stay a `suggest`, not a
 * `require`, of this package.
 *
 * @extends \SilverStripe\Core\Extension<SiteConfig>
 *
 * @api
 */
class SiteConfigSocialNetworksExtension extends \SilverStripe\Core\Extension
{
    /**
     * @param array<string, mixed> $data
     */
    public function updateSchemaData(array &$data): void
    {
        $owner = $this->getOwner();

        if (!$owner->hasMethod('SocialNetworks')) {
            return;
        }

        /** @var SiteConfig&\Roseblade\SocialNetworks\Extension\HasSocialNetworks $owner */
        $socialNetworks = $owner->SocialNetworks();

        if ($socialNetworks->count() === 0) {
            return;
        }

        $sameAs = [];

        foreach ($socialNetworks as $socialNetwork) {
            $sameAs[] = $socialNetwork->URL;
        }

        $data['sameAs'] = $sameAs;
    }
}
