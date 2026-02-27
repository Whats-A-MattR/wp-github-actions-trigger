# Changelog

## v0.0.3-beta (12-Mar-2025)

- Initial Release
- Added GitHub Integration
- Implemented Rate Limiting
- Added Invocation History

## v0.0.4-beta (13-Mar-2025)

- Added ui element to enable trigger on update of a published post
- Added function to handle updating of published post


## v0.0.5-beta

- Refactored, segmenting code for better readability
- Changed implementation to use cleaner invocations and handling of queues, rate limits etc.
- Fixed case where queued updates were ALL run individually, rather than running once and clearing pending queue. 
  - Previous behaviour - bad
    - 18 updates = 18 workflow invocations
  - New behaviour - better
    - 18 updates = 1 workflow invocation
- No new features.

## v0.0.6-beta (27-Feb-2026)
- **Fix:** PATs were not working because the plugin documented the wrong scope. The workflow dispatch API requires the **`workflow`** scope (classic PAT) or **Actions: Read and write** (fine-grained PAT), not `repo_deployment`. Updated admin UI and README with correct scopes.
- **Fix:** Dispatch failures (e.g. 401/403 from bad or under-scoped PAT) are now logged and no longer incorrectly update "last run" (only 204 success does).
