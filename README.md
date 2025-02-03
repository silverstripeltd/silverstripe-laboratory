# Silverstripe Laboratory

[![CI](https://github.com/silverstripe/silverstripe-laboratory/actions/workflows/ci.yml/badge.svg)](https://github.com/silverstripe/silverstripe-laboratory/actions/workflows/ci.yml)


## Installation

```sh
composer require silverstripe/laboratory
```

### Integration

Add `$TrafficSplitSnippet` into your page template.
Placement inside the `<body>` tag is recommended.

#### Element variation feature

This feature allows the have multiple content variations within a single page using Elemental module (content blocks).
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

Use the Laboratory admin in the CMS to manage content related to this module.
