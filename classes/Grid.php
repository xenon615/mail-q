<?php
namespace MailQ;
class Grid extends \AnyData\Grid{
    private $db;
    function __construct($slug) {
        add_action('admin_enqueue_scripts', function() {
            wp_enqueue_script('mail-q-pusher', plugin_dir_url(__DIR__) . '/assets/pusher-g.js', false, filemtime(dirname(__DIR__) . '/assets/pusher-g.js'));
            wp_enqueue_style('mail-q-pusher', plugin_dir_url(__DIR__) . '/assets/pusher-g.css', false, filemtime( dirname(__DIR__) . '/assets/pusher-g.css'));
            wp_localize_script('mail-q-pusher', 'mail_q', [
                'nonce' => wp_create_nonce('wp_rest'),
                'is_running' => getMailQ()->is_running(), 
                'return_to' => wp_get_referer()
            ]);
        });
        
        $this->db = new Data();
        parent::__construct($slug, 'Mail Queue entry', 'Mail Queue entries', ['disableNew']);
    }

    public function get_count() {
        return $this->db->get_count()->total;
    }

    public function get_items($from, $per_page) {
        $ii = $this->db->get_list($per_page, $from);
        return $this->_prepare_items($ii);
    }

    private function _prepare_items($ii) {
        $items = [];
        foreach($ii as $i) {
            if (!empty($i->attachments)) {
                $attachments = implode(', ', array_column(unserialize($i->attachments), 'name'));
            } else {
                $attachments = '-';
            }
            
            $items[] = [
                'id' => $i->id,
                'email'  => $i->email,
                'subject' => $i->subject,
                'attachments'=> $attachments,
                'error_text' => $i->error_text
            ];
        }
        return $items;
    }

    protected function _get_search_by() {
        return [];
    }

    protected function _get_views() {
        return [];
    }

    protected function _get_bulk_actions() {
        $ba =  [
            'delete' => 'Delete',
            'clear_errors' => 'Clear Errors',
            'export_errors' => 'Export Errors',
            'clear_queue' => '!!! Clear Queue !!!'
        ];
        return $ba;
    }

    protected function _get_actions() {
        $actions = [
            'edit' => ['title' => 'Edit', 'page' => 'mail-q-form'],
            'clear_error' => 'Clear Error',
            'delete' => 'Delete',
        ];
        return $actions;
    }

    protected function _get_columns() {
        return [
            'id' => [
                'label' => 'Id',
                'style' => 'width: 15%;',
                'sortable'=>['id', false],
                'uid' => true,
            ],
            'email'  => [
                'label' => 'Email',
                'style' => 'width: 15% !important;'
            ],

            'subject'  => [
                'label' => 'Subject',
                'style' => 'width: 25% !important;'
            ],
            'attachments'  => [
                'label' => 'Attachments',
                'style' => 'width: 15% !important;'
            ],

            'error_text' => [
                'label' => 'Error',
                'style' => 'width: 25% !important;'
            ],

        ];
    }

    protected function _get_filters() {
        return [];
    }


    protected function _process_action($redirect, $params) {
        switch($params['action'])  {
            case 'delete':
                $this->db->delete($params['id']);
                break;
            case 'clear_error':
                    $this->db->clear_errors([$params['id']]);
                break;
        }
        return $redirect;
    }
    
    protected function _process_bulk_action($redirect_to, $action_name, $ids) {
        $ids = array_filter($ids, fn($e) => absint($e) > 0 );
        switch($action_name) {
            case 'delete':
                if (!empty($ids)) {
                    $this->db->bulk_delete($ids);
                }
                break;
            case 'clear_errors':
                $this->db->clear_errors($ids);
                break;
            case 'export_errors':
                $file = base64_decode(getMailQ()->export_errors());
                header('Content-type: text/csv');
                header('Content-Disposition: attachment; filename=errors.csv');
                echo $file;
                die();
                $redirect_to = '';
            case 'clear_queue':
                $this->db->clear();     
        }

        return $redirect_to;
    } 

    protected function extra_tablenav($which) {
        if ($which == 'bottom') {
			return;
		}
        $mq = getMailQ();
        $count = getMailQ()->q_count();
        $disabled = intval($count->total) === 0 ? 'disabled="true"' : ''; 
        echo '<div class="alignleft actions queue">';
        echo '<div> In Queue: ' . $count->total . ', Errors: ' . $count->errors . '</div>';
        if (!$mq->is_running()) {
            echo '<div><button id="q_run" type="button" class="button button-primary button-large"' . $disabled . ' >Start</button></div>';
        } else {
            echo '<div>Queue is running (autorefresh 10 sec)</div>';
            echo '<div id="progress"><img src="' . plugin_dir_url(__DIR__) . '/assets/progress.svg' . '"></div>';
            echo '<div><button id="q_stop" type="button" class="button button-primary button-large">Stop</button></div>';
        }
        echo '<div><button id="q_refresh" type="button" class="button button-primary button-large">Refresh</button></div>';
        echo '</div>';
        ?>
        <table class="wp-list-table" style="display:none;">
            <tbody>
                <tr>
                    <td class="check-column">
                        <input type="checkbox" checked name="item_ids[]" value="0" id="cb-select-0"/>
                    </td>
                </tr>
            </tbody>
        </table> 
<?php        

    }

} 

