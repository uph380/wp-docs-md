<?php

namespace WP_Docs_Parser;

use League\HTMLToMarkdown\HtmlConverter;

class WP_Docs {

	private array $sites = [];

	private HtmlConverter $markdowner;

	public function __construct( HtmlConverter $markdowner ) {
		$this->markdowner = $markdowner;
	}

	public function add_site( WP_API_Client $site ): void {
		$this->sites[] = $site;
	}

	private function get_anchor_id_for_url( string $link ): ?string {
		$path = parse_url( $link, PHP_URL_PATH );

		if ( $path ) {
			return trim( $path, '\\/' );
		}

		return null;
	}

	public function get_post_json_as_markdown( array $json ): string {
		$link = parse_url( $json['link'] );

		// Normalize line breaks.
		$content = str_replace( "\r", "\n", $json['content']['rendered'] );
		
		$id = $this->get_anchor_id_for_url( $json['link'] );

		// Replace absolute links to anchor links.
		if ( preg_match_all( '/href\s*=\s*["\']?([^"\' >]+)["\']?/', $content, $matches ) ) {
			foreach ( $matches[1] as $link_url ) {
				$link_url_clean = html_entity_decode( trim( $link_url ) );
				$content_link = parse_url( $link_url_clean );
				
				if ( ! empty( $content_link['path'] ) && ! empty( $content_link['host'] ) && $link['host'] === $content_link['host'] ) {
					$article_path_parts = explode( '/', $content_link['path'] );
					$content_link_parts = explode( '/', $link['path'] );

					if ( array_intersect( $content_link_parts, $article_path_parts ) ) {
						$content = str_replace( $link_url, sprintf( '#%s', $this->get_anchor_id_for_url( $link_url_clean ) ), $content );
					}
				}
			}
		}

		$content = $this->markdowner->convert( $content );
		$content = preg_replace( "/[\r\n]{2,}/", "\n\n", $content );

		$entry = [
			sprintf( '# %s <a name="%s" />', $json['title']['rendered'], $id ),
			sprintf( 'Source: %s', $json['link'] ),
			$content,
		];

		return implode( "\n\n", $entry );
	}
}