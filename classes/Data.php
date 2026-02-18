<?php
namespace MailQ;
class Data {
    private $dbh = null;
    private $q_table = '_wp_mail_queue';

    function __construct() {
        global $wpdb;
        $this->dbh = $wpdb;
        $this->dbh->suppress_errors(true);
        $this->dbh->hide_errors();
    }

    // ---

    public function clear() {
        $this->dbh->query('TRUNCATE TABLE '. $this->q_table);
    }

    // ---

    public function clear_errors($ids = null) {
        $sql = "UPDATE " . $this->q_table . ' SET error = 0, error_text = NULL WHERE error = 1';
        if (!empty($ids)) {
            $sql .= " AND id in (" . implode(',' , $ids) . ")";
        }
        $this->dbh->query($sql);

        // $this->dbh->update($this->q_table, ['error' => 0, 'error_text' => null], ['error' => 1]);
        return $this->get_count();
    }

    // --- 

    public function get_errors() {
        $sql = "select email, error_text from {$this->q_table}  where error = 1  order by id";
        return $this->dbh->get_results($sql);
    }

    // ---

    public function delete($id) {
        $this->dbh->delete($this->q_table, ['id' => $id]);
    } 

    // ---

    public function insert($data) {
        $this->dbh->insert($this->q_table, $data);
    }

    // ---

    public function get_any() {
        $sql = "select * from {$this->q_table}  where error = 0  order by id LIMIT 1";
        return $this->dbh->get_row($sql, ARRAY_A);
    }

    // ---

    public function get($id) {
        $sql = "select * from {$this->q_table}  where id=$id";
        return $this->dbh->get_row($sql, ARRAY_A);
    }

    // ---

    public function update($data, $id) {
        $this->dbh->update($this->q_table, $data, ['id' => $id]);
    }

    // ---

    public function get_count() {
        $sql = "select count(*) total, ifnull(sum(error), 0) errors from $this->q_table";
        return $this->dbh->get_row($sql);
    }

    public function get_list($per_page = 25 , $page_number = 1 ) {
        $offset = ( $page_number - 1 ) * $per_page;
        $sql = "select id, email, subject, attachments, error, error_text from $this->q_table  ORDER BY id DESC LIMIT $per_page OFFSET $offset";
        return $this->dbh->get_results($sql);
    }

    public  function bulk_delete($ids) {
        $idss = implode(',', $ids);
        $sql = "DELETE FROM " .  $this->q_table  .  " where id IN ($idss) ";
        return $this->dbh->query($sql);
    }


}