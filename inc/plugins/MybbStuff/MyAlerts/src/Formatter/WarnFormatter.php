<?php

/**
 * Alert formatter for warn alerts.
 */
class MybbStuff_MyAlerts_Formatter_WarnFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
{
    /**
     * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
     *
     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
     *
     * @return string The formatted alert string.
     */
    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
    {	
        $alertContent = $alert->getExtraDetails();
        
        // This warning hasn't expired yet
        $expiry_date = '';
        if ($alertContent['expires'] > TIME_NOW) {
        	$expiry_date = ' ' . my_date($this->mybb->settings['dateformat'], $alertContent['expires']) . ", " . my_date($this->mybb->settings['timeformat'], $alertContent['expires']);
        }
        
        // This warning will expire today
        if ($alertContent['expires'] < strtotime('tomorrow') and $expiry_date) {
        	$expiry_label = $this->lang->sprintf($this->lang->myalertsmore_warn_expires, $this->lang->myalertsmore_warn_will, $expiry_date, '');
        }
        // This warning has expired
        else if (!$expiry_date) {
	        $expiry_label = $this->lang->sprintf($this->lang->myalertsmore_warn_expires, $this->lang->myalertsmore_warn_has, $this->lang->myalertsmore_warn_d, '');
        }
        // This warning will expire in the future
        else {
        	$expiry_label = $this->lang->sprintf($this->lang->myalertsmore_warn_expires, $this->lang->myalertsmore_warn_will, $this->lang->myalertsmore_warn_on, $expiry_date);
        }

        return $this->lang->sprintf(
            $this->lang->myalertsmore_warn,
            $outputAlert['from_user'],
            $alertContent['points'],
            $expiry_label
        );
    }

    /**
     * Init function called before running formatAlert(). Used to load language files and initialize other required
     * resources.
     *
     * @return void
     */
    public function init()
    {
        if (!$this->lang->myalertsmore_warn) {
            $this->lang->load('myalertsmore');
        }
    }

    /**
     * Build a link to an alert's content so that the system can redirect to it.
     *
     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
     *
     * @return string The built alert, preferably an absolute link.
     */
    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
    {
    	return false;
    }
}
