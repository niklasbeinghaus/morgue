<?php

class Irc
{
    /** constants mapped from Persistence class */
    const OK = Persistence::OK;
    const ERROR = Persistence::ERROR;

    /**
     * get a list of the IRC channels that can be selected for a given postmortem
     * if the 'morgue_get_irc_channels_list' exists, call it and return
     * its results - otherwise, lookup the config file for ['irc']['channels']
     *
     * @return array of IRC channels strings
     */
    static function get_irc_channels_list()
    {
        if (function_exists("morgue_get_irc_channels_list")) {
            return morgue_get_irc_channels_list();
        } else {
            $irc_config = Configuration::get_configuration("irc");
            $channels = isset($irc_config["channels"]) ? $irc_config["channels"] : array();
            return $channels;
        }
    }

    /**
     * get all IRC channels associated with an event. The single channel
     * maps have the keys "id" and "channel".
     *
     * @param int $event_id - the numeric event id
     * @param PDO|null $conn - a PDO connection object
     * @return array ( "status" => self::OK, "error" => "", "values" => array(channels) ) on success
     * and ( "status" => self::ERROR, "error" => "message", "values" => array() ) on failure
     */
    static function get_irc_channels_for_event($event_id, $conn = null)
    {
        $event_id = (int)$event_id;
        $conn = $conn ?: Persistence::get_database_object();
        $columns = array('id', 'channel');
        $table_name = 'irc';
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
     * @param array $channels - array of channels to store
     * @param PDO|null $conn - a PDO connection object
     *
     * @return array ( "status" => self::OK ) on success
     * or ( "status" => self::ERROR, "error" => "an error message" ) on failure
     */
    static function save_irc_channels_for_event($event_id, $channels, $conn = null)
    {
        $event_id = (int) $event_id;
        $conn = $conn ?: Persistence::get_database_object();
        $table_name = 'irc';
        $assoc_column = 'channel';
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
            );
        }
        return Persistence::store_array(
            $table_name,
            $assoc_column,
            $channels,
            $event_id,
            $conn
        );
    }

    /**
     * delete irc channels belonging to a certain event from the database
     *
     * @param int $event_id - numeric ID of the event to store for
     * @param PDO|null $conn - a PDO connection object
     *
     * @return array ( "status" => self::OK ) on success
     * or ( "status" => self::ERROR, "error" => "an error message" ) on failure
     */
    static function delete_irc_channels_for_event($event_id, $conn = null)
    {
        $event_id = (int)$event_id;
        $conn = $conn ?: Persistence::get_database_object();
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
            );
        }
        return Persistence::flag_as_deleted('irc', 'postmortem_id', $event_id, $conn);
    }

    /**
     * function to get an irc channel from the association table
     *
     * @param int $id - ID to get
     * @param PDO|null $conn - PDO connection object (default: null)
     *
     * @return array ( "status" => self::OK, "value" => $row ) on success
     * or ( "status" => self::ERROR, "error" => "an error message" ) on failure
     */
    static function get_channel($id, $conn = null)
    {
        $id = (int)$id;
        $conn = $conn ?: Persistence::get_database_object();
        $columns = array('id', 'channel');
        $table_name = 'irc';
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
            );
        }
        return Persistence::get_association_by_id($columns, $table_name, $id, $conn);
    }

    /**
     * function to delete a channel from the association table
     *
     * @param int $id - ID to delete
     * @param PDO|null $conn - PDO connection object (default: null)
     *
     * @return array ( "status" => self::OK ) on success
     * or ( "status" => self::ERROR, "error" => "an error message" ) on failure
     */
    static function delete_channel($id, $conn = null)
    {
        $id = (int)$id;
        $conn = $conn ?: Persistence::get_database_object();
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
            );
        }
        return Persistence::flag_as_deleted('irc', 'id', $id, $conn);
    }

    /**
     * function to UNdelete a channel from the association table
     *
     * @param int $id - ID to delete
     * @param PDO|null $conn - PDO connection object (default: null)
     *
     * @return array ( "status" => self::OK ) on success
     * or ( "status" => self::ERROR, "error" => "an error message" ) on failure
     */
    static function undelete_channel($id, $conn = null)
    {
        $id = (int)$id;
        $conn = $conn ?: Persistence::get_database_object();
        $table_name = 'irc';
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
            );
        }
        return Persistence::flag_as_undeleted($table_name, 'postmortem_id', $id, $conn);
    }
}

