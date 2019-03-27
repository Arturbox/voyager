<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 3/27/2019
 * Time: 2:12 PM
 */


namespace TCG\Voyager\Actions;

class RestoreLogAction extends AbstractAction
{
    public function getTitle()
    {
        return __('voyager::generic.restoreLog');
    }

    public function getIcon()
    {
        return 'voyager-restore';
    }

    public function getPolicy()
    {
        return 'restoreLog';
    }

    public function getAttributes()
    {
        return [
            'class'   => 'btn btn-sm btn-danger pull-right restoreLog',
            'data-id' => $this->data->{$this->data->getKeyName()},
            'id'      => 'restoreLog-'.$this->data->{$this->data->getKeyName()},
        ];
    }

    public function getDefaultRoute()
    {
        return 'javascript:;';
    }
}
