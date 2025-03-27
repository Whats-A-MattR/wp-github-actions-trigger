<?php

function trigger_github_action()
{
  $repo = get_option('github_repo');
  $token = get_option('github_token');
  $workflow = get_option('github_workflow_id');

  if (empty($repo) || empty($token) || empty($workflow)) {
    error_log('WP GitHub Build Trigger: Missing required settings');
    error_log("{$repo}, {$token}, {$workflow}");
  } else {
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
    set_github_last_run();
    return $result;
  }
}
