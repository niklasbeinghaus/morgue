<?php
require_once('Persistence.php');

/**
 * Implementing DB persistence for postmortem events
 */
class Postmortem
{
    /** constants mapped from Persistence class */
    const OK = Persistence::OK;
    const ERROR = Persistence::ERROR;
    const ACTION_ADD = 'add';
    const ACTION_EDIT = 'edit';
    const EDIT_UNLOCKED = 0;
    const EDIT_LOCKED = 1;
    const EDIT_CLOSED = 2;

    /**
     * Save an event to the database. If an id is given, the existing event is
     * updated, if not a new one is created. The event will be stored in the
     * events table and all properties given as arrays are stored in the
     * accompanying junction table.
     *
     * @param array $event - map of an event with the following keys
     *                 - title => the title of the event
     *                 - summary => the summary of the post mortem
     *                 - starttime => start time as unix timestamp
     *                 - endtime   => end time as unix timestamp
     *                 - statustime => status time as unix timestamp
     *                 - detecttime  => detect time as unix timestamp
     * @param PDO|null $conn - PDO connection object, will be newly instantiated when
     *                null (default: null)
     *
     * @return array $event the event map including an "id" field on success and a map of the
     * form ( "id" => null, "error" => "an error message" ) on failure
     */
    static function save_event($event, $conn = null)
    {
        $conn = $conn ?: Persistence::get_database_object();
        if (is_null($conn)) {
            return array("id" => null, "error" => "Couldn't get connection object.");
        }
        $action = (isset($event["id"])) ? self::ACTION_EDIT : self::ACTION_ADD;
        if ($action == self::ACTION_ADD) {
            $now = new DateTime(null, new DateTimeZone('UTC'));
            $event["created"] = $now->getTimestamp();
        }
        $event = Persistence::save_event($event, $conn);
        if (is_null($event["id"])) {
            return $event;
        }
        if ($action == self::ACTION_ADD) {
            $app = (new \Slim\App)->getContainer();
            $env = $app->environment;
            $admin = $env['admin']['username'];
            $result = Postmortem::add_history($event, $admin, $action);
        }
        // close connection and return
        $conn = null;
        return $event;
    }

    /**
     * Get an event from the database
     *
     * @param $event_id - id of the event to get
     * @param $conn - PDO connection object, will be newly instantiated when
     *                null (default: null)
     *
     * @return array an event map including an "id" field on success or a map of the
     * form ( "id" => null, "error" => "an error message" ) on failure
     */
    static function get_event($event_id, $conn = null)
    {
        $event_id = (int)$event_id;
        $conn = $conn ?: Persistence::get_database_object();
        if (is_null($conn)) {
            return array("id" => null, "error" => "Couldn't get connection object.");
        }
        $event = Persistence::get_postmortem($event_id, $conn);
        if (is_null($event["id"])) {
            return $event;
        }
        $tags = Postmortem::get_tags_for_event($event_id, $conn);
        if ($tags["status"] != Persistence::OK) {
            $conn = null;
            return array("id" => null, "error" => "error fetching data");
        } else {
            $event["tags"] = $tags["values"];
            $event["history"] = self::get_history($event["id"]);
            $conn = null;
            return $event;
        }
    }

    /**
     * Delete an event from the database. All child assets are left in their
     * undeleted state to differentiate them from manually-deleted assets.
     *
     * @param $event_id - id of the event to get
     * @param $conn - PDO connection object, will be newly instantiated when
     *                null (default: null)
     *
     * @return array ( "status" => self::OK ) on success
     * or ( "status" => self::ERROR, "error" => "message" ) on failure
     */
    static function delete_event($event_id, $conn = null)
    {
        $event_id = (int)$event_id;
        $conn = $conn ?: Persistence::get_database_object();
        if (is_null($conn)) {
            return array("id" => null, "error" => "Couldn't get connection object.");
        }
        return Persistence::flag_as_deleted('postmortems', 'id', $event_id, $conn);
    }

    /**
     * Restore (undelete) an event.
     */
    static function undelete_event($event_id, $conn = null)
    {
        $event_id = (int) $event_id;
        $conn = $conn ?: Persistence::get_database_object();
        if (is_null($conn)) {
            return array("id" => null, "error" => "Couldn't get connection object.");
        }
        return Persistence::flag_as_undeleted('postmortems', 'id', $event_id, $conn);
    }

    /**
     * Determine if event is editable
     * @param array $event - event object returned from \Postmortem::get_event
     * @return int EDIT_UNLOCKED if editable, EDIT_LOCKED if a user is currently editing,
     *  or EDIT_CLOSED if no longer editable
     */
    static function get_event_edit_status($event)
    {
        $config = Configuration::get_configuration();
        $config = $config["locking"];
        $lock_date = new DateTime();
        $lock_date->setTimestamp($event['created']);
        $lock_date->add(new DateInterval('P' . $config['editable_days'] . 'D'));
        $now = new DateTime();
        // the number of days allowed to edit this event has expired
        if ($now > $lock_date) {
            return self::EDIT_CLOSED;
        }
        $user = MorgueAuth::get_auth_data();
        // the lock for another user's edits hasn't expired yet
        if (strcmp($user['username'], $event['modifier']) != 0) {
            $now = $now->getTimestamp();
            if ($now < $event['modified'] + $config['lock_time']) {
                return self::EDIT_LOCKED;
            }
        }
        return self::EDIT_UNLOCKED;
    }

    /**
     * Sets the given event as being modified by the current user
     */
    static function set_event_edit_status($id, $conn = null)
    {
        $id = (int) $args['id'];
        $conn = $conn ?: Persistence::get_database_object();
        if (!$conn) {
            return null;
        }
        $user = MorgueAuth::get_auth_data();
        $modifier = $user['username'];
        $modified = new DateTime();
        $modified = $modified->getTimestamp();
        $sql = "UPDATE postmortems SET modifier = '" . $modifier . "', modified = " . $modified;
        $sql = $sql . " WHERE id = " . $id;
        try {
            $stmt = $conn->prepare($sql);
            $success = $stmt->execute();
            return null;
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Get all postmortems from the database
     *
     * @param $conn - PDO connection object, will be newly instantiated when
     *                null (default: null)
     *
     * @return array ( "status" => self::OK, "error" => "", "values" => array(events) ) on success
     * and ( "status" => self::ERROR, "error" => "message", "values" => array() ) on failure
     */
    static function get_all_events($conn = null)
    {
        $columns = array('id', 'title', 'starttime', 'endtime', 'severity');
        $table_name = 'postmortems';
        $conn = $conn ?: Persistence::get_database_object();
        if (is_null($conn)) {
            return array(
                "status" => Persistence::ERROR,
                "error" => "Couldn't get connection object.",
            );
        }
        return Persistence::get_array($columns, null, $table_name, $conn);
    }

    /**
     * Get all tags associated with an event. The tags have the keys "id" and "title"
     *
     * @param $event_id - the numeric event id
     * @param $conn - a PDO connection object
     *
     * @return array ( "status" => self::OK, "error" => "", "values" => array(tags) ) on success
     * and ( "status" => self::ERROR, "error" => "message", "values" => array() ) on failure
     */
    static function get_tags_for_event($event_id, $conn = null)
    {
        $event_id = (int)$event_id;
        $conn = $conn ?: Persistence::get_database_object();
        $columns = array('id', 'title');
        $table_name = 'tags';
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
     * get all events that match at least one tag id.
     *
     * @param array $tag_ids - array of tag ids
     * @param PDO|null $conn - a PDO connection object
     *
     * @return array ( "status" => self::OK, "error" => "", "values" => array(events)) on success
     * and ( "status" => self::ERROR, "error" => "message", "values" => array() ) on failure
     * @throws Exception if you pass it a tag id that is invalid
     */
    static function get_events_for_tags($tag_ids, $conn = null)
    {
        $columns = array('id', 'title', 'starttime', 'endtime', 'severity');
        $table_name = 'tags';
        //Sanitize because we get tag ids from user input.
        $tag_ids = array_map(
            function ($tag) {
                if (!is_numeric($tag)) {
                    throw new Exception("\"$tag\" is not a valid tag ID.");
                }
                return intval($tag);
            },
            $tag_ids
        );
        $conn = $conn ?: Persistence::get_database_object();
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
                "values" => array(),
            );
        }
        return Persistence::get_array($columns, array('tag_ids' => $tag_ids), $table_name, $conn);
    }

    /**
     * get_events_by_date
     *
     * @param mixed $start_date
     * @param mixed $end_date
     * @param mixed $conn
     * @static
     * @access public
     * @return void
     */
    static function get_events_by_date($start_date = null, $end_date = null, $conn = null)
    {
        $conn = $conn ?: Persistence::get_database_object();
        $columns = array('id', 'title', 'starttime', 'endtime', 'severity', 'summary');
        // set some default date ranges - 1 month in this case
        if (!$start_date) {
            $start_date = time() - (30 * 86400);
        }
        if (!$end_date) {
            $end_date = time();
        }
        $tween = new StdClass();
        $tween->operator = "BETWEEN";
        $tween->min_value = $start_date;
        $tween->max_value = $end_date;
        $deleted = new StdClass();
        $deleted->operator = "=";
        $deleted->value = '0';
        $where = array('starttime' => $tween, 'deleted' => $deleted);
        $data = Persistence::range_query($columns, "postmortems", $where, $conn);
        return $data;
    }

    /**
     * Get all tags. The tags have the keys "id" and "title"
     *
     * @param $conn - a PDO connection object
     *
     * @return array ( "status" => self::OK, "error" => "", "values" => array(tags) ) on success
     * and ( "status" => self::ERROR, "error" => "message", "values" => array() ) on failure
     */
    static function get_tags($conn = null)
    {
        $columns = array('id', 'title');
        $table_name = 'tags';
        $conn = $conn ?: Persistence::get_database_object();
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
                "values" => array(),
            );
        }
        return Persistence::get_array($columns, null, $table_name, $conn);
    }

    /**
     * save tags belonging to a certain event to the database
     *
     * @param $event_id - numeric ID of the event to store for
     * @param $tags - array of tag titles to store
     * @param $conn - a PDO connection object
     *
     * @return array ( "status" => self::OK ) on success
     * or ( "status" => self::ERROR, "error" => "an error message" ) on failure
     */
    static function save_tags_for_event($event_id, $tags, $conn = null)
    {
        $event_id = (int)$event_id;
        $conn = $conn ?: Persistence::get_database_object();
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
            );
        }
        try {
            foreach ($tags as $title) {
                //Check if the tag title already exists
                $select_tag = "SELECT id FROM tags WHERE title = :value LIMIT 1";
                $stmt = $conn->prepare($select_tag);
                $stmt->execute(array('value' => $title));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                //If it doesn't, create it.
                if (!$row) {
                    $insert_tag = "INSERT INTO tags (title) VALUES (:value)";
                    $stmt = $conn->prepare($insert_tag);
                    $stmt->execute(array('value' => $title));
                    //Re-select the row so we can get the ID
                    $tag_id = $conn->lastInsertId();
                } else {
                    $tag_id = $row['id'];
                }
                $select_assoc = "SELECT tag_id, deleted FROM postmortem_referenced_tags
                                 WHERE tag_id = :value AND postmortem_id = {id} LIMIT 1";
                $stmt = $conn->prepare($select_assoc);
                $stmt->execute(array('value' => $tag_id, 'id' => $event_id));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    // No association yet, so create one
                    $insert_assoc = "INSERT INTO postmortem_referenced_tags (postmortem_id, tag_id)
                                     VALUES (:p_id, :t_id)";
                    $stmt = $conn->prepare($insert_assoc);
                    $stmt->execute(array('p_id' => $event_id, 't_id' => $tag_id));
                } elseif ($row['deleted']) {
                    // Row exists; undelete it
                    $sql = "UPDATE postmortem_referenced_tags SET deleted = 0
                            WHERE postmortem_id = :p_id AND tag_id = :t_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute(array('p_id' => $event_id, 't_id' => $tag_id));
                }
            }
        } catch (PDOException $e) {
            return array("status" => self::ERROR, "error" => $e->getMessage());
        }
        return array("status" => self::OK);
    }

    /**
     * delete tags belonging to a certain event to the database
     *
     * @param $event_id - numeric ID of the event to delete for
     * @param $conn - a PDO connection object
     *
     * @return array ( "status" => self::OK ) on success
     * or ( "status" => self::ERROR, "error" => "an error message" ) on failure
     */
    static function delete_tags_for_event($event_id, $conn = null)
    {
        $event_id = (int)$event_id;
        $res = Postmortem::get_tags_for_event($event_id);
        if ($res == Persistence::ERROR) {
            return $res;
        }
        $tags = $res['values'];
        foreach ($tags as $tag) {
            $res = Postmortem::delete_tag($tag['id'], $event_id, $conn);
            if ($res['status'] == Persistence::ERROR) {
                break;
            }
        }
        return $res;
    }

    /**
     * function to delete a tag from an event
     *
     * @param $tag_id - tag ID to delete
     * @param $event_id - event ID to delete tag from. If null, tag will be deleted for all events.
     * @param $conn - PDO connection object (default: null)
     *
     * @return array ( "status" => self::OK ) on success
     * or ( "status" => self::ERROR, "error" => "an error message" ) on failure
     */
    static function delete_tag($tag_id, $event_id = null, $conn = null)
    {
        $tag_id = (int)$tag_id;
        $event_id = $event_id ? (int) $event_id : null;
        $conn = $conn ?: Persistence::get_database_object();
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
            );
        }
        try {
            $delete_tag = false;
            //Delete all of the assocations first.
            if ($event_id) {
                $delete_assoc = "UPDATE postmortem_referenced_tags
                                 SET deleted=1
                                 WHERE postmortem_id={id} AND tag_id=:value";
                $stmt = $conn->prepare($delete_assoc);
                $stmt->execute(array('id' => $event_id, 'value' => $tag_id));
                //Then check if we need to delete the tag
                $select_tag = "SELECT tag_id from postmortem_referenced_tags where tag_id=:value LIMIT 1";
                $stmt = $conn->prepare($select_tag);
                $stmt->execute(array('value' => $tag_id));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    $delete_tag = true;
                }
            } else {
                $delete_assoc = "UPDATE postmortem_referenced_tags SET deleted=1 WHERE tag_id:vale";
                $stmt = $conn->prepare($delete_assoc);
                $stmt->execute(array('value' => $tag_id));
                $delete_tag = true;
            }
            //The tag is no longer used
            if ($delete_tag) {
                $update_sql = "UPDATE tags SET deleted=1 WHERE id=:value";
                $stmt = $conn->prepare($update_sql);
                $stmt->execute(array('value' => $tag_id));
            }
        } catch (PDOException $e) {
            return array("status" => Postmortem::ERROR, "error" => $e->getMessage());
        }
        return array("status" => self::OK);
    }

    /**
     * function to undelete a tag from an event
     *
     * @param $tag_id - tag ID to delete
     * @param $event_id - event ID to delete tag from. If null, tag will be deleted for all events.
     * @param $conn - PDO connection object (default: null)
     *
     * @return array ( "status" => self::OK ) on success
     * or ( "status" => self::ERROR, "error" => "an error message" ) on failure
     */
    static function undelete_tag($tag_id, $event_id = null, $conn = null)
    {
        $tag_id = (int) $tag_id;
        $event_id = $event_id ? (int) $event_id : null;
        $conn = $conn ?: Persistence::get_database_object();
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
            );
        }
        try {
            $undelete_tag = false;
            //Undelete all of the assocations first.
            if ($event_id) {
                $undelete_assoc = "UPDATE postmortem_referenced_tags
                                 SET deleted=0
                                 WHERE postmortem_id={id} AND tag_id=:value";
                $stmt = $conn->prepare($undelete_assoc);
                $stmt->execute(array('id' => $event_id, 'value' => $tag_id));
                //Then check if we need to undelete the tag
                $select_tag = "SELECT tag_id from postmortem_referenced_tags where tag_id=:value LIMIT 1";
                $stmt = $conn->prepare($select_tag);
                $stmt->execute(array('value' => $tag_id));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    $undelete_tag = true;
                }
            } else {
                $undelete_assoc = "UPDATE postmortem_referenced_tags SET deleted=0 WHERE tag_id:vale";
                $stmt = $conn->prepare($undelete_assoc);
                $stmt->execute(array('value' => $tag_id));
                $undelete_tag = true;
            }
            //The tag will be used
            if ($undelete_tag) {
                $update_sql = "UPDATE tags SET deleted=0 WHERE id=:value";
                $stmt = $conn->prepare($update_sql);
                $stmt->execute(array('value' => $tag_id));
            }
        } catch (PDOException $e) {
            return array("status" => Postmortem::ERROR, "error" => $e->getMessage());
        }
        return array("status" => self::OK);
    }

    /**
     * function to add a history row for an event
     *
     * @param array $event_id - ID of the postmortem the action was taken on
     * @param string $admin - LDAP name of the person taking the action
     * @param string $action - The action being taken (must be one of the ACTION_* class constants
     * @param PDO|null $conn - PDO connection object
     *
     * @return array ( "status" => self::OK ) on success
     * or ( "status" => self::ERROR, "error" => "an error message" ) on failure
     */
    static function add_history($event_id, $admin, $action, $conn = null)
    {
        $event_id = (int) $event_id;
        // validate action
        if (!in_array($action, array(self::ACTION_ADD, self::ACTION_EDIT))) {
            return array(
                "status" => self::ERROR,
                "error" => "Invalid action specified.",
            );
        }
        $conn = $conn ?: Persistence::get_database_object();
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
            );
        }
        $now = new DateTime(null, new DateTimeZone('UTC'));
        $sql = "INSERT INTO postmortem_history
                   (postmortem_id, auth_username, action, create_date, summary, why_surprised)
                   VALUES (:pid, :admin, :action, :date, :summary, :why_surprised)";
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute(
                array(
                    "pid" => $event_id["id"],
                    "admin" => $admin,
                    "action" => $action,
                    "date" => $now->getTimestamp(),
                    "summary" => $event_id['summary'],
                    "why_surprised" => $event_id['why_surprised'],
                )
            );
        } catch (PDOException $e) {
            return array("status" => Postmortem::ERROR, "error" => $e->getMessage());
        }
        return array("status" => self::OK);
    }

    /**
     * function to get all history records for a postmortem
     *
     * @param int $event_id - ID of the postmortem
     * @param PDO|null $conn - PDO connection object
     *
     * @return array - Array containing an entry for each
     * associated history record
     */
    static function get_history($event_id, $conn = null)
    {
        $event_id = (int) $event_id;
        $conn = $conn ?: Persistence::get_database_object();
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
            );
        }
        $sql = "SELECT id, postmortem_id, auth_username, action, create_date FROM postmortem_history WHERE postmortem_id=:pid";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array("pid" => $event_id));
        return $stmt->fetchAll();
    }

    /**
     * function to get all data from specific history event
     * @param $id - ID of the postmortem_history row
     * @param $conn - PDO connection object
     *
     * @return array - Array containing all data for specificed history record
     */
    static function get_history_event($id, $conn = null)
    {
        $id = (int) $args['id'];
        $conn = $conn ?: Persistence::get_database_object();
        if (is_null($conn)) {
            return array(
                "status" => self::ERROR,
                "error" => "Couldn't get connection object.",
            );
        }
        try {
            $sql = "SELECT * FROM postmortem_history WHERE id={id}";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array("id" => $id));
            $history = $stmt->fetch(PDO::FETCH_ASSOC);
            $history["status"] = self::OK;
            return $history;
        } catch (PDOException $e) {
            return array("status" => self::ERROR, "error" => $e->getMessage());
        }
    }

    /**
     * function to translate a history record to a readable string
     *
     * @param array $history - Associative array corresponding to a history record
     *
     * @return string - A human readable string
     */
    static function humanize_history($history)
    {
        $dt = DateTime::createFromFormat('U', (string)$history['create_date']);
        $who = $history['auth_username'];
        $who_html = Contact::get_html_for_user($who);
        $when = $dt->format('H:i:s T, m/d/Y');
        switch ($history['action']) {
            case self::ACTION_ADD:
                return '<a href="/history/' . $history['postmortem_id'] . '/' . $history['id'] . '">Created</a> by ' . $who_html . ' @ ' . $when;
            case self::ACTION_EDIT:
                return '<a href="/history/' . $history['postmortem_id'] . '/' . $history['id'] . '">Edited</a> by ' . $who_html . ' @ ' . $when;
        }
    }

    /**
     * Provide the different severity levels for a post mortem event
     * @return array of severity levels
     */
    static function get_severity_levels()
    {
        $config = Configuration::get_configuration();
        if (isset($config['severity']) && isset($config['severity']['levels'])) {
            return $config['severity']['levels'];
        } else {
            return array('default');
        }
    }
}
