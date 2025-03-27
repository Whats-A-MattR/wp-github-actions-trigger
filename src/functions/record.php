<?php

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
