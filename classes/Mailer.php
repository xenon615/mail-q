<?php
namespace MailQ;
class Mailer {
    private $attachments = [];
    private $keepAlive = false;
    private $contentType = 'text/html';
    private $mailer = null;
    private $settings = [];
    private static $_instance;
    // ---

    private function __construct($keepAlive) {
        $this->keepAlive = $keepAlive;
        $this->settings = get_option('mail-q_settings_mailer', []);
    }
    
    // ---

    public static function getInstance($keepAlive = false) {
        if (static::$_instance == null) {
            static::$_instance = new static($keepAlive);
        }
        return static::$_instance;
    }

    // ---

    public function __destruct() {
        if ($this->keepAlive && $this->mailer) {
            $this->mailer->smtpClose();
        }
    }   

    // ---

    public  function send($params) {
        $this->attachments =  !empty($params['attachments']) ? $params['attachments'] : [];
        $this->contentType = !empty($params['is-text']) ? 'text/plain' : 'text/html';
        $params['headers'] = !empty($params['headers']) ? $params['headers'] : [];
        
        
        if (empty($params['to'])) {
            return false;
        }
        if (empty($params['message'])) {
            return false;
        }
        if (($this->settings['Misc']['is_split'] ?? 'split') == 'split') {
            $addressesTo = array_values(array_filter(preg_split('/\s*,\s*/', $params['to']), fn($e) => !empty($e)));
            if ((count($addressesTo) > 1)) {
                $this->keepAlive = true;
            }
            foreach($addressesTo as $addressTo) {
                if (!($result = wp_mail($addressTo, $params['subject'] ?? '', $params['message'], $params['headers']))){
                    break;
                }
            }
        } else {
            $result = wp_mail($params['to'], $params['subject'] ?? '', $params['message'], $params['headers']);
        }
        return $result;
    }

    // ----
    
    public function init($mailer) {
        if( $mailer->ContentType != 'text/plain' ) {
            $mailer->msgHTML($mailer->Body);
        }

        foreach ($this->attachments as $n => $at) {
            if (isset($at['path'])) {
                $mailer->AddAttachment($at['path'], $at['name']);
            } else {
                $mailer->addStringAttachment($at['data'], $at['name']);
            }
        }

        // debug($this->settings, '', 'settings');  
        $mailer->Sender = $mailer->From;
        // if (($mailer->Mailer == 'smtp') && $this->keepAlive) {
        //     if (!$this->mailer) {
        //         $this->mailer = $mailer;
        //     }
        //     $mailer->SMTPKeepAlive = true;
        // }

        if (!empty($this->settings['isSMTP'])) {
            $mailer->isSMTP();
            $mailer->Host = $this->settings['smtp']['Host'];
            $mailer->Port = $this->settings['smtp']['Port'];
            if(!empty($this->settings['smtp']['SMTPAuth'])) {
                $mailer->SMTPAuth = true;
                $mailer->Username =  $this->settings['smtp']['Username'];
                $mailer->Password = $this->settings['smtp']['Password'];
                if (!empty($this->settings['smtp']['isSender'])) {
                    $mailer->Sender = $this->settings['smtp']['Username'];
                    $mailer->From = $this->settings['smtp']['Username'];
                }
            }
            if (!empty($this->settings['smtp']['SMTPSecure'])) {
                $mailer->SMTPSecure = $this->settings['smtp']['SMTPSecure'];    
            }
            $mailer->SMTPAutoTLS = !empty($this->settings['smtp']['SMTPAutoTLS']);

            if ($this->keepAlive) {
                $mailer->SMTPKeepAlive = true;
            }
        }
        if (!empty($this->settings['isDKIM'])) {
            $mailer->DKIM_domain = $this->settings['dkim']['DKIM_domain'];
            $mailer->DKIM_private_string = $this->settings['dkim']['DKIM_private_string'];
            $mailer->DKIM_selector = $this->settings['dkim']['DKIM_selector'];
        }
        
        if (!empty($this->settings['ReplyTo'])) {
            foreach($this->settings['ReplyTo'] as $rt) {
                $mailer->addReplyTo($rt['email'], $rt['name']);     
            }
        }
    }

    // ----

    public  function set_content_type() {
        return $this->contentType;
    }

    // ---

    public function mail_headers($args) {
        $from = [
            'name' => get_bloginfo('name'),
            'email' => get_option('admin_email')
        ];

        if(!empty($this->settings['smtp']['SMTPAuth'])) {
            if (!empty($this->settings['smtp']['isSender'])) {
                $from['email'] = $this->settings['smtp']['Username'];
            }
        }

        $reply_to = $from;
        $from = apply_filters('mail-q_mailer_from', $from);
        $reply_to = apply_filters('mail-q_mailer_reply-to', $reply_to);

        $headers = [
            'From' => '"' . $from['name'] . '" <' . $from['email'] . '>',
            'Reply-To' => '"' . $reply_to['name'] . '" <' . $reply_to['email'] . '>',
            'Errors-To' => '"' . $from['name'] . '" <' . $from['email'] . '>'
        ];

        if (empty($args['headers'])) {
            $args['headers'] = [];
        } else if (!is_array($args['headers'])) {
            $args['headers'] = explode("\n", $args['headers']);
        }
        $args['headers'] = array_filter($args['headers'], fn($e) => !empty($e));
        $headers_plus = [];

        foreach($args['headers'] as $h) {
            [$k, $v] = explode(':', $h);
            $headers_plus[trim($k)] = trim($v);
        }
        $headers = array_merge($headers, $headers_plus);
        $args['headers'] = [];
        foreach($headers as $k => $v) {
            $args['headers'][] = $k . ': '. $v;    
        }
        return $args;
    }

    // ---
}