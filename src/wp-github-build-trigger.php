<?php
/*
Plugin Name: WP GitHub Actions Trigger
Description: Triggers a GitHub Action on defined content changes. Perfect for use cases for when WordPress is used as a headless CMS with a statically generated frontend.
Version: 0.0.5-beta
Author: Matt Russell
*/

require_once plugin_dir_path(__FILE__) . 'wp-github-build-trigger-admin.php';
require_once plugin_dir_path(__FILE__) . '/functions/record.php';
require_once plugin_dir_path(__FILE__) . '/functions/action.php';
require_once plugin_dir_path(__FILE__) . '/functions/queue.php';
require_once plugin_dir_path(__FILE__) . '/functions/handlers.php';


if (!wp_next_scheduled(('wp_github_trigger_cron'))) {
  wp_schedule_event(time(), 'hourly', 'wp_github_trigger_cron');
}

add_action('publish_post', 'trigger_handler');
add_action('unpublish_post', 'trigger_handler');
add_action('wp_github_trigger_cron', 'queue_processor');
add_action('wp_ajax_force_github_build', 'handler_unqueued');
add_action('post_updated', 'handle_update_published_post', 10, 3);
