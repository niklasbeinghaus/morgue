<?php

class Images
{
    /** constants mapped from Persistence class */
    const OK = Persistence::OK;
    const ERROR = Persistence::ERROR;

    /**
     * get all image URLs associated with an event. The single image maps have
     * the keys "id" and "image_link"
     *
     * @param int $event_id - the numeric event id
     * @param PDO|null $conn - a PDO connection object
     *
     * @return array ( "status" => self::OK, "error" => "", "values" => array(images) ) on success
     * and ( "status" => self::ERROR, "error" => "message", "values" => array() ) on failure
     */
    static function get_images_for_event($event_id, $conn = null)
    {
        $event_id = (int) $event_id;
        $conn = $conn ?: Persistence::get_database_object();
        $columns = array('id', 'image_link');
        $table_name = 'images';
        $where = array(
            'postmortem_id' => $event_id,
            'deleted' => 0,
        );
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
                "values" => array(),
            );
        }
        return Persistence::get_array($columns, $where, $table_name, $conn);
    }

    /**
     * save images belonging to a certain event to the database
     *
     * @param int $event_id - numeric ID of the event to store for
     * @param array $images - array of image URLs to store
     * @param PDO|null $conn - a PDO connection object
     *
     * @return array ( "status" => self::OK ) on success
     * or ( "status" => self::ERROR, "error" => "an error message" ) on failure
     */
    static function save_images_for_event($event_id, $images, $conn = null)
    {
        $event_id = (int) $event_id;
        $conn = $conn ?: Persistence::get_database_object();
        $table_name = 'images';
        $assoc_column = 'image_link';
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
            );
        }
        return Persistence::store_array(
            $table_name,
            $assoc_column,
            $images,
            $event_id,
            $conn
        );
    }

    /**
     * delete images belonging to a certain event to the database
     *
     * @param int $event_id - numeric ID of the event to delete for
     * @param PDO|null $conn - a PDO connection object
     *
     * @return array ( "status" => self::OK ) on success
     * or ( "status" => self::ERROR, "error" => "an error message" ) on failure
     */
    static function delete_images_for_event($event_id, $conn = null)
    {
        $event_id = (int) $event_id;
        $conn = $conn ?: Persistence::get_database_object();
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
            );
        }
        return Persistence::flag_as_deleted('images', 'postmortem_id', $event_id, $conn);
    }

    /**
     * function to get an image from the association table
     *
     * @param int $id - ID to get
     * @param PDO|null $conn - PDO connection object (default: null)
     * @return array ( "status" => self::OK, "value" => $row ) on success
     * or ( "status" => self::ERROR, "error" => "an error message" ) on failure
     */
    static function get_image($id, $conn = null)
    {
        $id = (int) $args['id'];
        $conn = $conn ?: Persistence::get_database_object();
        $columns = array('id', 'image_link');
        $table_name = 'images';
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
            );
        }
        return Persistence::get_association_by_id($columns, $table_name, $id, $conn);
    }

    /**
     * function to delete an image from the association table
     *
     * @param int $id - ID to delete
     * @param PDO|null $conn - PDO connection object (default: null)
     *
     * @return array ( "status" => self::OK ) on success
     * or ( "status" => self::ERROR, "error" => "an error message" ) on failure
     */
    static function delete_image($id, $conn = null)
    {
        $conn = $conn ?: Persistence::get_database_object();
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
            );
        }
        return Persistence::flag_as_deleted('images', 'id', $id, $conn);
    }

    /**
     * function to UNdelete an image from the association table
     *
     * @param int $id - ID to undelete
     * @param PDO|null $conn - PDO connection object (default: null)
     *
     * @return array ( "status" => self::OK ) on success
     * or ( "status" => self::ERROR, "error" => "an error message" ) on failure
     */
    static function undelete_image($id, $conn = null)
    {
        $conn = $conn ?: Persistence::get_database_object();
        $table_name = 'images';
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
            );
        }
        return Persistence::flag_as_undeleted($table_name, 'postmortem_id', $id, $conn);
    }
}
