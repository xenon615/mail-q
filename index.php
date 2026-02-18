<?php
/*
Plugin Name: Mail-Q
Plugin URI: 
Description: Mail Queue for Wordpress
Version: 0.1
Requires Plugins: form-a, anydata
*/
namespace MailQ;
spl_autoload_register(function ($class) {
    if (strpos($class, __NAMESPACE__) !== false) {
        $filename = __DIR__ . '/classes/' . str_replace([__NAMESPACE__ .'\\', '\\'], ['', '/'], $class) . '.php';
        if(file_exists($filename) ) {
            require_once $filename;
        }  
    } else { //backward compatibility
        if(in_array($class, ['Mailer', 'Mail_Q'])) {
            require_once  __DIR__ . '/classes/' . $class . '.php';
        }
    }

});



register_activation_hook(__FILE__,  function() {
    global $wpdb;
    $sqls = [
        "START TRANSACTION",
        "DROP TABLE IF EXISTS `_wp_mail_queue`",
        "CREATE TABLE `_wp_mail_queue` (
            `id` int(11) NOT NULL,
            `email` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
            `email_hash` char(32) COLLATE utf8_unicode_ci NOT NULL,
            `subject` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
            `message` text COLLATE utf8_unicode_ci NOT NULL,
            `message_hash` char(32) COLLATE utf8_unicode_ci NOT NULL,
            `start` timestamp NULL DEFAULT NULL,
            `attachments` text COLLATE utf8_unicode_ci DEFAULT NULL,
            `error` tinyint(1) DEFAULT 0,
            `error_text` text DEFAULT NULL
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ",
        "DROP TRIGGER IF EXISTS `MQ_AU`;",
        "CREATE TRIGGER `MQ_AU` BEFORE UPDATE ON `_wp_mail_queue` FOR EACH ROW  SET new.email_hash = MD5(LOWER(NEW.email)), new.message_hash = MD5(NEW.message)",
        "DROP TRIGGER IF EXISTS `MQ_BI`",
        "CREATE TRIGGER `MQ_BI` BEFORE INSERT ON `_wp_mail_queue` FOR EACH ROW SET NEW.email_hash = MD5(LOWER(NEW.email)), NEW.message_hash = MD5(NEW.message)",
        "ALTER TABLE `_wp_mail_queue`   ADD PRIMARY KEY (`id`),  ADD UNIQUE KEY `email_message` (`email_hash`,`message_hash`) USING BTREE;",
        "ALTER TABLE `_wp_mail_queue`   MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;"
    ];
    foreach($sqls as $s) {
        $r = $wpdb->query(trim($s));
        if($r === false) {
            $wpdb->query('ROLLBACK');
        }
    }
    $wpdb->query('COMMIT');    
});

// ---

register_deactivation_hook(__FILE__, function () {
    global $wpdb;
    $sql = "DROP TABLE IF EXISTS `_wp_mail_queue`";
    $wpdb->query($sql);
});

// ---

require_once __DIR__ . '/inc/admin.php';
require_once __DIR__ . '/inc/functions.php';

// add_action('phpmailer_init', [Mailer::getInstance(), 'init'], 1000);
// add_filter('wp_mail', [Mailer::getInstance(), 'mail_headers'], 1000);
// add_filter('wp_mail_content_type', [Mailer::getInstance(), 'set_content_type'], 1000);
// add_action('mail-q_push', [Queue::getInstance(), 'push']);

add_action('phpmailer_init', function($mailer) {
    Mailer::getInstance()->init($mailer);
}, 1000);

add_filter('wp_mail', function($args) {
    return Mailer::getInstance()->mail_headers($args);
}, 1000);

add_filter('wp_mail_content_type', function() {
    return Mailer::getInstance()->set_content_type();
}, 1000);

add_filter('cron_schedules', function($schedules) {
    $schedules['15_seconds'] = [
            'interval'  => 15, 
            'display'   => 'Every 15 seconds'
    ];
    return $schedules;
});

add_action('mail-q_push', function() {
    Queue::getInstance()->push();
});

// + temp ------------------------------------------

// add_action('wp_mail_failed', function ($error) {
//     if ($error->errors && is_array($error->errors)) {
//         $error_text = '';
//         foreach($error->errors as $ek => $el) {
//             foreach($el as $e) {
//                 $error_text .= $e . ', ';
//             }
//         }
//         debug($error_text, '', '_wp_mail-error');
//     }
// });

// - temp ------------------------------------------