<?php

/**
 * One-off task migrating legacy Roseblade_SocialNetworks rows.
 *
 * @package roseblade/businessdata
 * @author  Roseblade Media
 */

declare(strict_types=1);

namespace Roseblade\BusinessData\BuildTask;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\SiteConfig\SiteConfig;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Migrates rows from this package's own, now-removed `SocialNetwork`
 * DataObject (table `Roseblade_SocialNetworks`, a fixed `has_many` on
 * `SiteConfig`) into `roseblade/silverstripe-social-networks`' record
 * of the same name, whose `Owner` relation is polymorphic and can be
 * attached to any class.
 *
 * This is a one-off, run-once task, not something either package keeps
 * running indefinitely: once run, the old table (`Roseblade_SocialNetworks`)
 * can be dropped. This task does not drop it itself, since removing a
 * database table is not something a build task should ever do silently.
 *
 * Deliberately has no `use` statement importing anything from
 * `Roseblade\SocialNetworks`: this task only makes sense to run once
 * that package is installed, but businessdata itself only ever
 * `suggest`s it (see composer.json), so this class must not fail to
 * autoload on a site that doesn't have it. The new table is written to
 * directly via raw SQL instead.
 *
 * @api
 */
class MigrateSocialNetworksTask extends BuildTask
{
    private static string $segment = 'MigrateSocialNetworksTask';

    protected string $title = 'Migrate legacy social networks to roseblade/silverstripe-social-networks';

    protected static string $description = 'Copies rows from the old Roseblade_SocialNetworks table (this '
        . 'package\'s own, now-removed SocialNetwork DataObject) into roseblade/silverstripe-social-networks\' '
        . 'table, attached to the current SiteConfig. Safe to run more than once: a row already migrated (matched '
        . 'by NetworkName, Label and URL) is skipped, not duplicated. Does not drop the old table.';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        if (!class_exists('Roseblade\\SocialNetworks\\DataObject\\SocialNetwork')) {
            $output->writeln(
                'roseblade/silverstripe-social-networks is not installed; nothing to migrate into. '
                . 'Install it, run dev/build, then run this task again.'
            );

            return 1;
        }

        if (!DB::get_schema()->hasTable('Roseblade_SocialNetworks')) {
            $output->writeln('No Roseblade_SocialNetworks table found; nothing to migrate.');

            return 0;
        }

        $siteConfig = SiteConfig::current_site_config();
        $ownerClass = $siteConfig->baseClass();

        $legacyRows = DB::query('SELECT "NetworkName", "Label", "URL", "Sort" FROM "Roseblade_SocialNetworks"');

        $migrated = 0;
        $skipped = 0;

        foreach ($legacyRows as $row) {
            $alreadyMigrated = DB::prepared_query(
                'SELECT COUNT(*) FROM "SocialNetwork" '
                . 'WHERE "NetworkName" = ? AND "Label" = ? AND "URL" = ? '
                . 'AND "OwnerID" = ? AND "OwnerClass" = ?',
                [$row['NetworkName'], $row['Label'], $row['URL'], $siteConfig->ID, $ownerClass]
            )->value();

            if ($alreadyMigrated > 0) {
                $skipped++;

                continue;
            }

            $socialNetwork = \Roseblade\SocialNetworks\DataObject\SocialNetwork::create();
            $socialNetwork->NetworkName = $row['NetworkName'];
            $socialNetwork->Label = $row['Label'];
            $socialNetwork->URL = $row['URL'];
            $socialNetwork->Sort = (int) $row['Sort'];
            $socialNetwork->OwnerID = $siteConfig->ID;
            $socialNetwork->OwnerClass = $ownerClass;
            $socialNetwork->write();

            $migrated++;
        }

        $output->writeln(sprintf('Migrated %d row(s); skipped %d already-migrated row(s).', $migrated, $skipped));
        $output->writeln(
            'The old Roseblade_SocialNetworks table has not been removed. '
            . 'Once you have confirmed the migrated records in the CMS, it can be dropped manually.'
        );

        return 0;
    }
}
