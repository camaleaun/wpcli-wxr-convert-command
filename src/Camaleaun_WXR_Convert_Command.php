<?php

use Mustangostang\Spyc;

/**
 * Lossless WXR ↔ YAML/JSON converter.
 *
 * Converts WordPress eXtended RSS (WXR) export files to YAML or JSON, and
 * back to valid WXR. The conversion is fully reversible: every field,
 * attribute, namespace, and CDATA section is preserved so the reconstructed
 * XML can be imported by the WordPress importer without data loss.
 *
 * ## EXAMPLES
 *
 *     # WXR → YAML
 *     $ wp wxr from blueprints/content.xml --format=yml
 *     Success: Created blueprints/content.yml
 *
 *     # WXR → JSON
 *     $ wp wxr from blueprints/content.xml --format=json
 *     Success: Created blueprints/content.json
 *
 *     # YAML → WXR
 *     $ wp wxr to blueprints/content.yml
 *     Success: Created blueprints/content.xml
 *
 *     # JSON → WXR
 *     $ wp wxr to blueprints/content.json
 *     Success: Created blueprints/content.xml
 *
 * @package camaleaun/wxr-command
 */
class Camaleaun_WXR_Convert_Command extends WP_CLI_Command {

	const SCHEMA_BASE = 'https://raw.githubusercontent.com/camaleaun/wxr-command/trunk/schemas/1.2/';

	// -------------------------------------------------------------------------
	// wp wxr from
	// -------------------------------------------------------------------------

	/**
	 * Convert a WXR/XML file to YAML or JSON.
	 *
	 * Reads a WordPress WXR export file and writes an equivalent YAML or JSON
	 * file. The output preserves every field so it can be converted back to a
	 * valid WXR with `wp wxr to`.
	 *
	 * Use --compact to produce a leaner file that omits fields the WordPress
	 * importer ignores or regenerates (e.g. _edit_lock meta, redundant dates,
	 * zero-value defaults). The compact output still round-trips to a valid,
	 * importable WXR via `wp wxr to`.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the WXR/XML file to convert.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: yml
	 * options:
	 *   - yml
	 *   - json
	 * ---
	 *
	 * [--compact]
	 * : Omit fields that the WordPress importer ignores or can safely
	 *   regenerate: _edit_lock/_edit_last/_pingme/_encloseme and other
	 *   disposable meta keys; post_modified when equal to post_date;
	 *   zero-value defaults (is_sticky, menu_order, post_parent); empty
	 *   fields (excerpt, description, post_password, creator, attachment_url);
	 *   item guid/link/pub_date; and unused channel fields (image, generator).
	 *   post_date_gmt and post_modified_gmt are always collapsed into post_date
	 *   and post_modified (with a ±HHMM suffix when the site is not in UTC).
	 *   The resulting file is still fully reversible to valid WXR.
	 *
	 * [--output=<path>]
	 * : Output file path. Defaults to same directory and name as <file> with
	 *   the new extension.
	 *
	 * [--force]
	 * : Overwrite the output file if it already exists.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp wxr from blueprints/content.xml
	 *     Success: Created blueprints/content.yml
	 *
	 *     $ wp wxr from blueprints/content.xml --compact
	 *     Success: Created blueprints/content.yml
	 *
	 *     $ wp wxr from blueprints/content.xml --format=json --force
	 *     Success: Created blueprints/content.json
	 *
	 * @when before_wp_load
	 */
	public function from( array $args, array $assoc_args ): void {
		$input   = $args[0];
		$format  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'yml' );
		$compact = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'compact', false );
		$force   = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );
		$output  = \WP_CLI\Utils\get_flag_value(
			$assoc_args,
			'output',
			$this->swap_ext( $input, $format )
		);

		$xml = $this->read_file( $input );

		$parser = new WXR_Parser();
		try {
			$data = $parser->parse( $xml );
		} catch ( RuntimeException $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		$data = $this->normalize_data( $data );

		if ( $compact ) {
			$data = $this->compact_data( $data );
		}

		$schema  = self::SCHEMA_BASE . ( $compact ? 'wxr-compact' : 'wxr-complete' ) . '.schema.json';
		$content = 'json' === $format
			? $this->to_json( $data, $schema )
			: $this->to_yaml( $data, $schema );

		$this->write_file( $output, $content, $force );
		WP_CLI::success( "Created {$output}" );
	}

	// -------------------------------------------------------------------------
	// wp wxr to
	// -------------------------------------------------------------------------

	/**
	 * Convert a YAML or JSON file back to WXR/XML.
	 *
	 * Reads a YAML or JSON file produced by `wp wxr from` and reconstructs a
	 * valid WordPress WXR export file, ready for import via Tools → Import.
	 *
	 * The input format is detected from the file extension (.yml / .yaml → YAML;
	 * .json → JSON).
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the YAML or JSON file to convert.
	 *
	 * [--output=<path>]
	 * : Output file path. Defaults to same directory and name as <file> with
	 *   the .xml extension.
	 *
	 * [--force]
	 * : Overwrite the output file if it already exists.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp wxr to blueprints/content.yml
	 *     Success: Created blueprints/content.xml
	 *
	 *     $ wp wxr to blueprints/content.json --output=blueprints/import.xml
	 *     Success: Created blueprints/import.xml
	 *
	 * @when before_wp_load
	 */
	public function to( array $args, array $assoc_args ): void {
		$input  = $args[0];
		$force  = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );
		$output = \WP_CLI\Utils\get_flag_value(
			$assoc_args,
			'output',
			$this->swap_ext( $input, 'xml' )
		);

		$raw  = $this->read_file( $input );
		$ext  = strtolower( pathinfo( $input, PATHINFO_EXTENSION ) );

		if ( 'json' === $ext ) {
			$data = $this->from_json( $raw );
		} elseif ( 'yml' === $ext || 'yaml' === $ext ) {
			$data = $this->from_yaml( $raw );
		} else {
			WP_CLI::error( "Cannot detect format from extension '.{$ext}'. Use a .yml, .yaml, or .json file." );
		}

		$builder = new WXR_Builder();
		$xml     = $builder->build( $data );

		$this->write_file( $output, $xml, $force );
		WP_CLI::success( "Created {$output}" );
	}

	// -------------------------------------------------------------------------
	// Compact
	// -------------------------------------------------------------------------

	/**
	 * Post meta keys that the WordPress importer explicitly skips or regenerates.
	 * Safe to strip in --compact mode.
	 *
	 * Sources: class-wp-import.php lines 1691-1692 and common WP housekeeping.
	 */
	const COMPACT_STRIP_META = [
		'_edit_lock',               // Explicitly skipped by importer.
		'_edit_last',               // Last editor user ID — irrelevant on import.
		'_pingme',                  // Ping queue flag — not used by importer.
		'_encloseme',               // Enclosure queue flag — not used by importer.
		'_wp_old_slug',             // Historical URL slug — not needed for import.
		'_wp_old_date',             // Historical date — not needed for import.
		'_wp_attached_file',        // Regenerated from attachment on import.
		'_wp_attachment_metadata',  // Regenerated from attachment on import.
	];

	/**
	 * Item fields to drop when they match their import-time default.
	 * The WordPress importer only acts on non-zero / non-empty values for these.
	 */
	const COMPACT_ITEM_DEFAULTS = [
		'is_sticky'      => 0,   // Importer only sticks when value === 1.
		'menu_order'     => 0,   // Zero is the WordPress default.
		'post_parent'    => 0,   // Importer skips parent lookup when 0.
		'post_password'  => '',  // Empty means no password.
		'description'    => '',  // Rarely set on CPTs.
		'creator'        => '',  // dc:creator — not used for post authorship.
		'excerpt'        => '',  // Empty excerpt.
		'attachment_url' => '',  // Only relevant for attachment post type.
		'comments'       => [],  // Empty comment list.
		'categories'     => [],  // Empty taxonomy assignments.
	];

	/**
	 * Collapse a local+gmt date pair into a single compact datetime string.
	 *
	 * - local === gmt (or gmt empty) → "YYYYMMDDTHHmmss" (UTC, no suffix).
	 * - local !== gmt               → "YYYYMMDDTHHmmss±HHMM" (trailing 00 minutes stripped).
	 *
	 * @param string $local "YYYY-MM-DD HH:MM:SS" local time from WXR.
	 * @param string $gmt   "YYYY-MM-DD HH:MM:SS" UTC time from WXR.
	 * @return string Compact date string.
	 */
	private function collapse_date_pair( string $local, string $gmt ): string {
		if ( '' === $local ) {
			return '';
		}
		$compact = $this->compact_datetime_local( $local );
		if ( '' === $gmt || $local === $gmt ) {
			return $compact; // UTC or identical — no TZ suffix needed.
		}
		$ts_local = strtotime( $local );
		$ts_gmt   = strtotime( $gmt );
		if ( false === $ts_local || false === $ts_gmt ) {
			return $compact; // Unparseable — safe fallback.
		}
		$offset_sec = $ts_local - $ts_gmt;
		if ( 0 === $offset_sec ) {
			return $compact;
		}
		$sign    = $offset_sec >= 0 ? '+' : '-';
		$abs     = abs( $offset_sec );
		$hours   = intdiv( $abs, 3600 );
		$minutes = ( $abs % 3600 ) / 60;
		$suffix  = sprintf( '%s%02d%02d', $sign, $hours, $minutes );
		// Strip trailing zero-minutes: "-0300" → "-03", "+0530" stays.
		$suffix  = preg_replace( '/00$/', '', $suffix );
		return $compact . $suffix;
	}

	/**
	 * Reformat a local-time datetime string without TZ conversion.
	 *
	 * "YYYY-MM-DD HH:MM:SS" → "YYYYMMDDTHHmmss"  (ISO 8601 basic)
	 * "YYYY-MM-DD HH:MM"    → "YYYYMMDTHTHHMM"
	 * Already-compact or unrecognised values are returned as-is.
	 */
	private function compact_datetime_local( string $v ): string {
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', $v, $m ) ) {
			return $m[1] . $m[2] . $m[3] . 'T' . $m[4] . $m[5] . $m[6];
		}
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})$/', $v, $m ) ) {
			return $m[1] . $m[2] . $m[3] . 'T' . $m[4] . $m[5];
		}
		return $v;
	}

	/**
	 * Compact an RFC 822 datetime, appending the UTC offset only when non-zero.
	 *
	 * "Sat, 13 Jun 2026 09:15:03 +0000" → "20260613T091503"
	 * "Sat, 13 Jun 2026 09:15:03 -0300" → "20260613T091503-0300"
	 */
	private function compact_datetime_rfc( string $v ): string {
		// Parse RFC 822 preserving the original local time and offset.
		$dt = \DateTime::createFromFormat( 'D, d M Y H:i:s O', trim( $v ) );
		if ( ! $dt ) {
			$dt = \DateTime::createFromFormat( 'd M Y H:i:s O', trim( $v ) );
		}
		if ( ! $dt ) {
			return $this->compact_datetime_local( $v ); // fallback.
		}
		$local  = $dt->format( 'Ymd\THis' ); // local time in original timezone.
		$offset = $dt->format( 'O' );         // e.g. "+0530", "-0300", "+0000".
		if ( $offset !== '+0000' && $offset !== '-0000' ) {
			// Strip trailing "00" minutes when zero: "+0900" → "+09", "+0530" stays.
			$short = preg_replace( '/00$/', '', $offset );
			return $local . $short;
		}
		return $local; // UTC — omit suffix.
	}

	/**
	 * Normalize data for all output formats (full and compact).
	 *
	 * Strips empty-string and empty-array values, removes fields that are
	 * redundant with the schema URL (wxr_version, namespaces) or derivable
	 * by the builder (base_site_url/base_blog_url from link, channel generator
	 * URL), and converts datetime values to compact numeric strings to avoid
	 * YAML 1.1 datetime coercion.
	 *
	 * @param array $data Parsed WXR data.
	 * @return array Normalized data.
	 */
	private function normalize_data( array $data ): array {
		// Meta: wxr_version is encoded in the schema URL path; namespaces are standard.
		unset( $data['meta']['wxr_version'], $data['meta']['namespaces'] );

		// Meta: rename generator ("WordPress/7.0") → wordpress ("7.0").
		if ( ! empty( $data['meta']['generator'] ) ) {
			$gen = $data['meta']['generator'];
			if ( preg_match( '/WordPress\/([\d.]+)/i', $gen, $m ) ) {
				$data['meta']['wordpress'] = $m[1];
			} else {
				$data['meta']['wordpress'] = $gen; // non-WP generator, keep as-is.
			}
			unset( $data['meta']['generator'] );
		}

		// Meta: compact the created timestamp ("YYYY-MM-DD HH:MM" → "YYYYMMDD-HHMM").
		if ( ! empty( $data['meta']['created'] ) ) {
			$data['meta']['created'] = $this->compact_datetime_local( (string) $data['meta']['created'] );
		}

		// Channel: base_site/blog_url derivable from link; generator URL not needed.
		unset(
			$data['channel']['base_site_url'],
			$data['channel']['base_blog_url'],
			$data['channel']['generator']
		);

		// Channel: compact pub_date (RFC 822 → "YYYYMMDD-HHmmss[±HHMM]").
		if ( ! empty( $data['channel']['pub_date'] ) ) {
			$data['channel']['pub_date'] = $this->compact_datetime_rfc( (string) $data['channel']['pub_date'] );
		}

		// Channel: strip empty string fields and empty taxonomy arrays.
		foreach ( [ 'description', 'link', 'language' ] as $key ) {
			if ( isset( $data['channel'][ $key ] ) && '' === $data['channel'][ $key ] ) {
				unset( $data['channel'][ $key ] );
			}
		}
		foreach ( [ 'categories', 'tags', 'terms' ] as $key ) {
			if ( isset( $data['channel'][ $key ] ) && [] === $data['channel'][ $key ] ) {
				unset( $data['channel'][ $key ] );
			}
		}

		// Authors: strip empty string fields (first_name, last_name, etc.).
		if ( ! empty( $data['channel']['authors'] ) ) {
			$data['channel']['authors'] = array_map( function ( array $a ): array {
				return array_filter( $a, function ( $v ) { return ! ( is_string( $v ) && '' === $v ); } );
			}, $data['channel']['authors'] );
		}

		// Items: collapse date pairs, strip empty strings and empty arrays.
		$empty_str_keys = [ 'creator', 'description', 'excerpt', 'attachment_url', 'post_password', 'link' ];
		$empty_arr_keys = [ 'categories', 'meta', 'comments' ];

		$data['items'] = array_map( function ( array $item ) use ( $empty_str_keys, $empty_arr_keys ): array {
			// Collapse post_date + post_date_gmt → single field with optional TZ suffix.
			$item['post_date'] = $this->collapse_date_pair(
				(string) ( $item['post_date'] ?? '' ),
				(string) ( $item['post_date_gmt'] ?? '' )
			);
			unset( $item['post_date_gmt'] );

			// Collapse post_modified + post_modified_gmt → single field with optional TZ suffix.
			if ( isset( $item['post_modified'] ) || isset( $item['post_modified_gmt'] ) ) {
				$item['post_modified'] = $this->collapse_date_pair(
					(string) ( $item['post_modified'] ?? $item['post_date'] ?? '' ),
					(string) ( $item['post_modified_gmt'] ?? '' )
				);
				unset( $item['post_modified_gmt'] );
			}

			// Compact item pub_date (RFC 822 with TZ).
			if ( ! empty( $item['pub_date'] ) ) {
				$item['pub_date'] = $this->compact_datetime_rfc( (string) $item['pub_date'] );
			}
			// Strip empty strings.
			foreach ( $empty_str_keys as $key ) {
				if ( isset( $item[ $key ] ) && '' === $item[ $key ] ) {
					unset( $item[ $key ] );
				}
			}
			// Strip empty arrays.
			foreach ( $empty_arr_keys as $key ) {
				if ( isset( $item[ $key ] ) && [] === $item[ $key ] ) {
					unset( $item[ $key ] );
				}
			}
			return $item;
		}, $data['items'] );

		return $data;
	}

	/**
	 * Strip fields that the WordPress importer ignores or can regenerate.
	 *
	 * @param array $data Parsed WXR data from WXR_Parser::parse().
	 * @return array Compacted data — still fully reversible to valid WXR.
	 */
	private function compact_data( array $data ): array {
		// Meta: strip informational-only fields not used by the importer.
		unset(
			$data['meta']['generator'], // WordPress version comment — informational.
			$data['meta']['created']    // Export timestamp — informational.
		);
		if ( empty( $data['meta'] ) ) {
			unset( $data['meta'] ); // Empty meta key would serialize as [] (array).
		}

		// Channel: remove fields not used by the importer.
		unset(
			$data['channel']['image'],   // Site favicon — importer ignores.
			$data['channel']['pub_date'] // Channel publish date — not used.
			// generator URL already removed by normalize_data().
		);

		// Channel: drop empty taxonomy collections.
		foreach ( [ 'categories', 'tags', 'terms' ] as $key ) {
			if ( empty( $data['channel'][ $key ] ) ) {
				unset( $data['channel'][ $key ] );
			}
		}

		// Channel: drop authors when no item has a creator (e.g. template parts).
		$has_creator = false;
		foreach ( $data['items'] as $item ) {
			if ( ! empty( $item['creator'] ) ) {
				$has_creator = true;
				break;
			}
		}
		if ( ! $has_creator ) {
			unset( $data['channel']['authors'] );
		}

		$data['items'] = array_map( [ $this, 'compact_item' ], $data['items'] );

		return $data;
	}

	/**
	 * Compact a single item array.
	 *
	 * @param array $item Parsed item.
	 * @return array Compacted item.
	 */
	private function compact_item( array $item ): array {
		// guid, link, pub_date — not used by the importer.
		unset( $item['guid'], $item['link'], $item['pub_date'] );

		// post_modified == post_date: redundant; builder restores from post_date.
		// (_gmt fields no longer exist — collapsed into post_date/post_modified by normalize_data.)
		if (
			isset( $item['post_modified'] ) &&
			$item['post_modified'] === ( $item['post_date'] ?? '' )
		) {
			unset( $item['post_modified'] );
		}

		// Drop fields that match their WordPress import-time defaults.
		foreach ( self::COMPACT_ITEM_DEFAULTS as $key => $default ) {
			if ( array_key_exists( $key, $item ) && $item[ $key ] === $default ) {
				unset( $item[ $key ] );
			}
		}

		// Strip disposable post meta keys.
		foreach ( self::COMPACT_STRIP_META as $key ) {
			unset( $item['meta'][ $key ] );
		}
		if ( isset( $item['meta'] ) && [] === $item['meta'] ) {
			unset( $item['meta'] );
		}

		return $item;
	}

	// -------------------------------------------------------------------------
	// Format helpers
	// -------------------------------------------------------------------------

	/**
	 * Serialize data to YAML and prepend a yaml-language-server schema comment.
	 *
	 * Compatible with VS Code (redhat.vscode-yaml), IntelliJ/PhpStorm, and any
	 * editor that implements the YAML language server protocol.
	 *
	 * @param array  $data       Parsed WXR data.
	 * @param string $schema_url Absolute URL to the JSON Schema file.
	 * @return string YAML content with leading schema directive.
	 */
	private function to_yaml( array $data, string $schema_url ): string {
		$yaml = Spyc::YAMLDump( $data, 2, 0 );

		// Strip the `---` document-start marker Spyc always prepends.
		$yaml = preg_replace( '/^---\n/', '', $yaml );

		// Strip quotes from float-like version strings (e.g. wordpress: '7.0')
		// that Spyc quotes defensively. Date strings (e.g. 20260613-091503) use
		// a dash separator so Spyc leaves them unquoted already.
		$yaml = preg_replace( "/: '(\d+\.\d+)'/m",  ': $1', $yaml );
		$yaml = preg_replace( '/: "(\d+\.\d+)"/m',   ': $1', $yaml );

		// Prepend yaml-language-server directive (no blank line before content).
		return "# yaml-language-server: \$schema={$schema_url}\n" . $yaml;
	}

	private function from_yaml( string $raw ): array {
		// Spyc ignores YAML comments, so the schema directive is transparent.
		$parsed = Spyc::YAMLLoadString( $raw );
		if ( ! is_array( $parsed ) ) {
			WP_CLI::error( 'YAML parse error: unexpected result from Spyc.' );
		}
		return $parsed;
	}

	/**
	 * Serialize data to JSON with $schema as the first key.
	 *
	 * Editors that support JSON Schema (VS Code, IntelliJ) will pick up
	 * the `$schema` property automatically without any plugin.
	 *
	 * @param array  $data       Parsed WXR data.
	 * @param string $schema_url Absolute URL to the JSON Schema file.
	 * @return string Pretty-printed JSON.
	 */
	private function to_json( array $data, string $schema_url ): string {
		// $schema must be the first key so editors detect it immediately.
		$with_schema = array_merge( [ '$schema' => $schema_url ], $data );
		$json        = json_encode( $with_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			WP_CLI::error( 'JSON encode error: ' . json_last_error_msg() );
		}
		return $json . "\n";
	}

	private function from_json( string $raw ): array {
		$data = json_decode( $raw, true );
		if ( null === $data ) {
			WP_CLI::error( 'JSON parse error: ' . json_last_error_msg() );
		}
		// Strip the injected schema ref before processing.
		unset( $data['$schema'] );
		return $data;
	}

	// -------------------------------------------------------------------------
	// File helpers
	// -------------------------------------------------------------------------

	private function read_file( string $path ): string {
		if ( ! file_exists( $path ) ) {
			WP_CLI::error( "File not found: {$path}" );
		}
		$content = file_get_contents( $path );
		if ( false === $content ) {
			WP_CLI::error( "Cannot read file: {$path}" );
		}
		return $content;
	}

	private function write_file( string $path, string $content, bool $force ): void {
		if ( file_exists( $path ) && ! $force ) {
			WP_CLI::error( "File already exists: {$path} — use --force to overwrite." );
		}
		$dir = dirname( $path );
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}
		if ( false === file_put_contents( $path, $content ) ) {
			WP_CLI::error( "Cannot write file: {$path}" );
		}
	}

	private function swap_ext( string $path, string $new_ext ): string {
		$base = pathinfo( $path, PATHINFO_DIRNAME )
			. DIRECTORY_SEPARATOR
			. pathinfo( $path, PATHINFO_FILENAME );
		return $base . '.' . ltrim( $new_ext, '.' );
	}
}
