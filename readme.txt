=== LFW Filters ===
Contributors: lfwcom
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accessible filters for any post type, built from its public taxonomies.

== Description ==

Four blocks:

* Filters: a filter band (search plus one menu per taxonomy) or an open panel. Per taxonomy: checkbox group, select, or toggle buttons. Optional keyword field and per-option counts.
* Filter results: wraps a core Query Loop so it follows the filters; can group results by a taxonomy; shows an empty state.
* Active filters: removable chips and Clear filters.
* Result count: "Showing 12 of 30 programs" in a polite live region.

Every filtered view is a plain link (/programs/?area=health&format=evening) that the server renders, so it works without JavaScript and can be shared. With JavaScript, results update in place, history is kept, focus stays sensible and the count is announced.

Works on its own: no LFW account, no outside service, no build step. Theme styling through CSS custom properties (--lfwf-accent, --lfwf-surface and others) that fall back to theme.json presets.

== URL contract ==

* ?{taxonomy}={term-slug}. Repeat a parameter, or use commas, for several values: OR within a taxonomy, AND across taxonomies.
* ?q=keyword for a search phrase.
* Unknown parameters and values are ignored. Filtered views are noindex, follow.

== Abuse controls ==

Abuse case: a stranger can only choose among existing public terms and a capped keyword that is never printed, so filter links cannot put their words on the site, and every response is a cacheable read-only page.

* Only public taxonomies of the post type are honored; values must be existing term slugs; everything else is dropped, never echoed.
* Caps: 6 taxonomies, 12 values per taxonomy, 24 in all, 200-character slugs, an 80-character, 8-word keyword, and a 4,096-byte query string.
* The keyword is never printed by the server (a generic "Keyword search" chip stands in for it).
* Nothing is stored: no options, transients, logs, cookies or nonces.
* Responses depend only on the URL, so page caches and CDNs can serve them. Rate limiting is left to the host or CDN.

== Notes ==

* Put the finder on its own page, not the front page: the parameter is the taxonomy name, which WordPress also reads on the front page.
* A Query Loop inside Filter results is flagged on the server at render time on WordPress 6.8 and later; on 6.6 and 6.7 keep the "lfwFilters": true flag the editor saves in the Query Loop's query.
* One finder per page.
