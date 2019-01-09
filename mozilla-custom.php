<?php
/*
Plugin Name: Mozilla Custom Plugins
Plugin URI: https://blog.mozilla.org
Description: Does custom things that we need
Version: .3
Author: Jeremiah Orem, Craig Cook
*/

    // Our plugin
    define('MOZ_PLUGIN_BASE', __FILE__);

    // Allow changing the version number in only one place (the header above)
    $plugin_data = get_file_data(MOZ_PLUGIN_BASE, array('Version' => 'Version'));
    define('MOZ_PLUGIN_VERSION', $plugin_data['Version']);

    // Setup Moz plugin url (must be in mu-plugins)
    if (is_multisite()) {
        define('MOZ_PLUGIN_URL', network_site_url('/wp-content/mu-plugins/mozilla-custom'));
    } else {
        define('MOZ_PLUGIN_URL', content_url('/mu-plugins/mozilla-custom'));
    }

    // Force HTTPS for some pages
    function moz_ob_handler($buffer) {
        $admin_url = get_option('siteurl') . '/wp-admin';
        $login_url = get_option('siteurl') . '/wp-login.php';
        $comment_url = get_option('siteurl') . '/wp-comments-post.php';
        $secure_admin_url = preg_replace('/^https?/', 'https', $admin_url);
        $secure_login_url = preg_replace('/^https?/', 'https', $login_url);
        $secure_comment_url = preg_replace('/^https?/', 'https', $comment_url);

        $replace_this = array($admin_url, $login_url, $comment_url);
        $with_this = array($secure_admin_url, $secure_login_url, $secure_comment_url);
        if ( is_admin() ) {
            $includes_url = get_option('siteurl') . '/wp-includes';
            $includes_url = preg_replace('/^https?/', 'http', $includes_url);
            $secure_includes_url = preg_replace('/^https?/', 'https', $includes_url);
            $replace_this[] = $includes_url;
            $with_this[] = $secure_includes_url;
        }

        if ((is_preview() && 'on' == $_SERVER['HTTPS'])
                || preg_match('/wp-login.php$/',$_SERVER['SCRIPT_FILENAME']) ) {
            $site_url = get_option('siteurl');
            $site_url = preg_replace('/^https?/', 'http', $site_url);
            $secure_site_url = preg_replace('/^https?/', 'https', $site_url);
            $replace_this[] = $site_url;
            $with_this[] = $secure_site_url;
        }

        return (str_replace($replace_this, $with_this, $buffer));
    }

    function moz_register_ob_handler() {
        ob_start('moz_ob_handler');
    }

    // Use full email for usernames
    function moz_uid_to_email($userid) {
        $details =  get_userdata($userid);
        update_user_status($userid, 'user_login', $details->user_email);
    }
    add_action('wpmu_new_user', 'moz_uid_to_email');

    // Do something with trackback authors
    function moz_trackback_author_switch($commentdata) {
        if ($commentdata['comment_type'] == 'trackback') {
            $matches = array();
            if (preg_match('/^<strong>(.*?)<\/strong>/', $commentdata['comment_content'], $matches)) {
                $commentdata['comment_author'] = $matches[1];
            }
        }

        $commentdata['comment_author_IP'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
        return $commentdata;
    }
    add_filter('preprocess_comment', 'moz_trackback_author_switch');

    // Strip the email domain from author names
    function moz_strip_domain($authorname) {
        return preg_replace('/@.*/','',$authorname);
    }
    add_filter('the_author','moz_strip_domain');
    add_filter('get_comment_author','moz_strip_domain');

    // Remove some post filtering
    function moz_remove_post_filtering() {
        remove_filter('content_save_pre', 'wp_filter_post_kses');
        // remove_filter('excerpt_save_pre', 'wp_filter_post_kses');
        // remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');
    }
    add_action('init', 'moz_remove_post_filtering', 11);

    // Don't cache comments
    function moz_no_cache_comments() {
        if (isset($_COOKIE['comment_author_'.COOKIEHASH]))
            nocache_headers();
    }
    add_action('init', 'moz_no_cache_comments', 12);

    // Add more file types to allowed uploads
    function moz_add_upload_mimes($mimes) {
        $mimes['otf']  = 'application/octet-stream';
        $mimes['webm'] = 'video/webm';
        $mimes['ogv']  = 'video/ogg';
        $mimes['mp4']  = 'video/mp4';
        $mimes['m4v']  = 'video/mp4';
        $mimes['flv']  = 'video/x-flv';
        $mimes['svg']  = 'image/svg+xml';
        return $mimes;
    }
    add_filter('upload_mimes', 'moz_add_upload_mimes');

    // Add XSS-Protection header
    function moz_http_headers() {
        header('X-XSS-Protection: 1; mode=block');
    }
    add_action('init', 'moz_http_headers');

    // Add meta tag for the blog name; ga-snippet.js needs this.
    function moz_meta_blogname() {
        echo '<meta name="blog-name" content="' . esc_attr(get_bloginfo('name')) . '" />' . "\n";
    }
    add_action('wp_head', 'moz_meta_blogname', 1);

    // Loads the blog.mozilla.org Google Analytics snippet in the head
    // of every blog.
    function moz_add_webanalytics() {
        $blog_id = get_current_blog_id();

        // No GA on 258, theglassroomnyc.org. See bug 1309025.
        // No GA on 242, hacks.mozilla.org. Hacks uses their own tracking ID, not the global snippet.
        if ($blog_id != '258' && $blog_id != '242') :
            wp_enqueue_script('ga-snippet', MOZ_PLUGIN_URL . '/ga-snippet.js', '', MOZ_PLUGIN_VERSION);
        endif;
    }
    add_action('wp_enqueue_scripts', 'moz_add_webanalytics');
?>
