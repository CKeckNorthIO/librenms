<?php

$name = 'hv-monitor';
$unit_text = 'Errors/Second';
$colours = 'psychedelic';
$dostack = 0;
$printtotal = 0;
$addarea = 0;
$transparency = 15;

if (isset($vars['vmif'])) {
    $rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'vmif', $vars['vm'], '__-__', $vars['vmif']]);
} elseif (isset($vars['vm'])) {
    $rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'vm', $vars['vm']]);
} else {
    $rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id]);
}

$rrd_list = [
    [
        'filename' => $rrd_filename,
        'descr' => 'In',
        'ds' => 'ierrs',
    ],
    [
        'filename' => $rrd_filename,
        'descr' => 'Out',
        'ds' => 'oerrs',
        'invert' => true,
    ],
];

require 'includes/html/graphs/generic_multi_line.inc.php';
