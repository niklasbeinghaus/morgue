<!-- Calendar -->

<link rel="stylesheet" href="/features/calendar/css/calendar.css" />
<script type="text/javascript">
     let cal = {};
     
     function loadCal() {
         <?php
             $cal = new Calendar;
             $timeZone = getUserTimezone();
             $cal_scopes = implode(",", $cal->scopes);
             echo "cal.clientId='{$cal->clientId}'; cal.apiKey='{$cal->apiKey}'; cal.scopes='{$cal_scopes}'; cal.id='{$cal->id}'; cal.timeZone='{$timeZone}';";
             echo "cal.attendees=[";
             foreach ($cal->attendees as $attendee_email) {
                 echo "'{$attendee_email}',";
             }
             echo "];";
             echo "cal.override_calendar_link = '{$cal->override_calendar_link}';";
             echo "cal.override_calendar_link_href = '{$cal->override_calendar_link_href}';";
             echo "cal.override_calendar_link_description = '{$cal->override_calendar_link_description}';";
         ?>
        
         cal.scopes = cal.scopes.split(",");
         cal.src = 'https://www.google.com/calendar/embed?src=';
         cal.authorized = false;
         handleClientLoad(false);
     }
     $(window).load(loadCal);
</script>

<div id="calendar-div" name="calendar">
     <h3>Post Mortem Calendar</h3>
     <a href="#calendar" id="calendar-link" name="calendar">Login to your Google Account to view the Post Mortem Calendar!</a>
</div>
<br/>

