camaleaun/wxr-command
=====================

Lossless WXR ↔ YAML/JSON converter for WP-CLI.

[![Testing](https://github.com/camaleaun/wxr-command/actions/workflows/testing.yml/badge.svg)](https://github.com/camaleaun/wxr-command/actions/workflows/testing.yml)

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing) | [Support](#support)

## Using

Converts WordPress eXtended RSS (WXR) export files to YAML or JSON, and back
to valid WXR. The conversion is fully reversible: every field, attribute,
namespace, and CDATA section is preserved so the reconstructed XML can be
imported by the WordPress importer without data loss.

**EXAMPLES**

	# WXR → YAML
	$ wp wxr from blueprints/content.xml --format=yml
	Success: Created blueprints/content.yml

	# WXR → JSON
	$ wp wxr from blueprints/content.xml --format=json
	Success: Created blueprints/content.json

	# YAML → WXR (round-trip)
	$ wp wxr to blueprints/content.yml
	Success: Created blueprints/content.xml

	# JSON → WXR
	$ wp wxr to blueprints/content.json
	Success: Created blueprints/content.xml

---

### wp wxr from

~~~
wp wxr from <file> [--format=<format>] [--compact] [--output=<path>] [--force]
~~~

Convert a WXR/XML file to YAML or JSON.

Reads a WordPress WXR export file and writes an equivalent YAML or JSON file.
The output preserves every field so it can be converted back to a valid WXR
with `wp wxr to`.

Use `--compact` to produce a leaner file that omits fields the WordPress
importer ignores or regenerates (e.g. `_edit_lock` meta, redundant dates,
zero-value defaults). The compact output still round-trips to a valid,
importable WXR via `wp wxr to`.

The generated YAML includes a `# yaml-language-server: $schema=` directive
and the generated JSON includes a `$schema` key so IDEs with YAML/JSON Schema
support (VS Code, JetBrains, etc.) provide autocompletion and validation
automatically.

**OPTIONS**

	<file>
		Path to the WXR/XML file to convert.

	[--format=<format>]
		Output format.
		---
		default: yml
		options:
		  - yml
		  - json
		---

	[--compact]
		Omit fields that the WordPress importer ignores or can safely
		regenerate:

		- Disposable meta keys: _edit_lock, _edit_last, _pingme, _encloseme,
		  _wp_attached_file, _wp_attachment_metadata, _wp_old_slug, _wp_old_date.
		- post_modified when equal to post_date (restored by wp wxr to).
		- Zero-value defaults: is_sticky:0, menu_order:0, post_parent:0.
		- Empty fields: excerpt, description, post_password, dc_creator,
		  attachment_url, comments, terms.
		- Per-item: guid, link, pub_date.
		- Channel: image, generator, pub_date.
		- Channel authors when no item has a dc:creator value.
		- Meta: generator, created (informational only).

		The resulting file is still fully reversible to valid WXR via
		`wp wxr to`, which restores all defaults on conversion.

	[--output=<path>]
		Output file path. Defaults to same directory and name as <file> with
		the new extension.

	[--force]
		Overwrite the output file if it already exists.

**EXAMPLES**

	# WXR → YAML (default)
	$ wp wxr from blueprints/content.xml
	Success: Created blueprints/content.yml

	# WXR → compact YAML (human-readable, for version control)
	$ wp wxr from blueprints/content.xml --compact
	Success: Created blueprints/content.yml

	# WXR → JSON
	$ wp wxr from blueprints/content.xml --format=json --force
	Success: Created blueprints/content.json

	# Custom output path
	$ wp wxr from blueprints/content.xml --output=exports/content.yml
	Success: Created exports/content.yml

---

### wp wxr to

~~~
wp wxr to <file> [--output=<path>] [--force]
~~~

Convert a YAML or JSON file back to WXR/XML.

Reads a YAML or JSON file produced by `wp wxr from` and reconstructs a valid
WordPress WXR export file, ready for import via Tools → Import.

The input format is detected from the file extension (`.yml` / `.yaml` →
YAML; `.json` → JSON).

Fields omitted by `--compact` are restored to their WordPress defaults
automatically: `post_modified` is derived from `post_date`, `pub_date` is
derived from `post_date` in RFC-822 format, and zero-value integer fields are
set to `0`.

**OPTIONS**

	<file>
		Path to the YAML or JSON file to convert.

	[--output=<path>]
		Output file path. Defaults to same directory and name as <file> with
		the .xml extension.

	[--force]
		Overwrite the output file if it already exists.

**EXAMPLES**

	# YAML → WXR (round-trip)
	$ wp wxr to blueprints/content.yml
	Success: Created blueprints/content.xml

	# JSON → WXR with custom output
	$ wp wxr to blueprints/content.json --output=blueprints/import.xml
	Success: Created blueprints/import.xml

---

## JSON Schema

Both output formats are annotated with their JSON Schema so editors provide
autocompletion and inline validation without any configuration:

| Format | Schema directive |
|--------|-----------------|
| YAML (full) | `# yaml-language-server: $schema=https://raw.githubusercontent.com/camaleaun/wxr-command/trunk/schemas/wxr-complete.schema.json` |
| YAML (compact) | `# yaml-language-server: $schema=https://raw.githubusercontent.com/camaleaun/wxr-command/trunk/schemas/wxr-compact.schema.json` |
| JSON (full) | `"$schema": "https://raw.githubusercontent.com/camaleaun/wxr-command/trunk/schemas/wxr-complete.schema.json"` |
| JSON (compact) | `"$schema": "https://raw.githubusercontent.com/camaleaun/wxr-command/trunk/schemas/wxr-compact.schema.json"` |

Schema files live in [`schemas/`](schemas/).

---

## Installing

Installing this package requires WP-CLI v2.0 or greater. Update to the latest
stable release with `wp cli update`.

~~~
wp package install camaleaun/wxr-command
~~~

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn't limited to just code. We encourage you to contribute in the
way that best fits your abilities, by writing tutorials, giving a demo at your
local meetup, helping other users with their support questions, or revising our
documentation.

### Reporting a bug

Think you've found a bug? We'd love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/camaleaun/wxr-command/issues?q=label%3Abug%20)
to see if there's an existing resolution to it, or if it's already been fixed
in a newer version.

Once you've done a bit of searching and discovered there isn't an open or fixed
issue for your bug, please [create a new issue](https://github.com/camaleaun/wxr-command/issues/new).
Include as much detail as you can, and clear steps to reproduce if possible.

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/camaleaun/wxr-command/issues/new)
to discuss whether the feature is a good fit for the project.

## Support

GitHub issues aren't for general support questions, but there are other venues
you can try: https://wp-cli.org/#support
