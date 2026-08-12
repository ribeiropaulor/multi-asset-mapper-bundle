# MultiAssetMapperBundle

This bundle extends the Symfony Asset Mapper component to allow managing multiple import maps.
It allows multiple `importmap.php` to exist, enabling better organization and management of assets in 
complex Symfony applications.

Table of Contents
=================

* [MultiAssetMapperBundle](#multiassetmapperbundle)
    * [Why use this bundle?](#why-use-this-bundle)
    * [Installation](#installation)
        * [Add the bundle to your Symfony project](#add-the-bundle-to-your-symfony-project)
        * [Add the importmap to a Twig base template](#add-the-importmap-to-a-twig-base-template)
        * [Add the JavaScript dependencies](#add-the-javascript-dependencies)
        * [Configure symfony/asset for serving files (image, PDF, etc.)](#configure-symfonyasset-for-serving-files-image-pdf-etc)
    * [Usage](#usage)
        * [Available console commands](#available-console-commands)
    * [Hotwire Stimulus](#hotwire-stimulus)
    * [License](#license)

<!-- Created by https://github.com/ekalinin/github-markdown-toc -->

## Why use this bundle?

You might have different areas in your application that require different sets of assets. For example, you might have
a public-facing website and an admin area, each with its own set of JavaScript, CSS and image files (referred to as 
**asset collections**). Each **asset collection** has a separate importmap, keeping their dependencies isolated.
So, you can even have different versions of JavaScript libraries for each **asset collection**, without conflicts.

Examples of common asset collections include:

- `admin`, `backend`: for the admin area of your application
- `frontend`, `public`: for the public-facing website
- `mobile`, `desktop`: for different device-specific assets
- `marketing`, `blog`: for marketing or blog sections of your application
- `legacy`, `modern`: for legacy and modern versions of your application
- `user`, `guest`: for user-specific and guest-specific assets
- `theme1`, `theme2`: for different themes or skins of your application

The following showcases demonstrates how you can manage different asset collections in a Symfony application:

- `admin` and `frontend` asset collections: [See example](https://github.com/ribeiropaulor/multi-asset-mapper-bundle/wiki/Example-01:%60admin%60-e-%60frontend%60-asset-collections)

The following issues provide more context on the motivation behind this bundle:

- [#54377](https://github.com/symfony/symfony/issues/54377)
- [#62668](https://github.com/symfony/symfony/issues/62668)

## Installation

### Add the bundle to your Symfony project

You can install this bundle using Composer:

```bash
composer require prr/multi-asset-mapper-bundle
```

Create `config/packages/multi_asset_mapper.yaml` listing the asset collections you want to manage. In the following
example, we are managing two asset collections: `admin` and `frontend`.

```bash
php bin/console mam:asset-collection:install admin frontend
```

Now, you have to clear the cache to make the bundle aware of the new configuration:

```bash
php bin/console cache:clear
```

### Add the importmap to a Twig base template

Edit you Twig template to include the importmap for the desired asset collection. For example,
to include the `admin` asset collection importmap using the `main` entrypoint,
add the following line to your Twig template:

```twig
{{ mam_importmap('admin', 'main') }}
```

### Add the JavaScript dependencies

Adding a new dependency to an asset collection is done using the `mam:importmap:require` command.
For example, to add the `imask` library to the `admin` asset collection, run:

```bash
php bin/console mam:importmap:require admin imask
```

### Configure symfony/asset for serving files (image, PDF, etc.)

You need to configure the `assets` section in your `config/packages/framework.yaml` file to serve files from the 
`public/asset-collections` directory. For example, the following configuration serves files from the 
`admin` and `frontend` asset collections:

```yaml
framework:
    assets:
        packages:
            admin:
                base_path: '/asset-collections/admin'
            frontend:
                base_path: '/asset-collections/frontend'
```

To serve assets like images or other files, use the `asset` function in your Twig templates.
For example, to include an image from the `admin` asset collection, use:

```twig
<img src="{{ asset('images/admin.png', 'admin') }}">
```

**You must be sure to require `symfony/asset` in your project to use the `asset` function.**
The second argument of the `asset` function is the name of the asset collection.

## Usage

### Available console commands

| Command | Description |
|---------|-------------|
| `mam:importmap:require` | Add a new dependency to an asset collection |
| `mam:importmap:update` | Update JavaScript packages to their latest versions |
| `mam:importmap:remove` | Remove JavaScript packages |
| `mam:asset-map:compile` | Compile all mapped assets and writes them to the final public output directory |
| `mam:importmap:install` | Download all assets that should be downloaded |
| `mam:debug:asset-map` | Output all mapped assets |
| `mam:importmap:outdated` | List outdated JavaScript packages and their latest versions |
| `mam:importmap:audit` | Check for security vulnerability advisories for dependencies |
| `mam:asset-collection:install` | Install asset collections |

## Hotwire Stimulus

If you want to use Stimulus with this bundle, you can install the bundle `prr/multi-stimulus-bundle`.
It will allow you to manage a different set of Stimulus controllers for each asset collection.

## License

MultiAssetMapperBundle is released under the [MIT License](https://opensource.org/licenses/MIT).