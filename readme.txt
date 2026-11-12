=== Nera – Vote ===
Contributors: Nera
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later

Time-boxed votes for signed-in members, with ranked results for the team.

== Description ==

The team creates a **Vote**: a list of **Options** (bottles) with a start and an end date.
Each Vote has its own shortcode, which is pasted into any page or text field. While the
Vote is open, signed-in **Members** tick the Options they like; every tick saves at once.
When it closes, the team sees every Option ranked from most to fewest Ballots, with colour
bands and progress bars.

= Admin (CMS) =
**Votes → Add new Vote**:

* **Opens / Closes** — site time zone. An empty opening date opens as soon as the Vote is published.
* **Members can see results** — only after the Vote closes, or while it is open too.
* **Play a sound when saved** — on or off for this Vote.
* **Options** — name, image, short description and link. Members see the name; clicking it opens a popup with the rest. An Option that already has Ballots cannot be deleted, only hidden.
* **Shortcode** — `[nera_vote id="123"]`, with a Copy button.
* **Results** — ranked Options, bars against the leader (green from 75%, amber from 40%, red below), Ballots and share of voters. Names are never shown. Export CSV has counts only.

= Front end =
* Shortcode `[nera_vote id="123"]` (add `title="no"` to hide the Vote title).
* Shortcode `[nera_vote latest="open"]` shows the Vote that is open now, the same one the Vote page shows first, and changes by itself when the next Vote opens. With no open Vote it prints nothing; add `fallback="closed"` to show the Vote that closed most recently instead. Combine with `title="no"` or `suggest="no"` as for an ID.
* Scheduled: only the opening date. Open: tickable list. Closed: read-only list, with Results when the Vote allows it.
* Toast and chime reuse the Nera theme's add-to-cart feedback when present, with a built-in fallback.

= Demo tool =
**Tools → Nera Vote Demo Seeder** creates three demo Votes (open, scheduled, closed) with
3 to 4 Options each, demo Members, random Ballots and generated images, and removes exactly
what it created. Creating is blocked on production unless `NERA_VOTE_ALLOW_DEMO` is true.
The **Finish and hide this tool** button removes the menu item once no demo data is left.

= Privacy =
Ballots are stored against the Member's account. They are included in the WordPress personal
data export and erase tools, and removed when the account is deleted.

== Changelog ==

= Unreleased =
* Shortcode `[nera_vote latest="open"]` (optional `fallback="closed"`): the Vote that is open now, so a home page section follows the open Vote without a hard-coded ID.
* Choice mode on each Vote: Members pick one or more Options, or exactly one (radio buttons, can be changed but not withdrawn). Locked once the Vote has Ballots.
* Suggest a bottle: a Fluent Forms form created on activation, with a weekly limit per Member and no duplicate suggestions.
* Votes > Suggestions: review suggested bottles grouped by bottle, add them to a Vote's shortlist or decline them.
* Vote page created on activation, with the [nera_vote_page] shortcode (current Vote, Suggest a bottle button, rules) and Votes > Settings.
* Vote section: bottle thumbnails, a voting deadline with live countdown and a Suggest a bottle button.
* My account tab "My suggestions and votes".
* Suggestions are included in the personal data export and erase tools.

= 0.1.0 =
* Initial release.
