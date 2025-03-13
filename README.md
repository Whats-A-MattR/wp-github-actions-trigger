# wp-github-actions-trigger

## What is it?
A simple WordPress plugin to dispatch GitHub Actions on events in WordPress. Useful for static sites using WordPress as a Headless CMS.

 
## Installation
Steps may vary between hosting providers
* Download latest zip
* Upload to /wp-content/plugins
* Unzip file
* Navigate to http://\<your website>/wp-admin/plugins.php
* Locate WP GitHub Actions Trigger
* Click Activate
* Configure in the settings blade

## Basic Features
This plugin allows for GitHub Actions to be triggered by events in WordPress.
Currently, only Publish and Unpublish are supported.

### Use Personal Access Tokens
Connect to GitHub with a Personal Access Token. Works with Fine Grained Personal Access Tokens so you can scope to your desired repository, and limit permissions as needed.

### Select Repo and Action
Once you have connected to GitHub, you will be able to select the repository and action you would like to run. Currently only one action is supported.

### Triggers
Can be triggered by Publishing or Unpublishing a post. 

### Rate Limiting
Rate limits can help prevent excessive GitHub Actions costs. If there were multiple posts published, or unpublished, rather than invoking the select Action for each instance of change we delay invocation until the rate limit has refreshed.

Once the rate limit has refreshed, there may be N number of updates pending, but this will result in a single invocation and clearing of the 'queue'.

The queue is checked every hour, and the GitHub Action is run if the rate limit has expired. 

A pending invocation can be cleared by visiting the settings page, and clicking Clear Queue

### Force Build
This will bypass the rate limit, allowing you to force the running of your GitHub Action

## Gotchas

The expected behaviour for configuration is this:
* Enter PAT
* Click Connect - success! Repositories are queried using PAT
* GitHub Repository dropdown is now populated with Repos
* Select Repository - using selected item, workflows are queried
* Workflow / Action - is now populated with workflows
* Select Rate Limit or leave as default
* Save Changes

Sometimes, the automatic fetching of workflows fails. To get around this, just save the changes, shortly thereafter the Workflows will be populated. 
