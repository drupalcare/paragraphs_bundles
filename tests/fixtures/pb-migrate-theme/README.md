# pb:migrate-theme fixture

A synthetic v1 theme (`v1/`) and the files `PbThemeClassMigrator` must turn it
into (`expected/`). Written by hand for the test; no real site's code.
Files under `v1/` that have no twin under `expected/` must come out unchanged
(`fixture.settings.yml`, `css/vendor.min.css`).
Used by `tests/src/Unit/Migration/PbThemeClassMigratorTest.php`.
