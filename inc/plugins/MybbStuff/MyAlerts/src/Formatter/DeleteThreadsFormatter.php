<?php

/**
 * Alert formatter for delete threads alerts.
 */
class MybbStuff_MyAlerts_Formatter_DeleteThreadsFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
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

        return $this->lang->sprintf(
            $this->lang->modpack_deletethreads,
            $outputAlert['from_user'],
            htmlspecialchars_uni(
                $this->parser->parse_badwords($alertContent['subject'])
            )
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
        if (!$this->lang->modpack_deletethreads) {
            $this->lang->load('myalertsmore');
        }

        require_once MYBB_ROOT . 'inc/class_parser.php';
        $this->parser = new postParser;
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
