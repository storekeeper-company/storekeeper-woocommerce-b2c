<?php
/*
Plugin Name: Disable Canonical URL Redirection
Description: Disables the "Canonical URL Redirect"
Version: 1.0
*/
remove_filter('template_redirect', 'redirect_canonical');
