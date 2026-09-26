=== Image Governance ===
Tags: alphasys
Contributors:
Requires at least: 7.0
Requires PHP: 7.4
Tested up to: 7.0
Stable tag: 0.1.19
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Records image source, authority, usage, attribution, and lightweight collections.

== Description ==

Records image source, authority, usage, attribution, and lightweight collections.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add Plugin > Upload Plugin.
2. Activate and configure the existing feature settings.
3. Use the matching author controller for updates.

== Changelog ==

= 0.1.19 =
* Remove independent GitHub update checks and delegate updates to AlphaSys Update Controller. Keep Beta readiness and existing feature settings, package identity and domain restrictions.

== Managed updates ==

Install or activate AlphaSys Update Controller using the plugin row action. Update discovery is manual or scheduled by the controller; ordinary page rendering never checks GitHub. Feature operation does not require the controller. This release remains Beta. Earlier standalone updater instructions are superseded. Explicit controller installation downloads the official GitHub release; no feature settings or site inventory are sent. GitHub receives normal request metadata.

Service terms: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service
Privacy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement
