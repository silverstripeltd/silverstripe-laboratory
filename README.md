# Silverstripe Laboratory

[![CI](https://github.com/silverstripe/silverstripe-laboratory/actions/workflows/ci.yml/badge.svg)](https://github.com/silverstripe/silverstripe-laboratory/actions/workflows/ci.yml)

## Installation

```sh
composer require silverstripe/laboratory
```

### Integration

Add `$TrafficSplitSnippet` into your page template.
Placement inside the `<body>` tag is recommended.

#### Elemental content variation feature

This feature allows to have multiple content variations within a single page using Elemental module (content blocks).
This feature is disabled by default.
To enable it please follow the steps below.

Add this code snippet into your `config.yml`

```yaml
SilverStripe\Laboratory\Service\TrafficSplitService:
  element_variation_enabled: true
```

Add a template override to your theme with the following contents:

```
<% if $ElementControllersWithTrafficSplit %>
  <% loop $ElementControllersWithTrafficSplit %>
    $Me
  <% end_loop %>
<% end_if %>
```

The file needs to be called `ElementalArea.ss` and it needs to be placed under the following path:

`/themes/<your-theme-folder>/templates/DNADesign/Elemental/Models/ElementalArea.ss`

## Documentation

This module provides capability to support experimentation.
Typical experimentation setup consists of:

* primary content variation - usually current content on the website
* secondary content variation - usually new content which needs to be validated
* traffic split rule - segments website visitors between the two content variations

This module aims to provide the necessary content setup to run content experiments.

### Default setup - only using pages

This setup is intended for sites that do not use Elemental module.
The most common setup is as follows:

* entry page - `www.mysite.com/entry-page-url`
* primary content variation page - `www.mysite.com/primary-variation-url`
* secondary content variation page - `www.mysite.com/secondary-variation-url`

It's common that entry page is the same page as primary content variation page.
Website visitor will arrive at the entry page URL and will end up in one of the content variation pages.
The page URL changes which may be confusing for the website visitor, and also it may have SEO impact as web crawlers might not like this.

### Elemental setup - using block level content variation

This setup is intended for sites that do use Elemental module.
The most common setup is as follows:

* entry page - `www.mysite.com/my-page`
* primary content variation page - `www.mysite.com/my-page?laboratory-traffic-split=primary`
* secondary content variation page - `www.mysite.com/my-page?laboratory-traffic-split=secondary`

In this situation the website visitor stays on the same page URL, the only difference are GET params.
This provides a better solution compared to the default setup.
It also has lower impact on SEO.

SEO can be further mitigated by including canonical page meta tag in your page template.

### CMS laboratory admin

Use the Laboratory admin in the CMS to manage content related to this module.
This admin allows you to set up traffic splits and associate them with content pages.

If you're using the Elemental setup of this module, you need to set up content variations on block level on your variation pages.
The content variation identifier is located under settings tab on each content block.

The recommended setup is below:

* content blocks that are the same for all content variations - leave the field empty
* content blocks that belong to primary content variation - use "primary" as the identifier value
* content blocks that belong to secondary content variation - use "secondary" as the identifier value

### Configuration

`TrafficSplitService` has several static configuration properties which come with defaults but can be changed.
This is intended to provide an easier integration with tracking solutions and CDNs.
You can change how GET params and cookies created by this module are named as well as turn some features on or off.

### What's not included

There are several parts of experimentation setup that aren't covered, and you're expected to cover it yourself.

#### User tracking

This module doesn't provide any out of the box capability to evaluate experiments - it has no user tracking capability.
The reason for this is that the module should be used only as a foundation for experimentation so it remains flexible and compatible with many different user tracking solutions.

#### CDN integration

The content variations which this module provides uses cookies and GET params.
It is your responsibility to set up your CDN in a way that the content variations are correctly presented to the website visitor on the CDN level.
This module doesn't require a dynamic render so CDN caching will work as long as your caching keys are correctly configured.

#### CDN level audience segmentation

This module relies on JavaScript to perform redirection of the website visitor to the assigned content variation.
Some CDNs are capable of performing this task and serve content variations from a single URL.
In this case the module can be used only as a foundation to manage multiple content variations under a single URL, opting out of the redirection behaviour of the module.
