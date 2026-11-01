---
name: publishpress-mcp
description: Implement or extend Planner actions through the WordPress Abilities API. Use for action registration, schemas, permissions, feature operations, and integration tests.
---

# PublishPress MCP integration

Hub provides an optional connection through the separately installed WordPress MCP Adapter; it does not bundle the adapter or implement MCP transport. Users connect through a compatible external MCP client. The integration does not require a particular AI provider or integrate with WordPress.com's hosted MCP service.

## Hub connection contract

Hub owns the shared connection and discovers abilities in category `publishpress`. Connection code and generic documentation live in the Hub repository. Keep Planner action implementations and coverage in this repository; adding actions must not require Hub registration changes.

## Plugin ownership

- Register abilities independently of Hub on `wp_abilities_api_init`, outside admin-only bootstrap paths. Check API availability to preserve older WordPress compatibility.
- Register shared category `publishpress` on `wp_abilities_api_categories_init` if `wp_has_ability_category('publishpress')` is false. Do not probe with `wp_get_ability_category`: a missing category emits a developer notice. Every participating plugin must work alone and coexist with other category registrants.
- Use names in the form `{plugin-namespace}/{action-name}`, explicit input/output JSON schemas, clear descriptions, and an execution callback that reuses plugin services.
- Set `meta.public`, `meta.mcp.public`, and `meta.show_in_rest` to false. Custom-server registration is explicit; public default-server discovery is a different route.
- Match the operation's existing permission checks, including object-specific checks where relevant. Never replace permissions with a nonce or trust a tool annotation as authorization.
- Check the actual operation capability with `current_user_can`; do not bypass a denial just because the user has the administrator role. Respect application-password capability restrictions.
- Preserve each plugin's validation, storage formats, filters and lockout protections. Validate all bulk targets before writing, and reuse existing services instead of simulating admin forms or minting nonces.
- Gate edition-specific registrations on the active edition and network operations on their existing network permissions. Update shared dependencies through the normal release process instead of editing vendored copies.
- Do not change the authenticated MCP identity or return authentication secrets.
- Annotate read-only, destructive, and idempotent behavior truthfully. Avoid arbitrary PHP, SQL, option writes, or generic unrestricted method dispatch.
- Keep Free/Pro bootstrap and minimum PHP compatibility in mind. Do not register duplicates when Pro loads shared Free code.

## Planner implementation

- Follow Planner's legacy module lifecycle and namespaces. Shared abilities live in `core/Mcp`; Pro adds its own extension after the Pro modules load. Do not import Hub's container architecture or edit vendored Free code in Pro.
- Reuse the instantiated notification workflow steps and their native setters. Browser wrappers retain nonces; MCP supplies typed data directly. Never set `$_POST`, fake a nonce, replace the authenticated user, or use global post state for persistence.
- Use the native Calendar/Board/Overview query and configuration methods, editorial-field handlers, comment hooks, follower taxonomies, channel preferences, reminder scheduler and log model. Restore query globals and filters after native view queries.
- Discover actual module settings and active dependencies. Booleans use JSON booleans; native option maps and CSV fields are converted at the boundary. Default notification channels are a map keyed by workflow IDs. Workflow categories use slugs; general terms use integer IDs.
- Require native view, settings, channel and object capabilities on every execution. A denied administrator capability must remain denied. Validate complete bulk requests and revisions before writes; report confirmed IDs on partial failures.
- Do not expose license keys, OAuth tokens, subscription credentials, raw log payloads or unrelated metadata. Template writes accept Planner placeholders and reject protected metadata substitutions. User email followers require native follower/post access.
- Keep native Edit Flow migrations staged, permission checked, revision guarded and bounded. Preview must not modify options, metadata, relationships, schedules or notices. Never imply transactional rollback.
- Preserve PHP 8.2 compatibility in shared Free code and PHP 7.2.5 syntax in Pro-only additions. Keep the optional API guards working independently of Hub.

## Verification

Run `tests/Mcp/smoke.php` against a disposable `planner_mcp_*` WordPress database with the released MCP Adapter. Test real Free and Pro bootstraps, native discovery/preview/save/read-back, malformed JSON, mixed invalid batches, stale revisions, denied capabilities and objects, native form nonce guards, disabled modules and dependencies. Block mail and external network calls in fixtures. Keep tests and usage documentation in the owning repository.

Skills here guide developers and their coding agents. They are separate from optional end-user instructions for AI clients consuming tools.
