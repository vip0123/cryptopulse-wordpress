<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
delete_option('cryptopulse_api_key');
delete_option('cryptopulse_base_url');
