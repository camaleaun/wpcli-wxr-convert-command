Feature: Lossless WXR ↔ YAML/JSON conversion

  Background:
    Given a WP install
    And a export/content.xml file:
      """
      <?xml version="1.0" encoding="UTF-8"?>
      <!-- generator="WordPress/7.0" created="2026-01-01 00:00" -->
      <rss version="2.0" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:wfw="http://wellformedweb.org/CommentAPI/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/1.2/">
        <channel>
          <title>Test Site</title>
          <link>http://example.com</link>
          <description/>
          <pubDate>Wed, 01 Jan 2026 00:00:00 +0000</pubDate>
          <language>en-US</language>
          <wp:wxr_version>1.2</wp:wxr_version>
          <wp:base_site_url>http://example.com</wp:base_site_url>
          <wp:base_blog_url>http://example.com</wp:base_blog_url>
          <wp:author>
            <wp:author_id>1</wp:author_id>
            <wp:author_login>admin</wp:author_login>
            <wp:author_email>admin@example.com</wp:author_email>
            <wp:author_display_name><![CDATA[admin]]></wp:author_display_name>
            <wp:author_first_name><![CDATA[]]></wp:author_first_name>
            <wp:author_last_name><![CDATA[]]></wp:author_last_name>
          </wp:author>
          <generator>https://wordpress.org/?v=7.0</generator>
          <item>
            <title><![CDATA[Navigation]]></title>
            <link>http://example.com/?p=1</link>
            <pubDate>Wed, 01 Jan 2026 00:00:00 +0000</pubDate>
            <dc:creator/>
            <guid isPermaLink="false">http://example.com/?p=1</guid>
            <description/>
            <content:encoded><![CDATA[<!-- wp:page-list /-->]]></content:encoded>
            <excerpt:encoded><![CDATA[]]></excerpt:encoded>
            <wp:post_id>1</wp:post_id>
            <wp:post_date>2026-01-01 00:00:00</wp:post_date>
            <wp:post_date_gmt>2026-01-01 00:00:00</wp:post_date_gmt>
            <wp:post_modified>2026-01-01 00:00:00</wp:post_modified>
            <wp:post_modified_gmt>2026-01-01 00:00:00</wp:post_modified_gmt>
            <wp:comment_status>closed</wp:comment_status>
            <wp:ping_status>closed</wp:ping_status>
            <wp:post_name>navigation</wp:post_name>
            <wp:status>publish</wp:status>
            <wp:post_parent>0</wp:post_parent>
            <wp:menu_order>0</wp:menu_order>
            <wp:post_type>wp_navigation</wp:post_type>
            <wp:post_password/>
            <wp:is_sticky>0</wp:is_sticky>
          </item>
          <item>
            <title><![CDATA[Header]]></title>
            <link>http://example.com/?p=2</link>
            <pubDate>Wed, 01 Jan 2026 00:00:00 +0000</pubDate>
            <dc:creator/>
            <guid isPermaLink="false">http://example.com/?p=2</guid>
            <description/>
            <content:encoded><![CDATA[<!-- wp:site-logo {"shouldSyncIcon":true} /--><!-- wp:navigation {"ref":1} /-->]]></content:encoded>
            <excerpt:encoded><![CDATA[]]></excerpt:encoded>
            <wp:post_id>2</wp:post_id>
            <wp:post_date>2026-01-01 00:00:00</wp:post_date>
            <wp:post_date_gmt>2026-01-01 00:00:00</wp:post_date_gmt>
            <wp:post_modified>2026-01-01 00:00:00</wp:post_modified>
            <wp:post_modified_gmt>2026-01-01 00:00:00</wp:post_modified_gmt>
            <wp:comment_status>closed</wp:comment_status>
            <wp:ping_status>closed</wp:ping_status>
            <wp:post_name>header</wp:post_name>
            <wp:status>publish</wp:status>
            <wp:post_parent>0</wp:post_parent>
            <wp:menu_order>0</wp:menu_order>
            <wp:post_type>wp_template_part</wp:post_type>
            <wp:post_password/>
            <wp:is_sticky>0</wp:is_sticky>
            <wp:postmeta>
              <wp:meta_key><![CDATA[theme]]></wp:meta_key>
              <wp:meta_value><![CDATA[blank-theme]]></wp:meta_value>
            </wp:postmeta>
            <wp:postmeta>
              <wp:meta_key><![CDATA[area]]></wp:meta_key>
              <wp:meta_value><![CDATA[header]]></wp:meta_value>
            </wp:postmeta>
          </item>
        </channel>
      </rss>
      """

  # ── wp wxr from ──────────────────────────────────────────────────────────────

  Scenario: Convert WXR to YAML (default format)
    When I run `wp wxr from export/content.xml`
    Then STDOUT should contain:
      """
      Success: Created export/content.yml
      """
    And the export/content.yml file should exist
    And the export/content.yml file should contain:
      """
      # yaml-language-server: $schema=https://raw.githubusercontent.com/camaleaun/wxr-command/trunk/schemas/1.2/wxr-complete.schema.json
      """
    And the export/content.yml file should contain:
      """
      meta:
      """
    And the export/content.yml file should contain:
      """
      post_type: wp_template_part
      """
    And the export/content.yml file should contain:
      """
      post_type: wp_navigation
      """

  Scenario: Convert WXR to YAML explicitly with --format=yml
    When I run `wp wxr from export/content.xml --format=yml`
    Then STDOUT should contain:
      """
      Success: Created export/content.yml
      """
    And the export/content.yml file should exist

  Scenario: Convert WXR to JSON with --format=json
    When I run `wp wxr from export/content.xml --format=json`
    Then STDOUT should contain:
      """
      Success: Created export/content.json
      """
    And the export/content.json file should exist
    And the export/content.json file should contain:
      """
      "$schema": "https://raw.githubusercontent.com/camaleaun/wxr-command/trunk/schemas/1.2/wxr-complete.schema.json"
      """
    And the export/content.json file should contain:
      """
      "post_type": "wp_template_part"
      """

  Scenario: YAML output preserves block content
    When I run `wp wxr from export/content.xml`
    Then the export/content.yml file should contain:
      """
      wp:site-logo
      """
    And the export/content.yml file should contain:
      """
      wp:page-list
      """

  Scenario: YAML output preserves post meta
    When I run `wp wxr from export/content.xml`
    Then the export/content.yml file should contain:
      """
      theme: blank-theme
      """
    And the export/content.yml file should contain:
      """
      area: header
      """

  Scenario: YAML output preserves channel authors
    When I run `wp wxr from export/content.xml`
    Then the export/content.yml file should contain:
      """
      login: admin
      """

  Scenario: Custom output path with --output
    When I run `wp wxr from export/content.xml --output=export/custom.yml`
    Then STDOUT should contain:
      """
      Success: Created export/custom.yml
      """
    And the export/custom.yml file should exist
    And the export/content.yml file should not exist

  Scenario: Error when output already exists without --force
    Given a export/content.yml file:
      """
      existing: content
      """
    When I try `wp wxr from export/content.xml`
    Then STDERR should contain:
      """
      Error: File already exists
      """
    And the return code should be 1

  Scenario: Overwrite existing output with --force
    Given a export/content.yml file:
      """
      existing: content
      """
    When I run `wp wxr from export/content.xml --force`
    Then STDOUT should contain:
      """
      Success: Created export/content.yml
      """
    And the export/content.yml file should not contain:
      """
      existing: content
      """

  Scenario: Error on non-existent input file
    When I try `wp wxr from export/missing.xml`
    Then STDERR should contain:
      """
      Error: File not found
      """
    And the return code should be 1

  Scenario: Error on invalid XML
    Given a export/broken.xml file:
      """
      this is not xml
      """
    When I try `wp wxr from export/broken.xml`
    Then STDERR should contain:
      """
      Error:
      """
    And the return code should be 1

  # ── wp wxr to ────────────────────────────────────────────────────────────────

  Scenario: Round-trip WXR → YAML → WXR preserves post types
    When I run `wp wxr from export/content.xml`
    And I run `wp wxr to export/content.yml --output=export/restored.xml`
    Then STDOUT should contain:
      """
      Success: Created export/restored.xml
      """
    And the export/restored.xml file should contain:
      """
      <wp:post_type>wp_template_part</wp:post_type>
      """
    And the export/restored.xml file should contain:
      """
      <wp:post_type>wp_navigation</wp:post_type>
      """

  Scenario: Round-trip WXR → YAML → WXR preserves CDATA block content
    When I run `wp wxr from export/content.xml`
    And I run `wp wxr to export/content.yml --output=export/restored.xml`
    Then the export/restored.xml file should contain:
      """
      <content:encoded><![CDATA[<!-- wp:site-logo {"shouldSyncIcon":true} /--><!-- wp:navigation {"ref":1} /-->]]></content:encoded>
      """

  Scenario: Round-trip WXR → YAML → WXR preserves WXR namespaces
    When I run `wp wxr from export/content.xml`
    And I run `wp wxr to export/content.yml --output=export/restored.xml`
    Then the export/restored.xml file should contain:
      """
      xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
      """
    And the export/restored.xml file should contain:
      """
      xmlns:wp="http://wordpress.org/export/1.2/"
      """

  Scenario: Round-trip WXR → YAML → WXR preserves post meta
    When I run `wp wxr from export/content.xml`
    And I run `wp wxr to export/content.yml --output=export/restored.xml`
    Then the export/restored.xml file should contain:
      """
      <wp:meta_key><![CDATA[theme]]></wp:meta_key>
      """
    And the export/restored.xml file should contain:
      """
      <wp:meta_value><![CDATA[blank-theme]]></wp:meta_value>
      """

  Scenario: Round-trip WXR → YAML → WXR preserves wxr_version
    When I run `wp wxr from export/content.xml`
    And I run `wp wxr to export/content.yml --output=export/restored.xml`
    Then the export/restored.xml file should contain:
      """
      <wp:wxr_version>1.2</wp:wxr_version>
      """

  Scenario: Round-trip WXR → JSON → WXR preserves post types
    When I run `wp wxr from export/content.xml --format=json`
    And I run `wp wxr to export/content.json --output=export/restored.xml`
    Then STDOUT should contain:
      """
      Success: Created export/restored.xml
      """
    And the export/restored.xml file should contain:
      """
      <wp:post_type>wp_template_part</wp:post_type>
      """

  Scenario: Round-trip WXR → JSON → WXR preserves CDATA block content
    When I run `wp wxr from export/content.xml --format=json`
    And I run `wp wxr to export/content.json --output=export/restored.xml`
    Then the export/restored.xml file should contain:
      """
      <content:encoded><![CDATA[<!-- wp:page-list /-->]]></content:encoded>
      """

  Scenario: Convert from YAML to WXR with custom --output
    When I run `wp wxr from export/content.xml`
    And I run `wp wxr to export/content.yml --output=export/custom.xml`
    Then STDOUT should contain:
      """
      Success: Created export/custom.xml
      """
    And the export/custom.xml file should exist

  Scenario: Error when converting to non-existent YAML file
    When I try `wp wxr to export/missing.yml`
    Then STDERR should contain:
      """
      Error: File not found
      """
    And the return code should be 1

  Scenario: Error on unsupported file extension for wp wxr to
    Given a export/content.txt file:
      """
      some content
      """
    When I try `wp wxr to export/content.txt`
    Then STDERR should contain:
      """
      Error: Cannot detect format
      """
    And the return code should be 1

  Scenario: Error when output already exists without --force for wp wxr to
    When I run `wp wxr from export/content.xml`
    And I try `wp wxr to export/content.yml --output=export/content.xml`
    Then STDERR should contain:
      """
      Error: File already exists
      """
    And the return code should be 1

  Scenario: Overwrite existing output with --force for wp wxr to
    When I run `wp wxr from export/content.xml`
    And I run `wp wxr to export/content.yml --output=export/restored.xml`
    And I run `wp wxr to export/content.yml --output=export/restored.xml --force`
    Then STDOUT should contain:
      """
      Success: Created export/restored.xml
      """

  # ── --compact ─────────────────────────────────────────────────────────────

  Scenario: Compact YAML omits fields the importer ignores (guid, link, pub_date)
    When I run `wp wxr from export/content.xml --compact`
    Then STDOUT should contain:
      """
      Success: Created export/content.yml
      """
    And the export/content.yml file should contain:
      """
      # yaml-language-server: $schema=https://raw.githubusercontent.com/camaleaun/wxr-command/trunk/schemas/1.2/wxr-compact.schema.json
      """
    And the export/content.yml file should not contain:
      """
      guid:
      """
    And the export/content.yml file should not contain:
      """
      pub_date:
      """

  Scenario: Compact YAML omits zero-value default fields
    When I run `wp wxr from export/content.xml --compact`
    Then the export/content.yml file should not contain:
      """
      is_sticky:
      """
    And the export/content.yml file should not contain:
      """
      menu_order:
      """
    And the export/content.yml file should not contain:
      """
      post_parent:
      """

  Scenario: Compact YAML omits redundant post_modified when equal to post_date
    When I run `wp wxr from export/content.xml --compact`
    Then the export/content.yml file should not contain:
      """
      post_modified:
      """

  Scenario: Compact YAML omits channel image and generator
    When I run `wp wxr from export/content.xml --compact`
    Then the export/content.yml file should not contain:
      """
      image:
      """
    And the export/content.yml file should not contain:
      """
      generator:
      """

  Scenario: Compact YAML omits authors when no item has a creator
    When I run `wp wxr from export/content.xml --compact`
    Then the export/content.yml file should not contain:
      """
      authors:
      """

  Scenario: Compact YAML strips disposable meta keys
    Given a export/content-meta.xml file:
      """
      <?xml version="1.0" encoding="UTF-8"?>
      <!-- generator="WordPress/7.0" created="2026-01-01 00:00" -->
      <rss version="2.0" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:wfw="http://wellformedweb.org/CommentAPI/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/1.2/">
        <channel>
          <title>Test</title><link>http://example.com</link><description/><pubDate/><language>en-US</language>
          <wp:wxr_version>1.2</wp:wxr_version>
          <wp:base_site_url>http://example.com</wp:base_site_url>
          <wp:base_blog_url>http://example.com</wp:base_blog_url>
          <generator>https://wordpress.org/?v=7.0</generator>
          <item>
            <title><![CDATA[Header]]></title>
            <link/><pubDate/><dc:creator/><guid isPermaLink="false"/><description/>
            <content:encoded><![CDATA[<!-- wp:site-logo /-->]]></content:encoded>
            <excerpt:encoded><![CDATA[]]></excerpt:encoded>
            <wp:post_id>1</wp:post_id>
            <wp:post_date>2026-01-01 00:00:00</wp:post_date>
            <wp:post_date_gmt>2026-01-01 00:00:00</wp:post_date_gmt>
            <wp:post_modified>2026-01-01 00:00:00</wp:post_modified>
            <wp:post_modified_gmt>2026-01-01 00:00:00</wp:post_modified_gmt>
            <wp:comment_status>closed</wp:comment_status>
            <wp:ping_status>closed</wp:ping_status>
            <wp:post_name>header</wp:post_name>
            <wp:status>publish</wp:status>
            <wp:post_parent>0</wp:post_parent>
            <wp:menu_order>0</wp:menu_order>
            <wp:post_type>wp_template_part</wp:post_type>
            <wp:post_password/>
            <wp:is_sticky>0</wp:is_sticky>
            <wp:postmeta><wp:meta_key><![CDATA[_edit_lock]]></wp:meta_key><wp:meta_value><![CDATA[1234567890:1]]></wp:meta_value></wp:postmeta>
            <wp:postmeta><wp:meta_key><![CDATA[_edit_last]]></wp:meta_key><wp:meta_value><![CDATA[1]]></wp:meta_value></wp:postmeta>
            <wp:postmeta><wp:meta_key><![CDATA[theme]]></wp:meta_key><wp:meta_value><![CDATA[blank-theme]]></wp:meta_value></wp:postmeta>
          </item>
        </channel>
      </rss>
      """
    When I run `wp wxr from export/content-meta.xml --compact`
    Then the export/content-meta.yml file should not contain:
      """
      _edit_lock
      """
    And the export/content-meta.yml file should not contain:
      """
      _edit_last
      """
    And the export/content-meta.yml file should contain:
      """
      theme: blank-theme
      """

  Scenario: Compact round-trip WXR → YAML → WXR still produces importable WXR
    When I run `wp wxr from export/content.xml --compact`
    And I run `wp wxr to export/content.yml --output=export/restored.xml`
    Then the export/restored.xml file should contain:
      """
      <wp:post_type>wp_template_part</wp:post_type>
      """
    And the export/restored.xml file should contain:
      """
      <wp:wxr_version>1.2</wp:wxr_version>
      """
    And the export/restored.xml file should contain:
      """
      <wp:post_modified>
      """
    And the export/restored.xml file should contain:
      """
      <content:encoded><![CDATA[<!-- wp:site-logo {"shouldSyncIcon":true} /--><!-- wp:navigation {"ref":1} /-->]]></content:encoded>
      """

  Scenario: Compact and non-compact both contain the same post types
    When I run `wp wxr from export/content.xml --compact --output=export/compact.yml`
    And I run `wp wxr from export/content.xml --output=export/full.yml`
    Then the export/compact.yml file should contain:
      """
      post_type: wp_template_part
      """
    And the export/full.yml file should contain:
      """
      post_type: wp_template_part
      """
