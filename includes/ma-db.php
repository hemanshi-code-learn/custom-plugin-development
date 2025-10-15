<?php 
if ( ! class_exists('Contact_Form_DB') ) {

class Contact_Form_DB{

    private static $instance = null;
    private $table_name;

    /**
     * Singleton Pattern: Ensures only one instance of the class exists.
     */

     public static function get_instance(){
        if(is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
     }

     private function __construct(){
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ma_cf_submissions';
     }
    

     /**
      * Creates the database table on plugin activation.
      */

      public function create_table(){
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name}(
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                firstname tinytext NOT NULL,
                lastname tinytext NOT NULL,
                email varchar(100) NOT NULL,
                phone varchar(20) DEFAULT '' NOT NULL,
                message text NOT NULL,
                PRIMARY KEY (id) 
                ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql); // Safe way to create/update tables
      }

     /**
      * Deletes the database table.
      */

      public function delete_table(){
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
      }


     /**
      * Inserts sanitized form data into the database.
      */

      public function insert_data($data){
        global $wpdb;
        return $wpdb->insert(
            $this->table_name,
            [
                'firstname' => $data['cf_firstname'],
                'lastname' => $data['cf_lastname'],
                'email' => $data['cf_email'],
                'phone' => $data['cf_phone'],
                'message' => $data['cf_message'],
            ],
            //Format Placeholders
            [ '%s', '%s', '%s', '%s', '%s' ]
            );
      }


     /**
      * Retrieves all submission data for the admin panel.
      */

    //   public function get_all_submissions(){
    //     global $wpdb;
    //     return $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY time DESC", ARRAY_A);
    //   }

    public function get_submission($paged = 1, $per_page = 10){
        global $wpdb;

        $paged = max(1, absint($paged));
        $per_page = max(1, absint($per_page));

        $offset = ($paged -1) * $per_page;

        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM {$this->table_name}");
        $total_items = absint($total_items);

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} ORDER BY time DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),ARRAY_A
        );

        return[
            'results' => $results,
            'total_items' => $total_items,
            'per_page' => $per_page,
            'current_page' => $paged,
            'total_pages' => ceil($total_items / $per_page),
        ];
    }

    public function count_submissions() {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(id) FROM $this->table_name");
    }

  
public function is_email_duplicate($email) {
    global $wpdb;
    // $table_name = $wpdb->prefix . 'ma_cf_submissions'; 
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$this->table_name} WHERE email = %s",
        $email
    ));
    return (int)$count > 0;
}

public function is_phone_duplicate($phone) {
    global $wpdb;
    // $table_name = $wpdb->prefix . 'ma_cf_submissions'; 
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$this->table_name} WHERE phone = %s",
        $phone
    ));
    return (int)$count > 0;
}

}
}
?>