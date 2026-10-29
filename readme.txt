=== TalkToYourBlog Event Calendar ===
Contributors: mjfutera
Tags: events, calendar, event calendar, schedule, agenda
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Events with dates, places and coloured categories. A list or month calendar via shortcode, and a page for every event with structured data.

== Description ==

TalkToYourBlog Event Calendar adds an **Events** section to WordPress. Each event has a start and end, an optional time zone, a location (or an online meeting link), an image, a description, an external link for tickets or registration, and any number of coloured categories.

**For visitors**

* A page for every event at `/events/your-event/`, rendered by your theme like any other post.
* An event list at `/events/` and category pages at `/events/category/name/`, upcoming events first.
* The `[talktoyourblog_event_calendar]` shortcode shows upcoming events as a list, or a month grid with previous/next navigation, on any page or post.
* Each event page carries schema.org `Event` structured data, so search engines can show the date and place in results.
* Cancelled events stay visible with a clear notice instead of disappearing.

**For editors**

* Events use the standard editor, featured image, excerpt and revisions.
* Categories are a regular taxonomy with a colour picker; the colour appears in badges and in the month grid.
* The event list in the admin shows when and where each event takes place, sorted by date.

**Shortcode attributes**

* `view` – `list` (default) or `month`
* `category` – category slug, e.g. `workshops`
* `limit` – number of events in the list (default 10)
* `upcoming` – `yes` (default) or `no` to include past events
* `featured` – `yes` to show featured events only
* `month` – starting month for the month view, e.g. `2026-10`

Example: `[talktoyourblog_event_calendar view="month" category="workshops"]`

**Time zones**

Dates are stored as entered. Leave the time zone empty to use the site's time zone; set one for online events aimed at another country. The structured data carries the right offset either way.

**Privacy**

The plugin stores only the events you create. It makes no external requests and sets no cookies.

== Installation ==

1. Upload the `talktoyourblog-event-calendar` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin.
3. Add categories under Events > Categories, then add events under Events > Add New.
4. Put `[talktoyourblog_event_calendar]` on a page, or link to `/events/`.

If event pages return 404, open Settings > Permalinks and click Save once.

== Frequently Asked Questions ==

= Can I change the /events/ address? =

Yes, under Events > Settings. The event pages, the list and the category pages all move with it.

= Can an event be in several categories? =

Yes. Categories work like post categories: tick as many as you need.

= Does it work with SEO plugins? =

Events are a regular post type, so Yoast, Rank Math and similar plugins handle titles, descriptions and sitemaps for them. The plugin adds only the schema.org `Event` data, which those plugins do not provide.

= What happens when I delete the plugin? =

Nothing is removed unless you tick "Delete all events, categories and settings" under Events > Settings before deleting it. Deactivating never removes data.

== Source code ==

The full, human-readable source of this plugin is public: https://github.com/TalkToYourBlog-com/event-calendar.

== Changelog ==

= 1.2.0 =
* Added a built-in error log (Events -> Error log in the admin menu), modelled on Anonymous Sales Plugin's: off by default, logs event and category saves, settings changes and activation/deactivation. Includes a live viewer with level/search filtering, select, copy and clear.

= 1.1.0 =
* Renamed to TalkToYourBlog Event Calendar (directory slug: talktoyourblog-event-calendar), to match the renamed MFPro Blog MCP -> TalkToYourBlog. No events, categories or settings are affected.
* About page now shows the plugin's logo and a "Report a bug or idea" link to the new central issue tracker.

= 1.0.0 =
* First release.
