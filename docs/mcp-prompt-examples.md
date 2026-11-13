# Planner MCP request examples

Read discovery before selecting exact fields and identifiers. Existing-object writes use the current revision. Preview supported mutations and verify their native read-back. These examples describe expected behavior rather than promising that every AI client will select the same tools.

| Request | Expected behavior |
| --- | --- |
| What Planner features can I use? | Read `list-features`, then the relevant settings, content, workflow or editorial-field discovery schema. Disabled modules and unavailable editions remain gated. |
| Show my pending posts next month. | Choose Calendar, Board or Overview; discover native filters, then use `list-view-content` with the date/status/author scope. Follow pagination. |
| Show the posts I follow. | Use `get-dashboard-content` with `kind: followed`; only the connected user's permitted posts are returned. |
| Show unpublished content counts. | Use `get-dashboard-content` with `kind: statuses`; the native widget must be enabled and the account must have the required count permissions. |
| Move these posts to next Friday. | Resolve the exact post IDs, read current revisions, preview `move-calendar-items`, then save. Preserve native times and inspect partial-result IDs. |
| Schedule this Board item two weeks from now. | Read its revision, then preview/save `move-board-item` with `status: future`, `number: 2`, `period: weeks`. |
| Change Estimated hours to 4. | Discover the selected post's editorial field schema and patch only its exact field slug using `update-editorial-values`. |
| Add Editors as followers. | Read `get-post-followers`, preserve existing role selections and preview/save the replacement role list. This changes post subscriptions, not user roles. |
| Clear the group followers. | Read followers and submit an empty `groups` list with its revision. The active Permissions dependency and group capability are required even for clearing. |
| Reply to this editorial comment. | Resolve the post/comment, then use `save-editorial-comment` with the parent ID. Attach only permitted existing media. |
| Notify Editors when posts enter Pending. | Discover workflow events and recipients, create an inactive workflow, preview its native configuration and publish only when requested. |
| Remind Editors two days before publication. | Discover the active Pro reminder event, set integer amount 2 and unit day, and inspect eligible content statuses before saving. |
| Mute my notifications for that workflow. | Discover the current user's workflow preferences, then patch the selected workflow's channel to mute. |
| Test Slack. | Use the configured native synthetic test action in active Pro. The action cannot send arbitrary text or choose a different destination. |
| Migrate Edit Flow metadata. | Inspect the metadata stage revision and affected counts, make a backup, preview, then run the explicit migration stage. Migration is bounded and nontransactional. |

OAuth authorization, licensing, raw debug/message payloads and third-party plugin administration retain their native interfaces. A stale client tool list may need refresh in the client after feature or edition changes.
