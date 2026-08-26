# Changelog

All notable changes to this module are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed

- **Social network links have moved to a separate, optional module.** This module no longer defines a `SocialNetwork` DataObject, no longer has a `SocialNetworks` relation on `SiteConfig`, and no longer shows a "Social Networks" tab in the CMS. This functionality now lives in [`roseblade/silverstripe-social-networks`](https://github.com/RosebladeMedia/silverstripe-social-networks), a standalone module that can attach social network links to any DataObject, not just `SiteConfig`.
- The `sameAs` entry in this module's JSON-LD output is no longer built directly by `SiteConfigExtension`. If `roseblade/silverstripe-social-networks` is installed and its extension is applied to `SiteConfig` (see the README), a new class, `SiteConfigSocialNetworksExtension`, adds `sameAs` via the existing `updateSchemaData` extension hook. If the new module is not installed, `sameAs` is simply absent from the output, exactly as it would be if there were no social network links configured at all.
- `fromholdio/silverstripe-externalurlfield` has been removed from `require`, since it was only ever used by the now-removed `SocialNetwork` DataObject.
- `roseblade/silverstripe-social-networks` has been added to `suggest`. It is entirely optional: this module works fully without it, exactly as it always has for every feature other than social network links.

### Added

- `MigrateSocialNetworksTask`, a `dev/tasks` task that copies existing social network data from this module's old `Roseblade_SocialNetworks` table into `roseblade/silverstripe-social-networks`' table, attached to the current `SiteConfig`. Safe to run more than once. Does not remove the old table.
- A warning, printed after `dev/build`, if social network data exists in the old table but has not yet been migrated into the new module's table. This only appears if `roseblade/silverstripe-social-networks` is installed; it does not apply to sites that do not use social network links at all.

### Why this change was made

Social network links never needed to belong specifically to `SiteConfig`, or to this module. Keeping them here meant any other project wanting the same feature on a different record (a team, a player, an event) had no way to reuse the code, and had to write it again from scratch. Splitting it into its own module means the feature can be attached to anything, is independently useful outside this module entirely, and this module stays focused on what it is actually for: describing the business itself.

**This is a breaking change if your site uses this module's social network links.** Existing links are not lost; they remain in the database until you choose to migrate and remove them. However, they will stop appearing in the CMS and in JSON-LD output until you install the new module and run the migration task described in the README's [Social Networks](README.md#social-networks) section. If your site does not use social network links, this change has no effect on you.

## [1.0.1] - 2026-08-25

### Fixed

- Removed a reference to an internal, unrelated project from the README that should not have been published.

## [1.0.0] - 2026-08-25

### Changed

- Added an explicit `license` key (`BSD-3-Clause`, matching the existing `LICENSE` file), and `php`/`silverstripe/framework` version constraints to `composer.json`.
- Added a PSR-4 `autoload` block to `composer.json`, rather than relying on manifest scanning.
- Added `declare(strict_types=1)` and a header docblock to every source file.
- Removed `package.json`, unused front-end tooling left over from module scaffolding. This module has no JavaScript assets.

This is a non-breaking, hygiene-only release. No public behaviour changed.

## [0.0.2] - 2026-01-23

- SilverStripe 6 compatibility fixes, including a corrected class reference for the external URL field and a corrected favicon existence check.

## [0.0.1] - 2025-04-03

- Initial tagged release.
