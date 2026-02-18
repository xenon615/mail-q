<?php
require_once  dirname(__DIR__, 5) . '/wp-load.php';

$options = get_option('_lbs_site_options');

$schedule = !empty($options['buyer_reminder']['schedule']) ? $options['buyer_reminder']['schedule'] : [];
$current_time_g = current_time('timestamp', 1);

$need_update = false;
foreach($schedule as $ise => $se) {
    if (empty($se['time']['value'])) {
        continue;
    }
    if (($se['time']['value'] < $current_time_g) && (_safe_value($se['active']) == 'yes')) {
        $r = (new Mail_Q())->send_next(1, date('Y-m-d H:i:s', $se['time']['value']));
        if ($r) {
            $options['buyer_reminder']['schedule'][$ise]['active']['value'] = 'no';
            $need_update = true;
        }
    }
}

if ($need_update) {
    update_option('_lbs_site_options', $options);
}

