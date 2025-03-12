<?php
/*
Plugin Name: WP GitHub Actions Trigger
Description: Triggers a GitHub Action on defined content changes. Perfect for use cases for when WordPress is used as a headless CMS with a statically generated frontend.
Version: 0.0.3-beta
Author: Matt Russell
*/

require_once plugin_dir_path(__FILE__) . 'wp-github-build-trigger-admin.php';


function trigger_github_action()
{
  $repo = get_option('github_repo');
  $token = get_option('github_token');
  $workflow = get_option('github_workflow_id');
  $triggers = get_option('github_build_triggers');

  $rate_limit = get_option('github_build_interval', 3600);

  $transient_key = 'github_build_trigger_last_run';
  $last_run = get_transient($transient_key);

  if ($last_run === false) {
    $last_run = 0; // handle the case where the transient has expired or doesn't exist
  }

  if (time() - $last_run < $rate_limit) {
    // rate limit exceeded, add to queue
    $queue = get_option('github_build_queue', array());
    $queue[] = time();
    update_option('github_build_queue', $queue);
    error_log('WP GitHub Build Trigger: Rate limit exceeded, adding to queue');
    return;
  }

  set_transient($transient_key, time(), $rate_limit);

  if (empty($repo) || empty($token) || empty($workflow)) {
    error_log('WP GitHub Build Trigger: Missing required settings');
    error_log("{$repo}, {$token}, {$workflow}");
    return;
  }

  $current_action = current_action();

  if (!in_array($current_action, $triggers)) {
    return; // we don't want to trigger the build on this action
  }

  $url = "https://api.github.com/repos/$repo/actions/workflows/$workflow/dispatches";
  error_log("WP GitHub Build Trigger: Triggering GitHub Action: $url");
  $data = array(
    'ref' => 'main'
  );

  $context = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' => [
        "Authorization: Bearer {$token}",
        'Accept: application/vnd.github+json',
        'User-Agent: WP-GitHub-Build-Trigger',
        'Content-Type: application/json',
        'X-GitHub-Api-Version: 2022-11-28'
      ],
      "content" => json_encode($data)
    ]
  ]);

  $result = file_get_contents($url, false, $context);

  if ($result === false) {
    error_log('WP GitHub Build Trigger: Failed to trigger GitHub Action');
    record_invocation('trigger', 'failed', ['error' => 'Failed to trigger GitHub Action']);
  } else {
    error_log('WP GitHub Build Trigger: GitHub Action triggered');
    record_invocation('trigger', 'success');
  }
}

add_action('publish_post', 'trigger_github_action');
add_action('unpublish_post', 'trigger_github_action');


function process_github_trigger_queue()
{
  $queue = get_option('github_build_queue', array());
  $rate_limit = get_option('github_build_interval', 3600);
  $transient_key = 'github_build_trigger_last_run';
  $last_run = get_transient($transient_key);

  if ($last_run === false) {
    $last_run = 0; // handle the case where the transient has expired or doesn't exist
  }

  if (empty($queue)) {
    return; // early exit, queue is empty
  }

  $new_queue = array();
  foreach ($queue as $trigger_time) {
    if (time() - $trigger_time >= $rate_limit && time() - $last_run >= $rate_limit) {
      // rate limit has passed, trigger the build
      set_transient($transient_key, time(), $rate_limit);
      $github_repo = get_option('github_repo');
      $github_token = get_option('github_token');
      $github_workflow_id = get_option('github_workflow_id');

      if (empty($github_repo) || empty($github_token) || empty($github_workflow_id)) {
        error_log('WP GitHub Build Trigger: Missing required settings');
        return;
      }

      $url = "https://api.github.com/repos/$github_repo/actions/workflows/$github_workflow_id/dispatches";
      error_log("WP GitHub Build Trigger: Triggering GitHub Action: $url");
      $data = array(
        'ref' => 'main'
      );

      $context = stream_context_create([
        'http' => [
          'method' => 'POST',
          'header' => [
            "Authorization: Bearer {$github_token}",
            'Accept: application/vnd.github+json',
            'User-Agent: WP-GitHub-Build-Trigger',
            'Content-Type: application/json',
            'X-GitHub-Api-Version: 2022-11-28'
          ],
          "content" => json_encode($data)
        ]
      ]);

      $result = file_get_contents($url, false, $context);

      if ($result === false) {
        record_invocation('queue', 'failed', ['error' => 'Failed to trigger GitHub Action']);
        error_log('WP GitHub Build Trigger: Failed to trigger GitHub Action');
      } else {
        record_invocation('queue', 'success');
        error_log('WP GitHub Build Trigger: GitHub Action triggered');
      }
    } else {
      $new_queue[] = $trigger_time;
    }
  }

  update_option('github_build_queue', $new_queue);
}

add_action('wp_github_trigger_cron', 'process_github_trigger_queue');

if (!wp_next_scheduled(('wp_github_trigger_cron'))) {
  wp_schedule_event(time(), 'hourly', 'wp_github_trigger_cron');
}

function force_github_build()
{
  check_ajax_referer('force_github_build_nonce', 'nonce');

  $github_repo = get_option('github_repo');
  $github_workflow_id = get_option('github_workflow_id');
  $github_token = get_option('github_token');

  if (empty($github_repo) || empty($github_workflow_id) || empty($github_token)) {
    echo 'Configuration missing.';
    wp_die();
  }

  $url = "https://api.github.com/repos/{$github_repo}/actions/workflows/{$github_workflow_id}/dispatches";

  $data = array(
    'ref' => 'main',
  );

  $context = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' => [
        "Authorization: Bearer {$github_token}",
        'Accept: application/vnd.github.v3+json',
        'User-Agent: WP-GitHub-Build-Trigger',
        'Content-Type: application/json'
      ],
      "content" => json_encode($data)
    ]
  ]);

  $result = file_get_contents($url, false, $context);

  if ($result === false) {
    echo 'Failed to trigger GitHub Action.';
    record_invocation('manual', 'failed', ['error' => 'Failed to trigger GitHub Action']);
    error_log('WP GitHub Build Trigger: Failed to trigger GitHub Action');
  } else {
    echo 'GitHub Action triggered successfully.';
    record_invocation('manual', 'success');
    error_log('WP GitHub Build Trigger: GitHub Action triggered');
  }

  wp_die();
}
add_action('wp_ajax_force_github_build', 'force_github_build');

function record_invocation($trigger_type, $status, $details = [])
{
  $history = get_option('github_build_history', []);
  $history[] = [
    'timestamp' => time(),
    'trigger_type' => $trigger_type,
    'status' => $status,
    'details' => $details,
  ];

  if (count($history) > 30) {
    $hsitory = array_slice($history, -30);
  }
  update_option('github_build_history', $history);
}
