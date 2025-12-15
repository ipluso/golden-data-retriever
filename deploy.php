<?php
namespace Deployer;

require 'recipe/laravel.php';

// Project name
set('application', 'Golden Data Retriever');

set('keep_releases', 3);

set('repository', 'git@github.com:ipluso/fom-golden-data-retriever.git');

// sudo
set('http_user', '5765814');
//set('writable_mode', 'chown');
//set('writable_use_sudo', true);

add('shared_files', [
    '.env'
]);
add('shared_dirs', [
    'storage',
]);
// Writable dirs by web server
add('writable_dirs', [
    'bootstrap/cache',
    'storage',
    'storage/app',
    'storage/app/public',
    'storage/logs'
]);

host('dev')
    ->set('config_file', '~/.ssh/config')
    ->set('hostname', 'www488.your-server.de')
    ->set('port', '222' )
    ->set('branch', 'main')
    ->set('remote_user', 'ipluso')
    ->set('deploy_path', '/usr/home/ipluso/public_html/sites/golden-data-retriever')
    ->set('labels', ['stage' => 'dev']);

// Hooks
after('deploy:failed', 'deploy:unlock');
