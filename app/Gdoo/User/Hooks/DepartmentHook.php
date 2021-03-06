<?php namespace Gdoo\User\Hooks;

use DB;
use Gdoo\User\Models\Department;

class DepartmentHook
{
    static $linkOptions = [];

    public function onBeforeForm($params) {
        return $params;
    }

    public function onAfterForm($params) {
        return $params;
    }

    public function onBeforeStore($params) {
        return $params;
    }

    public function onAfterStore($params) {
        Department::treeRebuild();
        return $params;
    }

    public function onBeforeDelete($params) {
        return $params;
    }
    
}
