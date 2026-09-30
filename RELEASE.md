# Release checklist — StatusReport 1.0.0

Use this checklist before publishing the first public release.

- [ ] Merge the publication preparation pull request.
- [ ] Make the repository public.
- [ ] Confirm `Plugin.php` reports `StatusReport`, version `1.0.0`, and compatibility `>=1.2.50`.
- [ ] Run `php plugins/StatusReport/Test/run.php` in the real Kanboard runtime.
- [ ] Confirm all validation checks pass.
- [ ] Run the **Build plugin package** GitHub Actions workflow.
- [ ] Download and inspect `StatusReport-1.0.0.zip`.
- [ ] Confirm the ZIP contains `StatusReport/Plugin.php` at its expected path.
- [ ] Create GitHub release/tag `v1.0.0`.
- [ ] Attach `StatusReport-1.0.0.zip` to the release.
- [ ] Verify the release asset can be downloaded without authentication.
- [ ] Submit a pull request to `kanboard/website` adding the plugin to `plugins.json`.

## Proposed plugins.json entry

```json
"StatusReport": {
    "author": "OrkFlowTech",
    "compatible_version": ">=1.2.50",
    "description": "Classify comments as status reports and expose the current status of a task.",
    "download": "https://github.com/cesarmarcelo-ork/kanboard-plugin-statusreport/releases/download/v1.0.0/StatusReport-1.0.0.zip",
    "has_hooks": true,
    "has_overrides": true,
    "has_schema": true,
    "homepage": "https://github.com/cesarmarcelo-ork/kanboard-plugin-statusreport",
    "is_type": "plugin",
    "last_updated": "2026-09-29",
    "license": "MIT",
    "readme": "https://github.com/cesarmarcelo-ork/kanboard-plugin-statusreport/blob/main/README.md",
    "remote_install": true,
    "title": "StatusReport",
    "version": "1.0.0"
}
```
