<?php
defined( 'ABSPATH' ) || exit;

?>
<div id="customify-sites-filter" class="wp-filter hide-if-no-js">
    <div class="filter-count">
        <span id="customify-sites-filter-count" class="count theme-count">&#45;</span>
    </div>
    <ul id="customify-sites-filter-cat" class="filter-links">
        <li><a href="#" data-slug="all" class="current"><?php esc_html_e( 'All', 'customify-starter-sites' ); ?></a></li>
    </ul>
    <form class="search-form">
        <label class="screen-reader-text" for="wp-filter-search-input"><?php esc_html_e( 'Search Themes', 'customify-starter-sites' ); ?></label><input placeholder="<?php esc_attr_e( 'Search sites...', 'customify-starter-sites' ); ?>" type="search" aria-describedby="live-search-desc" id="customify-sites-search-input" class="wp-filter-search">
    </form>
    <ul id="customify-sites-filter-tag"  class="filter-links float-right" style="float: right;"></ul>
</div>


<script id="customify-site-item-html" type="text/html">
    <div class="theme" title="{{ data.title }}" tabindex="0" aria-describedby="" data-slug="{{ data.slug }}">
        <div class="theme-screenshot">
            <img src="{{ data.thumbnail_url }}" alt="">
        </div>
        <#  if ( data.pro ) {  #>
        <span class="theme-pro-bubble"><?php esc_html_e( 'Pro', 'customify-starter-sites' ); ?></span>
        <# } #>
        <div class="theme-id-container">
            <h2 class="theme-name" id="{{ data.slug }}-name">{{ data.title }}</h2>
            <div class="theme-actions">
                <# if ( data.demo_url ) { #>
                <a class="cs-open-preview button button-secondary  hide-if-no-customize" data-slug="{{ data.slug }}" href="{{ data.demo_url }}" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview', 'customify-starter-sites' ); ?></a>
                <# } #>
                <a class="cs-open-modal button button-primary  hide-if-no-customize" href="#"><?php esc_html_e( 'Details', 'customify-starter-sites' ); ?></a>
            </div>
        </div>
    </div>
</script>


<div id="customify-sites-listing-wrapper" class="theme-browser rendered">
    <div id="customify-sites-listing" class="themes wp-clearfix">
    </div>
</div>
<p  id="customify-sites-no-demos"  class="no-themes"><?php esc_html_e( 'No sites found. Try a different search.', 'customify-starter-sites' ); ?></p>
<span class="spinner"></span>


