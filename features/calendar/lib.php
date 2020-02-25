<?php

class Calendar extends Persistence
{
    public $clientId;
    public $apiKey;
    public $scopes;
    public $id;
    /**
     * @var string
     */
    public $override_calendar_link;
    /**
     * @var string
     */
    public $override_calendar_link_href;
    /**
     * @var string
     */
    public $override_calendar_link_description;
    public $facilitator;
    /**
     * @var array
     */
    public $attendees;

    function __construct()
    {
        $config = Configuration::get_configuration("calendar");
        $this->clientId = $config['clientId'];
        $this->apiKey = $config['apiKey'];
        $this->scopes = $config['scopes'];
        $this->id = $config['id'];
        $this->override_calendar_link = array_key_exists('override_calendar_link', $config) ? $config['override_calendar_link'] : '';
        $this->override_calendar_link_href = array_key_exists('override_calendar_link_href', $config) ? $config['override_calendar_link_href'] : '';
        $this->override_calendar_link_description = array_key_exists('override_calendar_link_description', $config)
            ? $config['override_calendar_link_description'] : '';
        $this->facilitator = $config['facilitator'];
        if (isset($config['attendees_email'])) {
            if (!is_array($config['attendees_email'])) {
                $config['attendees_email'] = array($config['attendees_email']);
            }
            $this->attendees = $config['attendees_email'];
        } else {
            $this->attendees = [];
        }
    }

    /**
     * @param int $id
     * @param PDO|null $conn
     * @return array|null
     */
    public static function get_facilitator($id, $conn = null)
    {
        if (!$conn) {
            return null;
        }
        $sql = "SELECT facilitator, facilitator_email FROM postmortems WHERE id = :id";
        try {
            $ret = array();
            $stmt = $conn->prepare($sql);
            $stmt->execute(['id' => $id]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                array_push($ret, $row);
            }
            return array("status" => self::OK, "error" => "", "values" => $ret);
        } catch (PDOException $e) {
            return array("status" => self::ERROR, "error" => $e->getMessage(), "values" => array());
        }
    }

    /**
     * @param int $id
     * @param array $facilitator
     * @param PDO|null $conn
     * @return string|bool
     */
    public static function set_facilitator(int $id, array $facilitator, $conn = null)
    {
        if (!$conn) {
            return null;
        }
        $sql = "UPDATE postmortems SET facilitator = :faciliator_name, facilitator_email = :faciliator_email WHERE id = :id";
        try {
            $stmt = $conn->prepare($sql);
            $success = $stmt->execute(['faciliator_name' => $facilitator['name'], 'faciliator_email' => $facilitator['email'], 'id' => $id]);
            return $success;
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }
}

?>
