<?php

function wp_github_build_trigger_menu()
{
  add_options_page(
    'GitHub Actions Integration',
    'GitHub Actions Trigger',
    'manage_options',
    'wp-github-build-trigger',
    'wp_github_build_trigger_settings_page'
  );
  add_action('admin_enqueue_scripts', 'wp_github_build_trigger_admin_enqueue_styles');
}
add_action('admin_menu', 'wp_github_build_trigger_menu');

function wp_github_build_trigger_admin_enqueue_styles($hook)
{
  if ('settings_page_wp-github-build-trigger' != $hook) {
    return;
  }
  wp_enqueue_style('wp-github-build-trigger-admin', plugin_dir_url(__FILE__) . 'wp-github-build-trigger-admin.css');
}

function wp_github_build_trigger_settings_page()
{
  if (isset($_POST['submit'])) {
    // token
    $github_token = sanitize_text_field($_POST['github_token']);
    update_option('github_token', $github_token);

    // repo
    $github_repo = sanitize_text_field($_POST['github_repo']);
    update_option('github_repo', $github_repo);

    // workflow file
    $github_workflow_id = sanitize_text_field($_POST['github_workflow_id']);
    update_option('github_workflow_id', $github_workflow_id);

    // build triggers
    $github_build_triggers = isset($_POST['github_build_triggers']) ? array_map('sanitize_text_field', $_POST['github_build_triggers']) : [];
    update_option(('github_build_triggers'), $github_build_triggers);

    $github_build_interval = intval($_POST['github_build_interval']);
    update_option('github_build_interval', $github_build_interval);

    // show success message on admin page
    echo '<div class="updated"><p>Settings saved.</p></div>';
  }

  if (isset($_POST['cancel_queue'])) {
    update_option('github_build_queue', []);
    $queue = get_option('github_build_queue', []);
    echo '<div class="updated"><p>Queue cleared.</p></div>';
  }

  $queue = get_option('github_build_queue', []);
  if (!empty($queue)) {
    echo '<div class="error"><p>Rate limit exceeded, a build will be triggered at next selected interval.</p></div>';
  }

  $github_repo = get_option('github_repo');
  $github_workflow_id = get_option('github_workflow_id');
  $github_token = get_option('github_token');
  $github_build_triggers = get_option('github_build_triggers', []);
  $github_build_interval = get_option('github_build_interval', 3600);

  $history = get_option('github_build_history', []);

  usort($history, function ($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
  });

  $repos = [];
  $workflows = [];

  if (!empty($github_token) && empty($repos)) {
    $repos = fetch_github_repos($github_token);
    if (!empty($repos)) {
      $workflows = fetch_github_workflows($github_token, $github_repo);
    }
  }

?>
  <div class="wrap">
    <h2>GitHub Actions Trigger Settings</h2>
    <p>To use this plugin, you need to create a GitHub Personal Access Token (PAT) with the <code>repo:repo_deployment</code> scope.</p>
    <p>Instructions to create a PAT:</p>
    <ol>
      <li>Go to your GitHub <a href="https://github.com/settings/tokens/new?scopes=repo:repo_deployment" target="_blank">Personal Access Tokens</a> page.</li>
      <li>Click "Generate new token".</li>
      <li>Give your token a descriptive name.</li>
      <li>Select the <code>repo:repo_deployment</code> scope.</li>
      <li>Click "Generate token".</li>
      <li>Copy the generated token and paste it into the field below.</li>
    </ol>
    <form method="post">
      <table class="form-table">
        <tr valign="top">
          <th scope="row">GitHub Personal Access Token (PAT)</th>
          <td>
            <input type="password" id="github_token" name="github_token" value="<?php echo esc_attr($github_token); ?>" />
            <button type="button" id="connect_github">Connect</button>
            <span id="connect_status"></span>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row">GitHub Repository</th>
          <td>
            <select id="github_repo" name="github_repo">
              <option value="">Select Repository</option>
              <?php foreach ($repos as $repo) : ?>
                <option value="<?php echo esc_attr($repo['full_name']); ?>" <?php selected($github_repo, $repo['full_name']); ?>><?php echo esc_html($repo['full_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row">Workflow / Action</th>
          <td>
            <select id="github_workflow_id" name="github_workflow_id">
              <option value="">Select Workflow</option>
              <?php foreach ($workflows as $workflow) : ?>
                <option value="<?php echo esc_attr($workflow['id']); ?>" <?php selected($github_workflow_id, $workflow['id']); ?>><?php echo esc_html($workflow['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
        <tr>
          <th scope="row">Triggers</th>
          <td>
            <label><input type="checkbox" name="github_build_triggers[]" value="publish_post" <?php checked(in_array('publish_post', $github_build_triggers)); ?>>&nbsp;Publish Post</label><br>
            <label><input type="checkbox" name="github_build_triggers[]" value="edit_published_post" <?php checked(in_array('edit_published_post', $github_build_triggers)); ?>>&nbsp;Edit Published Post</label><br>
            <label><input type="checkbox" name="github_build_triggers[]" value="unpublish_post" <?php checked(in_array('unpublish_post', $github_build_triggers)); ?>>&nbsp;Unpublish Post</label><br>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row">Rate Limiting</th>
          <td>
            Limit GitHub Action triggers to once every
            <select id="github_build_interval" name="github_build_interval">
              <option value="3600" <?php selected($github_build_interval, 3600); ?>>1 Hour</option>
              <option value="7200" <?php selected($github_build_interval, 7200); ?>>2 Hours</option>
              <option value="14400" <?php selected($github_build_interval, 14400); ?>>4 Hours</option>
              <option value="21600" <?php selected($github_build_interval, 21600); ?>>6 Hours</option>
              <option value="43200" <?php selected($github_build_interval, 43200); ?>>12 Hours</option>
              <option value="86400" <?php selected($github_build_interval, 86400); ?>>1 Day</option>
              <option value="172800" <?php selected($github_build_interval, 172800); ?>>2 Days</option>
              <option value="432000" <?php selected($github_build_interval, 432000); ?>>5 Days</option>
            </select>
          </td>
        </tr>
      </table>
      <p class="submit">
        <input type="submit" name="submit" class="button-primary" value="Save Changes" />
      </p>
    </form>
    <div>

    </div>

    <div class="">
      <div class="">
        <div class="">
          <h3>Trigger History</h3>
          <div style="margin-bottom: 1em; gap: 0.8em; display: flex; align-items: center;">
            <button id="force-build-button" class="button-primary">Force Build</button>
            <span id="force-build-message"></span>
            <form method="post">
              <div style="display: flex; align-items: center;">
                <input type="submit" name="cancel_queue" class="button-secondary" value="Clear Queue" <?php echo empty($queue) ? 'disabled' : '' ?> />
                <div>
                  &nbsp;
                  <?php echo count($queue) ?> updates pending due to rate limiting.
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
      <div class="">
        <?php if (empty($history)) : ?>
          <p>No trigger history.</p>
        <?php else : ?>
          <table class="wp-list-table widefat fixed striped">
            <thead>
              <tr>
                <th>Timestamp</th>
                <th>Trigger Type</th>
                <th>Status</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $entry) : ?>
                <tr>
                  <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $entry['timestamp']); ?></td>
                  <td><?php echo esc_html($entry['trigger_type']); ?></td>
                  <td><?php echo esc_html($entry['status']); ?></td>
                  <td><?php echo esc_html(json_encode($entry['details'])); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <p>Max 30 items kept in history</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const connectButton = document.getElementById('connect_github');
      const tokenInput = document.getElementById('github_token');
      const repoSelect = document.getElementById('github_repo');
      const workflowSelect = document.getElementById('github_workflow_file');
      const connectStatus = document.getElementById('connect_status');

      connectButton.addEventListener('click', function() {
        const token = tokenInput.value;
        connectStatus.textContent = "Connecting...";

        const url = '<?php echo admin_url('admin-ajax.php'); ?>';

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'action=fetch_github_repos&token=' + encodeURIComponent(token)
          })
          .then(response => response.json())
          .then(({
            success,
            data
          }) => {
            console.log(data);
            console.log(data.success);
            console.log('repos ', data.repos);
            if (success == true && data.repos.length > 0 && data.repos instanceof Array) {
              connectStatus.textContent = "Connected!";
              repoSelect.innerHTML = '<option value="">Select Repository</option>';
              data.repos.map(repo => {
                const option = document.createElement('option');
                option.value = repo.full_name;
                option.textContent = repo.full_name;
                repoSelect.appendChild(option);
              });
            } else {
              connectStatus.textContent = "Connection failed: " + data.message;
            }
          })
          .catch(error => {
            connectStatus.textContent = "Connection error: " + error.message;
          });
      });

      repoSelect.addEventListener('change', function() {
        const token = tokenInput.value;
        const repo = repoSelect.value;
        workflowSelect.innerHTML = '<option value="">Loading workflows...</option>';
        const url = '<?php echo admin_url('admin-ajax.php'); ?>'
        console.log(url)

        fetch(url, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'action=fetch_github_workflows&token=' + encodeURIComponent(token) + '&repo=' + encodeURIComponent(repo)
          })
          .then(response => response.json())
          .then(({
            data,
            success
          }) => {
            console.log(data)
            if (success) {
              workflowSelect.innerHTML = '<option value="">Select Workflow</option>';
              data.workflows.forEach(workflow => {
                const option = document.createElement('option');
                option.value = workflow.path;
                option.textContent = workflow.name;
                workflowSelect.appendChild(option);
              });
            } else {
              workflowSelect.innerHTML = '<option value="">Failed to load workflows</option>';
            }
          })
          .catch(error => {
            console.log(error);
            workflowSelect.innerHTML = '<option value="">Error loading workflows</option>';
          });
      });

      document.getElementById('force-build-button').addEventListener('click', function() {
        fetch(ajaxurl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=force_github_build&nonce=<?php echo wp_create_nonce('force_github_build_nonce'); ?>'
          })
          .then(response => response.text())
          .then(data => {
            document.getElementById('force-build-message').textContent = data;
            window.location.reload();
          })
          .catch(error => {
            document.getElementById('force-build-message').textContent = 'Error: ' + error;
            window.location.reload();
          });
      });
    });
  </script>
<?php
}

function fetch_github_repos_ajax()
{
  $token = sanitize_text_field($_POST['token']);
  $repos = fetch_github_repos($token);

  if (empty($repos)) {
    wp_send_json_error(['message' => 'Failed to fetch repositories.']);
  }

  wp_send_json_success(['repos' => $repos]);
}
add_action('wp_ajax_fetch_github_repos', 'fetch_github_repos_ajax');

function fetch_github_workflows_ajax()
{
  $token = sanitize_text_field($_POST['token']);
  $repo = sanitize_text_field($_POST['repo']);
  $workflows = fetch_github_workflows($token, $repo);

  if (empty($workflows)) {
    wp_send_json_error(['message' => 'Failed to fetch workflows.']);
  }

  wp_send_json_success(['workflows' => $workflows]);
}
add_action('wp_ajax_fetch_github_workflows', 'fetch_github_workflows_ajax');

function fetch_github_repos($token)
{
  $url = 'https://api.github.com/user/repos';
  $response = wp_remote_get($url, [
    'headers' => [
      'Authorization' => 'Bearer ' . $token,
      'User-Agent' => 'WordPress Plugin',
    ],
  ]);

  if (is_wp_error($response)) {
    error_log('Failed to fetch GitHub repos: ' . $response->get_error_message());
    return [];
  }

  $body = wp_remote_retrieve_body($response);
  $repos = json_decode($body, true);

  if (!is_array($repos)) {
    error_log('Invalid GitHub repos response.');
    return [];
  }

  return $repos;
}

function fetch_github_workflows($token, $repo)
{
  $repo_parts = explode('/', $repo); // Split the repository name
  if (count($repo_parts) !== 2) {
    error_log('Invalid repository format: ' . $repo);
    return [];
  }

  $owner = $repo_parts[0];
  $repo_name = $repo_parts[1];

  $url = 'https://api.github.com/repos/' . $owner . '/' . $repo_name . '/actions/workflows'; // Corrected URL
  error_log('Fetching GitHub workflows: ' . $url);

  $response = wp_remote_get($url, [
    'headers' => [
      'Authorization' => 'Bearer ' . $token,
      'User-Agent' => 'WordPress Plugin',
    ],
  ]);

  if (is_wp_error($response)) {
    error_log('Failed to fetch GitHub workflows: ' . $response->get_error_message() . ' URL: ' . $url);
    return [];
  }

  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, true); // Changed variable name to $data

  error_log('GitHub workflows response: ' . print_r($data, true)); // Log the entire response

  if (!is_array($data) || !isset($data['workflows'])) {
    error_log('Invalid GitHub workflows response.');
    return [];
  }

  $workflows = []; // Changed to store workflow data
  foreach ($data['workflows'] as $workflow) {
    $workflows[] = [
      'id' => $workflow['id'],
      'name' => $workflow['name'],
    ];
  }

  return $workflows; // Return the modified workflows array
}
?>