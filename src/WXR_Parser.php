<?php

/**
 * Parses a WXR (WordPress eXtended RSS) XML file into a PHP array.
 *
 * All fields are preserved so the array can be round-tripped back to
 * identical WXR via WXR_Builder.
 */
class WXR_Parser {

	const NAMESPACES = [
		'excerpt' => 'http://wordpress.org/export/1.2/excerpt/',
		'content' => 'http://purl.org/rss/1.0/modules/content/',
		'wfw'     => 'http://wellformedweb.org/CommentAPI/',
		'dc'      => 'http://purl.org/dc/elements/1.1/',
		'wp'      => 'http://wordpress.org/export/1.2/',
	];

	/** @var DOMXPath */
	private $xpath;

	/**
	 * Parse a WXR XML string into a PHP array.
	 *
	 * @param string $xml Raw WXR XML content.
	 * @return array
	 * @throws RuntimeException On parse failure.
	 */
	public function parse( string $xml ): array {
		$dom = new DOMDocument();

		libxml_use_internal_errors( true );
		$ok = $dom->loadXML( $xml );
		$errors = libxml_get_errors();
		libxml_clear_errors();

		if ( ! $ok ) {
			$msg = $errors ? $errors[0]->message : 'unknown XML error';
			throw new RuntimeException( "Failed to parse XML: {$msg}" );
		}

		$this->xpath = new DOMXPath( $dom );
		foreach ( self::NAMESPACES as $prefix => $uri ) {
			$this->xpath->registerNamespace( $prefix, $uri );
		}

		// Extract generator + created from the XML comment.
		$generator = '';
		$created   = '';
		foreach ( $dom->childNodes as $node ) {
			if ( XML_COMMENT_NODE === $node->nodeType ) {
				if ( preg_match( '/generator="([^"]+)"/', $node->nodeValue, $m ) ) {
					$generator = $m[1];
				}
				if ( preg_match( '/created="([^"]+)"/', $node->nodeValue, $m ) ) {
					$created = $m[1];
				}
			}
		}

		$channel = $this->xpath->query( '/rss/channel' )->item( 0 );
		if ( ! $channel ) {
			throw new RuntimeException( 'No <channel> element found — is this a valid WXR file?' );
		}

		return [
			'meta'    => [
				'wxr_version' => $this->text( $channel, 'wp:wxr_version' ),
				'generator'   => $generator,
				'created'     => $created,
				'namespaces'  => self::NAMESPACES,
			],
			'channel' => $this->parse_channel( $channel ),
			'items'   => $this->parse_items( $channel ),
		];
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function text( DOMNode $ctx, string $xp ): string {
		$nodes = $this->xpath->query( $xp, $ctx );
		return $nodes && $nodes->length ? (string) $nodes->item( 0 )->nodeValue : '';
	}

	private function attr( DOMNode $ctx, string $xp, string $attr ): string {
		$nodes = $this->xpath->query( $xp, $ctx );
		if ( ! $nodes || ! $nodes->length ) return '';
		/** @var DOMElement $el */
		$el = $nodes->item( 0 );
		return $el->getAttribute( $attr );
	}

	// -------------------------------------------------------------------------
	// Channel
	// -------------------------------------------------------------------------

	private function parse_channel( DOMNode $ch ): array {
		return [
			'title'         => $this->text( $ch, 'title' ),
			'link'          => $this->text( $ch, 'link' ),
			'description'   => $this->text( $ch, 'description' ),
			'pub_date'      => $this->text( $ch, 'pubDate' ),
			'language'      => $this->text( $ch, 'language' ),
			'base_site_url' => $this->text( $ch, 'wp:base_site_url' ),
			'base_blog_url' => $this->text( $ch, 'wp:base_blog_url' ),
			'generator'     => $this->text( $ch, 'generator' ),
			'image'         => $this->parse_image( $ch ),
			'authors'       => $this->parse_authors( $ch ),
			'categories'    => $this->parse_wp_terms( $ch, 'wp:category', [
				'id'          => 'wp:term_id',
				'slug'        => 'wp:category_nicename',
				'parent'      => 'wp:category_parent',
				'name'        => 'wp:cat_name',
				'description' => 'wp:category_description',
			] ),
			'tags'          => $this->parse_wp_terms( $ch, 'wp:tag', [
				'id'          => 'wp:term_id',
				'slug'        => 'wp:tag_slug',
				'name'        => 'wp:tag_name',
				'description' => 'wp:tag_description',
			] ),
			'terms'         => $this->parse_wp_terms( $ch, 'wp:term', [
				'id'          => 'wp:term_id',
				'taxonomy'    => 'wp:term_taxonomy',
				'slug'        => 'wp:term_slug',
				'parent'      => 'wp:term_parent',
				'name'        => 'wp:term_name',
				'description' => 'wp:term_description',
			] ),
		];
	}

	private function parse_image( DOMNode $ch ): array {
		$node = $this->xpath->query( 'image', $ch )->item( 0 );
		if ( ! $node ) return [];
		return [
			'url'    => $this->text( $node, 'url' ),
			'title'  => $this->text( $node, 'title' ),
			'link'   => $this->text( $node, 'link' ),
			'width'  => (int) $this->text( $node, 'width' ),
			'height' => (int) $this->text( $node, 'height' ),
		];
	}

	private function parse_authors( DOMNode $ch ): array {
		$out = [];
		foreach ( $this->xpath->query( 'wp:author', $ch ) as $node ) {
			$out[] = [
				'id'           => (int) $this->text( $node, 'wp:author_id' ),
				'login'        => $this->text( $node, 'wp:author_login' ),
				'email'        => $this->text( $node, 'wp:author_email' ),
				'display_name' => $this->text( $node, 'wp:author_display_name' ),
				'first_name'   => $this->text( $node, 'wp:author_first_name' ),
				'last_name'    => $this->text( $node, 'wp:author_last_name' ),
			];
		}
		return $out;
	}

	private function parse_wp_terms( DOMNode $ch, string $tag, array $map ): array {
		$out = [];
		foreach ( $this->xpath->query( $tag, $ch ) as $node ) {
			$term = [];
			foreach ( $map as $key => $xp ) {
				$term[ $key ] = $this->text( $node, $xp );
			}
			$out[] = $term;
		}
		return $out;
	}

	// -------------------------------------------------------------------------
	// Items
	// -------------------------------------------------------------------------

	private function parse_items( DOMNode $ch ): array {
		$out = [];
		foreach ( $this->xpath->query( 'item', $ch ) as $node ) {
			$out[] = $this->parse_item( $node );
		}
		return $out;
	}

	private function parse_item( DOMNode $item ): array {
		$guid_node = $this->xpath->query( 'guid', $item )->item( 0 );

		$meta = [];
		foreach ( $this->xpath->query( 'wp:postmeta', $item ) as $m ) {
			$meta[ $this->text( $m, 'wp:meta_key' ) ] = $this->text( $m, 'wp:meta_value' );
		}

		$cats = [];
		foreach ( $this->xpath->query( 'category', $item ) as $c ) {
			/** @var DOMElement $c */
			$cats[] = [
				'domain'   => $c->getAttribute( 'domain' ),
				'nicename' => $c->getAttribute( 'nicename' ),
				'value'    => $c->nodeValue,
			];
		}

		$comments = [];
		foreach ( $this->xpath->query( 'wp:comment', $item ) as $c ) {
			$comments[] = $this->parse_comment( $c );
		}

		return [
			'post_id'           => (int) $this->text( $item, 'wp:post_id' ),
			'title'             => $this->text( $item, 'title' ),
			'link'              => $this->text( $item, 'link' ),
			'pub_date'          => $this->text( $item, 'pubDate' ),
			'creator'           => $this->text( $item, 'dc:creator' ),
			'guid'              => [
				'value'       => $guid_node ? $guid_node->nodeValue : '',
				'isPermaLink' => $guid_node ? $guid_node->getAttribute( 'isPermaLink' ) : 'false',
			],
			'description'       => $this->text( $item, 'description' ),
			'content'           => $this->text( $item, 'content:encoded' ),
			'excerpt'           => $this->text( $item, 'excerpt:encoded' ),
			'post_date'         => $this->text( $item, 'wp:post_date' ),
			'post_date_gmt'     => $this->text( $item, 'wp:post_date_gmt' ),
			'post_modified'     => $this->text( $item, 'wp:post_modified' ),
			'post_modified_gmt' => $this->text( $item, 'wp:post_modified_gmt' ),
			'comment_status'    => $this->text( $item, 'wp:comment_status' ),
			'ping_status'       => $this->text( $item, 'wp:ping_status' ),
			'post_name'         => $this->text( $item, 'wp:post_name' ),
			'status'            => $this->text( $item, 'wp:status' ),
			'post_parent'       => (int) $this->text( $item, 'wp:post_parent' ),
			'menu_order'        => (int) $this->text( $item, 'wp:menu_order' ),
			'post_type'         => $this->text( $item, 'wp:post_type' ),
			'post_password'     => $this->text( $item, 'wp:post_password' ),
			'is_sticky'         => (int) $this->text( $item, 'wp:is_sticky' ),
			'attachment_url'    => $this->text( $item, 'wp:attachment_url' ),
			'categories'        => $cats,
			'meta'              => $meta,
			'comments'          => $comments,
		];
	}

	private function parse_comment( DOMNode $c ): array {
		return [
			'id'           => (int) $this->text( $c, 'wp:comment_id' ),
			'author'       => $this->text( $c, 'wp:comment_author' ),
			'author_email' => $this->text( $c, 'wp:comment_author_email' ),
			'author_url'   => $this->text( $c, 'wp:comment_author_url' ),
			'author_ip'    => $this->text( $c, 'wp:comment_author_IP' ),
			'date'         => $this->text( $c, 'wp:comment_date' ),
			'date_gmt'     => $this->text( $c, 'wp:comment_date_gmt' ),
			'content'      => $this->text( $c, 'wp:comment_content' ),
			'approved'     => $this->text( $c, 'wp:comment_approved' ),
			'type'         => $this->text( $c, 'wp:comment_type' ),
			'parent'       => (int) $this->text( $c, 'wp:comment_parent' ),
			'user_id'      => (int) $this->text( $c, 'wp:comment_user_id' ),
		];
	}
}
