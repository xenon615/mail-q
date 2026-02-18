<?php
namespace MailQ;
class Queue {
    private $is_runnimg = false;
    private static $_instance;
    private $mailer;
    private $db;

    // ---

    public static function getInstance() {
        if (static::$_instance == null) {
            static::$_instance = new static();
        }
        return static::$_instance;
    }

    // ---

    public  function __construct() {
        // $this->mailer = getMailer(true);
        $this->mailer = getMailer(true);
        $this->db = new Data();
        add_action('wp_mail_failed', [$this, 'set_error_text']); 
    }

    // ---
    
    public function clear() {
        $this->db->clear();
    }

    // ---

    public function clear_errors() {
        return $this->db->clear_errors();
    }

    // ---

    public function export_errors() {
        $errors = $this->db->get_errors();
        $output = '';
        if (!empty($errors)) {
            foreach($errors as $error) {
                $output .= '"' . $error->email . '", "' . $error->error_text . '"' . PHP_EOL;
            }
        }
        return base64_encode($output);
    }

    // --- 

    private function del($id) {
        $this->db->delete($id);
    } 

    // ---
    
    public function add($params) {
        $addresses = preg_split('/\s*,\s*/', trim($params['email']));
        $q = [
            'message' => $params['message'],
            'subject' => $params['subject'],
        ];

        if (!empty($params['attachments'])) {
            array_walk($params['attachments'], function(&$e) {
                if (isset($e['data'])) {
                    $e['data'] = base64_encode($e['data']);
                }
            });
            $q['attachments'] = serialize($params['attachments']);
        }
        
        foreach($addresses as $email) {
            $q['email']  = $email;
            $this->db->insert($q);
            

            // $sql = sprintf("select count(*) from {$this->q_table} where email_hash = '%s' and message_hash='%s' ", md5($email), md5($q['message']));
            // debug($sql, '', 'sql');
            // if ($this->dbh->get_var($sql) == 0) {
            //     $this->dbh->insert($this->q_table, $q);
            // } 
        }
    }

    // ---
    
    private function get() {
        return $this->db->get_any();
    }

    // ---

    public function set_error_text($error) {
        $id = $this->sent_id; 
        if ($error->errors && is_array($error->errors)) {
            $error_text = '';
            foreach($error->errors as $ek => $el) {
                foreach($el as $e) {
                    $error_text .= $e . ', ';
                }
            }
            $error_text = substr($error_text, 0, -2);
            $this->db->update(['error_text' => $error_text], $id);
        }
    }

    // ---

    private function set_error($id) {
        $this->db->update(['error' => 1], $id);
    }

    // ---
    
    public function q_count() {
        return $this->db->get_count();
    }

    // ---

    public function send_next($count = 0) {
        $this->is_runnimg = true;
        if ($count == 0) {
            $count = PHP_INT_MAX;
        }
        $start = time();
        $maxtime = ini_get('max_execution_time');

        $headers = ['List-Unsubscribe: <mailto:' . preg_replace('/.+?(?=\@)/', 'unsubscribe', get_option('admin_email')) . '>, <' . site_url('/unsubscribe/'). '>']; 
        $unsubscribe = '<br><p style="font-size:0.9em;"><i> If you don`t want to receive these emails from ' . get_bloginfo('name') .
             ' in the future, please <a href="' . site_url('/unsubscribe/?email=%s') . '"> unsubscribe</a></i></p><br><br>';
        $sent = 0;
        while ($sent < $count) {
            $m = $this->get();
            if (empty($m)) {
                break;
            }

            $this->sent_id = $m['id'];
            $e = [
                'message' =>  $m['message'] . sprintf($unsubscribe, $m['email']),
                'to' => $m['email'],
                'subject' => $m['subject'],    
                'headers'=> $headers,
            ];
            if (!empty($m['attachments'])) {
                $e['attachments'] = unserialize($m['attachments']);
                array_walk($e['attachments'], function(&$u) {
                    if (isset($u['data'])) {
                        $u['data'] = base64_decode($u['data']);
                    }
                });
            }
            

            if ($sent == 0) {
                $e['keepAlive'] = true;
            }
    
            if ($this->mailer->send($e)) {
                $this->del($m['id']);
            } else {
                $this->set_error($m['id']);
            } 

            $sent++;    

            if ((time() - $start) > $maxtime) {
                break;
            }
        }
        $this->is_runnimg = false;
        return $this->q_count();
    }

    // ---

    function push() {
        if ($this->is_runnimg) {
            return;
        }
        $q_count = $this->send_next();
        if ($q_count->total ==  $q_count->errors) {
            wp_clear_scheduled_hook('mail-q_push');
            // if ($q_count->total === 0) {
            //     $this->clear();
            // }
        }

    }

    // ---

    function run() {
        if (!wp_next_scheduled('mail-q_push')) {
            $q_count = $this->q_count();
            if ($q_count->total > $q_count->errors) {
                wp_schedule_event(time(), '15_seconds', 'mail-q_push');
            }
        }
    }

    // ---

    function is_running() {
        return wp_next_scheduled('mail-q_push');
    }

    // ---

    function stop() {
        wp_clear_scheduled_hook('mail-q_push');
    }
}