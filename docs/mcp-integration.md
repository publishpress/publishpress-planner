# Planner MCP integration

Planner provides private WordPress abilities in the `publishpress` category under the `publishpress-planner` namespace. Use PublishPress Hub and a compatible MCP client to connect through the separately installed WordPress MCP Adapter. The WordPress Abilities API must be available; the integration remains optional when it is absent.

## Connecting and permissions

Enable the Hub connection, select the actions the client may use, and enable write access only for operations it needs. Every action also checks the connected WordPress account's native capabilities, application-password restrictions and relevant post, comment, user or workflow permissions. An administrator role does not override a denied capability. Optional Planner modules must be enabled; their native initialization takes effect on the next request. Refresh client tools after changing editions or enabled features.

## Data shared with the client

Permitted reads can include post content, editorial discussions, configured editorial/custom field values and authorized follower email addresses. Workflow writes can deliver native notifications to configured recipients. Select only the actions the connected client needs. Calendar public visibility follows the native subscription setting and disables per-user visibility filters for feed queries; leave it off when subscriptions must retain those filters. Credentials, authenticated feed keys, raw debug logs and delivery message payloads are not returned.

## Discovery and writes

Start with `list-features`, `get-plugin-settings`, `get-content-choices`, `lookup-choices`, `get-workflow-fields` and `list-editorial-fields`. Use the schemas and identifiers they return. Existing objects require their current `expected_hash`; read again if a revision is stale. A `dry_run` validates supported writes without saving, scheduling or sending notifications.

JSON booleans are `true` and `false`, not strings. IDs are integers except native slugs, role names and object property names. Settings patches preserve omitted keys. Notification taxonomy exclusions are JSON lists and use native CSV storage. Default notification channels are an object keyed by workflow ID, for example `{"42":"email"}`. User channel `default` clears the override; `mute` disables that user's delivery for the workflow.

Editorial fields support text, paragraph, checkbox, number, date, location, user and select values. Discover a post's own field schema before updating it. Native zero/empty clearing and sanitization apply; multiple-select fields use lists. User-field role restrictions are lists. A select default is a zero-based option index or `null` for no default; single-select type is the native empty string, and multi-select type is `multiple`. Date fields use `YYYY-MM-DD HH:MM`. Workflow category filters use category slugs; general term filters use numeric term IDs.

## Content and views

Use `list-content` for explicit search filters and `list-view-content` for the native Calendar, Board or Overview query with saved or supplied view filters. Native integration filters remain active. `get-view-item` reads native popup or configured column values as plain text; it withholds form links, protected keys and structured custom metadata. `save-content` creates or patches content with native author, publication and taxonomy permissions. Calendar moves retain the time of day; Board scheduling uses native status transitions and relative days, weeks, months or years.

View configuration actions manage ordered columns, filters and custom metadata labels. View preferences belong to the connected user. Protected/security metadata keys are unavailable through MCP. `export-calendar` renders selected authorized items as iCalendar text. Subscription links and feed credentials remain in native administration; rotating a feed key invalidates existing authenticated links without returning a new credential.

## Notifications and collaboration

Workflow discovery describes active events, status transitions, content types, categories, terms, recipients, templates and options. Workflow creation starts inactive and publishes only after configuration verification. Templates accept Planner placeholders; unrelated shortcodes and protected metadata substitutions are rejected. Workflow export/import uses the Planner MCP transfer format.

Editorial discussions support comments, replies, existing media attachments and native deletion rules. Followers support users, roles, authorized email addresses and active Permissions groups. Notification preferences apply per user and workflow. Delivery log actions expose status and queued-task information, with object permissions on referenced content; message payloads, raw errors and credentials are withheld. Deleting a log uses the native cancellation path for its queued delivery.

## Pro

Active Pro modules supply before/after publishing timer filters, reminder intervals, Slack channel preferences and themes, branding controls and the network-administrator recipient. The configured Slack test sends the native synthetic message only; it does not accept arbitrary messages or destinations. OAuth authorization and license management stay in native administration. Checklists, SEO, Revisions and Elementor compatibility follows the active native dependencies and filters.

## Maintenance and failures

Dashboard notepad actions use native plain-text storage. Diagnostics return log existence, size and revision; raw logs are not exported. Edit Flow migration runs as explicit options, user-metadata, editorial-metadata and finish stages. Inspect the stage revision first, preview it, and make a backup before migration. Stages can overwrite data, require permission on affected users/posts, respect configuration locks and are bounded to 1,000 source records/objects.

Native multi-step operations are not database transactions. A partial failure reports confirmed IDs or an object ID to inspect before retrying. Re-read the object after an unconfirmed result. Cross-site delivery-log payloads, authentication credentials, license operations and third-party configuration are handled through their native administration.

## Example requests

- Show pending posts on my Content Board and preview moving one to next week.
- Add a numeric editorial field called Estimated hours for posts, then set it to 4 on the selected post.
- Create a draft notification for new posts entering Pending, addressed to Editors, and show its configuration before publishing.
- Mute my notifications for the selected workflow.
- In Pro, remind Editors two days before scheduled publication and use my configured Slack channel.
- Preview migrating Edit Flow user preferences and show the affected-record count.
