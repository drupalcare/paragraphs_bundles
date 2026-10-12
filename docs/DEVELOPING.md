# Paragraphs Bundles: developer guide

Extension points, services, hooks, and how to add a new bundle
sub-module.

---

## Architecture overview

```
paragraphs_bundles/          Parent: shared fields, CSS pipeline, hooks
├── src/Hook/                OOP hooks (entity, preprocess, library, …)
├── src/Service/             Bundle, CSS, Theme, Form verifiers
├── config/optional/         Shared field storage + simple_bundle demo
└── modules/
    └── paragraph_bundle_*/  One sub-module per bundle family
        ├── config/optional/
        ├── css/  js/  templates/
        └── src/Hook/          Bundle-specific preprocess / theme hooks
```

Parent module owns the **dynamic CSS pipeline** and shared
`pb_display_*` field storage. Sub-modules own paragraph types, bundle-
specific fields, Twig templates, and static CSS/JS.

---

## Services (parent module)

Inject interfaces from `paragraphs_bundles.services.yml`:

| Service ID | Interface | Role |
|------------|-----------|------|
| `paragraphs_bundles.bundle_slugger` | `BundleSluggerInterface` | Machine name
→ CSS slug (`card_two_columns_bundle` → `card-two-columns`) |
| `paragraphs_bundles.css.dynamic_builder` | `PbDynamicCssBuilderInterface` |
Paragraph entity → scoped CSS string |
| `paragraphs_bundles.css.user_var_builder` | `PbDynamicUserVarBuilderInterface`
| Non-color display fields → `--pb-*` declarations |
| `paragraphs_bundles.css.color_normalizer` | `PbColorNormalizerInterface` |
Hex/RGB normalization for dynamic CSS |
| `paragraphs_bundles.css.file_manager` | `PbCssFileManagerInterface` |
Read/write/delete files under `public://paragraphs_bundles/` |
| `paragraphs_bundles.css.library_registrar` |
`PbDynamicLibraryRegistrarInterface` | `hook_library_info_build` entries per
paragraph |
| `paragraphs_bundles.theme_bridge` | `PbThemeBridgeServiceInterface` | Resolve
`theme-bridge-solo` / `apex` / `neutral` library |
| `paragraphs_bundles.form_display_verifier` | `PbFormDisplayVerifierInterface`
| Display tab contract checks (Drush + tests) |
| `paragraphs_bundles.image_field_url_resolver` |
`PbImageFieldUrlResolverInterface` | File/media → absolute URL for CSS `url()` |

**Example:** a custom module that exports paragraph style previews can
inject `PbDynamicCssBuilderInterface` and call `build($paragraph)` without
writing files.

Do not use `\Drupal::service()` in new code. Constructor injection
only.

---

## OOP hooks (Drupal 11.1+)

Hooks live in `src/Hook/` as `final` classes with `#[Hook('hook_name')]`
attributes. Drupal autowires them via `paragraphs_bundles.services.yml`.

| Class | Hooks | Role |
|-------|-------|------|
| `PbEntityHook` | `paragraph_insert/update/delete` | Write/delete dynamic CSS
on save |
| `PbPreprocessHook` | `preprocess_paragraph` | Attach dynamic library, template
variables |
| `PbLibraryHook` | `library_info_build` | Register per-paragraph CSS libraries
|
| `PbLibraryInfoAlterHook` | `library_info_alter` | Attach theme-bridge
dependency |
| `PbThemeHook` | `theme`, `theme_registry_alter` | Register Twig templates |
| `PbFieldWidgetHook` | `field_widget_*_alter` | Admin color/range widgets |
| `PbCssAlterHook` | `css_alter` | Cascade layer ordering for PB CSS |
| `PbHelpHook` | `help` | Module help |

Sub-modules follow the same pattern (e.g. `PbaPreprocessAlertHook` in
`paragraph_bundle_alert`).

---

## Theme bridge contract

- PB sets **`--pb-*`** only (via dynamic CSS or static foundation).
- Themes set **`--r-*`** only (Solo/APEX region system).
- Bridge CSS aliases unset text/border/link tokens to region vars.

`PbThemeBridgeService::resolveLibraryName()` returns the library name
appended to every PB front-end library in `PbLibraryInfoAlterHook`.

See **`docs/PB-V2-THEME-BRIDGE.md`** and
`css/theme-bridge-*.css`.

---

## Twig

### Templates

Override in your theme:

```
templates/paragraph--{bundle-machine-name}.html.twig
```

Base templates set BEM classes on L0–L4 layers. Use the `pb_class` filter
for list/string field values that map to modifier classes:

```twig
{{ paragraph.pb_display_width.value|pb_class }}
```

Map keys live in `src/Twig/PbValueClassMap.php`.

### Libraries in Twig

Attach bundle CSS/JS in Twig when content exists (lazy load), not in
preprocess:

```twig
{{ attach_library('paragraph_bundle_alert/alert') }}
```

Check module enabled before attaching optional integrations.

---

## Adding a new bundle sub-module

### 1. Scaffold

```
modules/paragraph_bundle_example/
├── paragraph_bundle_example.info.yml
├── paragraph_bundle_example.libraries.yml
├── paragraph_bundle_example.services.yml   # if hooks need DI
├── config/optional/
│   ├── paragraphs.paragraphs_type.example_bundle.yml
│   ├── field.storage.paragraph.*.yml       # only bundle-specific fields
│   ├── field.field.paragraph.example_bundle.*.yml
│   ├── core.entity_form_display.paragraph.example_bundle.default.yml
│   └── core.entity_view_display.paragraph.example_bundle.default.yml
├── css/paragraph-bundle-example.css
├── templates/paragraph--example-bundle.html.twig
└── src/Hook/PeExamplePreprocessHook.php    # optional
```

`info.yml` dependencies must include `paragraphs_bundles:paragraphs_bundles`
and `paragraphs:paragraphs`.

### 2. Reuse parent display fields

Prefer existing `field.field.paragraph.*` instances from the parent
`config/optional/` for standard colors and spacing. Add new
`field.storage.paragraph.*` only for bundle-specific content or display.

Wire the Display tab using the same Field Group nesting as
`simple_bundle` (see **`docs/CONFIGURATION.md`**).

### 3. Twig and BEM

- L0: `paragraphs-bundles` + `pb-{bundle}` (e.g. `pb-example`)
- L1: `pb-ui-inner`
- L2: `pb-content__full` on the main content region
- L3/L4: `pb-example__*` elements; states `pb-is-*`, `pb-has-*`

Naming reference (ships): `docs/PB-V2-BUNDLE-VOCABULARY.md`,
`docs/PB-V2-BEM-VOCABULARY.md`.

### 4. CSS

- Scope rules under `.pb-example` (or bundle root).
- Use design tokens and `--pb-*` vars, not hardcoded colors.
- Include all four resilience media queries:
  `prefers-reduced-motion`, `prefers-contrast: high`,
  `forced-colors: active`, `print`.

Dynamic colors from the Display tab need **no extra PHP**; parent
`PbEntityHook` already writes CSS for shared `pb_display_*` fields.

### 5. JavaScript (if interactive)

- `Drupal.behaviors.pbExample` with `once()`.
- Track `AbortController` instances; abort on `detach(..., 'unload')`.
- Coordinate across behaviors with `document.dispatchEvent(new
  CustomEvent('pb:…'))`.
- Add PHPCS disable guards for `TRUE`/`FALSE`/`NULL` in the `@file` block
  (see existing `js/*.js` files).

### 6. Verify

```bash
drush en paragraph_bundle_example -y
drush pb:verify-form-displays
drush pb:regenerate-css
```

Add Kernel/Functional tests under `tests/src/` (Workstream 1: parent
test namespace in root `composer.json` psr-4).

---

## Drush commands

Defined in `src/Drush/Commands/PbCommands.php`:

| Command | Purpose |
|---------|---------|
| `pb:regenerate-css` | Rebuild CSS for all paragraphs and pb_content nodes |
| `pb:regenerate-css --purge` | Delete `public://paragraphs_bundles/*` first |
| `pb:verify-form-displays` | Validate Display tab field-group contract |

---

## Testing

Run the unit suite from the module directory. The config path is relative to
wherever you invoke it, and `phpunit` must be the one from your project's
`vendor/bin` so Composer's autoloader is already loaded:

```
cd <drupal-root>/modules/custom/paragraphs_bundles
<project-root>/vendor/bin/phpunit -c phpunit.xml.dist
```

| Runner | Scope |
|--------|--------|
| `vendor/bin/phpunit -c phpunit.xml.dist` | Unit tests (mocks, no kernel) |
| Drupal core PHPUnit + DB | Kernel / Functional / FunctionalJavascript |

Unit tests use `tests/bootstrap.php` and `tests/stubs/`. Paragraphs
need not be installed for unit runs. Kernel tests require
`drupal/paragraphs` and core's test bootstrap (see checklist Workstream
1).

`tests/bootstrap.php` locates Composer's autoloader rather than assuming a
fixed depth, so the suite works whether the module sits in `modules/contrib`,
`modules/custom`, or, as during development, is a **symlink** into the
docroot, and whether `vendor/` lives at the project root or inside the
docroot. If it cannot find one, set `PB_COMPOSER_AUTOLOAD` to the absolute path
of your `vendor/autoload.php`.

---

## Code standards

- PHP: `declare(strict_types=1);`, `final` classes, readonly constructor
  promotion, `match` not `switch`, full PHPDoc with array shapes.
- Run `phpcs` from module root: `../../../vendor/bin/phpcs -p` (uses
  `phpcs.xml.dist`).
- Do not change stored field values or `field.storage.*.yml`
  `allowed_values` on existing sites.

---

## Related reading

- **`docs/CONFIGURATION.md`**: editor/site-builder configuration
- **`docs/reference/pb-css-variables.md`**: `--pb-*` tokens
- **`docs/CSS-CLASS-MIGRATION.md`**: v1 → v2 class renames
- **`docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md`**: a11y checklist
