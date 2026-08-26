# roseblade/businessdata

A Silverstripe module that adds business contact details, structured data (JSON-LD), and favicon generation to your site via `SiteConfig`.

## Features

- Business name, description, legal name, address, telephone, and email fields on `SiteConfig`
- Automatic geo-coordinates lookup from postcode via the Postcodes.io API
- JSON-LD structured data (`schema.org`) injected into page `<head>`, supporting `Organization`, `LocalBusiness`, and `Event` types
- Favicon generation at multiple sizes from a single uploaded image, injected as `<link>` tags
- Optional social network links via [`roseblade/silverstripe-social-networks`](https://github.com/RosebladeMedia/silverstripe-social-networks), a separate, optional module that adds a `sameAs` entry to the JSON-LD output when installed. See [Social Networks](#social-networks) below.

## Requirements

- Silverstripe CMS ^6.0
- PHP ^8.1

## Installation

```sh
composer require roseblade/businessdata
```

Run a database build after installation:

```sh
vendor/bin/sake db:build
```

or by visiting `/dev/build?flush=all` in your browser

## Configuration

### JSON-LD structured data

By default, JSON-LD is only output on the home page. You can change this in YAML:

```yaml
Silverstripe\CMS\Model\SiteTree:
  include_site_jsonld: home   # 'home' (default) or 'all'
```

To disable minification of the JSON-LD output (good for debugging purposes):

```yaml
Roseblade\BusinessData\DataExtension\SiteTreeExtension:
  minify_jsonld: false
```

You can also control inclusion on a per-page basis in PHP:

```php
$page->setIncludeSiteSchemaData(true);  // force include
$page->setIncludeSiteSchemaData(false); // force exclude
```

### Favicon

Upload a favicon image via **Settings > Business > Branding** in the CMS. The module will automatically generate and inject `<link>` tags at the following sizes: 16×16, 32×32, 96×96, 180×180, 300×300, and 512×512.

The default behaviour pads the image to a square with a white fill. You can override the fill colour or use a different resize function:

```yaml
Roseblade\BusinessData\DataExtension\SiteTreeExtension:
  icon_fill: "#000000"         # background fill colour for padding (default: #ffffff)
  icon_size_function: pad      # Silverstripe image manipulation method (default: pad)
```

To customise which icon sizes and `<link>` attributes are generated:

```yaml
Roseblade\BusinessData\DataExtension\SiteTreeExtension:
  icons:
    - rel: icon
      sizes: 32x32
      type: "{getMimeType}"
    - rel: apple-touch-icon
      sizes: 180x180
      type: "{getMimeType}"
```

Values wrapped in `{}` are resolved as method calls on the generated image object (e.g. `{getMimeType}` calls `$image->getMimeType()`).

### Geo-coordinates

When a postcode is saved on `SiteConfig`, the module will automatically look up the latitude and longitude via the Postcodes.io API. To disable this:

```yaml
Roseblade\BusinessData\DataExtension\SiteConfigExtension:
  update_geodata_by_api: false
```

## Social Networks

From version 2.0.0, this module no longer stores social network links itself. That functionality has moved to a separate, optional module, [`roseblade/silverstripe-social-networks`](https://github.com/RosebladeMedia/silverstripe-social-networks), which can attach social network links to any DataObject, not just `SiteConfig`. See the [changelog](CHANGELOG.md) for the full reasoning and an upgrade guide if you are updating from an earlier version.

### Adding social network links to your site

Install the new module alongside this one:

```sh
composer require roseblade/silverstripe-social-networks
```

Apply its extension to `SiteConfig` in your own project YAML:

```yaml
SilverStripe\SiteConfig\SiteConfig:
  extensions:
    - Roseblade\SocialNetworks\Extension\WithSocialNetworks
  has_many:
    SocialNetworks: Roseblade\SocialNetworks\DataObject\SocialNetwork.Owner
```

Run `dev/build`. A "Social networks" tab appears in **Settings**, and any links you add there are automatically included as a `sameAs` entry in this module's JSON-LD output, added via `SiteConfigSocialNetworksExtension`, which hooks into the same `updateSchemaData` extension point that lets any module contribute to the structured data this module produces.

If `roseblade/silverstripe-social-networks` is not installed, nothing changes: the JSON-LD output simply has no `sameAs` entry, and no error occurs.

See `roseblade/silverstripe-social-networks`'s own documentation for the full detail on attaching social network links to other DataObjects, such as a team or a player, beyond `SiteConfig`.

### Upgrading from a version with built-in social networks

If your site already has social network links stored by an earlier version of this module, install `roseblade/silverstripe-social-networks`, apply the extension as shown above, run `dev/build`, then run:

```sh
vendor/bin/sake tasks:Roseblade-BusinessData-BuildTask-MigrateSocialNetworksTask
```

This copies your existing links into the new module's table. It does not remove the old data, and it is safe to run more than once. If you run `dev/build` before running this task, a warning is printed reminding you to do so.
