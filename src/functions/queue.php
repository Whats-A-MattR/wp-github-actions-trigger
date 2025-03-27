<?php

function queue_handler()
{
  if (empty($queue)) { // early exit, queue is empty
    error_log('WP GitHub Build Trigger: Queue is empty');
    return;
  }
}

function can_run()
{
  error_log('WP GitHub Build Trigger: Checking rate limit');
  $rate_limit = get_option('github_build_interval', 3600);
  $last_run = get_option('github_build_trigger_last_run', false);

  error_log("WP GitHub Build Trigger: Last run: $last_run");
  error_log("WP GitHub Build Trigger: Rate Limit: $rate_limit");
  error_log("WP GitHub Build Trigger: Next available run: " . ($last_run + $rate_limit));

  if ($last_run === false) return true;
  if ($last_run + $rate_limit < time()) return true;
  return false;
}

function set_github_last_run()
{
  update_option('github_build_trigger_last_run', time());
}

function clear_gibhub_last_run()
{
  update_option('github_build_trigger_last_run', null);
}

function clear_github_build_queue()
{
  update_option('github_build_queue', array());
}

function get_github_build_queue()
{
  return get_option('github_build_queue', array());
}

function add_to_github_build_queue()
{
  $queue = get_github_build_queue();
  array_push($queue, time());
  update_option('github_build_queue', $queue);
}
