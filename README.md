# roseblade/businessdata

A Silverstripe module that adds business contact details, structured data (JSON-LD), favicon generation, and social network links to your site via `SiteConfig`.

This module is fully standalone: it has no dependency on `roseblade/core` or any other Roseblade package, and works completely on its own. A separate package, [`roseblade/businessdata-bridge`](https://bitbucket.org/roseblade/businessdata-bridge), makes this module's data available to the wider Roseblade ecosystem for sites that use it; installing that bridge is entirely optional and this module never needs to know it exists.

## Features

- Business name, description, legal name, address, telephone, and email fields on `SiteConfig`
- Automatic geo-coordinates lookup from postcode via the Postcodes.io API
- JSON-LD structured data (`schema.org`) injected into page `<head>` — supports `Organization`, `LocalBusiness`, and `Event` types
- Favicon generation at multiple sizes from a single uploaded image, injected as `<link>` tags
- Social network links (stored as `SocialNetwork` records, exposed as `sameAs` in JSON-LD)

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
