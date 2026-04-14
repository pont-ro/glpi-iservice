<?php

// Imported from iService2, needs refactoring.
trait PluginIserviceCommonITILObject
{
    /*
     * @param bool $onlyIfEmpty [optional]<p>Reload the actors only if <b>suppliers</b>, <b>users</b> and <b>groups</b> are empty.<br>Set it to <i>FALSE</i> if you want to force the reload.</p>
     */
    public function reloadActors($onlyIfEmpty = true)
    {
        // Old if: if (!$onlyIfEmpty || (empty($this->suppliers) && empty($this->users) && empty($this->groups))) {
        // The problem: empty($this->suppliers) — while suppliers is whitelisted in __isset/__get, calling empty() on it triggers a DB load (via __get), then if ALL three arrays are empty, 
        // calls loadActors() which reloads them all again (double load). Worse, on a ticket with genuinely no actors, this fires every time.
        // The fix: Check the backing protected $lazy_loaded_* properties directly to test "have actors been loaded yet?" — null means never loaded. 
        if (!$onlyIfEmpty || ($this->lazy_loaded_suppliers === null && $this->lazy_loaded_users === null && $this->lazy_loaded_groups === null)) {
            $this->loadActors();
        }
    }

}
