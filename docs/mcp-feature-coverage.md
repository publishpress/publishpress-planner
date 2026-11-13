# Planner MCP feature coverage

Planner tools are private WordPress abilities exposed by PublishPress Hub according to the connected account’s permissions and selected actions. Discovery describes the active edition, dependencies, native settings and accepted JSON types. Mutation previews do not save or send notifications.

| Native area | MCP operations | Access and scope |
| --- | --- | --- |
| Modules and settings | Discover modules, read and patch settings, toggle optional modules | Native management capabilities, configuration locks and exact native choices; credentials and license values excluded |
| Calendar, Board and Overview | Read filters and layouts, save personal filters, browse content and item details, edit supported content, move scheduled items/statuses | Native screen capabilities plus post read/edit, publication, author and taxonomy permissions |
| Calendar export | Export selected content as iCalendar; rotate personal feed key | Object checks; authenticated feed URLs and keys are not returned |
| Editorial metadata | Discover, create, edit, delete and reorder definitions; read and patch post values | Native metadata management/view/edit capabilities and post access; all native field types supported |
| Editorial comments | Browse discussions, search by text/author, add/edit/reply and delete | Post access, native own/others capabilities, permitted attachments and reply/deletion rules |
| Notifications | Read and patch followers; read/save per-user workflow channel preferences | Native subscription/user permissions; users, roles, authorized email recipients and active Permissions groups |
| Workflows | Discover native steps, create/edit/trash/restore/delete, export/import | Native workflow capabilities and receiver/object checks; bounded imports and revisions |
| Delivery logs | Read delivery/queue status, archive/delete selected logs and cancel their native queued delivery | Native log capability and referenced-object access; no raw messages, errors, recipient addresses or cron payloads |
| Dashboard | Read native status counts and your own followed content; read/save notepad | Enabled widgets, native capabilities and post access; aggregate counts additionally require edit_others_posts/read_private_posts; note revisions |
| Diagnostics | Read debug-log state and clear log | Native debug capability; log contents and server paths withheld |
| Edit Flow migration | Inspect, preview and execute options, user metadata, editorial metadata and finish stages | Native management/network permissions, affected-user/post checks, locks and current revisions; maximum 1,000 source records/objects per dataset |
| Pro reminders | Configure native before/after publication events and reminder recurrence | Active Pro module, native workflow/settings permissions and installed schedules |
| Pro Slack and branding | Native channel preferences, theme/branding settings, configured synthetic Slack test | Active edition/module and native permissions; no arbitrary destination/message or OAuth credentials |
| Pro network recipient | Use native network-administrator workflow receiver | Multisite and actual network-management capability |
| Optional compatibility | Native Checklists, SEO, Revisions and Elementor discovery/render/query hooks | Active dependency and native permissions; third-party administration remains with its owning plugin |

## Operation boundaries

Use the revision returned by reads when changing existing objects. Patches preserve omitted fields. Malformed or mixed invalid values are rejected before writes. Native multi-object operations are not transactions; inspect reported completed IDs and read an unconfirmed object before retrying.

OAuth authorization, licensing, arbitrary debug/message payload export and cross-site delivery payload access remain in native administration. Media uploads use WordPress media administration; editorial comment tools attach permitted existing media. Migration datasets above the stated bound require native administration. Third-party plugin settings are managed through that plugin’s own tools.
