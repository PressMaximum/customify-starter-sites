
jQuery( document ).ready( function( $ ){

    var modal_sites = {};
    var current_page_builder = 'all';

    /** Plugins never shown or auto-installed by the starter wizard (match PHP skips). */
    var SKIP_RECOMMEND_PLUGIN_SLUGS = {
        'custom-sidebars': true,
        'gutenberg': true
    };

    function shouldSkipRecommendPluginSlug( slug ) {
        return Boolean( slug && SKIP_RECOMMEND_PLUGIN_SLUGS[ String( slug ).toLowerCase() ] );
    }

    function safeSlug( value ) {
        value = String( value || '' ).toLowerCase();
        return /^[a-z0-9_-]+$/.test( value ) ? value : '';
    }

    function safeHttpsUrl( value ) {
	    value = String( value || '' ).trim();
	    if ( ! /^https:\/\//i.test( value ) ) {
	        return '';
	    }
        try {
	        var url = new window.URL( value );
            return url.protocol === 'https:' ? url.href : '';
        } catch ( error ) {
            return '';
        }
    }

    function escapeHtml( value ) {
        return _.escape( String( value === null || _.isUndefined( value ) ? '' : value ) );
    }

    function normalizePluginMap( raw ) {
        var normalized = {};
        if ( ! _.isObject( raw ) ) {
            return normalized;
        }
	    if ( _.isArray( raw ) ) {
	        _.each( raw, function( item ) {
	            var slug = safeSlug( _.isObject( item ) ? item.slug : item );
	            if ( slug && ! shouldSkipRecommendPluginSlug( slug ) ) {
	                normalized[ slug ] = String( _.isObject( item ) && item.name ? item.name : slug );
	            }
	        } );
	        return normalized;
	    }
        _.each( raw, function( name, slug ) {
            slug = safeSlug( slug );
            if ( slug && ! shouldSkipRecommendPluginSlug( slug ) ) {
                normalized[ slug ] = String( name || slug );
            }
        } );
        return normalized;
    }

    function normalizeResourceMap( raw ) {
        var normalized = {};
        if ( ! _.isObject( raw ) ) {
            return normalized;
        }
        _.each( raw, function( value, key ) {
            key = String( key || '' );
            if ( /^[a-z0-9_]+$/.test( key ) ) {
                normalized[ key ] = value === false || value === 'false' ? false : safeHttpsUrl( value );
            }
        } );
        return normalized;
    }

    function normalizeApiResponse( raw ) {
        var response = {
            total: 0,
            posts: {},
            categories: [],
            tags: []
        };
        if ( ! _.isObject( raw ) ) {
            return response;
        }

        _.each( _.isObject( raw.posts ) ? raw.posts : {}, function( item ) {
            if ( ! _.isObject( item ) ) {
                return;
            }
            var slug = safeSlug( item.slug );
            var demoUrl = safeHttpsUrl( item.demo_url );
            var thumbnailUrl = safeHttpsUrl( item.thumbnail_url );
            if ( ! slug || ! demoUrl || ! thumbnailUrl ) {
                return;
            }
            response.posts[ slug ] = {
                slug: slug,
                title: String( item.title || '' ),
                desc: String( item.desc || '' ),
                demo_url: demoUrl,
                thumbnail_url: thumbnailUrl,
	            pro: item.pro === true || item.pro === 1 || item.pro === '1',
                resources: normalizeResourceMap( item.resources ),
                plugins: normalizePluginMap( item.plugins ),
                manual_plugins: normalizePluginMap( item.manual_plugins )
            };
        } );

        _.each( _.isArray( raw.categories ) ? raw.categories : [], function( item ) {
            var slug = item && safeSlug( item.slug );
            if ( slug ) {
                response.categories.push( { slug: slug, name: String( item.name || '' ) } );
            }
        } );
        _.each( _.isArray( raw.tags ) ? raw.tags : [], function( item ) {
            var slug = item && safeSlug( item.slug );
            if ( slug ) {
                response.tags.push( { slug: slug, name: String( item.name || '' ) } );
            }
        } );

        response.total = _.size( response.posts );
        return response;
    }

    var getTemplate = _.memoize(function () {

        var compiled,
            /*
             * Underscore's default ERB-style templates are incompatible with PHP
             * when asp_tags is enabled, so WordPress uses Mustache-inspired templating syntax.
             *
             * @see trac ticket #22344.
             */
            options = {
                evaluate: /<#([\s\S]+?)#>/g,
                interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
                escape: /\{\{([^\}]+?)\}\}(?!\})/g,
                variable: 'data'
            };

        return function (data, id, data_variable_name ) {
            if (_.isUndefined(id)) {
                id = 'customify-site-item-html';
            }
            if ( ! _.isUndefined( data_variable_name ) && _.isString( data_variable_name ) ) {
                options.variable = data_variable_name;
            } else {
                options.variable = 'data';
            }
            compiled = _.template($('#' + id).html(), null, options);
            return compiled(data);
        };

    });


    var Customify_Modal_Site = function( $item, data ){
        var m = this;
        var steps;
        steps = {
            modal: null,
            item: $item,
            owl: null,
            current_step: 0, // mean first step
            breadcrumb: null,
            getTemplate: getTemplate,
            data: {},
            buttons: {},
            last_step: 4,
            xml_id: 0,
            json_id: 0,
            doing: false,
            current_builder: 'all',
            recommend_plugins: {},
            skip_plugins: false,
            add_modal: function () {
                var that = this;
                var template = that.getTemplate();

                that.data = data;
                var html = template( data, 'tpl-cs-item-modal' );
                that.modal = $( html );
                $( '#wpbody-content' ).append( that.modal );
                that.init();
            },
            _reset: function(){
                this.doing = false;
                $( '.cs-breadcrumb', this.modal ).removeClass('cs-hide');
                $( '.cs-action-buttons a, .cs-step', this.modal ).removeClass('loading circle-loading completed cs-hide');
            },
            _open: function(){
                var that = this;

                that.item.on('click', '.cs-open-modal, .theme-screenshot', function (e) {
                    e.preventDefault();
                    $('body').addClass('customify-sites-show-modal');
                    if (that.owl) {
                        that.owl.trigger('to.owl.carousel', [0, 0]);
                    }
                    that.modal.addClass('cs-show');
                    that._reset();
                    $(window).resize();

                    if ( that.data.pro && ! Customify_Starter_Sites.license_valid ) {
                        that.disable_button( 'start' );
                        that.buttons.start.addClass( 'pro-only' );
                        that.buttons.start.find( '.cs-btn-circle-text' ).text( Customify_Starter_Sites.pro_text );
                    }

                });

            },

            /**
             * Normalize _recommend_plugins from JSON download (slug => label or alternate array shapes).
             */
            _normalizeRecommendPlugins: function( raw ) {
                var out = {};
                if ( ! raw ) {
                    return out;
                }
                if ( _.isArray( raw ) ) {
                    _.each( raw, function( item ){
                        var slug = item && _.isObject( item ) ? safeSlug( item.slug ) : '';
                        if ( slug && ! shouldSkipRecommendPluginSlug( slug ) ) {
                            out[ slug ] = String( item.name || slug );
                        }
                    });
                } else if ( _.isObject( raw ) ) {
                    _.each( raw, function( name, slug ){
                        slug = safeSlug( slug );
                        if ( slug && ! shouldSkipRecommendPluginSlug( slug ) ) {
                            out[ slug ] = String( name || slug );
                        }
                    });
                }
                return out;
            },

            _install_plugins_notice: function (){
                //.cs-install-plugins
                var that = this;
                var manual_plugins      = '';

                if ( ! _.isObject( that.data.manual_plugins ) ) {
                    that.data.manual_plugins  ={};
                }

                _.each( that.data.manual_plugins, function( plugin_name, plugin_file ){
                    // The manual plugin in the list recommend plugin
                    if ( ! _.isUndefined( that.recommend_plugins[ plugin_file ] ) ) {
                        if ( ! that.is_installed( plugin_file ) ) {
                            if (_.isUndefined(Customify_Starter_Sites.installed_plugins[plugin_file])) {
                                manual_plugins += '<li><div class="circle-loader "><div class="checkmark draw"></div></div><span class="cs-plugin-name">' + escapeHtml( plugin_name ) + '</span></li>';
                            }
                        }
                    }
                } );


                $( '.cs-installed-plugins', that.modal ).hide();
                $( '.cs-install-plugins', that.modal ).hide();

                if ( manual_plugins !==  '' ) {
                    $( '.cs-install-manual-plugins', that.modal ).show();
                    $( '.cs-install-manual-plugins ul', that.modal ).html( manual_plugins );
                } else {
                    $( '.cs-install-manual-plugins', that.modal ).hide();
                }
            },

            _setup_plugins: function(){
                var that = this;

                that.recommend_plugins = that._normalizeRecommendPlugins( that.recommend_plugins );

                if ( _.isEmpty( that.data.manual_plugins ) ) {
                    that.data.manual_plugins = {};
                }

                that.skip_plugins = true;
                $('.cs-installing-plugins', that.modal ).html('');

                if ( _.size( that.recommend_plugins ) < 1 ) {
                    return;
                }

                var pending = _.filter(_.keys(that.recommend_plugins), function(slug){
                    return ! that.is_activated( slug );
                });

                if ( pending.length === 0 ) {
                    /* All recommended plugins already active — skip this carousel step via owl handler */
                    return;
                }

                that.skip_plugins = false;

                _.each(that.recommend_plugins, function (name, slug) {
                        var html = '';
                        slug = safeSlug( slug );
                        if ( ! slug ) {
                            return;
                        }
                        var safeName = escapeHtml( name );
                        // If plugin not in manual install
                        if (_.isUndefined(that.data.manual_plugins[slug])) {
                            if (that.is_activated(slug)) {
                                html = '<li data-slug="' + slug + '" class="is-activated"><div class="circle-loader load-complete"><div class="checkmark draw"></div></div><span class="cs-plugin-name">' + safeName + '</span></li>';
                            } else if (!that.is_installed(slug)) { // plugin not installed
                                html = '<li data-slug="' + slug + '" class="do-install-n-activate"><div class="circle-loader "><div class="checkmark draw"></div></div><span class="cs-plugin-name">' + safeName + '</span></li>';
                            } else { // Plugin install but not active
                                html = '<li data-slug="' + slug + '" class="do-activate"><div class="circle-loader"><div class="checkmark draw"></div></div><span class="cs-plugin-name">' + safeName + '</span></li>';
                            }
                        } else {
                            // Manual Plugin installed and activated
                            if (that.is_activated(slug)) {
                                html = '<li data-slug="' + slug + '" class="is-activated"><div class="circle-loader load-complete"><div class="checkmark draw"></div></div><span class="cs-plugin-name">' + safeName + '</span></li>';
                            } else if (that.is_installed(slug)) {   // Manual Plugin install but not activated
                                html = '<li data-slug="' + slug + '" class="do-activate"><div class="circle-loader"><div class="checkmark draw"></div></div><span class="cs-plugin-name">' + safeName + '</span></li>';
                            }
                        }

                        if ( html !== '' ) {
                            $('.cs-installing-plugins', that.modal).append(html);
                        }
                });

            },

            disable_button: function( button ){
                if ( !_.isUndefined( this.buttons[ button ] ) ) {
                    this.buttons[ button ].addClass( 'disabled' );
                }
            },
            loading_button: function( button ){
                if ( !_.isUndefined( this.buttons[ button ] ) ) {
                    this.buttons[ button ].addClass( 'loading circle-loading' );
                }
            },
            completed_button: function( button ){
                if ( !_.isUndefined( this.buttons[ button ] ) ) {
                    this.buttons[ button ].addClass( 'completed' );
                }
            },
            active_button: function( button ){
                if ( !_.isUndefined( this.buttons[ button ] ) ) {
                    this.buttons[ button ].removeClass( 'disabled' );
                }
            },
            init: function(){
                var that = this;

                that.buttons.skip = $( '.cs-skip', that.modal );
                that.buttons.start = $( '.cs-do-start', that.modal );
                that.buttons.install_plugins = $( '.cs-do-install-plugins', that.modal );
                that.buttons.import_content = $( '.cs-do-import-content', that.modal );
                that.buttons.import_options = $( '.cs-do-import-options', that.modal );
                that.buttons.view_site = $( '.cs-do-view-site', that.modal );

                /*
                 * Do not remove the Install Plugins step when site JSON lacks data.plugins/manual_plugins —
                 * recommended plugins often come later from `_recommend_plugins` in the downloaded config.
                 */

                that.breadcrumb = $( '.cs-breadcrumb li', that.modal );

                /**
                 * @see https://owlcarousel2.github.io/OwlCarousel2/docs/api-events.html
                 * @type {jQuery}
                 */
                that.owl = $(".owl-carousel", that.modal ).owlCarousel({
                    items: 1,
                    loop: false,
                    mouseDrag: false,
                    touchDrag: false,
                    pullDrag: false,
                    freeDrag: false,
                    rewind: false,
                    autoHeight:true
                });

                that._make_steps_clickable();

                that.owl.on( 'initialize.owl.carousel', function( e, a  ) {
                    that._make_steps_clickable();
                });

                that.owl.on( 'changed.owl.carousel', function( e, a  ) {
                    that.current_step = e.page.index;
                    that._make_steps_clickable();
                    that.doing = false;

                    if ( that.current_step === 1 && that.skip_plugins ) {
                        that.step_completed( 'install_plugins', 0 );
                    }
                });

                // back to list
                that.modal.on( 'click', '.cs-back-to-list', function( e  ) {
                    e.preventDefault();
                    $( 'body' ).removeClass( 'customify-sites-show-modal' );
                    that.modal.removeClass( 'cs-show' );
                } );

                that.modal.on( 'click', '.cs-skip', function( e  ) {
                    e.preventDefault();
                    if ( ! that.doing ) {
                        that.next_step();
                    }
                } );

                that._breadcrumb_actions();
                that._do_start_import();
                that._installing_plugins();
                that._importing_content();
                that._importing_options();
                that._open();
            },

            next_step: function(){
                this.owl.trigger('next.owl.carousel');
            },

            /**
             * Skip this step and next
             */
            skip: function(){
                this.owl.trigger('next.owl.carousel');
            },

            _make_steps_clickable: function(){
                var that = this;
                that._reset();
                that.breadcrumb.removeClass( 'cs-clickable' );
                for( var i = 0; i <= this.current_step; i++ ) {
                    that.breadcrumb.eq( i ).addClass( 'cs-clickable' );
                }

                if ( that.current_step === that.last_step ) {
                    that.buttons.skip.addClass( 'cs-hide' );
                    $('.cs-breadcrumb', that.modal ).addClass( 'cs-hide' );
                } else {
                    that.buttons.skip.removeClass( 'cs-hide' );
                    $('.cs-breadcrumb', that.modal ).removeClass( 'cs-hide' );
                }

                if ( that.current_step !== 0 && that.current_step !== that.last_step ) {
                    that.buttons.skip.removeClass( 'cs-hide' );
                } else {
                    that.buttons.skip.addClass( 'cs-hide' );
                }

                $( '.cs-action-buttons a', that.modal ).removeClass( 'current' );
                $( '.cs-action-buttons a', that.modal ).eq(that.current_step).addClass( 'current' );

            },

            _breadcrumb_actions: function(){
                var that = this;
                that.breadcrumb.on( 'click', function( e ){
                    e.preventDefault();
                    if ( ! that.doing ) {
                        var index = $(this).index();
                        if ($(this).hasClass('cs-clickable') && index !== that.current_step) {
                            that.current_step = index;
                            that.owl.trigger('to.owl.carousel', [index]);
                        }
                    }
                } );
            },

            is_activated: function( plugin_slug ){
                return _.isUndefined( Customify_Starter_Sites.activated_plugins[ plugin_slug ] ) ? false : true;
            },

            is_installed: function( plugin_slug ){
                return _.isUndefined( Customify_Starter_Sites.installed_plugins[ plugin_slug ] ) ? false : true;
            },

            step_completed: function( step, t ){
                var that = this;
                $( '.cs-step.cs-step-'+step, this.modal ).addClass('completed');
                this.completed_button( step );
                if ( _.isUndefined( t ) ) {
                    t = 2000;
                }
                setTimeout( function(){
                    that.next_step();
                }, t );
            },

            _do_start_import: function(){
                var that = this;

                // Skip or start import
                that.buttons.start.on( 'click', function( e  ) {
                    e.preventDefault();

                    if ( $( this ).hasClass( 'disabled' ) ) {
                        return;
                    }

                    if ( ! that.doing ) {
                        that.loading_button('start');
                        that.doing = true;
                        that.current_builder = $( '#customify-sites-filter-cat a.current' ).eq(0).attr( 'data-slug' ) || '';
                        var placeholder_only = $( 'input[name="import_placeholder_only"]', that.modal ).length > 0 ? $( 'input[name="import_placeholder_only"]', that.modal ).is(':checked') : false ;
                        $.ajax({
                            url: Customify_Starter_Sites.ajax_url,
                            dataType: 'json',
                            type: 'post',
                            data: {
                                action: 'custstsi_download_files',
                                nonce: Customify_Starter_Sites.ajax_nonce,
                                resources: that.data.resources,
                                builder: that.current_builder,
                                site_slug: that.data.slug,
                                placeholder_only : placeholder_only
                            },
                            success: function (res) {
	                            if ( ! _.isObject( res ) ) {
	                                return;
	                            }
                                that.xml_id = res.xml_id;
                                that.json_id = res.json_id;
                                that.recommend_plugins = res._recommend_plugins;

                                that.recommend_plugins = that._normalizeRecommendPlugins( that.recommend_plugins );
                                if (that.xml_id <= 0) {
                                    that._reset();
                                    $('.cs-error-download-files', that.modal).removeClass('cs-hide');
                                    that.doing = false;
                                    that.buttons.start.find('.cs-btn-circle-text').text(Customify_Starter_Sites.try_again);
                                } else {

                                    _.each(res.texts, function (t, k) {
	                                    $('.cs-' + safeSlug( k ), that.modal).text( String( t || '' ) );
                                    });

                                    that._install_plugins_notice();
                                    that._setup_plugins();

                                    that.step_completed('start');
                                }

	                            },
	                            error: function () {
	                                that._reset();
	                                $('.cs-error-download-files', that.modal).removeClass('cs-hide');
	                                that.doing = false;
	                                that.buttons.start.find('.cs-btn-circle-text').text(Customify_Starter_Sites.try_again);
	                            }
                        });
                    } // end if doing
                } );
            },

            _installing_plugins: function() {
                var that = this;
                var list;
                var n_plugin_installed = 0;
                var n;

                /**
                 * Keep localized maps in sync so later steps resolve plugin state correctly.
                 */
                var mergeLocalPluginState = function( slug ) {
                    if ( ! slug ) {
                        return;
                    }
                    var label = that.recommend_plugins[ slug ]
                        || Customify_Starter_Sites.installed_plugins[ slug ]
                        || ( Customify_Starter_Sites.support_plugins && Customify_Starter_Sites.support_plugins[ slug ] )
                        || slug;
                    Customify_Starter_Sites.installed_plugins[ slug ] = label;
                    Customify_Starter_Sites.activated_plugins[ slug ] = slug;
                };

                var resetInstallUiAfterFailure = function ( pluginSlug ) {
                    that.doing = false;
                    that._reset();
                    if ( pluginSlug ) {
                        $( '.cs-installing-plugins li[data-slug="' + pluginSlug + '"] .circle-loader', that.modal ).removeClass( 'circle-loading' );
                    }
                };

                that.buttons.install_plugins.on( 'click', function( e ){
                    e.preventDefault();
                    list = $( '.cs-installing-plugins li', that.modal );
                    n = list.length;
                    if ( n > 0 ) {
                        if (!that.doing) {
                            n_plugin_installed = 0;
                            that.doing = true;
                            that.loading_button('install_plugins');
                            ajax_install_plugin();
                        }
                    } else {
                        that.step_completed( 'install_plugins' );
                    }
                } );

                var ajax_install_plugin = function () {
                    that.doing = true;
                    var plugin_data = list.eq(n_plugin_installed).attr( 'data-slug' ) || '';
                    if ( that.is_activated( plugin_data ) ){
                        mergeLocalPluginState( plugin_data );
                        n_plugin_installed++;
                        if( n_plugin_installed < n ) {
                            ajax_install_plugin();
                        } else {
                            that.doing = false;
                            that.step_completed( 'install_plugins' );
                        }
                    } else if( that.is_installed( plugin_data ) ) {
                        ajax_active_plugin();
                    } else {
                        $( '.cs-installing-plugins li[data-slug="'+plugin_data+'"] .circle-loader', that.modal ).removeClass('load-complete').addClass('circle-loading');
                        $.ajax({
                            url: Customify_Starter_Sites.ajax_url,
                            type: 'post',
                            dataType: 'json',
                            data: {
                                action: 'custstsi_install_plugin',
                                nonce: Customify_Starter_Sites.ajax_nonce,
                                plugin: plugin_data
                            },
                            success: function (res) {
                                if ( ! res || res.success !== true ) {
                                    resetInstallUiAfterFailure( plugin_data );
                                    return;
                                }
                                ajax_active_plugin();
                            },
                            error: function () {
                                resetInstallUiAfterFailure( plugin_data );
                            }
                        });
                    }

                };

                var ajax_active_plugin = function () {
                    that.doing = true;
                    var plugin_data = list.eq(n_plugin_installed).attr( 'data-slug' ) || '';
                    $( '.cs-installing-plugins li[data-slug="'+plugin_data+'"] .circle-loader', that.modal ).removeClass('load-complete').addClass('circle-loading');
                    $.ajax({
                        url: Customify_Starter_Sites.ajax_url,
                        type: 'post',
                        dataType: 'json',
                        data: {
                            action: 'custstsi_active_plugin',
                            nonce: Customify_Starter_Sites.ajax_nonce,
                            plugin: plugin_data
                        },
                        success: function (res) {
                            if ( ! res || res.success !== true ) {
                                resetInstallUiAfterFailure( plugin_data );
                                return;
                            }
                            mergeLocalPluginState( plugin_data );
                            n_plugin_installed++;
                            $( '.cs-installing-plugins li[data-slug="'+plugin_data+'"] .circle-loader', that.modal ).removeClass('circle-loading').addClass( 'load-complete' );
                            if( n_plugin_installed < n ) {
                                ajax_install_plugin();
                            } else {
                                that.doing = false;
                                that.step_completed( 'install_plugins' );
                            }
                        },
                        error: function () {
                            resetInstallUiAfterFailure( plugin_data );
                        }
                    });
                };

            },

            _importing_content: function(){
                var that = this;

                $( '.cs-do-import-content', that.modal ).on( 'click', function( e ){
                    e.preventDefault();
                    if ( ! that.doing ) {
                        that.doing = true;
                        that.disable_button('import_content');
                        that.loading_button('import_content');
                        $('.cs-import-content-status .circle-loader', that.modal).addClass('circle-loading');

                        var cb = function(){
                            $.ajax({
                                url: Customify_Starter_Sites.ajax_url,
                                data: {
                                    action: 'custstsi_import_content',
                                    nonce: Customify_Starter_Sites.ajax_nonce,
                                    id: that.xml_id,

                                },
                                success: function (res) {
                                    $('.cs-import-content-status .circle-loader', that.modal).removeClass('circle-loading').addClass('load-complete');
                                    that.step_completed('import_content');
                                },
                                error: function (res) {
	                                that.doing = false;
	                                that._reset();
	                                that.buttons.import_content.find('.cs-btn-circle-text').text(Customify_Starter_Sites.try_again);
                                }
                            });
                        };

                        $.ajax({ // cs_import__check
                            url: Customify_Starter_Sites.ajax_url,
                            data: {
                                action: 'custstsi_import__check',
                                nonce: Customify_Starter_Sites.ajax_nonce
                            },
                            success: function (res) {
                                cb();
                            },
                            error: function (res) {
                                cb();
                            }
                        });



                    } // end if doing
                } );
            },

            _importing_options: function(){
                var that = this;
                var option_completed = function(){
                    that.step_completed('import_options');
                    $('.cs-import-options-status .circle-loader', that.modal).removeClass('circle-loading').addClass('load-complete');

                    // Clear cache and reset library
                    $.ajax({
                        url: Customify_Starter_Sites.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'elementor_clear_cache',
                            _nonce: Customify_Starter_Sites.elementor_clear_cache_nonce
                        }
                    });

                    $.ajax({
                        url: Customify_Starter_Sites.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'elementor_reset_library',
                            _nonce: Customify_Starter_Sites.elementor_reset_library_nonce
                        }
                    });

                };

                $( '.cs-do-import-options', that.modal ).on( 'click', function( e ){
                    e.preventDefault();
                    if ( ! that.doing ) {
                        that.doing = true;
                        that.disable_button('import_options');
                        that.loading_button('import_options');
                        $('.cs-import-options-status .circle-loader', that.modal).addClass('circle-loading');
                        $.ajax({
                            url: Customify_Starter_Sites.ajax_url,
                            data: {
                                action: 'custstsi_import_options',
                                nonce: Customify_Starter_Sites.ajax_nonce,
                                id: that.json_id,
                                xml_id: that.xml_id
                            },
                            success: function (res) {
                                option_completed();
                            },
                            error: function (res) {
	                            that.doing = false;
	                            that._reset();
	                            that.buttons.import_options.find('.cs-btn-circle-text').text(Customify_Starter_Sites.try_again);
                            }
                        });
                    }
                } );

            }

        };

        return steps;
    };



    var Customify_Site = {
        data: {},
        filter_data: {},
        skip_render_filter: false,
        xhr: null,
        getTemplate: getTemplate,

        load_sites: function ( cb ) {
            var that = this;
            if ( that.xhr ) {
                //kill the request
                that.xhr.abort();
                that.xhr = null;
            }
            $( 'body' ).addClass('loading-content');
            $( '#customify-sites-listing-wrapper' ).hide();
            that.filter_data = that.get_filter_data();
            that.filter_data['_t'] = new Date().getTime();
            that.xhr = $.ajax( {
                url: Customify_Starter_Sites.api_url,
                data: that.filter_data,
                type: 'GET',
	            dataType: 'json',
                success: function( res ){
	                that.data = normalizeApiResponse( res );
	                $( '#customify-sites-filter-count' ).text( that.data.total );
                    that.render_items();
                    if ( ! that.skip_render_filter ) {
                        that.render_categories( );
                        that.render_tags( );
                    }
                    $( 'body' ).removeClass('loading-content');
                    that.view_details();

                    if ( typeof cb === 'function' ) {
	                    cb( that.data );
                    }
	            },
	            error: function() {
	                that.data = normalizeApiResponse( {} );
	                that.render_items();
	                $( 'body' ).removeClass('loading-content');
	            }
            } );
        },

        render_items: function(){
            var that = this;
            var template = that.getTemplate();
            if ( that.data.total <= 0 ) {
                $( '#customify-sites-listing-wrapper' ).hide();
                $( '#customify-sites-no-demos' ).show();
                $( 'body' ).addClass('no-results');
            } else {
                $( '#customify-sites-no-demos' ).hide();
                $( '#customify-sites-listing-wrapper' ).show();
                $( 'body' ).removeClass('no-results');
            }
            $( '#customify-sites-listing .theme' ).remove();

            _.each( that.data.posts, function( item ) {
                var html = template( item );
                var $item_html = $( html );
                $( '#customify-sites-listing' ).append( $item_html );
                var s = new Customify_Modal_Site( $item_html, item );
                s.add_modal();
                modal_sites[ item.slug ] = s;

            } );
        },

        render_categories: function(){
            var that = this;
	        $( '#customify-sites-filter-cat li:not(:first)' ).remove();
            _.each( that.data.categories, function( item ){
	            var $link = $( '<a>', { href: '#' } ).attr( 'data-slug', item.slug ).text( item.name );
	            $( '#customify-sites-filter-cat' ).append( $( '<li>' ).append( $link ) );
            } );
        },

        render_tags: function(){
            var that = this;
	        $( '#customify-sites-filter-tag' ).empty();
            _.each( that.data.tags, function( item ){
	            var $link = $( '<a>', { href: '#' } ).attr( 'data-slug', item.slug ).text( item.name );
	            $( '#customify-sites-filter-tag' ).append( $( '<li>' ).append( $link ) );
            } );
        },


        get_filter_data: function(){
            var that = this;
            var cat = $( '#customify-sites-filter-cat a.current' ).eq(0).attr( 'data-slug' ) || '';
            var tag = $( '#customify-sites-filter-tag a.current' ).eq(0).attr( 'data-slug' ) || '';
            var s = $( '#customify-sites-search-input' ).val();
            if ( cat === 'all' ) {
                cat = '';
            }
            current_page_builder = _.clone( cat );
            if ( ! current_page_builder ) {
                current_page_builder = 'all';
            }
            that.current_builder = current_page_builder;
             return {
                cat: cat,
                tag: tag,
                builder: current_page_builder,
	            s: s
            }
        },

        filter: function(){
            var that = this;
            $( document ).on( 'click', '#customify-sites-filter-cat a', function( e ){
                e.preventDefault();
                if ( ! $( this ).hasClass( 'current' ) ) {
                    $('#customify-sites-filter-cat a').removeClass('current');
                    $('#customify-sites-filter-tag a').removeClass('current');
                    $(this).addClass('current');
                    that.filter_data = {};
                    that.filter_data = that.get_filter_data();

                    that.skip_render_filter = true;
                    that.load_sites();
                }
            } );

            $( document ).on( 'click', '#customify-sites-filter-tag a', function( e ){
                e.preventDefault();
                if ( ! $( this ).hasClass( 'current' ) ) {
                    $('#customify-sites-filter-tag a').removeClass('current');
                    $(this).addClass('current');
                    that.filter_data = that.get_filter_data();
                    that.skip_render_filter = true;
                    that.load_sites();
                }
            } );

            // Search demo
            $( document ).on( 'change keyup', '#customify-sites-search-input', function(){
                $( '#customify-sites-filter-cat a' ).removeClass( 'current' );
                $( '#customify-sites-filter-tag a' ).removeClass( 'current' );
                that.skip_render_filter = true;
                that.filter_data = that.get_filter_data();
                that.load_sites();

            } );

        },

        view_details: function(){
            // Test
            //$( 'body' ).addClass( 'customify-sites-show-modal' );
            /*
            $( document ).on( 'click', '#customify-sites-listing .theme', function( e ){
                e.preventDefault();

            } );

            $( '.cs-modal' ).each( function(){
                var s = new Customify_Modal_Site( $( this ) );
                s.init();
            } );
            */

        },

        init: function(){
            var that = this;
            that.filter_data = {};
            that.load_sites();
            that.filter();

        }
    };


    // Starter-site previews open the public demo in a new browser tab rather
    // than embedding an external site inside the WordPress admin. The Preview
    // links carry a validated https href + target="_blank" in the templates,
    // so no JavaScript is required to open them. This guard only blocks the
    // navigation when a demo happens to have no preview URL.
    $( document ).on( 'click', '.cs-open-preview', function( e ) {
        var href = $( this ).attr( 'href' ) || '';
        if ( ! safeHttpsUrl( href ) ) {
            e.preventDefault();
        }
    } );


    Customify_Site.init();


} );
