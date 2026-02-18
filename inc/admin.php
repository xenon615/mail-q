<?php
namespace MailQ;
add_action('admin_menu' , function() {
    add_submenu_page('options-general.php', 'Mailer', 'Mailer','manage_options', 'mail-q-settings', __NAMESPACE__ . '\settings');
    // add_submenu_page('tools.php', 'Mail Queue', 'Mail Queue','manage_options', 'mail-q-manage', __NAMESPACE__ .'\manage');
    add_submenu_page('tools.php', 'Mail Tester', 'Mail Tester','manage_options', 'mail-q-tester', __NAMESPACE__ . '\tester');
    if (function_exists('\AnyData\bootstrap')) {
        \AnyData\bootstrap([
            'what' => 'grid',
            'parent' => 'tools.php',
            'menu_label' => 'Mail Queue',
            'page_title' => 'Mail Queue',
            'capability' => 'manage_options',
            'slug' => 'mail-q-grid',
            'class' => '\MailQ\Grid' 
        ]);
    }

    admin_submenu_page([
        'parent' => 'mail-q-grid',
        'menu_label' => 'Queue Element',
        'page_title' => 'Queue Element',
        'capability' => 'manage_options',
        'slug' => 'mail-q-form',
        'callback' => function($h) {
            $slug = 'mail-q_queue_details';
            add_filter('form-a_need-a-form', function($forms) use ($slug) {
                return [$slug  => ['remoteLoad' => true]];
            });
            add_action($h, function() {
?>
                <div>
                    <form method="POST">
                        <div id="mail-q_queue_details" class="form-a-placeholder"></div>
                    </form>
                </div>
                <?php                
            });
        
        }
    ]);

});

// ---

add_filter('form-a_need-a-form', function($forms) {
    $screen = get_current_screen();
    if ($screen->id == 'settings_page_mail-q-settings') {
        $forms = [
            'mail-q_settings_mailer' => ['remoteLoad' => true],
        ];
    } else if ($screen->id == 'tools_page_mail-q-tester') {
        $forms = [
            'mail-q_settings_mailer_test' => ['remoteLoad' => true],
        ];

    }
    return $forms;
});

// ---

function dashboard() {
    echo 'Coming soon';
}

// ---

function unsat_deps() {
    if (file_exists(WPMU_PLUGIN_DIR . '/form-a.php') || is_plugin_active('form-a/index.php')) {
        return '';
    } else {
        return 'Plugin "FormA" is required!';
    }

}

// ---

function settings() {
    if (unsat_deps()) {
        return;
    }
?>        
    <div style="width:50%; padding: 20px;">
        <form method="POST">
            <div id="mail-q_settings_mailer" class="form-a-placeholder"></div>
        </form>
    </div>
<?php        
}

// ---

function tester() {
    if (unsat_deps()) {
        return;
    }
?>        
    <div style="width:50%; padding: 20px;">
        <form method="POST">
            <div id="mail-q_settings_mailer_test" class="form-a-placeholder"></div>
        </form>
    </div>
<?php        
}

// ---

add_filter('form-a_form_load', function($form, $formSlug) {
    if ($formSlug == 'mail-q_settings_mailer') {
        $form = [
            'def' => [
                'title' => 'Mailer Settings',
                'remoteSubmit' => true,
                'buttons' => [
                    [
                        'text' => 'Update Settings',
                        'classes' => ['button', 'button-primary','button-large'],
                        'type' => 'submit'
                    ]   
                ],    
                'fields' => getFields($formSlug)
            ],
            'data' => get_option('mail-q_settings_mailer', [])
        ];
    } else if ($formSlug == 'mail-q_settings_mailer_test') {
        $form = [
            'def' => [
                'title' => 'Mailer Test',
                'remoteSubmit' => true,
                'buttons' => [
                    [
                        'text' => 'Send Test Message',
                        'classes' => ['button', 'button-primary','button-large'],
                        'type' => 'submit'
                    ]   
                ],    
                'fields' => getFields($formSlug)
            ],
            'data' => []
        ];

    } else if ($formSlug == 'mail-q_queue_details') {
        parse_str(parse_url(wp_get_referer())['query'], $f);
        $id  =  $f['id'] ?? 0;
        $data = (new Data())->get($id);
        $form = [
            'def' => [
                'title' => 'Queue Element',
                'remoteSubmit' => true,
                'buttons' => [
                    [
                        'text' => 'Close',
                        'classes' => ['button', 'button-primary','button-large'],
                        'type' => 'submit',
                        'action' => 'close'
                    ],
                    [
                        'text' => 'Update',
                        'classes' => ['button', 'button-primary','button-large'],
                        'type' => 'submit',
                        'action' => 'update'
                    ]

                ],   
                'fields' => [
                    [
                        'type' => 'text',
                        'name' => 'email',
                        'label' => 'Email',
                        'classes' => ['col-6'],
                        'breakAfter' => true
                    ],
                    [
                        'type' => 'text',
                        'name' => 'subject',
                        'label' => 'Subject',
                        'classes' => ['col-6'],
                        'breakAfter' => true
                    ],

                    [
                        'type' => 'html',
                        'name' => 'message',
                        'label' => 'Message'
                    ],

                ]
            ],
            'data' => $data
        ];

    }
    return $form;
}, 10, 2);

// ---

add_filter('form-a_submit', function($result, $data, $slug) {
    if ($slug == 'mail-q_settings_mailer') {
        if (!current_user_can('manage_options')) {
            throw new \Exception('you`re not allowed');
        }
        $result['message'] = 'Settings saved successfully!';
        update_option('mail-q_settings_mailer', $data);
    } else if ($slug == 'mail-q_settings_mailer_test') {
        if (!empty($data['use_queue'])) {
            $data['email'] = $data['to'];  // me is  dumb and lazy ass :)
            unset($data['to']);
            $mq = getMailQ();
            $mq->add($data);
            $mq->run();
            $result['message'] = 'Email queued';
        } else {
            \getMailer()->send($data);
            $result['message'] = 'Email sent';
        }
        
        
    } else if ($slug == 'mail-q_pusher') {
        $mq = getMailQ();
        switch($data['action']) {
            // case 'next':
            //     $result['payload'] = ['count' => $mq->send_next($data['count'] ?? 0)];
            //     break;
            // case 'clear':
            //     $mq->clear();
            //     $result['redirect'] = true;
            //     break;
            // case 'clear_errors':
            //     $result['payload'] = ['count' => $mq->clear_errors()];
            //     break;
            // case 'export_errors':
            //     $result['payload'] = ['file' => $mq->export_errors(), 'filename' => 'errors'];
            //     break;
            case 'run':
                $mq->run();
                break;
            case 'stop':
                $mq->stop();
                break;
    
        }
    } else if ($slug == 'mail-q_queue_details') {
        $action = $data['__action'] ?? 'close';
        if ($action == 'close') {
            $result['redirectURL'] = admin_url('admin.php?page=mail-q-grid');
        } else {
            parse_str(parse_url(wp_get_referer())['query'], $f);
            $id  =  $f['id'] ?? 0;
            unset($data['__action'], $data['message']);
            (new Data())->update($data, $id);
        }
    }
    return $result;
}, 10, 3);

// ---

add_action( 'admin_notices', function() {
    if (in_array(get_current_screen()->id , ['settings_page_mail-q-settings', 'tools_page_mail-q-tester'])) {
        if ($m = unsat_deps()) {
?>        
        <div class="error notice">
            <p><?= $m; ?></p>
        </div>
<?php
        }        
    }
});

// ---

add_filter('parent_file', function($parent_file) {
    $cs = get_current_screen();
	if (in_array($cs->id, ['admin_page_mail-q-grid', 'admin_page_mail-q-form'])) {
		$parent_file = 'tools.php';	
	}
	return $parent_file;
});

// ---

function getFields($formSlug) {
    if ($formSlug == 'mail-q_settings_mailer_test') {
        return [
            [
                'name' => 'to',
                'label' => 'Email',
                'type' => 'text',
                'classes' => ['col-5'],
                'breakAfter' => true
            ],
            [
                'name' => 'subject',
                'label' => 'Subject',
                'type' => 'text',
                'classes' => ['col-5'],
                'breakAfter' => true
            ],
            [
                'name' => 'message',
                'label' => 'Message',
                'type' => 'textarea',
                'classes' => ['col-18'],
            ],
            [
                'name' => 'use_queue',
                'label' => 'Use queue',
                'type' => 'true-false',
                'classes' => ['col-18'],
            ],

        ];

    } else if ($formSlug == 'mail-q_settings_mailer') {
        return [
            [
                'name' => 'isSMTP',
                'label' => 'Use SMTP ?',
                'type' => 'true-false',
                'classes' => ['col-18'],
            ],
            [
                'name' => 'smtp',
                'label' => 'SMTP Settings',
                'type' => 'group',
                'classes' => ['col-18'],
                'cLogic' => [
                    [
                        'path' => 'isSMTP',
                        'value' => true,
                        'compare' => '==',
                        'relation' => 'or'
                    ],
                ],
                'fields' => [
                    [
                        'name' => 'Host',
                        'label' => 'Host',
                        'type' => 'text',
                        'classes' => ['col-9']
                    ],
                    [
                        'name' => 'Port',
                        'label' => 'Port',
                        'type' => 'text',
                        'classes' => ['col-9']
                    ],

                    [
                        'name' => 'SMTPAuth',
                        'label' => 'SMTP Auth',
                        'type' => 'true-false',
                        'classes' => ['col-18'],
                        'default' => false
                    ],

                    [
                        'name' => 'Username',
                        'label' => 'User',
                        'type' => 'text',
                        'classes' => ['col-9'],
                        'cLogic' => [
                            [
                                'path' => 'smtp--SMTPAuth',
                                'value' => true,
                                'compare' => '==',
                                'relation' => 'or'
                            ],
                        ],
                    ],
                    [
                        'name' => 'Password',
                        'label' => 'Password',
                        'type' => 'password',
                        'classes' => ['col-9'],
                        'cLogic' => [
                            [
                                'path' => 'smtp--SMTPAuth',
                                'value' => true,
                                'compare' => '==',
                                'relation' => 'or'
                            ],
                        ],
                    ],
                    [
                        'name' => 'isSender',
                        'label' => 'is Sender',
                        'type' => 'true-false',
                        'classes' => ['col-9'],
                        'breakAfter' => true,
                        'cLogic' => [
                            [
                                'path' => 'smtp--SMTPAuth',
                                'value' => true,
                                'compare' => '==',
                                'relation' => 'or'
                            ],
                        ],
                    ],


                    [
                        'name' => 'SMTPSecure',
                        'label' => 'SMTP Secure',
                        'type' => 'select',
                        'classes' => ['col-9'],
                        'options' => [
                            ['value' => '', 'label' => 'None'],
                            ['value' => 'ssl', 'label' => 'SSL'],
                            ['value' => 'tls', 'label' => 'TLS'],
                        ],
                        'breakAfter' => true
                    ],

                    [
                        'name' => 'SMTPAutoTLS',
                        'label' => 'SMTP Auto TLS',
                        'type' => 'true-false',
                        'classes' => ['col-9']
                    ],
                        
                ]
            ],
            [
                'name' => 'isDKIM',
                'label' => 'Use DKIM ?',
                'type' => 'true-false',
                'classes' => ['col-18'],
            ],
            
            [
                'name' => 'dkim',
                'type' => 'group',
                'label' => 'DKIM',
                'classes' => ['col-18'],
                'cLogic' => [
                    [
                        'path' => 'isDKIM',
                        'value' => true,
                        'compare' => '==',
                        'relation' => 'or'
                    ],
                ],
                'fields' => [
                    [
                        'name' => 'DKIM_domain',
                        'label' => 'DKIM domain',
                        'type' => 'text',
                        'classes' => ['col-9'],
                    ],
                    [
                        'name' => 'DKIM_selector',
                        'label' => 'DKIM selector',
                        'type' => 'text',
                        'classes' => ['col-9'],
                    ],
                    [
                        'name' => 'DKIM_private_string',
                        'label' => 'DKIM Private Key',
                        'type' => 'textarea',
                        'classes' => ['col-18'],
                    ],
            
                ]
            ],
            [
                'name' => 'ReplyTo',
                'label' => 'Reply To',
                'type' => 'repeater',
                'classes' => ['col-18'],
                'fields' => [
                    [
                        'name' =>'email',
                        'type' => 'email',
                        'label' => 'Email',
                        'classes' => ['col-9']
                    ], 
                    [
                        'name' =>'name',
                        'type' => 'text',
                        'label' => 'Name',
                        'classes' => ['col-9']
                    ]
                ]
            ],
            [
                'name' => 'Misc',
                'label' => 'Misc',
                'type' => 'group',
                'classes' => ['col-18'],
                'fields' => [
                    [
                        'name' => 'is_split',
                        'label' => 'In yhe case of multiple emails separated by comma',
                        'type' => 'radio',
                        'options' => [
                            ['value' => 'split', 'label' => 'Send multiple separate'],
                            ['value' => 'not_split', 'label' => 'Semd once']
                        ],
                        'classes' => ['col-18', 'horizontal'],
                        'default' => 'split',
                        
                    ]

                ]
            ]            
        ];
    }
}
