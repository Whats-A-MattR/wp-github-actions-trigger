<?php

function handler_out($result)
{
  if ($result === false) {
    error_log('WP GitHub Build Trigger: Failed to trigger GitHub Action');
    record_invocation('trigger', 'failed', ['error' => 'Failed to trigger GitHub Action']);
  } else {
    error_log('WP GitHub Build Trigger: GitHub Action triggered');
    record_invocation('trigger', 'success');
  }
}

function handler_queued($invoked_by)
{
  error_log('WP GitHub Build Trigger: Queued handler invoked by: ' . $invoked_by);
  if (!can_run()) { // early exit for rate limiting
    error_log('WP GitHub Build Trigger: Rate limit exceeded');
    // add job to queue
    add_to_github_build_queue(time());
  } else {
    $result = trigger_github_action();
    handler_out($result);
    set_github_last_run();
    clear_github_build_queue();

    record_invocation($invoked_by, $result ? 'success' : 'failed');
  }
}

function handler_unqueued()
{
  $result = trigger_github_action();
  handler_out($result);
  record_invocation('manual', $result ? 'success' : 'failed');
}

function queue_processor()
{
  if (can_run()) { // early exit for rate limiting
    $queue = get_github_build_queue();
    if (empty($queue)) { // early exit, queue is empty
      error_log('WP GitHub Build Trigger: Queue is empty');
    } else {
      // we have a queue, process it
      $result = trigger_github_action();
      handler_out($result);
      set_github_last_run();
      clear_github_build_queue();

      record_invocation('queue', $result ? 'success' : 'failed');
    }
  } else {
    error_log('WP GitHub Build Trigger: Rate limit exceeded');
  }
}

function trigger_handler($post_id = null)
{

  $queue = get_option('github_build_queue', []);

  $current_action = current_action();

  if ($post_id) {
    $transient_name = 'github_build_trigger_' . $post_id;
    if (get_transient($transient_name)) {
      $transient_exists = true;
      error_log('WP GitHub Build Trigger: Transient exists for post ID: ' . $post_id);
    } else {
      $transient_exists = false;
    }
  }

  $triggers = get_option('github_build_triggers', []);

  $trigger_match = false;
  if (empty($triggers)) { // no triggers defined, early exit
    error_log('WP GitHub Build Trigger: No triggers defined');
  }

  if (in_array($current_action, $triggers)) $trigger_match = true;

  if ($trigger_match && !$transient_exists) {
    error_log('WP GitHub Build Trigger: Trigger matched');
    handler_queued($current_action);
  } else {
    error_log('WP GitHub Build Trigger: Trigger did not match');
  }

  if ($post_id) {
    set_transient($transient_name, true, 60);
  }
}

function handle_update_published_post($post_id, $post_after, $post_before)
{
  // post is, and was, published
  if ($post_after->post_status === 'publish' && $post_before->post_status === 'published') {
    if ($post_after->post_modified !== $post_before->post_modified) {
      // post is published and updated, perform your actions here
      error_log('Published post updated: ' . $post_id);
      // trigger the GitHub Action
      trigger_handler($post_id);
    }
  }
}
