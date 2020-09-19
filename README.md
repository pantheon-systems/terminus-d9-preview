# Terminus D9 Preview Plugin

[![CircleCI](https://circleci.com/gh/pantheon-systems/terminus-secrets-plugin.svg?style=shield)](https://circleci.com/gh/pantheon-systems/terminus-secrets-plugin)
[![Terminus v2.x Compatible](https://img.shields.io/badge/terminus-v2.x-green.svg)](https://github.com/pantheon-systems/terminus-secrets-plugin/tree/1.x)

Terminus Plugin that will take a Pantheon site that is using Drupal 8.9.x on the `dev` environment, and will create or re-create a "preview-d9" multidev with the latest Drupal 9 release.

## Prerequisites
Before using this plugin:

- Install the [Upgrade Status](https://drupal.org/project/upgrade_status) module and use it to survey the upgrade-rediness of your site.
- Install the [Terminus Build Tools plugin](https://github.com/pantheon-systems/terminus-build-tools-plugin)

## Installation
For help installing, see [Manage Plugins](https://pantheon.io/docs/terminus/plugins/)
```
mkdir -p ~/.terminus/plugins
composer create-project -d ~/.terminus/plugins pantheon-systems/terminus-d9-preview:^0.1
```

## Configuration

This plugin requires no configuration to use.

## Usage
```
terminus preview:d9
```

Creates a multidev "preview-d9" from the dev environment. The dev environment must be using some version of Drupal 8.9.x.
