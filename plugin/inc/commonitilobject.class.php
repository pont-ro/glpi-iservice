<?php

// Imported from iService2, needs refactoring.
trait PluginIserviceCommonITILObject
{
    /*
     * @param bool $onlyIfEmpty [optional]<p>Reload the actors only if <b>suppliers</b>, <b>users</b> and <b>groups</b> are empty.<br>Set it to <i>FALSE</i> if you want to force the reload.</p>
     */
    public function reloadActors($onlyIfEmpty = true)
    {
        if (!$onlyIfEmpty || (empty($this->suppliers) && empty($this->users) && empty($this->groups))) {
            $this->loadActors();
        }
    }
}
