# Jyavani People

Jyavani People is a general-purpose directory and professional profile plugin
for Jyavani CMS.

Version `0.1.0` provides a translation-ready schema, profile administration,
structured links and taxonomies, public list and single routes, responsive
neutral templates, year-aware typed-entry rendering, metadata, Schema.org
`Person`, and a people sitemap.

The plugin requires Jyavani `2.3.102` or newer. Install it through Jyavani's
Plugin Manager so migrations and static asset publication use Core lifecycle
guards. Do not copy the directory into a live `plugins/` tree.

Public routes use `/people/` by default. A deployment may set the
`jyp_base_path` setting before activation to choose another collision-free base
path. Active themes can override `list.php` and `single.php` under:

```text
views/themes/{active-theme}/plugins/jyavani-people/
```

The plugin operates in one source locale without another plugin. Its storage
separates translatable presentation from stable identity so an optional generic
translation adapter can be added without destructive migration.
