<?php

use League\HTMLToMarkdown\HtmlConverter;
use League\HTMLToMarkdown\Converter\TableConverter;
use WP_Docs_Parser\WP_API_Client;
use WP_Docs_Parser\WP_Docs;

require_once __DIR__ . '/vendor/autoload.php';

$cli_options = getopt( 
	'',
	[
		'update',
		'sitemaps',
	] 
);

$markdowner = new HtmlConverter(
	[
		'strip_tags' => true,
		'header_style' => 'atx', // Use # instead of underlines for h1 and h2.
	]
);

// Convert HTML tables to Markdown tables.
$markdowner->getEnvironment()->addConverter( new TableConverter() );

$docs = new WP_Docs( $markdowner );

$wp_developer = new WP_API_Client( 
	'https://developer.wordpress.org', 
	__DIR__ . '/docs/source/developer.wordpress.org' 
);

$wp_developer->set_delay( 10 ); // Workaround for WP.org rate limiting.

$content_types = [
	'apis-handbook',
	'plugin-handbook',
	'theme-handbook',
	'blocks-handbook',
	'wpcs-handbook',
	'rest-api-handbook',
	'scf-handbook',
	'adv-admin-handbook',
	// 'wp-parser-function',
	// 'wp-parser-class',
	// 'wp-parser-hook',
	// 'wp-parser-method',
];

if ( isset( $cli_options['sitemaps'] ) ) {
	$sitemap_urls = $wp_developer->get_sitemap_urls();

	file_put_contents( 
		__DIR__ . '/docs/sitemaps/developer.wordpress.org.json', 
		json_encode( $sitemap_urls, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) 
	);
}

$handbooks_md = [];
foreach ( $content_types as $content_type ) {
	$content_md = [];
	
	if ( isset( $cli_options['update'] ) ) {
		$json_files = $wp_developer->get_from_rest_api( $content_type );
	} else {
		$json_files = $wp_developer->get_json_files_for_content_types( [ $content_type ] );
	}

	foreach ( $json_files as $json_file ) {
		$content_md[] = $docs->get_post_json_as_markdown( $wp_developer->get_json( $json_file ) );
	}

	$handbooks_md[ $content_type ] = implode( "\n\n---\n\n", $content_md );

	file_put_contents(
		sprintf( '%s/docs/wp-%s.md', __DIR__, $content_type ), 
		$handbooks_md[ $content_type ]
	);
}

// Update the combined handbook file.
file_put_contents(
	sprintf( '%s/docs/wp-handbooks.md', __DIR__ ), 
	implode( "\n\n---\n\n", $handbooks_md )
);

/**
 * WordPress.org/documentation parser.
 * 
 * There seems to be only one content type `articles` for now.
 */
$wp_org = new WP_API_Client( 
	'https://wordpress.org/documentation', 
	__DIR__ . '/docs/source/wordpress.org' 
);

$wp_org->set_delay( 10 ); // Workaround for WP.org rate limiting.

$content_types = [
	'articles',
];

foreach ( $content_types as $content_type ) {
	$content_md = [];
	
	if ( isset( $cli_options['update'] ) ) {
		$json_files = $wp_org->get_from_rest_api( $content_type );
	} else {
		$json_files = $wp_org->get_json_files_for_content_types( [ $content_type ] );
	}

	foreach ( $json_files as $json_file ) {
		$content_md[] = $docs->get_post_json_as_markdown( $wp_org->get_json( $json_file ) );
	}

	file_put_contents(
		sprintf( '%s/docs/wp-%s.md', __DIR__, $content_type ), 
		implode( "\n\n---\n\n", $content_md )
	);
}

if ( isset( $cli_options['sitemaps'] ) ) {
	$sitemap_urls = $wp_org->get_sitemap_urls();

	file_put_contents( 
		__DIR__ . '/docs/sitemaps/wordpress.org-documentation.json', 
		json_encode( $sitemap_urls, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) 
	);
}