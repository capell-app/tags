## What it does

Tags provides a shared, multilingual taxonomy for Capell content. Tags can be site-specific or global, and Pages can be tagged from the page list as well as from the taxonomy resource.

## Setup requirements

Run `capell:tags-install` when installing the package directly. It publishes the Tags configuration and vendor/package migrations, then runs the migrations. Define at least one Capell language before opening the translatable Tags resource.

## Your screens

- **Tags**: create, translate, search, and review tags by site, featured state, status, and usage count.
- **Page list**: select Pages and use **Manage tags** to attach or remove tags in one operation.
- **Tag edit page**: see the Pages currently related to a tag and control its published status.

## What you can do

- Create page tags with a name, slug, type, optional site, featured state, and status.
- Add or remove tags from selected Pages without opening every Page individually.
- Merge duplicate tags into a selected target tag.
- Use the tag's Pages relation to review where it is used.

## Where to find it

Go to **Tags** in the admin to manage the taxonomy. When Blog is installed, Tags is grouped beneath **Articles**; otherwise it is in the website navigation. Select Pages from the page list to use **Manage tags**.

## Good to know

- Tags have a type and scope. A site-scoped tag is available only for that site, while a global tag can be reused; non-global admins see global tags plus tags for their assigned sites.
- Site scope is also an access boundary for site-specific tags. A scoped admin with the relevant Tag permission cannot manage another site's tags, but can manage global tags; reserve global tags and broad Tag permissions for editors who should affect every site that reuses them.
- Names and slugs are translated. A slug must be unique for its locale, tag type, and site scope.
- **Manage tags** can attach and remove names in one bulk operation, but the same tag cannot appear in both lists. A new site-scoped Page tag is created or reused, then missing translations are added for the selected Page's languages; Capell does not create a duplicate tag per language.
- An inactive tag remains attached to its records and visible in the admin. Active-only consumers in this package, including slug resolution and tag clouds, exclude it.
- Merge only tags with the same type and site scope. The target keeps its current slug and receives the source relationships; source slugs are retained as aliases so existing tag URLs can resolve to the canonical slug. The source tag records are then permanently deleted.
- Deleting a tag is permanent: there is no trash or restore workflow. Its tag relationships are removed, while the tagged content records remain. Check the all-content usage count and the Pages relation before deleting.
- The list's usage count covers all tagged records, not only Pages. Use it to identify candidates for a merge or deletion.
- Diagnostics confirms the registered resource, providers, migrations, and installation state.
