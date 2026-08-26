<?php

/**
 * Warns during dev/build if legacy social network data needs migrating.
 *
 * @package roseblade/businessdata
 * @author  Roseblade Media
 */

declare(strict_types=1);

namespace Roseblade\BusinessData\DataExtension;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\Command\DbBuild;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;

/**
 * Installing `roseblade/silverstripe-social-networks` alongside this
 * package does not, on its own, move any data out of this package's
 * old, now-removed `SocialNetwork` DataObject (table
 * `Roseblade_SocialNetworks`) into the new module's table. Running
 * `dev/build` creates the new table, but the old data stays exactly
 * where it was until `MigrateSocialNetworksTask` is run deliberately.
 *
 * Until that happens, the CMS shows no social network links and JSON-LD
 * output has no `sameAs` entry, with nothing to explain why: the old
 * data still exists, it is simply no longer being read by anything.
 * This extension exists so that gap is visible, not silent: it checks,
 * after every `dev/build`, whether the old table still has rows the new
 * one doesn't, and prints a warning naming the exact task to run.
 *
 * This only ever warns; it never migrates data itself. Migration is a
 * deliberate, explicit action a site operator takes (see
 * {@see MigrateSocialNetworksTask}), not something that happens as a
 * side effect of building the database schema.
 *
 * Only applied by `_config/social-networks.yml`, gated on
 * `roseblade/silverstripe-social-networks` actually being installed
 * (`Only: classexists`); on a site without that module, the old table
 * (if it exists at all) is simply legacy data with nothing to migrate
 * it into, and this extension does not apply.
 *
 * @extends Extension<DbBuild>
 *
 * @api
 */
class WarnAboutUnmigratedSocialNetworksExtension extends Extension
{
    protected function onAfterBuild(PolyOutput $output): void
    {
        $schema = DB::get_schema();

        if (!$schema->hasTable('Roseblade_SocialNetworks') || !$schema->hasTable('SocialNetwork')) {
            return;
        }

        $legacyCount = (int) DB::query('SELECT COUNT(*) FROM "Roseblade_SocialNetworks"')->value();

        if ($legacyCount === 0) {
            return;
        }

        $migratedCount = (int) DB::query('SELECT COUNT(*) FROM "SocialNetwork"')->value();

        if ($migratedCount > 0) {
            return;
        }

        $output->writeln([
            '',
            '<options=bold>Legacy social network data found, not yet migrated</>',
            sprintf(
                'The old Roseblade_SocialNetworks table has %d row(s) that have not been moved '
                    . 'into roseblade/silverstripe-social-networks\' SocialNetwork table.',
                $legacyCount
            ),
            'Until this is done, social network links will not appear in the CMS or in JSON-LD output.',
            'Run: sake tasks:Roseblade-BusinessData-BuildTask-MigrateSocialNetworksTask',
            '',
        ]);
    }
}
