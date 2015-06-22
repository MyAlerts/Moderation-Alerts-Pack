<?php

/**
 * Alert formatter for suspensions alerts.
 */
class MybbStuff_MyAlerts_Formatter_SuspensionsFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
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
        $type = $alert->getType()->getCode();
        
		// Posting suspension
		if ($type == 'suspendposting') {
		
			if (!$alertContent['expiry_date']) {
				$label = $this->lang->myalertsmore_unsuspend_posting;
			}
			else {
				$label = $this->lang->myalertsmore_suspend_posting;
			}
			
		}
		// Posting moderation
		else if ($type == 'moderateposting') {
			
			if (!$alertContent['expiry_date']) {
				$label = $this->lang->myalertsmore_unmoderate_posting;
			}
			else {
				$label = $this->lang->myalertsmore_moderate_posting;
			}
			
		}
		// Signature suspension
		else if ($type == 'suspendsignature') {
			
			if (!$alertContent['expiry_date']) {
				$label = $this->lang->myalertsmore_unsuspend_signature;
			}
			else {
				$label = $this->lang->myalertsmore_suspend_signature;
			}
			
		}
        
        // This suspension hasn't expired yet
        $expiry_date = '';
        if ($alertContent['expiry_date'] > TIME_NOW) {
        	$expiry_date = ' ' . my_date($this->mybb->settings['dateformat'], $alertContent['expiry_date']) . ", " . my_date($this->mybb->settings['timeformat'], $alertContent['expiry_date']);
        }
        
        // This suspension will expire today
        if ($alertContent['expiry_date'] < strtotime('tomorrow') and $expiry_date) {
        	$expiry_label = $this->lang->sprintf($this->lang->myalertsmore_warn_expires, $this->lang->myalertsmore_warn_will, $expiry_date, '');
        }
        // This suspension has expired
        else if (!$expiry_date and $alertContent['expiry_date']) {
	        $expiry_label = $this->lang->sprintf($this->lang->myalertsmore_warn_expires, $this->lang->myalertsmore_warn_has, $this->lang->myalertsmore_warn_d, '');
        }
        // This is not a suspension
        else if (!$alertContent['expiry_date']) {
	        $expiry_label = '';
        }
        // This suspension will expire in the future
        else {
        	$expiry_label = $this->lang->sprintf($this->lang->myalertsmore_warn_expires, $this->lang->myalertsmore_warn_will, $this->lang->myalertsmore_warn_on, $expiry_date);
        }

        return $this->lang->sprintf(
            $this->lang->myalertsmore_suspensions,
            $outputAlert['from_user'],
            $label,
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
        if (!$this->lang->myalertsmore) {
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
